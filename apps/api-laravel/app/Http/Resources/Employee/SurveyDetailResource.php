<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'questions' => $this->questions->map(fn($q) => [
                'id' => $q->id,
                'text' => $q->text,
                'type' => $q->type,
                'order' => $q->order,
                'isRequired' => $q->is_required,
                'options' => $q->options,
                'scaleMinLabel' => $q->scale_min_label,
                'scaleMaxLabel' => $q->scale_max_label,
            ]),
        ];
    }
}
