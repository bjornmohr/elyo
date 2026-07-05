<?php

namespace App\Services;

class LabMarkerRegistry
{
    /**
     * Marker metadata copied from the handoff source employee-lab-data.json.
     *
     * @var array<string, array{name: string, unit: string, low: float, high: float, group: string}>
     */
    private const MARKERS = [
        'hb' => ['name' => 'Hämoglobin', 'unit' => 'g/dl', 'low' => 13.5, 'high' => 17.5, 'group' => 'blutbild'],
        'hkt' => ['name' => 'Hämatokrit', 'unit' => '%', 'low' => 40.0, 'high' => 52.0, 'group' => 'blutbild'],
        'ery' => ['name' => 'Erythrozyten', 'unit' => 'Mio/µl', 'low' => 4.5, 'high' => 5.9, 'group' => 'blutbild'],
        'mcv' => ['name' => 'MCV', 'unit' => 'fl', 'low' => 80.0, 'high' => 96.0, 'group' => 'blutbild'],
        'mch' => ['name' => 'MCH', 'unit' => 'pg', 'low' => 28.0, 'high' => 33.0, 'group' => 'blutbild'],
        'mchc' => ['name' => 'MCHC', 'unit' => 'g/dl', 'low' => 33.0, 'high' => 36.0, 'group' => 'blutbild'],
        'rdw' => ['name' => 'RDW', 'unit' => '%', 'low' => 11.5, 'high' => 14.5, 'group' => 'blutbild'],
        'leuko' => ['name' => 'Leukozyten', 'unit' => '/nl', 'low' => 4.0, 'high' => 10.0, 'group' => 'immun'],
        'thrombo' => ['name' => 'Thrombozyten', 'unit' => '/nl', 'low' => 150.0, 'high' => 400.0, 'group' => 'immun'],
        'crp' => ['name' => 'CRP', 'unit' => 'mg/l', 'low' => 0.0, 'high' => 5.0, 'group' => 'immun'],
        'vitd' => ['name' => 'Vitamin D', 'unit' => 'ng/ml', 'low' => 30.0, 'high' => 50.0, 'group' => 'mikro'],
        'ferritin' => ['name' => 'Ferritin', 'unit' => 'ng/ml', 'low' => 30.0, 'high' => 300.0, 'group' => 'mikro'],
    ];

    /**
     * @return array{name: string, unit: string, low: float, high: float, group: string}|null
     */
    public function metadataFor(string $markerKey): ?array
    {
        return self::MARKERS[$markerKey] ?? null;
    }

    /**
     * @return string[]
     */
    public function orderedKeys(): array
    {
        return array_keys(self::MARKERS);
    }
}
