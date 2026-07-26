<?php

namespace Tests\Feature\Employee;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Health\LabMarker;
use App\Models\Health\LabMarkerReading;
use App\Models\User;
use App\Services\Health\LabMarkerService;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

/**
 * Employee lab-marker endpoints (ELYO-102 §1.1–§1.5, ELYO-113).
 *
 * The four routes are the only HTTP surface of the lab model: latest value per
 * marker, per-marker history, manual write, own-reading delete. Everything is
 * resolved through the caller's own health subject, so a foreign reading must be
 * indistinguishable from a non-existent one, and no response may carry a subject
 * id.
 */
class LabMarkerEndpointTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    private const CONTRACT_FIELDS = [
        'group', 'high', 'id', 'low', 'markerKey', 'measuredAt', 'name', 'source', 'status', 'unit', 'value',
    ];

    private const HISTORY_FIELDS = ['id', 'markerKey', 'measuredAt', 'source', 'status', 'value'];

    private Company $company;

    private User $employee;

    private User $otherEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('lab-marker-endpoint-test');
        $this->company = Company::factory()->create();
        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $this->otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);

        LabMarker::factory()->create([
            'marker_key' => 'ferritin',
            'name' => 'Ferritin',
            'unit' => 'ng/ml',
            'low' => '30.0000',
            'high' => '300.0000',
            'marker_group' => 'mikro',
        ]);
        LabMarker::factory()->rangeFromZero()->create([
            'marker_key' => 'crp',
            'name' => 'CRP',
            'unit' => 'mg/l',
            'marker_group' => 'immun',
        ]);
    }

    public function test_list_returns_the_latest_reading_per_marker_with_the_contract_field_set(): void
    {
        $this->seedReading($this->employee, 'ferritin', '24.0000', '2026-05-01');
        $this->seedReading($this->employee, 'ferritin', '42.1250', '2026-07-20');
        $this->seedReading($this->employee, 'crp', '7.0000', '2026-06-01');

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.markerKey', 'ferritin')
            ->assertJsonPath('data.0.name', 'Ferritin')
            ->assertJsonPath('data.0.unit', 'ng/ml')
            ->assertJsonPath('data.0.value', 42.125)
            ->assertJsonPath('data.0.measuredAt', '2026-07-20')
            ->assertJsonPath('data.0.status', 'in_range')
            // Whole-number bounds serialise without a decimal part (30, not
            // 30.0), so the assertion compares numerically.
            ->assertJsonPath('data.0.low', fn (mixed $low): bool => (float) $low === 30.0)
            ->assertJsonPath('data.0.high', fn (mixed $high): bool => (float) $high === 300.0)
            ->assertJsonPath('data.0.group', 'mikro')
            ->assertJsonPath('data.0.source', 'manual')
            ->assertJsonPath('data.1.markerKey', 'crp')
            ->assertJsonPath('data.1.status', 'above_range');

        $this->assertSame(
            self::CONTRACT_FIELDS,
            collect($response->json('data.0'))->keys()->sort()->values()->all(),
        );
        $this->assertTrue(Str::isUlid($response->json('data.0.id')));

        $this->assertStringNotContainsString('health_subject_id', $response->getContent());
        $this->assertStringNotContainsString(
            $this->subjectIdFor($this->employee),
            $response->getContent(),
            'The lab response must never expose the health subject id.',
        );
    }

    public function test_list_is_empty_when_the_employee_has_no_readings(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers')
            ->assertStatus(200)
            ->assertExactJson(['data' => []]);
    }

    public function test_list_excludes_another_employees_readings(): void
    {
        $this->seedReading($this->otherEmployee, 'ferritin', '99.0000', '2026-07-21');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_history_is_chronological_and_paginated(): void
    {
        $this->seedReading($this->employee, 'ferritin', '24.0000', '2026-05-01');
        $this->seedReading($this->employee, 'ferritin', '31.0000', '2026-06-01');
        $this->seedReading($this->employee, 'ferritin', '42.1250', '2026-07-20');

        $firstPage = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers/ferritin/history?perPage=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.measuredAt', '2026-05-01')
            ->assertJsonPath('data.0.value', fn (mixed $value): bool => (float) $value === 24.0)
            ->assertJsonPath('data.0.status', 'below_range')
            ->assertJsonPath('data.1.measuredAt', '2026-06-01')
            ->assertJsonPath('data.1.status', 'in_range')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta']);

        $this->assertSame(
            self::HISTORY_FIELDS,
            collect($firstPage->json('data.0'))->keys()->sort()->values()->all(),
        );

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers/ferritin/history?perPage=2&page=2')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.measuredAt', '2026-07-20')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_history_of_a_known_marker_without_readings_is_an_empty_list(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers/crp/history')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_history_of_an_unknown_marker_is_not_found(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers/unknown_marker/history')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'LAB_MARKER_NOT_FOUND');
    }

    public function test_history_rejects_a_per_page_outside_the_contract_bounds(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers/ferritin/history?perPage=101')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['perPage']]]);
    }

    public function test_history_excludes_another_employees_readings(): void
    {
        $this->seedReading($this->otherEmployee, 'ferritin', '99.0000', '2026-07-21');
        $own = $this->seedReading($this->employee, 'ferritin', '42.0000', '2026-07-20');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/lab-markers/ferritin/history')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_post_creates_a_manual_reading_for_the_caller(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/lab-markers', [
                'markerKey' => 'ferritin',
                'value' => 42.125,
                'measuredAt' => '2026-07-20',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.markerKey', 'ferritin')
            ->assertJsonPath('data.value', 42.125)
            ->assertJsonPath('data.measuredAt', '2026-07-20')
            ->assertJsonPath('data.status', 'in_range')
            ->assertJsonPath('data.source', 'manual');

        $this->assertSame(
            self::CONTRACT_FIELDS,
            collect($response->json('data'))->keys()->sort()->values()->all(),
        );

        $reading = LabMarkerReading::query()->sole();
        $this->assertSame($this->subjectIdFor($this->employee), $reading->health_subject_id);
        $this->assertSame($response->json('data.id'), $reading->id);
    }

    public function test_post_rejects_a_client_supplied_source(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/lab-markers', [
                'markerKey' => 'ferritin',
                'value' => 42.125,
                'measuredAt' => '2026-07-20',
                'source' => 'document_import',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['source']]]);

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing marker key' => [['value' => 1, 'measuredAt' => '2026-07-20'], 'markerKey'],
            'non numeric value' => [['markerKey' => 'ferritin', 'value' => 'high', 'measuredAt' => '2026-07-20'], 'value'],
            'negative value' => [['markerKey' => 'ferritin', 'value' => -0.0001, 'measuredAt' => '2026-07-20'], 'value'],
            'more than four decimal places' => [['markerKey' => 'ferritin', 'value' => 1.12345, 'measuredAt' => '2026-07-20'], 'value'],
            'value above storage maximum' => [['markerKey' => 'ferritin', 'value' => 100000000, 'measuredAt' => '2026-07-20'], 'value'],
            'missing value' => [['markerKey' => 'ferritin', 'measuredAt' => '2026-07-20'], 'value'],
            'missing measurement date' => [['markerKey' => 'ferritin', 'value' => 1], 'measuredAt'],
            'malformed measurement date' => [['markerKey' => 'ferritin', 'value' => 1, 'measuredAt' => '20.07.2026'], 'measuredAt'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_post_rejects_invalid_payloads(array $payload, string $expectedErrorField): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/lab-markers', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => [$expectedErrorField]]]);

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_post_rejects_a_measurement_date_in_the_future(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/lab-markers', [
                'markerKey' => 'ferritin',
                'value' => 42.125,
                'measuredAt' => now()->addDay()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['measuredAt']]]);

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_post_is_not_found_for_an_unknown_marker(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/lab-markers', [
                'markerKey' => 'unknown_marker',
                'value' => 42.125,
                'measuredAt' => '2026-07-20',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'LAB_MARKER_NOT_FOUND');

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_post_is_not_found_for_an_inactive_marker(): void
    {
        LabMarker::factory()->inactive()->create(['marker_key' => 'retired_marker']);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/employee/lab-markers', [
                'markerKey' => 'retired_marker',
                'value' => 42.125,
                'measuredAt' => '2026-07-20',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'LAB_MARKER_NOT_FOUND');

        $this->assertSame(0, LabMarkerReading::query()->count());
    }

    public function test_delete_removes_an_own_reading(): void
    {
        $reading = $this->seedReading($this->employee, 'ferritin', '42.0000', '2026-07-20');

        $this->actingAs($this->employee, 'sanctum')
            ->deleteJson("/api/employee/lab-markers/{$reading->id}")
            ->assertStatus(204);

        $this->assertFalse(LabMarkerReading::query()->whereKey($reading->id)->exists());
    }

    public function test_delete_of_a_foreign_reading_is_not_found_and_keeps_the_row(): void
    {
        $foreign = $this->seedReading($this->otherEmployee, 'ferritin', '42.0000', '2026-07-20');

        $this->actingAs($this->employee, 'sanctum')
            ->deleteJson("/api/employee/lab-markers/{$foreign->id}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'LAB_READING_NOT_FOUND');

        $this->assertTrue(LabMarkerReading::query()->whereKey($foreign->id)->exists());
    }

    public function test_delete_of_an_unknown_reading_is_indistinguishable_from_a_foreign_one(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->deleteJson('/api/employee/lab-markers/'.Str::ulid())
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'LAB_READING_NOT_FOUND');
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function forbiddenRoles(): array
    {
        return [
            'company owner' => [Role::COMPANY_OWNER],
            'company admin' => [Role::COMPANY_ADMIN],
            'company manager' => [Role::COMPANY_MANAGER],
            'platform admin' => [Role::ELYO_ADMIN],
            'platform support' => [Role::ELYO_SUPPORT],
            'partner' => [Role::PARTNER],
        ];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_non_employee_roles_are_forbidden_on_every_lab_route(Role $role): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => $role,
        ]);

        foreach ($this->labRoutes() as [$method, $uri, $payload]) {
            $this->actingAs($user, 'sanctum')
                ->json($method, $uri, $payload)
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    public function test_unauthenticated_requests_are_rejected_on_every_lab_route(): void
    {
        foreach ($this->labRoutes() as [$method, $uri, $payload]) {
            $this->json($method, $uri, $payload)->assertStatus(401);
        }
    }

    /**
     * The negative guarantee of §1.5: lab values are never reportable, so no
     * company, admin or reporting route may exist for them at all.
     */
    public function test_no_company_admin_or_reporting_route_exposes_lab_markers(): void
    {
        $exposed = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->filter(fn (string $uri): bool => Str::startsWith($uri, ['api/company', 'api/admin', 'api/partner']))
            ->filter(fn (string $uri): bool => Str::contains($uri, ['lab-marker', 'lab_marker']))
            ->values()
            ->all();

        $this->assertSame([], $exposed, 'Lab values must not be reachable outside the employee portal.');
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function labRoutes(): array
    {
        return [
            ['GET', '/api/employee/lab-markers', []],
            ['GET', '/api/employee/lab-markers/ferritin/history', []],
            ['POST', '/api/employee/lab-markers', [
                'markerKey' => 'ferritin',
                'value' => 42.125,
                'measuredAt' => '2026-07-20',
            ]],
            ['DELETE', '/api/employee/lab-markers/'.Str::ulid(), []],
        ];
    }

    private function seedReading(User $user, string $markerKey, string $value, string $measuredAt): LabMarkerReading
    {
        return app(LabMarkerService::class)->createReading(
            $this->subjectIdFor($user),
            $markerKey,
            $value,
            $measuredAt,
            'manual',
        );
    }

    private function subjectIdFor(User $user): string
    {
        return app(MappingServiceContract::class)->provisionOwnSubject(
            $user->id,
            PurposeCode::PROVISIONING,
        );
    }
}
