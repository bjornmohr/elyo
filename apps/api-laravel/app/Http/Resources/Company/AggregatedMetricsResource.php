<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AggregatedMetricsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'avgMood' => $this['avgMood'],
            'avgStress' => $this['avgStress'],
            'avgEnergy' => $this['avgEnergy'],
            'avgScore' => $this['avgScore'],
            'responseCount' => $this['responseCount'],
            'isAboveThreshold' => $this['isAboveThreshold'],
        ];
    }
}
