<?php

namespace Database\Factories\Health;

use App\Models\Health\LabMarker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabMarker>
 */
class LabMarkerFactory extends Factory
{
    protected $model = LabMarker::class;

    public function definition(): array
    {
        return [
            'marker_key' => $this->faker->unique()->lexify('marker_????'),
            'name' => $this->faker->unique()->word(),
            'unit' => 'ng/ml',
            'low' => '10.0000',
            'high' => '20.0000',
            'marker_group' => 'sonstige',
            'active' => true,
        ];
    }

    /**
     * Orientation range starting at zero — the CRP-shaped case where a reading
     * of exactly 0 is valid.
     */
    public function rangeFromZero(): self
    {
        return $this->state(fn (): array => ['low' => '0.0000', 'high' => '5.0000']);
    }

    public function withoutBounds(): self
    {
        return $this->state(fn (): array => ['low' => null, 'high' => null]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['active' => false]);
    }
}
