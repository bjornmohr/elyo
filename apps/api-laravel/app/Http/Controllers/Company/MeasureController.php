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
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $this->validateEffectiveDateRange($request, $measure);

        $updateData = $this->measureDomainData($request->validated(), $measure);

        if (in_array($measure->status, ['COMPLETED', 'DISMISSED'], true) && $updateData !== []) {
            throw ValidationException::withMessages([
                'status' => ['Completed or dismissed measures cannot be edited.'],
            ]);
        }

        if (
            array_key_exists('verification_requirement', $updateData)
            && $updateData['verification_requirement'] !== $measure->verification_requirement
            && $measure->participations()->exists()
        ) {
            throw ValidationException::withMessages([
                'verificationRequirement' => ['Verification requirement cannot be changed after participation exists.'],
            ]);
        }

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

    private function validateEffectiveDateRange(PatchMeasureRequest $request, Measure $measure): void
    {
        $startsAt = $request->has('startsAt')
            ? ($request->filled('startsAt') ? $request->date('startsAt') : null)
            : $measure->starts_at;
        $endsAt = $request->has('endsAt')
            ? ($request->filled('endsAt') ? $request->date('endsAt') : null)
            : $measure->ends_at;

        if ($startsAt && $endsAt && $endsAt->lte($startsAt)) {
            throw ValidationException::withMessages([
                'endsAt' => [__('validation.after', [
                    'attribute' => 'ends at',
                    'date' => 'starts at',
                ])],
            ]);
        }
    }

    private function measureDomainData(array $validated, ?Measure $measure = null): array
    {
        $map = [
            'title' => 'title',
            'category' => 'category',
            'description' => 'description',
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

        // Normalize explicit timezone offsets to UTC; Eloquent would otherwise
        // store the wall time of the submitted offset as if it were UTC.
        foreach (['starts_at', 'ends_at'] as $column) {
            if (isset($data[$column])) {
                $data[$column] = CarbonImmutable::parse($data[$column])->utc();
            }
        }

        $this->deriveScheduledDuration($data, $validated, $measure);

        return $data;
    }

    private function deriveScheduledDuration(array &$data, array $validated, ?Measure $measure): void
    {
        $touchesSchedule = array_intersect(
            ['startsAt', 'endsAt', 'durationMinutes', 'executionType'],
            array_keys($validated)
        ) !== [];

        if ($measure !== null && ! $touchesSchedule) {
            return;
        }

        $executionType = $validated['executionType'] ?? $measure?->execution_type ?? 'EVENT_PARTICIPATION';
        if (! in_array($executionType, ['EVENT_PARTICIPATION', 'GUIDED_SESSION'], true)) {
            return;
        }

        $startsAt = array_key_exists('startsAt', $validated)
            ? ($validated['startsAt'] !== null ? CarbonImmutable::parse($validated['startsAt']) : null)
            : ($measure?->starts_at ? CarbonImmutable::parse($measure->starts_at) : null);

        $endsAt = array_key_exists('endsAt', $validated)
            ? ($validated['endsAt'] !== null ? CarbonImmutable::parse($validated['endsAt']) : null)
            : ($measure?->ends_at ? CarbonImmutable::parse($measure->ends_at) : null);

        if ($startsAt && $endsAt) {
            $data['duration_minutes'] = (int) $startsAt->diffInMinutes($endsAt);

            return;
        }

        // Incomplete schedule window: an explicitly provided durationMinutes
        // stays as manual duration (measureDomainData already copied it).
        if (array_key_exists('durationMinutes', $validated)) {
            return;
        }

        // Only clear a previously derived value when a boundary update leaves
        // the window incomplete, never on unrelated schedule-field updates.
        if ($measure !== null && (array_key_exists('startsAt', $validated) || array_key_exists('endsAt', $validated))) {
            $data['duration_minutes'] = null;
        }
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
