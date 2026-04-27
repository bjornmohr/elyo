<?php

namespace App\Http\Resources\Company;

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
            'status' => $this->status,
            'startsAt' => $this->starts_at,
            'endsAt' => $this->ends_at,
            'isAnonymous' => $this->is_anonymous,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'responsesCount' => $this->whenCounted('responses'),
            'questionsCount' => $this->whenCounted('questions'),
        ];
    }
}
