<?php

namespace App\Http\Resources\Company;

use App\Services\MeasureExecutionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'derivedStatus' => app(MeasureExecutionService::class)->derivedStatusFor($this->resource),
            'suggestedAt' => $this->suggested_at,
            'startedAt' => $this->started_at,
            'completedAt' => $this->completed_at,
            'measureOrigin' => $this->measure_origin,
            'deliveryType' => $this->delivery_type,
            'executionType' => $this->execution_type,
            'verificationRequirement' => $this->verification_requirement,
            'visibilityScope' => $this->visibility_scope,
            'startsAt' => $this->starts_at,
            'endsAt' => $this->ends_at,
            'durationMinutes' => $this->duration_minutes,
            'instructions' => $this->instructions,
            'locationName' => $this->location_name,
            'locationAddress' => $this->location_address,
            'capacity' => $this->capacity,
            'pointsOverride' => $this->points_override,
            'team' => $this->whenLoaded('team', function () {
                return ['name' => $this->team->name];
            }),
        ];
    }
}
