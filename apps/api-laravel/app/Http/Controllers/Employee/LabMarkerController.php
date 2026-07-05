<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\LabMarkerRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LabMarkerController extends Controller
{
    public function __construct(private readonly LabMarkerRegistry $registry)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $markers = $request->user()
            ->labMarkers()
            ->get()
            ->map(function ($marker) {
                $metadata = $this->registry->metadataFor($marker->marker_key);

                return [
                    'markerKey' => $marker->marker_key,
                    'name' => $metadata['name'] ?? $marker->marker_key,
                    'unit' => $metadata['unit'] ?? '',
                    'value' => (float) $marker->value,
                    'status' => $marker->status,
                    'isHighlighted' => (bool) $marker->is_highlighted,
                    'low' => $metadata['low'] ?? null,
                    'high' => $metadata['high'] ?? null,
                    'group' => $metadata['group'] ?? 'sonstige',
                ];
            })
            ->sortBy(function (array $marker) {
                $position = array_search($marker['markerKey'], $this->registry->orderedKeys(), true);

                return $position === false ? PHP_INT_MAX : $position;
            })
            ->values();

        return response()->json(['data' => $markers]);
    }
}
