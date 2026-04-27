<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrendPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period' => $this['period'],
            'avgScore' => $this['avgScore'],
            'avgMood' => $this['avgMood'],
            'avgStress' => $this['avgStress'],
            'avgEnergy' => $this['avgEnergy'],
            'respondents' => $this['respondents'],
        ];
    }
}
