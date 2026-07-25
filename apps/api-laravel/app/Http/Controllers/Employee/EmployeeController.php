<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CheckinRequest;
use App\Http\Requests\Employee\UpdateProfileRequest;
use App\Http\Resources\Employee\AnamnesisResource;
use App\Http\Resources\Employee\EmployeeDocumentResource;
use App\Http\Resources\Employee\MeasureResource;
use App\Http\Resources\Employee\WellbeingEntryResource;
use App\Models\Measure;
use App\Services\Health\AnamnesisService;
use App\Services\Health\HealthDocumentService;
use App\Services\Health\WellbeingService;
use App\Services\MeasureCheckinTokenService;
use App\Services\MeasureParticipationService;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmployeeController extends Controller
{
    public function __construct(
        protected WellbeingService $wellbeingService,
        protected PointsService $pointsService,
        protected MeasureParticipationService $measureParticipationService,
        protected MeasureCheckinTokenService $measureCheckinTokenService,
        protected AnamnesisService $anamnesisService,
        protected HealthDocumentService $healthDocumentService
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
        // Anamnesis and documents live in the health domain on the caller's own
        // subject (ADR-003 D8); they are never joined from the identity.
        $anamnesis = $this->anamnesisService->profileFor($user->id);
        $documents = $this->healthDocumentService->documentsFor($user->id);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'anamnesis' => $anamnesis ? new AnamnesisResource($anamnesis) : null,
                'anamnesisDue' => ! $anamnesis || $anamnesis->updated_at->lte(now()->subMonths(6)),
                'documents' => EmployeeDocumentResource::collection($documents),
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

        ['profile' => $profile, 'created' => $created] = $this->anamnesisService
            ->saveProfile($user->id, $profileData);

        if ($created && $profile->completion_pct >= 80) {
            $this->pointsService->awardPoints($user, 'anamnesis_completed');
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'anamnesis' => new AnamnesisResource($profile),
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
        // Metadata and file both land subject-scoped; the identity never reaches
        // the health domain. ADR-001 §2.9 storage hardening follow-up.
        $document = $this->healthDocumentService->storeUploadedDocument($user->id, $request->file('file'));

        $this->pointsService->awardPoints($user, 'medical_document_upload');

        return response()->json([
            'data' => new EmployeeDocumentResource($document),
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
}
