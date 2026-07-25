<?php

namespace Database\Factories\Health;

use App\Models\Health\HealthSubject;
use App\Models\Health\WellbeingEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<WellbeingEntry>
 */
class WellbeingEntryFactory extends Factory
{
    protected $model = WellbeingEntry::class;

    public function definition(): array
    {
        $mood = $this->faker->numberBetween(1, 5);
        $stress = $this->faker->numberBetween(1, 5);
        $energy = $this->faker->numberBetween(1, 5);

        return [
            'mood' => $mood,
            'stress' => $stress,
            'energy' => $energy,
            'score' => round(($mood + (6 - $stress) + $energy) / 3, 1),
            'period_key' => now()->toDateString(),
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
