<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WellbeingEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'periodKey' => $this->period_key,
            'score' => $this->score,
            'mood' => $this->mood,
            'stress' => $this->stress,
            'energy' => $this->energy,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
