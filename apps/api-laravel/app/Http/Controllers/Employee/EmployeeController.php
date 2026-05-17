<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CheckinRequest;
use App\Http\Requests\Employee\UpdateProfileRequest;
use App\Http\Resources\Employee\WellbeingEntryResource;
use App\Http\Resources\Company\MeasureResource;
use App\Models\AnamnesisProfile;
use App\Models\Measure;
use App\Models\UserDocument;
use App\Models\WellbeingEntry;
use App\Services\WellbeingService;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function __construct(
        protected WellbeingService $wellbeingService,
        protected PointsService $pointsService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $entries = $user->wellbeingEntries()
            ->orderBy('created_at', 'desc')
            ->take(7)
            ->get();

        $latest = $entries->first();
        $streakCount = $this->pointsService->calculateStreak($user);

        return response()->json([
            'latest' => $latest ? new WellbeingEntryResource($latest) : null,
            'entries' => WellbeingEntryResource::collection($entries->reverse()),
            'streakCount' => $streakCount,
            'points' => $user->userPoints?->total ?? 0,
            'todayCheckinCompleted' => $this->hasCheckinToday($user),
        ]);
    }

    public function checkinStatus(Request $request): JsonResponse
    {
        $entry = $request->user()->wellbeingEntries()
            ->where('period_key', now()->toDateString())
            ->latest()
            ->first();

        return response()->json([
            'completed' => $entry !== null,
            'entry' => $entry ? new WellbeingEntryResource($entry) : null,
        ]);
    }

    public function checkin(CheckinRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->company_id) {
            return response()->json(['error' => 'Employee must belong to a company'], 403);
        }

        if ($this->hasCheckinToday($user)) {
            return response()->json([
                'error' => ['code' => 'CHECKIN_ALREADY_DONE', 'message' => 'Der Check-in wurde heute bereits abgeschlossen.'],
            ], 409);
        }

        $entry = $this->wellbeingService->submitCheckin($user, $request->validated());

        try {
            $this->pointsService->awardPoints($user, 'daily_checkin');
            $streak = $this->pointsService->updateStreak($user);

            if ($streak === 7) {
                $this->pointsService->awardPoints($user, 'streak_7days');
            } elseif ($streak === 30) {
                $this->pointsService->awardPoints($user, 'streak_30days');
            }
        } catch (\Exception $e) {
            // Log error but don't fail the checkin
            \Log::error('[CHECKIN] Points award failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'score' => $entry->score,
            'periodKey' => $entry->period_key,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 20);
        $entries = $request->user()->wellbeingEntries()
            ->orderBy('created_at', 'asc')
            ->take($limit)
            ->get();

        return response()->json([
            'entries' => WellbeingEntryResource::collection($entries),
        ]);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['anamnesisProfile', 'documents']);
        $anamnesis = $user->anamnesisProfile;

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'anamnesis' => $anamnesis ? $this->anamnesisPayload($anamnesis) : null,
                'anamnesisDue' => !$anamnesis || $anamnesis->updated_at->lte(now()->subMonths(6)),
                'documents' => $user->documents()
                    ->latest('uploaded_at')
                    ->get()
                    ->map(fn (UserDocument $document) => [
                        'id' => $document->id,
                        'fileName' => $document->file_name,
                        'mimeType' => $document->mime_type,
                        'size' => $document->size,
                        'uploadedAt' => $document->uploaded_at?->toIso8601String(),
                    ]),
            ]
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $user->update(['name' => $validated['name']]);

        $profileData = [
            'birth_year' => $validated['birthYear'] ?? null,
            'biological_sex' => $validated['biologicalSex'] ?? null,
            'activity_level' => $validated['activityLevel'] ?? null,
            'sleep_quality' => $validated['sleepQuality'] ?? null,
            'stress_tendency' => $validated['stressTendency'] ?? null,
            'smoking_status' => $validated['smokingStatus'] ?? null,
            'nutrition_type' => $validated['nutritionType'] ?? null,
            'chronic_patterns' => $validated['chronicPatterns'] ?? [],
            'has_medication' => $validated['hasMedication'] ?? null,
        ];
        $profileData['completion_pct'] = $this->calculateAnamnesisCompletion($profileData);

        $existingProfile = $user->anamnesisProfile;
        $profile = AnamnesisProfile::updateOrCreate(['user_id' => $user->id], $profileData);

        if (!$existingProfile && $profile->completion_pct >= 80) {
            $this->pointsService->awardPoints($user, 'anamnesis_completed');
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'anamnesis' => $this->anamnesisPayload($profile),
                'anamnesisDue' => false,
            ]
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $user = $request->user();
        $file = $request->file('file');
        $path = $file->store("employee-documents/{$user->id}", 'public');

        $document = UserDocument::create([
            'user_id' => $user->id,
            'file_name' => $file->getClientOriginalName(),
            'blob_url' => Storage::disk('public')->url($path),
            'blob_key' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);

        $this->pointsService->awardPoints($user, 'medical_document_upload');

        return response()->json([
            'data' => [
                'id' => $document->id,
                'fileName' => $document->file_name,
                'mimeType' => $document->mime_type,
                'size' => $document->size,
                'uploadedAt' => $document->uploaded_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function measures(Request $request): JsonResponse
    {
        $user = $request->user();

        $measures = Measure::where('company_id', $user->company_id)
            ->where('status', 'ACTIVE')
            ->where(function ($query) use ($user) {
                $query->whereNull('team_id');
                if ($user->team_id) {
                    $query->orWhere('team_id', $user->team_id);
                }
            })
            ->with('team:id,name')
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json(['data' => MeasureResource::collection($measures)]);
    }

    private function hasCheckinToday($user): bool
    {
        return WellbeingEntry::where('user_id', $user->id)
            ->where('period_key', now()->toDateString())
            ->exists();
    }

    private function calculateAnamnesisCompletion(array $profileData): int
    {
        $fields = [
            'birth_year',
            'biological_sex',
            'activity_level',
            'sleep_quality',
            'stress_tendency',
            'smoking_status',
            'nutrition_type',
            'has_medication',
        ];

        $filled = collect($fields)->filter(fn ($field) => $profileData[$field] !== null && $profileData[$field] !== '')->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    private function anamnesisPayload(AnamnesisProfile $profile): array
    {
        return [
            'completionPct' => $profile->completion_pct,
            'birthYear' => $profile->birth_year,
            'biologicalSex' => $profile->biological_sex,
            'activityLevel' => $profile->activity_level,
            'sleepQuality' => $profile->sleep_quality,
            'stressTendency' => $profile->stress_tendency,
            'smokingStatus' => $profile->smoking_status,
            'nutritionType' => $profile->nutrition_type,
            'chronicPatterns' => $profile->chronic_patterns ?? [],
            'hasMedication' => $profile->has_medication,
            'updatedAt' => $profile->updated_at?->toIso8601String(),
        ];
    }
}
