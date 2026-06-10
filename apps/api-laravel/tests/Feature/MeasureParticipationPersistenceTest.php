<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Measure;
use App\Models\MeasureParticipation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeasureParticipationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_defaults_create_tenant_consistent_company_wide_participation(): void
    {
        $participation = MeasureParticipation::factory()->create();

        $this->assertSame($participation->company_id, $participation->measure->company_id);
        $this->assertSame($participation->company_id, $participation->user->company_id);
        $this->assertNull($participation->team_id);
        $this->assertNull($participation->measure->team_id);
        $this->assertDatabaseHas('measure_participations', [
            'id' => $participation->id,
            'company_id' => $participation->measure->company_id,
            'team_id' => null,
        ]);
    }

    public function test_team_measure_factory_state_creates_tenant_consistent_participation(): void
    {
        $participation = MeasureParticipation::factory()->forTeamMeasure()->create();

        $this->assertSame($participation->company_id, $participation->measure->company_id);
        $this->assertSame($participation->company_id, $participation->user->company_id);
        $this->assertSame($participation->company_id, $participation->team->company_id);
        $this->assertSame($participation->team_id, $participation->measure->team_id);
        $this->assertSame($participation->team_id, $participation->user->team_id);
    }

    public function test_factory_derives_team_context_from_partial_measure_override(): void
    {
        [$company, $team, $user, $measure] = $this->createMeasureContext();

        $participation = MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
        ]);

        $this->assertSame($company->id, $participation->company_id);
        $this->assertSame($team->id, $participation->team_id);
        $this->assertSame($team->id, $participation->user->team_id);
        $this->assertNotSame($user->id, $participation->user_id);
    }

    public function test_factory_explicit_overrides_do_not_create_unused_tenant_records(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
        ]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'created_by' => $user->id,
        ]);

        $counts = [
            'companies' => Company::query()->count(),
            'users' => User::query()->count(),
            'measures' => Measure::query()->count(),
            'participations' => MeasureParticipation::query()->count(),
        ];

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => null,
        ]);

        $this->assertSame($counts['companies'], Company::query()->count());
        $this->assertSame($counts['users'], User::query()->count());
        $this->assertSame($counts['measures'], Measure::query()->count());
        $this->assertSame($counts['participations'] + 1, MeasureParticipation::query()->count());
    }

    public function test_measure_participation_can_be_created_with_team_context(): void
    {
        [$company, $team, $user, $measure] = $this->createMeasureContext();

        $participation = MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
            'participated_at' => now(),
        ]);

        $this->assertDatabaseHas('measure_participations', [
            'id' => $participation->id,
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
        $this->assertTrue($participation->measure->is($measure));
        $this->assertTrue($participation->user->is($user));
        $this->assertTrue($participation->company->is($company));
        $this->assertTrue($participation->team->is($team));
    }

    public function test_user_cannot_have_duplicate_participation_for_same_measure(): void
    {
        [$company, $team, $user, $measure] = $this->createMeasureContext();

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);

        $this->expectException(QueryException::class);

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_measure_can_have_many_participations(): void
    {
        [$company, $team, $user, $measure] = $this->createMeasureContext();
        $secondUser = User::factory()->create([
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $secondUser->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);

        $this->assertCount(2, $measure->participations);
    }

    public function test_user_can_have_many_measure_participations(): void
    {
        [$company, $team, $user, $measure] = $this->createMeasureContext();
        $secondMeasure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => $team->id,
            'status' => 'ACTIVE',
            'created_by' => $user->id,
        ]);

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
        MeasureParticipation::factory()->create([
            'measure_id' => $secondMeasure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);

        $this->assertCount(2, $user->measureParticipations);
    }

    public function test_company_and_team_denormalization_is_persisted(): void
    {
        [$company, $team, $user, $measure] = $this->createMeasureContext();

        MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);

        $this->assertCount(1, $company->measureParticipations);
        $this->assertCount(1, $team->measureParticipations);
        $this->assertDatabaseHas('measure_participations', [
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_nullable_team_id_works_for_company_wide_measure(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'team_id' => null]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => null,
            'status' => 'ACTIVE',
            'created_by' => $user->id,
        ]);

        $participation = MeasureParticipation::factory()->create([
            'measure_id' => $measure->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'team_id' => null,
        ]);

        $this->assertNull($participation->team_id);
        $this->assertDatabaseHas('measure_participations', [
            'id' => $participation->id,
            'team_id' => null,
        ]);
    }

    public function test_foreign_key_constraints_reject_unknown_references(): void
    {
        // Cross-company and team mismatch rejection remains service-level work for
        // the later employee participation API. The DB only guards references and
        // duplicate measure/user participation rows.
        $this->expectException(QueryException::class);

        DB::table('measure_participations')->insert([
            'measure_id' => 999,
            'user_id' => 999,
            'company_id' => 999,
            'team_id' => 999,
            'participated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMeasureContext(): array
    {
        $company = Company::factory()->create();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $team = Team::factory()->create([
            'company_id' => $company->id,
            'manager_id' => $manager->id,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'team_id' => $team->id,
        ]);
        $measure = Measure::factory()->create([
            'company_id' => $company->id,
            'team_id' => $team->id,
            'status' => 'ACTIVE',
            'created_by' => $manager->id,
        ]);

        return [$company, $team, $user, $measure];
    }
}
