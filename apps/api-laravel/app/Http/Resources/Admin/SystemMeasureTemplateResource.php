<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemMeasureTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'shortDescription' => $this->short_description,
            'description' => $this->description,
            'category' => $this->category,
            'difficulty' => $this->difficulty,
            'estimatedDurationMinutes' => $this->estimated_duration_minutes,
            'status' => $this->status,
            'isFeatured' => $this->is_featured,
            'targetSignal' => $this->target_signal,
            'assignmentReasonTemplate' => $this->assignment_reason_template,
            'effectMetric' => $this->effect_metric,
            'effectMetricUnit' => $this->effect_metric_unit,
            'locationTags' => $this->location_tags,
            'postureTags' => $this->posture_tags,
            'requiresFloor' => $this->requires_floor,
            'exerciseCount' => $this->whenCounted('templateExercises'),
            'exercises' => SystemMeasureTemplateExerciseResource::collection($this->whenLoaded('templateExercises')),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
