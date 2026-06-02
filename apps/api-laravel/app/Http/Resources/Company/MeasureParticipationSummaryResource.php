<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasureParticipationSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'measureId' => $this->resource['measureId'],
            'isAboveThreshold' => $this->resource['isAboveThreshold'],
            'eligibleCount' => $this->resource['eligibleCount'],
            'participantCount' => $this->resource['participantCount'],
            'participationRate' => $this->resource['participationRate'],
            'suppressionReason' => $this->resource['suppressionReason'],
            'teamBreakdown' => null,
        ];
    }
}
