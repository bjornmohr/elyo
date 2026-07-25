<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The anamnesis payload of the employee profile response. The health subject
 * behind the profile is never exposed — the resource has no `id` at all.
 */
class AnamnesisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'completionPct' => $this->completion_pct,
            'birthYear' => $this->birth_year,
            'biologicalSex' => $this->biological_sex,
            'activityLevel' => $this->activity_level,
            'sleepQuality' => $this->sleep_quality,
            'stressTendency' => $this->stress_tendency,
            'smokingStatus' => $this->smoking_status,
            'nutritionType' => $this->nutrition_type,
            'chronicPatterns' => $this->chronic_patterns ?? [],
            'hasMedication' => $this->has_medication,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
