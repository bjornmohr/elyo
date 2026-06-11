<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemExerciseTagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'sortOrder' => $this->sort_order,
            'isActive' => $this->is_active,
        ];
    }
}
