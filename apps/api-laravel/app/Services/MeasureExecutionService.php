<?php

namespace App\Services;

use App\Models\Measure;
use App\Models\User;

class MeasureExecutionService
{
    public function __construct(
        private readonly MeasureParticipationSummaryService $summaryService,
    ) {
    }

    /**
     * Execution details for a measure (MeasureExecution contract).
     * Fully derived from existing data — independent of the demo/prod
     * data mode.
     *
     * @return array<string, mixed>
     */
    public function executionFor(User $user, Measure $measure): array
    {
        $threshold = $user->company?->anonymity_threshold ?? AnonymityService::DEFAULT_THRESHOLD;
        $summary = $this->summaryService->summaryFor($user, $measure->id, $threshold);

        $activeToken = $measure->checkinTokens()
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>', now()))
            ->latest('created_at')
            ->first();

        return [
            'measureId' => $measure->id,
            'derivedStatus' => $this->derivedStatusFor($measure),
            'deliveryType' => $measure->delivery_type,
            'executionType' => $measure->execution_type,
            'startsAt' => $measure->starts_at?->toIso8601String(),
            'endsAt' => $measure->ends_at?->toIso8601String(),
            'locationName' => $measure->location_name,
            'capacity' => $measure->capacity,
            'registeredCount' => $summary['participantCount'],
            'checkin' => [
                'active' => $activeToken !== null,
                'createdAt' => $activeToken?->created_at?->toIso8601String(),
                'required' => $measure->verification_requirement === Measure::VERIFICATION_REQUIREMENT_QR_CODE,
            ],
            'isAboveThreshold' => $summary['isAboveThreshold'],
        ];
    }

    public function derivedStatusFor(Measure $measure): string
    {
        if ($measure->status === 'COMPLETED' || ($measure->status === 'ACTIVE' && $measure->ends_at?->isPast())) {
            return 'COMPLETED';
        }

        if (in_array($measure->status, ['SUGGESTED', 'DISMISSED'], true)) {
            return 'PLANNED';
        }

        if ($measure->starts_at?->isFuture()) {
            return 'UPCOMING';
        }

        return 'RUNNING';
    }
}
