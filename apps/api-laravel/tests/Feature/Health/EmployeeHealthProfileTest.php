<?php

namespace Tests\Feature\Health;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Health\AnamnesisProfile;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

/**
 * Employee profile and medical documents after the move into the health domain
 * (ELYO-91 prompt 08a, ADR-003 D8).
 *
 * The client-visible contract must be unchanged: same routes, same response
 * shapes, ids become opaque ULIDs. Everything behind it is subject-scoped, so a
 * second employee's anamnesis or documents must be unreachable.
 */
class EmployeeHealthProfileTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    private User $employee;

    private User $otherEmployee;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('employee-health-profile-test');
        $this->company = Company::factory()->create();
        $this->employee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
        $this->otherEmployee = User::factory()->create([
            'company_id' => $this->company->id,
            'role' => Role::EMPLOYEE,
        ]);
    }

    public function test_profile_round_trip_returns_the_stored_anamnesis(): void
    {
        $this->actingAs($this->employee, 'sanctum')
            ->putJson('/api/employee/profile', [
                'name' => 'Anamnesis Owner',
                'birthYear' => 1987,
                'biologicalSex' => 'PREFER_NOT_TO_SAY',
                'activityLevel' => 'HIGH',
                'sleepQuality' => 'GOOD',
                'stressTendency' => 'LOW',
                'smokingStatus' => 'NEVER',
                'nutritionType' => 'balanced',
                'chronicPatterns' => ['BACK_PAIN'],
                'hasMedication' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.anamnesis.birthYear', 1987)
            ->assertJsonPath('data.anamnesis.activityLevel', 'HIGH')
            ->assertJsonPath('data.anamnesisDue', false);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Anamnesis Owner')
            ->assertJsonPath('data.anamnesis.birthYear', 1987)
            ->assertJsonPath('data.anamnesis.chronicPatterns', ['BACK_PAIN'])
            ->assertJsonPath('data.anamnesis.hasMedication', false)
            ->assertJsonPath('data.anamnesisDue', false);

        $this->assertSame(
            ['activityLevel', 'biologicalSex', 'birthYear', 'chronicPatterns', 'completionPct',
                'hasMedication', 'nutritionType', 'sleepQuality', 'smokingStatus', 'stressTendency', 'updatedAt'],
            collect($response->json('data.anamnesis'))->keys()->sort()->values()->all(),
        );

        $this->assertStringNotContainsString(
            'health_subject_id',
            $response->getContent(),
            'The profile response must never expose the health subject.',
        );
        $this->assertStringNotContainsString(
            $this->subjectIdFor($this->employee),
            $response->getContent(),
            'The profile response must never expose the health subject id.',
        );
    }

    public function test_updating_the_anamnesis_twice_keeps_a_single_subject_scoped_profile(): void
    {
        $subjectId = $this->subjectIdFor($this->employee);

        foreach ([1980, 1990] as $birthYear) {
            $this->actingAs($this->employee, 'sanctum')
                ->putJson('/api/employee/profile', [
                    'name' => 'Anamnesis Owner',
                    'birthYear' => $birthYear,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.anamnesis.birthYear', $birthYear);
        }

        $rows = DB::connection('health')
            ->table('anamnesis_profiles')
            ->where('health_subject_id', $subjectId)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(1990, $rows->first()->birth_year);
        $this->assertTrue(Str::isUlid($rows->first()->id), 'Anamnesis ids must be opaque ULIDs.');
    }

    public function test_first_profile_write_recovers_when_a_concurrent_insert_wins_the_race(): void
    {
        $subjectId = $this->subjectIdFor($this->employee);

        // Make the subject visible to a second connection. This lets the model
        // event reproduce a competing request that inserts after this request's
        // initial lookup but before its own insert.
        DB::connection('health')->commit();
        config()->set('database.connections.health_competitor', config('database.connections.health'));
        DB::purge('health_competitor');

        $competitorInserted = false;

        AnamnesisProfile::creating(function () use ($subjectId, &$competitorInserted): void {
            $competitorInserted = true;

            DB::connection('health_competitor')->table('anamnesis_profiles')->insert([
                'id' => (string) Str::ulid(),
                'health_subject_id' => $subjectId,
                'completion_pct' => 0,
                'birth_year' => 1970,
                'chronic_patterns' => '[]',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->actingAs($this->employee, 'sanctum')
                ->putJson('/api/employee/profile', [
                    'name' => 'Concurrent Owner',
                    'birthYear' => 1992,
                    'biologicalSex' => 'PREFER_NOT_TO_SAY',
                    'activityLevel' => 'HIGH',
                    'sleepQuality' => 'GOOD',
                    'stressTendency' => 'LOW',
                    'smokingStatus' => 'NEVER',
                    'nutritionType' => 'balanced',
                    'hasMedication' => false,
                ])
                ->assertStatus(200)
                ->assertJsonPath('data.anamnesis.birthYear', 1992)
                ->assertJsonPath('data.anamnesis.completionPct', 100);

            $this->assertTrue($competitorInserted);
            $this->assertSame(
                1,
                DB::connection('health_competitor')
                    ->table('anamnesis_profiles')
                    ->where('health_subject_id', $subjectId)
                    ->count(),
            );
            $this->assertFalse(
                PointTransaction::query()
                    ->where('user_id', $this->employee->id)
                    ->where('reason', 'anamnesis_completed')
                    ->exists(),
                'The request that loses the insert race must not receive first-completion points.',
            );
        } finally {
            AnamnesisProfile::flushEventListeners();

            DB::connection('health_competitor')
                ->table('anamnesis_profiles')
                ->where('health_subject_id', $subjectId)
                ->delete();
            DB::connection('health_competitor')
                ->table('health_subjects')
                ->where('id', $subjectId)
                ->delete();
            DB::disconnect('health_competitor');

            // Restore the transaction expected by the shared test teardown.
            DB::connection('health')->beginTransaction();
        }
    }

    public function test_uploaded_document_is_returned_by_the_profile_endpoint_with_a_ulid_id(): void
    {
        Storage::fake('public');

        $document = $this->actingAs($this->employee, 'sanctum')
            ->post('/api/employee/documents', [
                'file' => UploadedFile::fake()->create('befund.pdf', 128, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->json('data');

        $this->assertSame(
            ['fileName', 'id', 'mimeType', 'size', 'uploadedAt'],
            collect($document)->keys()->sort()->values()->all(),
        );
        $this->assertTrue(Str::isUlid($document['id']), 'Document ids must be opaque ULIDs.');

        $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.documents.0.id', $document['id'])
            ->assertJsonPath('data.documents.0.fileName', 'befund.pdf');
    }

    /**
     * ADR-001 §2.9: a health document must not be findable through an identity.
     * The stored path is subject-scoped and keeps Laravel's random hash name, so
     * neither the employee's name, e-mail nor user id appears in it.
     */
    public function test_document_storage_path_carries_no_identity_data(): void
    {
        Storage::fake('public');

        $this->employee->update(['name' => 'Erika Mustermann', 'email' => 'erika.mustermann@example.com']);

        $this->actingAs($this->employee, 'sanctum')
            ->post('/api/employee/documents', [
                'file' => UploadedFile::fake()->create('Erika-Mustermann-Befund.pdf', 64, 'application/pdf'),
            ])
            ->assertStatus(201);

        $blobKey = DB::connection('health')->table('user_documents')->value('blob_key');

        $this->assertNotNull($blobKey);
        $this->assertStringStartsWith('employee-documents/', $blobKey);
        $this->assertStringContainsString($this->subjectIdFor($this->employee), $blobKey);

        foreach (['Erika', 'Mustermann', 'erika.mustermann', 'example.com'] as $identityFragment) {
            $this->assertStringNotContainsStringIgnoringCase(
                $identityFragment,
                $blobKey,
                "The storage path leaks identity data: {$blobKey}",
            );
        }

        $this->assertStringNotContainsString(
            "/{$this->employee->id}/",
            $blobKey,
            'The storage path must not be keyed on the identity user id.',
        );
    }

    public function test_employee_cannot_reach_another_subjects_anamnesis_or_documents(): void
    {
        Storage::fake('public');

        $this->actingAs($this->otherEmployee, 'sanctum')
            ->putJson('/api/employee/profile', ['name' => 'Foreign Owner', 'birthYear' => 1971])
            ->assertStatus(200);

        $foreignDocumentId = $this->actingAs($this->otherEmployee, 'sanctum')
            ->post('/api/employee/documents', [
                'file' => UploadedFile::fake()->create('foreign.pdf', 32, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->json('data.id');

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/employee/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.anamnesis', null)
            ->assertJsonPath('data.documents', []);

        $this->assertStringNotContainsString('foreign.pdf', $response->getContent());
        $this->assertStringNotContainsString($foreignDocumentId, $response->getContent());

        // There is deliberately no per-document read endpoint (bytes are served
        // from the public disk; ADR-001 §2.9 storage hardening follow-up), so a
        // foreign document id resolves to nothing at all.
        $this->actingAs($this->employee, 'sanctum')
            ->getJson("/api/employee/documents/{$foreignDocumentId}")
            ->assertStatus(404);
    }

    /**
     * The moved data must stay out of the company and admin portals entirely —
     * not merely be authorized away.
     */
    public function test_no_company_or_admin_route_exposes_anamnesis_or_documents(): void
    {
        $exposed = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->filter(fn (string $uri): bool => Str::startsWith($uri, ['api/company', 'api/admin']))
            ->filter(fn (string $uri): bool => Str::contains($uri, ['document', 'anamnesis', 'wearable']))
            ->values()
            ->all();

        $this->assertSame([], $exposed, 'Company/admin routes must not reach health documents or anamnesis data.');
    }

    private function subjectIdFor(User $user): string
    {
        return app(MappingServiceContract::class)->provisionOwnSubject(
            $user->id,
            PurposeCode::PROVISIONING,
        );
    }
}
