<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SurveyResultsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return [
            'survey' => [
                'id' => $this->id,
                'title' => $this->title,
                'status' => $status,
            ],
            'scope' => $this->result_scope ?? null,
            'participation' => $this->participation ?? null,
            'responseCount' => $this->scoped_response_count ?? $this->whenCounted('responses'),
            'minRequired' => $this->min_required ?? null,
            'isAboveThreshold' => $this->is_above_threshold ?? false,
            'questions' => $this->when(isset($this->questions_results), $this->questions_results),
        ];
    }
}
