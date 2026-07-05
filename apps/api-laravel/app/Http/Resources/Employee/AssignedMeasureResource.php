<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignedMeasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $template = $this->sourceTemplate;
        $demo = $this->recommendation_context['demo'] ?? [];

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $template?->category,
            'assignmentReason' => $this->assignment_reason,
            'exerciseCount' => $this->exercises->count(),
            'estMinutes' => $template?->estimated_duration_minutes,
            'streakDays' => $demo['streakDays'] ?? null,
            'weeklyDone' => $demo['weeklyDone'] ?? null,
            'weeklyTarget' => $demo['weeklyTarget'] ?? null,
            'effect' => $this->effectPayload(),
            'locationTags' => $template?->location_tags ?? [],
            'postureTags' => $template?->posture_tags ?? [],
            'requiresFloor' => (bool) ($template?->requires_floor ?? false),
        ];
    }

    protected function effectPayload(): ?array
    {
        $template = $this->sourceTemplate;
        $effect = $this->recommendation_context['demo']['lastEffect'] ?? null;

        if (! $template?->effect_metric || ! $effect) {
            return null;
        }

        return [
            'metric' => $template->effect_metric,
            'unit' => $template->effect_metric_unit,
            'before' => $effect['before'] ?? null,
            'after' => $effect['after'] ?? null,
            'direction' => $template->effect_metric === 'sleep_hours' ? 'up' : 'down',
        ];
    }
}
