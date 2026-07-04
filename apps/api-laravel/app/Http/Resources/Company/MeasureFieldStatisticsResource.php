<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasureFieldStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'field' => $this->resource['field'],
            'fieldLabel' => $this->resource['fieldLabel'],
            'measureCount' => $this->resource['measureCount'],
            'avgParticipationRate' => $this->resource['avgParticipationRate'],
            'isAboveThreshold' => $this->resource['isAboveThreshold'],
            'avgImpactRating' => $this->resource['avgImpactRating'],
            'impactIsPreliminary' => $this->resource['impactIsPreliminary'],
            'fieldTrend30d' => $this->resource['fieldTrend30d'],
        ];
    }
}
