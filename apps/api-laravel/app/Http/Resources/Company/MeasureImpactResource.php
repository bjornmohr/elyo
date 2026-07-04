<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasureImpactResource extends JsonResource
{
    public function toArray(Request $request): ?array
    {
        if ($this->resource === null) {
            return null;
        }

        return [
            'measureId' => $this->resource['measureId'],
            'field' => $this->resource['field'],
            'windowWeeks' => $this->resource['windowWeeks'],
            'participants' => $this->resource['participants'],
            'control' => $this->resource['control'],
            'netEffect' => $this->resource['netEffect'],
            'rating' => $this->resource['rating'],
            'isAboveThreshold' => $this->resource['isAboveThreshold'],
        ];
    }
}
