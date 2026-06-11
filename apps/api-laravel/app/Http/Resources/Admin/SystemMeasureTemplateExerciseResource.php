<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemMeasureTemplateExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'systemExerciseId' => $this->system_exercise_id,
            'sortOrder' => $this->position,
            'customTitle' => $this->custom_title,
            'customInstructions' => $this->custom_instructions,
            'customDurationMinutes' => $this->custom_duration_minutes,
            'customSets' => $this->custom_sets,
            'customRepetitions' => $this->custom_repetitions,
            'customHoldSeconds' => $this->custom_hold_seconds,
            'customFeedbackPrompt' => $this->custom_feedback_prompt,
            'isRequired' => $this->is_required,
            'exercise' => $this->whenLoaded('exercise', fn () => [
                'id' => $this->exercise->id,
                'slug' => $this->exercise->slug,
                'title' => $this->exercise->title,
                'shortDescription' => $this->exercise->short_description,
                'exerciseType' => $this->exercise->exercise_type,
                'difficulty' => $this->exercise->difficulty,
                'defaultDurationMinutes' => $this->exercise->default_duration_minutes,
                'status' => $this->exercise->status,
                'tags' => SystemExerciseTagResource::collection($this->exercise->tags),
            ]),
        ];
    }
}
