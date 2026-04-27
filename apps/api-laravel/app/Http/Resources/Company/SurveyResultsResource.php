<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyResultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'survey' => [
                'id' => $this->id,
                'title' => $this->title,
                'status' => $this->status,
            ],
            'responseCount' => $this->whenCounted('responses'),
            'isAboveThreshold' => $this->is_above_threshold ?? false,
            'questions' => $this->when(isset($this->questions_results), $this->questions_results),
        ];
    }
}
