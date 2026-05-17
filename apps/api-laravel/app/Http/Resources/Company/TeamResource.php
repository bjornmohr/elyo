<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'memberCount' => $this->whenCounted('members'),
            'managerId' => $this->manager_id,
            'manager' => $this->whenLoaded('manager', function() {
                return ['name' => $this->manager->name];
            }),
            'metrics' => $this->when(isset($this->metrics), $this->metrics),
        ];
    }
}
