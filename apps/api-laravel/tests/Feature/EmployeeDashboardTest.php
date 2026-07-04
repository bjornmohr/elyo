<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\SystemMeasureTemplate;
use App\Models\User;
use App\Models\UserSystemMeasure;
use App\Models\WellbeingEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        // The demo provider varies values per company; demo-gmbh is the
        // identity company that gets the base JSON unchanged.
        $this->company = Company::factory()->create(['slug' => 'demo-gmbh']);
        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
    }

    private function seedDailyEntries(): void
    {
        // Fixed midweek date so both ISO weeks have entries.
        $this->travelTo(Carbon::parse('2026-07-01 12:00:00')); // Wednesday, week 27

        // Previous week (Mon 22.06 - Sun 28.06): mood 3, stress 3, energy 3 -> score 3.0
        foreach (range(0, 6) as $offset) {
            $date = Carbon::parse('2026-06-22')->addDays($offset);
            WellbeingEntry::factory()->create([
                'user_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'mood' => 3, 'stress' => 3, 'energy' => 3, 'score' => 3.0,
                'period_key' => $date->toDateString(),
            ]);
        }

        // Current week (Mon 29.06 - Tue 30.06): mood 4, stress 2, energy 4 -> score 4.0
        foreach (range(0, 1) as $offset) {
            $date = Carbon::parse('2026-06-29')->addDays($offset);
            WellbeingEntry::factory()->create([
                'user_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'mood' => 4, 'stress' => 2, 'energy' => 4, 'score' => 4.0,
                'period_key' => $date->toDateString(),
            ]);
        }
    }

    public function test_dashboard_computes_week_over_week_aggregates_on_1_5_scale(): void
    {
        config(['elyo.data_mode' => 'prod']);
        $this->seedDailyEntries();

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard');

        $response->assertStatus(200)->assertJsonPath('wellbeing.scale', 5);

        // Whole floats are serialized as JSON ints, so compare loosely.
        $this->assertEquals(4.0, $response->json('wellbeing.current'));
        $this->assertEquals(3.0, $response->json('wellbeing.previous'));
        $this->assertEquals(1.0, $response->json('wellbeing.delta'));
        $this->assertEquals(4.0, $response->json('metrics.mood.current'));
        $this->assertEquals(3.0, $response->json('metrics.mood.previous'));
        $this->assertEquals(2.0, $response->json('metrics.stress.current'));
        $this->assertEquals(3.0, $response->json('metrics.stress.previous'));
        $this->assertEquals(1.0, $response->json('metrics.energy.delta'));

        // Sparkline: 9 daily scores oldest -> newest.
        $sparkline = $response->json('wellbeing.sparkline');
        $this->assertCount(9, $sparkline);
        $this->assertEquals(3.0, $sparkline[0]);
        $this->assertEquals(4.0, end($sparkline));
    }

    public function test_dashboard_demo_mode_returns_demo_blocks_with_resolved_levers(): void
    {
        config(['elyo.data_mode' => 'demo']);
        $this->seedDailyEntries();

        $template = SystemMeasureTemplate::create([
            'slug' => 'abend-routine-schlaf',
            'title' => 'Abend-Routine für besseren Schlaf',
        ]);
        $measure = UserSystemMeasure::create([
            'user_id' => $this->employee->id,
            'source_system_measure_template_id' => $template->id,
            'title' => $template->title,
            'status' => UserSystemMeasure::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('sleep.currentH', 6.8)
            ->assertJsonPath('healthFlag.state', 'caution')
            ->assertJsonPath('bodySignals.0.label', 'Nackenschmerzen');

        // Only the lever whose template slug resolves to an assigned measure survives.
        $levers = $response->json('levers');
        $this->assertCount(1, $levers);
        $this->assertSame('Schlaf stabilisieren', $levers[0]['title']);
        $this->assertSame($measure->id, $levers[0]['measureId']);
        $this->assertArrayNotHasKey('measureSlug', $levers[0]);
    }

    public function test_dashboard_prod_mode_returns_null_demo_blocks(): void
    {
        config(['elyo.data_mode' => 'prod']);
        $this->seedDailyEntries();

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('sleep', null)
            ->assertJsonPath('bodySignals', null)
            ->assertJsonPath('healthFlag', null)
            ->assertJsonPath('levers', null);
    }

    public function test_dashboard_aggregates_are_null_without_daily_entries(): void
    {
        config(['elyo.data_mode' => 'prod']);

        // Weekly aggregate rows must not leak into the daily aggregation.
        WellbeingEntry::factory()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'period_key' => '2026-W27',
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('wellbeing', null)
            ->assertJsonPath('metrics', null);
    }

    public function test_dashboard_only_aggregates_own_entries(): void
    {
        config(['elyo.data_mode' => 'prod']);
        $this->travelTo(Carbon::parse('2026-07-01 12:00:00'));

        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        WellbeingEntry::factory()->create([
            'user_id' => $other->id,
            'company_id' => $this->company->id,
            'mood' => 1, 'stress' => 5, 'energy' => 1, 'score' => 1.0,
            'period_key' => '2026-06-30',
        ]);
        WellbeingEntry::factory()->create([
            'user_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'mood' => 5, 'stress' => 1, 'energy' => 5, 'score' => 5.0,
            'period_key' => '2026-06-30',
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(5.0, $response->json('wellbeing.current'));
    }
}
