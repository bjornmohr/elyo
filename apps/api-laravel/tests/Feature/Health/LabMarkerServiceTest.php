<?php

namespace Tests\Feature\Health;

use App\Models\Health\LabMarker;
use App\Models\Health\LabMarkerReading;
use App\Services\Health\LabMarkerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LabMarkerServiceTest extends TestCase
{
    private LabMarkerService $service;

    private string $subjectId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LabMarkerService::class);
        $this->subjectId = $this->createSubject();

        LabMarker::factory()->create([
            'marker_key' => 'ferritin',
            'name' => 'Ferritin',
            'unit' => 'ng/ml',
            'low' => '30.0000',
            'high' => '300.0000',
            'marker_group' => 'mikro',
        ]);
        LabMarker::factory()->rangeFromZero()->create([
            'marker_key' => 'crp',
            'name' => 'CRP',
            'unit' => 'mg/l',
            'marker_group' => 'immun',
        ]);
    }

    public function test_readings_are_appended_and_history_is_chronological(): void
    {
        $newer = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '42.1250',
            '2026-07-20',
            'manual',
        );
        $older = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '35.5000',
            '2026-06-20',
            'manual',
        );

        $history = $this->service->historyForMarker($this->subjectId, 'ferritin');

        $this->assertCount(2, $history);
        $this->assertSame([$older->id, $newer->id], $history->pluck('id')->all());
        $this->assertSame(2, LabMarkerReading::query()->count());
        $this->assertTrue(Str::isUlid($newer->id));
        $this->assertSame($this->subjectId, $newer->health_subject_id);
        $this->assertSame('42.1250', $newer->value);
    }

    public function test_latest_per_marker_uses_created_at_for_same_day_duplicates(): void
    {
        $olderDuplicate = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '40.0000',
            '2026-07-20',
            'manual',
        );
        $newerDuplicate = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '45.0000',
            '2026-07-20',
            'manual',
        );
        $crp = $this->service->createReading(
            $this->subjectId,
            'crp',
            '2.0000',
            '2026-07-19',
            'manual',
        );

        LabMarkerReading::query()->whereKey($olderDuplicate->id)->update([
            'created_at' => '2026-07-20 10:00:00',
        ]);
        LabMarkerReading::query()->whereKey($newerDuplicate->id)->update([
            'created_at' => '2026-07-20 11:00:00',
        ]);

        $latest = $this->service->latestPerMarker($this->subjectId);

        $this->assertCount(2, $latest);
        $this->assertSame(
            [$newerDuplicate->id, $crp->id],
            $latest->pluck('id')->all(),
            'Latest readings are returned newest first.',
        );
        $this->assertSame('ferritin', $latest->first()->marker->marker_key);
    }

    public function test_latest_per_marker_never_returns_another_subjects_readings(): void
    {
        $otherSubjectId = $this->createSubject();
        $foreignReading = $this->service->createReading(
            $otherSubjectId,
            'ferritin',
            '99.0000',
            '2026-07-21',
            'manual',
        );
        $ownReading = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '42.0000',
            '2026-07-20',
            'manual',
        );

        $latest = $this->service->latestPerMarker($this->subjectId);

        $this->assertSame([$ownReading->id], $latest->pluck('id')->all());
        $this->assertNotContains($foreignReading->id, $latest->pluck('id')->all());
    }

    public function test_history_never_returns_another_subjects_readings(): void
    {
        $otherSubjectId = $this->createSubject();
        $foreignReading = $this->service->createReading(
            $otherSubjectId,
            'ferritin',
            '99.0000',
            '2026-07-21',
            'manual',
        );
        $ownReading = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '42.0000',
            '2026-07-20',
            'manual',
        );

        $history = $this->service->historyForMarker($this->subjectId, 'ferritin');

        $this->assertSame([$ownReading->id], $history->pluck('id')->all());
        $this->assertNotContains($foreignReading->id, $history->pluck('id')->all());
    }

    public function test_history_of_a_known_marker_without_readings_is_empty(): void
    {
        $this->assertCount(0, $this->service->historyForMarker($this->subjectId, 'crp'));
    }

    public function test_history_of_an_unknown_marker_is_rejected(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->historyForMarker($this->subjectId, 'unknown_marker');
    }

    public function test_delete_only_removes_a_reading_owned_by_the_subject(): void
    {
        $otherSubjectId = $this->createSubject();
        $reading = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '42.0000',
            '2026-07-20',
            'manual',
        );

        $this->assertFalse($this->service->deleteReading($otherSubjectId, $reading->id));
        $this->assertTrue(LabMarkerReading::query()->whereKey($reading->id)->exists());

        $this->assertTrue($this->service->deleteReading($this->subjectId, $reading->id));
        $this->assertFalse(LabMarkerReading::query()->whereKey($reading->id)->exists());
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'not numeric' => ['invalid'],
            'negative' => ['-0.0001'],
            'more than four decimal places' => ['1.12345'],
            'exceeds decimal capacity' => ['100000000.0000'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_create_rejects_values_outside_generic_numeric_bounds(mixed $value): void
    {
        try {
            $this->service->createReading(
                $this->subjectId,
                'ferritin',
                $value,
                '2026-07-20',
                'manual',
            );
            $this->fail('Invalid values must not be persisted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value', $exception->errors());
        }

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_create_accepts_zero_for_a_marker_whose_range_starts_at_zero(): void
    {
        $reading = $this->service->createReading(
            $this->subjectId,
            'crp',
            '0.0000',
            '2026-07-20',
            'manual',
        );

        $this->assertSame('0.0000', $reading->value);
        $this->assertSame(
            LabMarkerService::STATUS_IN_RANGE,
            $this->service->deriveStatus($reading->marker, $reading->value),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidMeasurementDates(): array
    {
        return [
            'blank' => [''],
            'invalid calendar date' => ['2026-02-30'],
            'not a date' => ['not-a-date'],
        ];
    }

    #[DataProvider('invalidMeasurementDates')]
    public function test_create_rejects_a_missing_or_invalid_measurement_date(string $measuredAt): void
    {
        try {
            $this->service->createReading(
                $this->subjectId,
                'ferritin',
                '42.0000',
                $measuredAt,
                'manual',
            );
            $this->fail('A valid measurement date is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('measured_at', $exception->errors());
        }

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_create_rejects_a_measurement_date_in_the_future(): void
    {
        try {
            $this->service->createReading(
                $this->subjectId,
                'ferritin',
                '42.0000',
                now()->addDay(),
                'manual',
            );
            $this->fail('A future measurement date must not be persisted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('measured_at', $exception->errors());
        }

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_create_rejects_an_unknown_source(): void
    {
        try {
            $this->service->createReading(
                $this->subjectId,
                'ferritin',
                '42.0000',
                '2026-07-20',
                'lab_api',
            );
            $this->fail('Only contract-defined provenance values are accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source', $exception->errors());
        }

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_create_rejects_an_unknown_marker_key(): void
    {
        $this->expectException(ModelNotFoundException::class);

        try {
            $this->service->createReading(
                $this->subjectId,
                'unknown_marker',
                '42.0000',
                '2026-07-20',
                'manual',
            );
        } finally {
            $this->assertSame(0, LabMarkerReading::query()->count());
        }
    }

    public function test_create_rejects_an_inactive_marker(): void
    {
        LabMarker::factory()->inactive()->create(['marker_key' => 'retired_marker']);

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->service->createReading(
                $this->subjectId,
                'retired_marker',
                '42.0000',
                '2026-07-20',
                'manual',
            );
        } finally {
            $this->assertSame(0, LabMarkerReading::query()->count());
        }
    }

    public function test_deleting_a_subject_removes_its_readings(): void
    {
        $reading = $this->service->createReading(
            $this->subjectId,
            'ferritin',
            '42.0000',
            '2026-07-20',
            'manual',
        );

        DB::connection('health')->table('health_subjects')->where('id', $this->subjectId)->delete();

        $this->assertFalse(LabMarkerReading::query()->whereKey($reading->id)->exists());
    }

    private function createSubject(): string
    {
        $subjectId = (string) Str::ulid();

        DB::connection('health')->table('health_subjects')->insert([
            'id' => $subjectId,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $subjectId;
    }
}
