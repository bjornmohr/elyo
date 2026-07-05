<?php

namespace Tests\Feature;

use Database\Seeders\DemoDataSeeder;
use Database\Seeders\SystemExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_mockup_measures_for_demo_company(): void
    {
        $this->seed(DemoDataSeeder::class);

        $companyId = DB::table('companies')->where('slug', 'demo-gmbh')->value('id');
        $measures = DB::table('measures')->where('company_id', $companyId)->get()->keyBy('title');

        $workshop = $measures['Rückenfit Bürostuhl-Workshop'];
        $this->assertSame('flexibility', $workshop->category);
        $this->assertSame('ACTIVE', $workshop->status);
        $this->assertSame('QR_CODE', $workshop->verification_requirement);
        $this->assertSame(80, (int) $workshop->capacity);
        $this->assertSame('Kantine EG', $workshop->location_name);
        $this->assertSame(1, DB::table('measure_checkin_tokens')->where('measure_id', $workshop->id)->count());

        $this->assertSame('COMPLETED', $measures['Vitamin-Info-Stand']->status);
        $this->assertSame('SUGGESTED', $measures['Ernährungs-Webinar']->status);
        $this->assertSame('sport', $measures['Lauftreff Mittwochs']->category);
        $this->assertSame('sport', $measures['Yoga am Morgen']->category);
        $this->assertSame('mental', $measures['Achtsamkeits-Session']->category);

        // Participation quotas above the anonymity threshold of 3.
        $this->assertSame(5, DB::table('measure_participations')->where('measure_id', $workshop->id)->count());
        $this->assertSame(4, DB::table('measure_participations')->where('measure_id', $measures['Vitamin-Info-Stand']->id)->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $companyId = DB::table('companies')->where('slug', 'demo-gmbh')->value('id');

        $this->assertSame(1, DB::table('measures')
            ->where('company_id', $companyId)
            ->where('title', 'Rückenfit Bürostuhl-Workshop')
            ->count());
        $this->assertSame(1, DB::table('companies')->where('slug', 'demo-gmbh')->count());
    }

    public function test_seeder_assigns_three_system_measures_to_every_demo_employee(): void
    {
        $this->seed(SystemExerciseSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $employeeIds = DB::table('users')->where('email', 'like', 'employee%@demo.de')->pluck('id');
        $this->assertCount(6, $employeeIds);

        foreach ($employeeIds as $employeeId) {
            $assigned = DB::table('user_system_measures')->where('user_id', $employeeId)->get();
            $this->assertCount(4, $assigned, "employee {$employeeId} should have 4 assigned measures");
            $this->assertEqualsCanonicalizing(
                ['Nacken-Mobilität', 'Abend-Routine für besseren Schlaf', 'Atem-Balance', 'Rücken-Fit im Alltag'],
                $assigned->pluck('title')->all()
            );
        }

        // Snapshot exercises copied per assignment: 5 + 4 + 4 + 3.
        $nacken = DB::table('user_system_measures')
            ->where('user_id', $employeeIds->first())
            ->where('title', 'Nacken-Mobilität')
            ->first();
        $this->assertNotNull($nacken->assignment_reason);
        $this->assertSame(5, DB::table('user_system_measure_exercises')
            ->where('user_system_measure_id', $nacken->id)
            ->count());

        $demo = json_decode($nacken->recommendation_context, true)['demo'] ?? null;
        $this->assertNotNull($demo);
        $this->assertSame(3, $demo['weeklyDone']);
        $this->assertSame(4, $demo['weeklyTarget']);
    }

    public function test_seeder_reassignment_is_idempotent_for_system_measures(): void
    {
        $this->seed(SystemExerciseSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $employeeId = DB::table('users')->where('email', 'employee1@demo.de')->value('id');
        $this->assertSame(4, DB::table('user_system_measures')->where('user_id', $employeeId)->count());
    }

    public function test_seeder_creates_daily_wellbeing_entries_on_1_5_scale(): void
    {
        $this->seed(DemoDataSeeder::class);

        $employeeIds = DB::table('users')->where('email', 'like', 'employee%@demo.de')->pluck('id');

        foreach ($employeeIds as $employeeId) {
            $daily = DB::table('wellbeing_entries')
                ->where('user_id', $employeeId)
                ->where('period_key', 'like', '____-__-__')
                ->get();

            $this->assertCount(14, $daily, "employee {$employeeId} should have 14 daily entries");

            foreach ($daily as $entry) {
                $this->assertGreaterThanOrEqual(1, $entry->mood);
                $this->assertLessThanOrEqual(5, $entry->mood);
                $this->assertLessThanOrEqual(5, $entry->stress);
                $this->assertLessThanOrEqual(5, $entry->energy);
            }

            // Today stays open so the check-in CTA shows as pending.
            $this->assertFalse($daily->contains('period_key', now()->toDateString()));

            // No duplicate period keys per user (weekly + daily combined).
            $all = DB::table('wellbeing_entries')->where('user_id', $employeeId)->pluck('period_key');
            $this->assertSame($all->count(), $all->unique()->count());
        }
    }

    public function test_seeded_pictogram_assets_exist_in_public_directory(): void
    {
        $this->seed(SystemExerciseSeeder::class);

        $paths = DB::table('system_exercises')
            ->whereNotNull('main_pictogram_path')
            ->pluck('main_pictogram_path');

        $this->assertNotEmpty($paths);

        foreach ($paths as $path) {
            $this->assertTrue(File::exists(public_path($path)), "missing pictogram asset: {$path}");
        }

        // Every step of every seeded exercise ships its own pictogram.
        $allSteps = DB::table('system_exercises')->whereNotNull('steps')->pluck('steps', 'slug');
        foreach ($allSteps as $slug => $steps) {
            foreach (json_decode($steps, true) as $index => $step) {
                $this->assertNotEmpty($step['pictogram_path'] ?? null, "exercise {$slug} step {$index} has no pictogram");
                $this->assertTrue(File::exists(public_path($step['pictogram_path'])), "missing step pictogram: {$step['pictogram_path']}");
            }
        }
    }
}
