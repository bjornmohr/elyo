<?php

namespace Tests\Feature\Health;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Services\Privacy\MappingOperation;
use App\Services\Privacy\PurposeCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

/**
 * The check-in resolves the caller's health subject through the mapping domain,
 * so every check-in must leave an audit trail (ADR-003 D3, prompt 07).
 *
 * `audit` is excluded from the per-test transaction because the audit database is
 * append-only for runtime roles: the employee role may INSERT but not SELECT, so
 * the rows are committed and read back through the migrator connection.
 */
class WellbeingCheckinAuditTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected $connectionsToTransact = ['identity', 'mapping', 'health'];

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('checkin-audit-test');
        DB::connection('audit_migrator')->table('audit_events')->delete();

        $this->employee = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'role' => Role::EMPLOYEE,
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('audit_migrator')->table('audit_events')->delete();

        parent::tearDown();
    }

    public function test_checkin_audits_its_mapping_resolution_with_a_write_purpose(): void
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', ['mood' => 4, 'stress' => 2, 'energy' => 5])
            ->assertStatus(200);

        $events = DB::connection('audit_migrator')
            ->table('audit_events')
            ->where('event_type', 'mapping.'.MappingOperation::RESOLVE_OWN_SUBJECT->value)
            ->where('purpose', PurposeCode::HEALTH_SELF_WRITE->value)
            ->where('outcome', 'success')
            ->get();

        $this->assertCount(1, $events, 'The check-in did not audit its mapping resolution.');
        $this->assertSame('success', $events->first()->outcome);
        // Never both sides of the mapping in one entry (prompt 07).
        $this->assertNull($events->first()->subject_ref);
        $this->assertNotNull($events->first()->user_ref);
    }

    public function test_duplicate_checkin_still_resolves_its_mapping_with_a_write_purpose(): void
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', ['mood' => 4, 'stress' => 2, 'energy' => 5])
            ->assertStatus(200);

        DB::connection('audit_migrator')->table('audit_events')->delete();

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', ['mood' => 3, 'stress' => 3, 'energy' => 3])
            ->assertStatus(409);

        $events = DB::connection('audit_migrator')
            ->table('audit_events')
            ->where('event_type', 'mapping.'.MappingOperation::RESOLVE_OWN_SUBJECT->value)
            ->get();

        $this->assertCount(1, $events);
        $this->assertSame(PurposeCode::HEALTH_SELF_WRITE->value, $events->first()->purpose);
        $this->assertSame('success', $events->first()->outcome);
    }

    public function test_repairing_a_missing_mapping_is_audited_as_provisioning(): void
    {
        $this->travelTo(Carbon::parse('2026-05-25 10:00:00'));

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/checkin', ['mood' => 4, 'stress' => 2, 'energy' => 5])
            ->assertStatus(200);

        $events = DB::connection('audit_migrator')
            ->table('audit_events')
            ->whereIn('event_type', [
                'mapping.'.MappingOperation::RESOLVE_OWN_SUBJECT->value,
                'mapping.'.MappingOperation::PROVISION_OWN_SUBJECT->value,
            ])
            // Ordered by the monotonic ULID: `travelTo` freezes the clock, so
            // occurred_at is identical for every event of one request.
            ->orderBy('id')
            ->get();

        // The failed resolve is audited as denied before the idempotent repair
        // provisions the subject.
        $this->assertSame(
            [
                ['mapping.resolveOwnSubject', 'denied'],
                ['mapping.provisionOwnSubject', 'success'],
                ['mapping.resolveOwnSubject', 'success'],
            ],
            $events->map(fn (object $event): array => [$event->event_type, $event->outcome])
                ->take(3)
                ->all(),
        );

        $provisionEvent = $events->firstWhere(
            'event_type',
            'mapping.'.MappingOperation::PROVISION_OWN_SUBJECT->value,
        );
        $actorContext = json_decode(
            $provisionEvent->actor_context,
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame([
            'type' => 'employee-self-service',
            'runtime' => 'employee-health-api',
            'role' => 'employee',
        ], $actorContext);
    }
}
