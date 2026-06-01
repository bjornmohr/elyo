<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participation = $this->relationLoaded('participations')
            ? $this->participations->first()
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'suggestedAt' => $this->suggested_at,
            'startedAt' => $this->started_at,
            'completedAt' => $this->completed_at,
            'team' => $this->whenLoaded('team', function () {
                return ['name' => $this->team->name];
            }),
            'participation' => [
                'isParticipating' => $participation !== null,
                'participatedAt' => $participation?->participated_at?->toIso8601String(),
            ],
        ];
    }
}
