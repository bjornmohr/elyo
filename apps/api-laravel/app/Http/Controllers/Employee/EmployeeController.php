<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CheckinRequest;
use App\Http\Requests\Employee\UpdateProfileRequest;
use App\Http\Resources\Employee\MeasureResource;
use App\Http\Resources\Employee\WellbeingEntryResource;
use App\Models\AnamnesisProfile;
use App\Models\Measure;
use App\Models\UserDocument;
use App\Services\Health\WellbeingService;
use App\Services\MeasureCheckinTokenService;
use App\Services\MeasureParticipationService;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeController extends Controller
{
    public function __construct(
        protected WellbeingService $wellbeingService,
        protected PointsService $pointsService,
        protected MeasureParticipationService $measureParticipationService,
        protected MeasureCheckinTokenService $measureCheckinTokenService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $entries = $this->wellbeingService->recentEntries($user->id, 7);

        $latest = $entries->first();
        $streakCount = $this->pointsService->calculateStreak($user);

        return response()->json([
            'latest' => $latest ? new WellbeingEntryResource($latest) : null,
            'entries' => WellbeingEntryResource::collection($entries->reverse()),
            'streakCount' => $streakCount,
            'points' => $user->userPoints?->total ?? 0,
            'todayCheckinCompleted' => $this->wellbeingService->hasDailyCheckin($user->id),
        ]);
    }

    public function checkinStatus(Request $request): JsonResponse
    {
        $entry = $this->wellbeingService->entryForPeriod($request->user()->id);

        return response()->json([
            'completed' => $entry !== null,
            'entry' => $entry ? new WellbeingEntryResource($entry) : null,
        ]);
    }

    public function checkin(CheckinRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->company_id) {
            return response()->json(['error' => 'Employee must belong to a company'], 403);
        }

        $entry = $this->wellbeingService->submitCheckin($user->id, $request->validated());

        if (! $entry) {
            return response()->json([
                'error' => ['code' => 'CHECKIN_ALREADY_DONE', 'message' => 'Der Check-in wurde heute bereits abgeschlossen.'],
            ], 409);
        }

        try {
            $this->pointsService->awardPoints($user, 'daily_checkin');
            $streak = $this->pointsService->updateStreak($user);

            if ($streak === 7) {
                $this->pointsService->awardPointsOnce($user, 'streak_7days');
            } elseif ($streak === 30) {
                $this->pointsService->awardPointsOnce($user, 'streak_30days');
            }
        } catch (\Exception $e) {
            // Log error but don't fail the checkin
            \Log::error('[CHECKIN] Points award failed: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'score' => $entry->score,
            'periodKey' => $entry->period_key,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);
        $entries = $this->wellbeingService->historyEntries($request->user()->id, $limit);

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
                'anamnesisDue' => ! $anamnesis || $anamnesis->updated_at->lte(now()->subMonths(6)),
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
            ],
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

        if (! $existingProfile && $profile->completion_pct >= 80) {
            $this->pointsService->awardPoints($user, 'anamnesis_completed');
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'anamnesis' => $this->anamnesisPayload($profile),
                'anamnesisDue' => false,
            ],
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
            ->with([
                'team:id,name',
                'participations' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->orderBy('started_at', 'desc')
            ->get();

        return response()->json(['data' => MeasureResource::collection($measures)]);
    }

    public function participateInMeasure(Request $request, int|string $measure): JsonResponse
    {
        $user = $request->user();

        try {
            $participation = $this->measureParticipationService->participate($user, $measure);
            $measureModel = $participation->measure()
                ->with([
                    'team:id,name',
                    'participations' => fn ($query) => $query->where('user_id', $user->id),
                ])
                ->firstOrFail();
        } catch (NotFoundHttpException) {
            return response()->json(['message' => 'Not found'], 404);
        } catch (ConflictHttpException $exception) {
            $code = $exception->getMessage();

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => match ($code) {
                        'MEASURE_ALREADY_PARTICIPATED' => 'Measure already participated.',
                        'MEASURE_NOT_ACTIVE' => 'Measure is not active.',
                        'MEASURE_REQUIRES_QR_CHECKIN' => 'Measure requires QR check-in.',
                        default => 'Measure participation conflict.',
                    },
                ],
            ], 409);
        }

        return response()->json([
            'data' => new MeasureResource($measureModel),
        ], 201);
    }

    public function redeemMeasureCheckin(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        $notFound = fn () => response()->json([
            'error' => ['code' => 'CHECKIN_TOKEN_NOT_FOUND', 'message' => 'Check-in token not found.'],
        ], 404);

        $checkinToken = $this->measureCheckinTokenService->findTokenByRawToken($token);

        if (! $checkinToken || ! $checkinToken->measure) {
            return $notFound();
        }

        $measure = $checkinToken->measure;

        if ((int) $measure->company_id !== (int) $user->company_id) {
            return $notFound();
        }

        if ($measure->team_id !== null && (int) $measure->team_id !== (int) $user->team_id) {
            return $notFound();
        }

        try {
            $this->measureCheckinTokenService->validateTokenLifecycle($checkinToken);

            $participation = $this->measureParticipationService->participateByQrCheckin(
                $user,
                $measure,
                fn () => $this->measureCheckinTokenService->markUsed($checkinToken)
            );
            $measureModel = $participation->measure()
                ->with([
                    'team:id,name',
                    'participations' => fn ($query) => $query->where('user_id', $user->id),
                ])
                ->firstOrFail();
        } catch (NotFoundHttpException) {
            // Safety net: scope was already verified above; this branch covers unexpected
            // race conditions (e.g. measure deleted mid-request).
            return $notFound();
        } catch (ConflictHttpException $exception) {
            $code = $exception->getMessage();

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => match ($code) {
                        'MEASURE_ALREADY_PARTICIPATED' => 'Measure already participated.',
                        'MEASURE_NOT_ACTIVE' => 'Measure is not active.',
                        'CHECKIN_TOKEN_REVOKED' => 'Check-in token has been revoked.',
                        'CHECKIN_TOKEN_NOT_YET_VALID' => 'Check-in token is not yet valid.',
                        'CHECKIN_TOKEN_EXPIRED' => 'Check-in token has expired.',
                        'MEASURE_DOES_NOT_ALLOW_QR_CHECKIN' => 'Measure does not allow QR check-in.',
                        default => 'Measure check-in conflict.',
                    },
                ],
            ], 409);
        }

        return response()->json([
            'data' => new MeasureResource($measureModel),
        ], 201);
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
