<?php

namespace App\Http\Resources\Employee;

use App\Services\Health\LabMarkerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One lab reading with its catalog metadata, per ELYO-102 §1.1.
 *
 * `status` comes from the single shared derivation in `LabMarkerService`, never
 * from a locally repeated comparison. `group` carries the `marker_group` column
 * (renamed because GROUP is reserved in Postgres). The health subject is never
 * part of the response.
 */
class LabMarkerReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $marker = $this->marker;

        return [
            'id' => $this->id,
            'markerKey' => $this->marker_key,
            'name' => $marker->name,
            'unit' => $marker->unit,
            'value' => (float) $this->value,
            'measuredAt' => $this->measured_at->toDateString(),
            'status' => app(LabMarkerService::class)->deriveStatus($marker, $this->value),
            'low' => $marker->low === null ? null : (float) $marker->low,
            'high' => $marker->high === null ? null : (float) $marker->high,
            'group' => $marker->marker_group,
            'source' => $this->source,
        ];
    }
}
