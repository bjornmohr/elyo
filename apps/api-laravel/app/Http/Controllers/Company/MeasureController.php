<?php

namespace App\Http\Controllers\Company;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateMeasureRequest;
use App\Http\Requests\Company\PatchMeasureRequest;
use App\Http\Resources\Company\MeasureResource;
use App\Http\Resources\Company\MeasureParticipationSummaryResource;
use App\Models\Measure;
use App\Models\Team;
use App\Services\AnonymityService;
use App\Services\Company\TeamLayerGuard;
use App\Services\MeasureCheckinTokenService;
use App\Services\MeasureParticipationSummaryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MeasureController extends Controller
{
    public function __construct(
        private readonly TeamLayerGuard $teamLayerGuard,
        private readonly MeasureParticipationSummaryService $measureParticipationSummaryService,
        private readonly MeasureCheckinTokenService $measureCheckinTokenService,
    )
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $managedTeamId = $isManager
            ? Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->value('id')
            : null;

        $query = Measure::where('company_id', $user->company_id);
        if (! $teamLayerEnabled) {
            $query->whereNull('team_id');
        }

        if ($isManager) {
            if (! $managedTeamId) {
                return MeasureResource::collection(collect());
            }
            $query->where(function ($q) use ($managedTeamId) {
                $q->whereNull('team_id')->orWhere('team_id', $managedTeamId);
            });
        }

        $query->when($teamLayerEnabled, fn ($q) => $q->with('team:id,name'));

        $measures = $query->orderBy('suggested_at', 'desc')->get();

        return MeasureResource::collection($measures);
    }

    public function store(CreateMeasureRequest $request)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
        $teamId = $request->teamId ?? null;
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && ($isManager || $teamId !== null)) {
            $this->teamLayerGuard->abortIfDisabled($user, $teamId !== null ? 422 : 403);
        }

        if ($isManager) {
            $managedTeamId = Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->value('id');
            if (! $managedTeamId) {
                abort(403);
            }
            $teamId = $managedTeamId;
        }

        $status = $request->status ?? 'ACTIVE';

        $measure = Measure::create([
            'company_id' => $user->company_id,
            'team_id' => $teamId,
            'title' => $request->title,
            'category' => $request->category,
            'description' => $request->description,
            'status' => $status,
            'started_at' => $status === 'ACTIVE' ? now() : null,
            'created_by' => $user->id,
            'measure_origin' => 'COMPANY_CREATED',
        ] + $this->measureDomainData($request->validated()));

        return new MeasureResource($measure->load('team:id,name'));
    }

    public function update(PatchMeasureRequest $request, $id)
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);
        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $managedTeamId = $isManager
            ? Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->value('id')
            : null;

        $measure = Measure::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        if (! $teamLayerEnabled && $measure->team_id !== null) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        if ($isManager && (! $managedTeamId || (int) $measure->team_id !== (int) $managedTeamId)) {
            abort(403);
        }

        $updateData = $this->measureDomainData($request->validated());

        if ($request->has('status')) {
            $validTransitions = [
                'SUGGESTED' => ['ACTIVE', 'DISMISSED'],
                'ACTIVE' => ['COMPLETED', 'DISMISSED'],
            ];

            $newStatus = $request->status;
            $allowed = $validTransitions[$measure->status] ?? [];

            if (! in_array($newStatus, $allowed)) {
                return response()->json(['error' => 'invalid_transition'], 400);
            }

            $updateData['status'] = $newStatus;
            if ($newStatus === 'ACTIVE') {
                $updateData['started_at'] = now();
            }
            if ($newStatus === 'COMPLETED') {
                $updateData['completed_at'] = now();
            }
        }

        $measure->update($updateData);

        return new MeasureResource($measure->load('team:id,name'));
    }

    public function participationSummary(Request $request, $id)
    {
        $company = $request->user()->company;
        $threshold = $company->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;

        return new MeasureParticipationSummaryResource(
            $this->measureParticipationSummaryService->summaryFor($request->user(), $id, $threshold)
        );
    }

    public function rotateCheckinToken(Request $request, int|string $measure)
    {
        $user = $request->user();
        $measureModel = $this->findCompanyManageableMeasure($request, $measure);

        try {
            [$checkinToken, $rawToken] = $this->measureCheckinTokenService->rotate($measureModel, $user);
        } catch (ConflictHttpException $exception) {
            $code = $exception->getMessage();

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => match ($code) {
                        'MEASURE_NOT_ACTIVE' => 'Measure is not active.',
                        'MEASURE_DOES_NOT_ALLOW_QR_CHECKIN' => 'Measure does not allow QR check-in.',
                        default => 'Measure check-in token conflict.',
                    },
                ],
            ], 409);
        }

        $path = "/employee/measure-checkins/{$rawToken}";

        return response()->json([
            'data' => [
                'measureId' => $measureModel->id,
                'token' => $rawToken,
                'checkinPath' => $path,
                'validFrom' => $checkinToken->valid_from?->toIso8601String(),
                'validUntil' => $checkinToken->valid_until?->toIso8601String(),
                'revokedAt' => $checkinToken->revoked_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function measureDomainData(array $validated): array
    {
        $map = [
            'deliveryType' => 'delivery_type',
            'executionType' => 'execution_type',
            'verificationRequirement' => 'verification_requirement',
            'startsAt' => 'starts_at',
            'endsAt' => 'ends_at',
            'durationMinutes' => 'duration_minutes',
            'instructions' => 'instructions',
            'locationName' => 'location_name',
            'locationAddress' => 'location_address',
            'capacity' => 'capacity',
            'pointsOverride' => 'points_override',
        ];

        $data = [];
        foreach ($map as $input => $column) {
            if (array_key_exists($input, $validated)) {
                $data[$column] = $validated[$input];
            }
        }

        return $data;
    }

    private function findCompanyManageableMeasure(Request $request, int|string $id): Measure
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $isManager = $user->hasRole('COMPANY_MANAGER') && ! $user->hasAnyRole([Role::COMPANY_ADMIN, Role::COMPANY_OWNER]);
        $teamLayerEnabled = $this->teamLayerGuard->enabledFor($user);

        if (! $teamLayerEnabled && $isManager) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        $measure = Measure::where('id', $id)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        if (! $teamLayerEnabled && $measure->team_id !== null) {
            $this->teamLayerGuard->abortIfDisabled($user);
        }

        if ($isManager) {
            $managedTeamId = Team::where('manager_id', $user->id)->where('company_id', $user->company_id)->value('id');
            if (! $managedTeamId || (int) $measure->team_id !== (int) $managedTeamId) {
                abort(403);
            }
        }

        return $measure;
    }
}
