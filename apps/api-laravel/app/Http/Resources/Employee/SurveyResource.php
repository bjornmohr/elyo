<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'questionCount' => $this->questions_count ?? $this->questions()->count(),
            'completed' => $this->responses()->where('user_id', $request->user()->id)->exists(),
            'endsAt' => $this->ends_at?->toISOString(),
        ];
    }
}
