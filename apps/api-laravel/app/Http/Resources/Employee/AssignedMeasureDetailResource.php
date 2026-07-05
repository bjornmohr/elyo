<?php

namespace App\Http\Resources\Employee;

use App\Models\UserSystemMeasureExercise;
use Illuminate\Http\Request;

class AssignedMeasureDetailResource extends AssignedMeasureResource
{
    public function toArray(Request $request): array
    {
        $demo = $this->recommendation_context['demo'] ?? [];

        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'lastSession' => $this->effectPayload() === null ? null : [
                'effect' => $this->effectPayload(),
                'effort' => $demo['lastEffort'] ?? null,
                'points' => $demo['lastPoints'] ?? null,
            ],
            'exercises' => $this->exercises->map(
                fn (UserSystemMeasureExercise $exercise) => $this->exercisePayload($exercise)
            )->values(),
        ]);
    }

    private function exercisePayload(UserSystemMeasureExercise $exercise): array
    {
        $template = $this->sourceTemplate;
        $source = $exercise->sourceExercise;

        return [
            'id' => $exercise->id,
            'position' => $exercise->position,
            'title' => $exercise->title,
            'description' => $exercise->short_description ?? $exercise->description,
            'instructions' => $exercise->instructions,
            'sets' => $exercise->sets,
            'repetitions' => $exercise->repetitions,
            'holdSeconds' => $exercise->hold_seconds,
            'durationMinutes' => $exercise->duration_minutes,
            'safetyNotes' => $exercise->safety_notes,
            'status' => $exercise->status,
            'mainPictogramPath' => $source?->main_pictogram_path,
            'mainPictogramAlt' => $source?->main_pictogram_alt,
            'steps' => collect($source?->steps ?? [])->map(fn (array $step) => [
                'text' => $step['text'] ?? '',
                'pictogramPath' => $step['pictogram_path'] ?? null,
                'alt' => $step['alt'] ?? null,
            ])->values(),
            'defaultEffort' => $source?->default_effort,
            'locationTags' => $source?->location_tags ?? $template?->location_tags ?? [],
            'postureTags' => $source?->posture_tags ?? $template?->posture_tags ?? [],
            'requiresFloor' => (bool) ($source?->requires_floor ?? $template?->requires_floor ?? false),
        ];
    }
}
