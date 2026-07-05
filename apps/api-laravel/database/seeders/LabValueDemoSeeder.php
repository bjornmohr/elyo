<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo seed for the Employee Dashboard lab-values variant.
 *
 * Seeds per-employee lab markers for the demo accounts
 * employee1@demo.de .. employee6@demo.de.
 *
 * PRIVACY: lab values are EMPLOYEE-FACING ONLY. They must never be exposed
 * through any employer / company / manager endpoint or aggregation. Treat this
 * table like other sensitive self-reported health data and scope every read to
 * the authenticated user (see docs/ai-context/health-data-guardrails.md).
 */
class LabValueDemoSeeder extends Seeder
{
    /**
     * In-range baseline. Any marker not overridden below is seeded from this
     * with status "im Orientierungsbereich".
     *
     * @var array<string, float>
     */
    private array $baseline = [
        'hb' => 14.8, 'hkt' => 44, 'ery' => 5.1, 'mcv' => 88, 'mch' => 30,
        'mchc' => 34, 'rdw' => 13, 'leuko' => 6.2, 'thrombo' => 250,
        'crp' => 2, 'vitd' => 38, 'ferritin' => 95,
    ];

    /**
     * Per-employee overrides: [value, status, highlight].
     *
     * @var array<string, array<string, array{0: float, 1: string, 2: bool}>>
     */
    private array $profiles = [
        'employee1@demo.de' => [
            'vitd' => [18, 'unter Bereich', true],
            'ferritin' => [18, 'unter Bereich', true],
            'crp' => [7, 'über Bereich', true],
        ],
        'employee2@demo.de' => [
            'ferritin' => [15, 'unter Bereich', true],
            'rdw' => [15.2, 'über Bereich', false],
        ],
        'employee3@demo.de' => [
            'crp' => [9, 'über Bereich', true],
        ],
        'employee4@demo.de' => [
            // all values in range
        ],
        'employee5@demo.de' => [
            'vitd' => [22, 'unter Bereich', true],
        ],
        'employee6@demo.de' => [
            'hb' => [12.8, 'unter Bereich', true],
            'ferritin' => [26, 'unter Bereich', true],
        ],
    ];

    public function run(): void
    {
        foreach ($this->profiles as $email => $overrides) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                $this->command?->warn("LabValueDemoSeeder: {$email} not found, skipping.");
                continue;
            }

            foreach ($this->baseline as $key => $baseValue) {
                [$value, $status, $highlight] = $overrides[$key]
                    ?? [$baseValue, 'im Orientierungsbereich', false];

                DB::table('lab_markers')->updateOrInsert(
                    ['user_id' => $user->id, 'marker_key' => $key],
                    [
                        'value' => $value,
                        'status' => $status,
                        'is_highlighted' => $highlight,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }
}
