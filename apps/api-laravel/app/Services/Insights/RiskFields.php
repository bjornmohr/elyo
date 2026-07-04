<?php

namespace App\Services\Insights;

class RiskFields
{
    /**
     * Canonical risk fields for measure statistics, keyed by field code.
     * 'category' is the measure category aggregated into the field
     * (null = no measure category exists yet -> always a gap row).
     *
     * @var array<string, array{label: string, category: string|null}>
     */
    public const FIELDS = [
        'SLEEP' => ['label' => 'Schlaf', 'category' => null],
        'BACK' => ['label' => 'Rücken', 'category' => 'flexibility'],
        'STRESS_MENTAL' => ['label' => 'Stress / Mentale Belastung', 'category' => 'mental'],
        'MOVEMENT' => ['label' => 'Bewegung / Sport', 'category' => 'sport'],
        'NUTRITION' => ['label' => 'Ernährung', 'category' => 'nutrition'],
        'KNOWLEDGE' => ['label' => 'Orientierung / Wissen', 'category' => 'workshop'],
    ];

    public static function categoryToField(string $category): ?string
    {
        foreach (self::FIELDS as $field => $definition) {
            if ($definition['category'] === $category) {
                return $field;
            }
        }

        return null;
    }
}
