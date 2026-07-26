<?php

namespace App\Http\Resources\Employee;

use App\Services\Health\LabMarkerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of a single marker's history, per ELYO-102 §1.3.
 *
 * Catalog metadata (name, unit, bounds, group) is not repeated per entry — the
 * overview response carries it. `markerKey` stays so a paginated page is
 * self-describing.
 */
class LabMarkerHistoryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'markerKey' => $this->marker_key,
            'value' => (float) $this->value,
            'measuredAt' => $this->measured_at->toDateString(),
            'status' => app(LabMarkerService::class)->deriveStatus($this->marker, $this->value),
            'source' => $this->source,
        ];
    }
}
