<?php

namespace Database\Factories\Health;

use App\Models\Health\HealthSubject;
use App\Models\Health\LabMarker;
use App\Models\Health\LabMarkerReading;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<LabMarkerReading>
 */
class LabMarkerReadingFactory extends Factory
{
    protected $model = LabMarkerReading::class;

    public function definition(): array
    {
        return [
            'value' => '15.0000',
            'measured_at' => now()->toDateString(),
            'source' => 'manual',
            'marker_key' => fn (): string => LabMarker::factory()->create()->marker_key,
            // Standalone subject by default. Tests that need the subject of a
            // concrete identity provision it through MappingService and pass the
            // resulting id in explicitly.
            'health_subject_id' => fn (): string => $this->createOrphanSubject(),
        ];
    }

    private function createOrphanSubject(): string
    {
        $subjectId = (string) Str::ulid();

        DB::connection('health')->table('health_subjects')->insert([
            'id' => $subjectId,
            'status' => HealthSubject::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $subjectId;
    }
}
