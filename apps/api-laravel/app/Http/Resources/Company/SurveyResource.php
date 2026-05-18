<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;
        $teamIds = $this->relationLoaded('teams') ? $this->teams->pluck('id')->values() : $this->whenLoaded('teams');
        $user = $request->user();
        $isAdmin = $user?->hasAnyRole([\App\Enums\Role::COMPANY_ADMIN, \App\Enums\Role::COMPANY_OWNER]) ?? false;
        $canEdit = $status === 'DRAFT' && ($isAdmin || (int) $this->created_by === (int) $user?->id);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $status,
            'startsAt' => $this->starts_at,
            'endsAt' => $this->ends_at,
            'isAnonymous' => $this->is_anonymous,
            'createdBy' => $this->created_by,
            'teamIds' => $teamIds,
            'canEdit' => $canEdit,
            'canActivate' => $canEdit,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'responsesCount' => $this->whenCounted('responses'),
            'questionsCount' => $this->whenCounted('questions'),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($question) => [
                'id' => $question->id,
                'text' => $question->text,
                'type' => $question->type instanceof \BackedEnum ? $question->type->value : $question->type,
                'order' => $question->order,
                'isRequired' => $question->is_required,
                'options' => $question->options,
                'scaleMinLabel' => $question->scale_min_label,
                'scaleMaxLabel' => $question->scale_max_label,
            ])->values()),
        ];
    }
}
