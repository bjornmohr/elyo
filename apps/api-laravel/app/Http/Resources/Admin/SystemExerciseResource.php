<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'shortDescription' => $this->short_description,
            'description' => $this->description,
            'exerciseType' => $this->exercise_type,
            'difficulty' => $this->difficulty,
            'defaultDurationMinutes' => $this->default_duration_minutes,
            'defaultSets' => $this->default_sets,
            'defaultRepetitions' => $this->default_repetitions,
            'defaultHoldSeconds' => $this->default_hold_seconds,
            'instructions' => $this->instructions,
            'safetyNotes' => $this->safety_notes,
            'contraindications' => $this->contraindications,
            'defaultFeedbackPrompt' => $this->default_feedback_prompt,
            'requiresFeedback' => $this->requires_feedback,
            'steps' => collect($this->steps ?? [])->map(fn (array $step) => [
                'text' => $step['text'] ?? '',
                'pictogramPath' => $step['pictogram_path'] ?? null,
                'alt' => $step['alt'] ?? null,
            ])->values(),
            'mainPictogramPath' => $this->main_pictogram_path,
            'mainPictogramAlt' => $this->main_pictogram_alt,
            'locationTags' => $this->location_tags,
            'postureTags' => $this->posture_tags,
            'requiresFloor' => $this->requires_floor,
            'defaultEffort' => $this->default_effort,
            'status' => $this->status,
            'tags' => SystemExerciseTagResource::collection($this->whenLoaded('tags')),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
