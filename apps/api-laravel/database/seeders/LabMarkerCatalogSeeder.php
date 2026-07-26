<?php

namespace Database\Seeders;

use App\Models\Health\LabMarker;
use Illuminate\Database\Seeder;

/**
 * Lab catalog derived from the demo reference field list.
 *
 * All names, units and ranges are neutral orientation-value content candidates
 * pending fachliche Freigabe in ELYO-94. They are not diagnostic thresholds.
 */
class LabMarkerCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, unit: string, low: float, high: float, marker_group: string}>
     */
    private const CATALOG = [
        'hb' => ['name' => 'Hämoglobin', 'unit' => 'g/dl', 'low' => 13.5, 'high' => 17.5, 'marker_group' => 'blutbild'],
        'hkt' => ['name' => 'Hämatokrit', 'unit' => '%', 'low' => 40.0, 'high' => 52.0, 'marker_group' => 'blutbild'],
        'ery' => ['name' => 'Erythrozyten', 'unit' => 'Mio/µl', 'low' => 4.5, 'high' => 5.9, 'marker_group' => 'blutbild'],
        'mcv' => ['name' => 'MCV', 'unit' => 'fl', 'low' => 80.0, 'high' => 96.0, 'marker_group' => 'blutbild'],
        'mch' => ['name' => 'MCH', 'unit' => 'pg', 'low' => 28.0, 'high' => 33.0, 'marker_group' => 'blutbild'],
        'mchc' => ['name' => 'MCHC', 'unit' => 'g/dl', 'low' => 33.0, 'high' => 36.0, 'marker_group' => 'blutbild'],
        'rdw' => ['name' => 'RDW', 'unit' => '%', 'low' => 11.5, 'high' => 14.5, 'marker_group' => 'blutbild'],
        'leuko' => ['name' => 'Leukozyten', 'unit' => '/nl', 'low' => 4.0, 'high' => 10.0, 'marker_group' => 'immun'],
        'thrombo' => ['name' => 'Thrombozyten', 'unit' => '/nl', 'low' => 150.0, 'high' => 400.0, 'marker_group' => 'immun'],
        'crp' => ['name' => 'CRP', 'unit' => 'mg/l', 'low' => 0.0, 'high' => 5.0, 'marker_group' => 'immun'],
        'vitd' => ['name' => 'Vitamin D', 'unit' => 'ng/ml', 'low' => 30.0, 'high' => 50.0, 'marker_group' => 'mikro'],
        'ferritin' => ['name' => 'Ferritin', 'unit' => 'ng/ml', 'low' => 30.0, 'high' => 300.0, 'marker_group' => 'mikro'],
    ];

    /**
     * Marker keys the demo history depends on. Public so `DemoDataSeeder` can
     * check the prerequisite instead of blindly re-running this seeder.
     *
     * @return array<int, string>
     */
    public static function markerKeys(): array
    {
        return array_keys(self::CATALOG);
    }

    public function run(): void
    {
        foreach (self::CATALOG as $markerKey => $metadata) {
            // Eloquent keeps `created_at` on inserts only; a plain
            // `updateOrInsert` would rewrite it on every re-seed.
            LabMarker::query()->updateOrCreate(
                ['marker_key' => $markerKey],
                $metadata + ['active' => true],
            );
        }

        $this->command?->info(sprintf(
            'Lab catalog seeded: %d content candidates pending ELYO-94 review.',
            count(self::CATALOG),
        ));
    }
}
