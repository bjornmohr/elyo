<?php

namespace App\Services\Health;

use App\Models\Health\LabMarker;
use App\Models\Health\LabMarkerReading;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LabMarkerService
{
    public const STATUS_BELOW_RANGE = 'below_range';

    public const STATUS_IN_RANGE = 'in_range';

    public const STATUS_ABOVE_RANGE = 'above_range';

    /**
     * @param  mixed  $value  Generic numeric sanity checks only. Marker-specific
     *                        plausibility ranges remain TODO ELYO-114.
     *
     * A measurement date in the future is rejected as a generic sanity check:
     * `latestPerMarker` ranks by `measured_at`, so one future date would pin the
     * "latest" reading permanently.
     */
    public function createReading(
        string $subjectId,
        string $markerKey,
        mixed $value,
        DateTimeInterface|string $measuredAt,
        string $source,
    ): LabMarkerReading {
        $measurementDate = $measuredAt instanceof DateTimeInterface
            ? $measuredAt->format('Y-m-d')
            : $measuredAt;

        $validated = Validator::make(
            ['value' => $value, 'measured_at' => $measurementDate, 'source' => $source],
            [
                // `gte:0`, not `gt:0`: markers whose orientation range starts at
                // zero (CRP) legitimately report 0 below the detection limit.
                'value' => ['required', 'numeric', 'gte:0', 'decimal:0,4', 'max:99999999.9999'],
                'measured_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'source' => ['required', Rule::in(['manual', 'document_import', 'bgm_import'])],
            ],
        )->validate();

        LabMarker::query()
            ->whereKey($markerKey)
            ->where('active', true)
            ->firstOrFail();

        return LabMarkerReading::query()->create([
            'health_subject_id' => $subjectId,
            'marker_key' => $markerKey,
            'value' => $validated['value'],
            'measured_at' => $validated['measured_at'],
            'source' => $validated['source'],
        ])->load('marker');
    }

    /**
     * Latest reading per marker, newest first. The winner per marker is picked
     * in Postgres via `DISTINCT ON` so the full history never has to be loaded;
     * only the resulting handful of rows is re-sorted for the response order.
     *
     * @return Collection<int, LabMarkerReading>
     */
    public function latestPerMarker(string $subjectId): Collection
    {
        return LabMarkerReading::query()
            ->select(DB::raw('distinct on (lab_marker_readings.marker_key) lab_marker_readings.*'))
            ->with('marker')
            ->where('health_subject_id', $subjectId)
            ->orderBy('marker_key')
            ->orderByDesc('measured_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->sortByDesc(fn (LabMarkerReading $reading): array => [
                $reading->measured_at,
                $reading->created_at,
                $reading->id,
            ])
            ->values();
    }

    /**
     * A known marker with no readings yields an empty list; an unknown marker
     * key raises `ModelNotFoundException` so the HTTP layer answers 404
     * (ELYO-102 §1.3). Inactive markers stay readable — only writes require an
     * active catalog entry.
     *
     * @return Collection<int, LabMarkerReading>
     */
    public function historyForMarker(string $subjectId, string $markerKey): Collection
    {
        LabMarker::query()->whereKey($markerKey)->firstOrFail();

        return LabMarkerReading::query()
            ->with('marker')
            ->where('health_subject_id', $subjectId)
            ->where('marker_key', $markerKey)
            ->orderBy('measured_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function deleteReading(string $subjectId, string $readingId): bool
    {
        return LabMarkerReading::query()
            ->whereKey($readingId)
            ->where('health_subject_id', $subjectId)
            ->delete() === 1;
    }

    /**
     * Derive soft status from catalog orientation bounds; status is not stored.
     */
    public function deriveStatus(LabMarker $marker, int|float|string $value): string
    {
        $numericValue = (float) $value;

        if ($marker->low !== null && $numericValue < (float) $marker->low) {
            return self::STATUS_BELOW_RANGE;
        }

        if ($marker->high !== null && $numericValue > (float) $marker->high) {
            return self::STATUS_ABOVE_RANGE;
        }

        return self::STATUS_IN_RANGE;
    }
}
