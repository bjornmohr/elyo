<?php

namespace Tests\Feature\Privacy;

use App\Models\Health\HealthSubject;
use App\Models\User;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use App\Services\Privacy\SubjectProvisioningState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

class ProvisionMissingHealthSubjectsTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('provision-command-test');
    }

    public function test_dry_run_reports_missing_users_without_provisioning(): void
    {
        $user = User::factory()->create(['email' => 'dry-run-provisioning@example.test']);

        $exitCode = Artisan::call('elyo:provision-subjects', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Users scanned: 1', $output);
        $this->assertStringContainsString('Missing: 1', $output);
        $this->assertStringContainsString('Active: 0', $output);
        $this->assertStringContainsString('Revoked: 0', $output);
        $this->assertStringNotContainsString($user->email, $output);
        $this->assertSame(0, HealthSubject::query()->count());
    }

    public function test_command_backfills_a_missing_subject_and_an_idempotent_rerun_creates_nothing(): void
    {
        $user = User::factory()->create(['email' => 'backfill-provisioning@example.test']);

        $firstExitCode = Artisan::call('elyo:provision-subjects');
        $firstOutput = Artisan::output();
        $subjectId = app(MappingServiceContract::class)->resolveOwnSubject(
            $user->id,
            PurposeCode::HEALTH_SELF_READ,
        );

        $this->assertSame(0, $firstExitCode);
        $this->assertStringContainsString('Users scanned: 1', $firstOutput);
        $this->assertStringContainsString('Missing before: 1', $firstOutput);
        $this->assertStringContainsString('Provisioned: 1', $firstOutput);
        $this->assertStringContainsString('Failed: 0', $firstOutput);
        $this->assertStringContainsString('Missing after: 0', $firstOutput);
        $this->assertStringNotContainsString($user->email, $firstOutput);
        $this->assertStringNotContainsString($subjectId, $firstOutput);
        $this->assertSame(1, HealthSubject::query()->count());

        $secondExitCode = Artisan::call('elyo:provision-subjects');
        $secondOutput = Artisan::output();

        $this->assertSame(0, $secondExitCode);
        $this->assertStringContainsString('Missing before: 0', $secondOutput);
        $this->assertStringContainsString('Provisioned: 0', $secondOutput);
        $this->assertStringContainsString('Failed: 0', $secondOutput);
        $this->assertStringContainsString('Missing after: 0', $secondOutput);
        $this->assertStringNotContainsString($subjectId, $secondOutput);
        $this->assertSame(1, HealthSubject::query()->count());
    }

    public function test_orphan_mapping_for_deleted_identity_does_not_hide_a_current_missing_user(): void
    {
        $mappingService = app(MappingServiceContract::class);
        $deletedUser = User::factory()->create();
        $mappingService->provisionOwnSubject($deletedUser->id, PurposeCode::PROVISIONING);
        $deletedUser->delete();
        User::factory()->create();

        $healthSubjectCount = HealthSubject::query()->count();
        $exitCode = Artisan::call('elyo:provision-subjects', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Users scanned: 1', $output);
        $this->assertStringContainsString('Missing: 1', $output);
        $this->assertStringContainsString('Active: 0', $output);
        $this->assertStringContainsString('Revoked: 0', $output);
        $this->assertSame($healthSubjectCount, HealthSubject::query()->count());
    }

    public function test_command_provisions_only_missing_users_and_reports_revoked_links_as_terminal(): void
    {
        $mappingService = app(MappingServiceContract::class);
        $activeUser = User::factory()->create();
        $revokedUser = User::factory()->create();
        $missingUser = User::factory()->create();

        $mappingService->provisionOwnSubject($activeUser->id, PurposeCode::PROVISIONING);
        $mappingService->provisionOwnSubject($revokedUser->id, PurposeCode::PROVISIONING);
        $mappingService->revokeSubjectLink($revokedUser->id, PurposeCode::REVOCATION);

        $exitCode = Artisan::call('elyo:provision-subjects');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Users scanned: 3', $output);
        $this->assertStringContainsString('Missing before: 1', $output);
        $this->assertStringContainsString('Active: 1', $output);
        $this->assertStringContainsString('Revoked: 1', $output);
        $this->assertStringContainsString('Provisioned: 1', $output);
        $this->assertStringContainsString('Failed: 0', $output);
        $this->assertStringContainsString('Missing after: 0', $output);
        $this->assertSame(
            SubjectProvisioningState::ACTIVE,
            $mappingService->provisioningStateForUser($missingUser->id, PurposeCode::PROVISIONING),
        );
        $this->assertSame(
            SubjectProvisioningState::REVOKED,
            $mappingService->provisioningStateForUser($revokedUser->id, PurposeCode::PROVISIONING),
        );
    }

    public function test_command_failure_output_and_log_do_not_expose_identity_or_subject_details(): void
    {
        $user = User::factory()->create(['email' => 'sensitive-command-user@example.test']);
        $sensitiveSubjectId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $mappingService = $this->createMock(MappingServiceContract::class);
        $mappingService->expects($this->once())
            ->method('provisioningStateForUser')
            ->with($user->id, PurposeCode::PROVISIONING)
            ->willReturn(SubjectProvisioningState::MISSING);
        $mappingService->expects($this->once())
            ->method('provisionOwnSubject')
            ->with($user->id, PurposeCode::PROVISIONING)
            ->willThrowException(new RuntimeException(
                "Provisioning failed for {$user->email} and {$sensitiveSubjectId}.",
            ));
        app()->instance(MappingServiceContract::class, $mappingService);
        Log::spy();

        $exitCode = Artisan::call('elyo:provision-subjects');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed: 1', $output);
        $this->assertStringNotContainsString($user->email, $output);
        $this->assertStringNotContainsString($sensitiveSubjectId, $output);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Health subject provisioning failed during elyo:provision-subjects sweep.');
    }

    public function test_state_inspection_failure_output_and_log_do_not_expose_identity_or_subject_details(): void
    {
        $user = User::factory()->create(['email' => 'sensitive-state-user@example.test']);
        $sensitiveSubjectId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $mappingService = $this->createMock(MappingServiceContract::class);
        $mappingService->expects($this->once())
            ->method('provisioningStateForUser')
            ->with($user->id, PurposeCode::PROVISIONING)
            ->willThrowException(new RuntimeException(
                "State lookup failed for {$user->email} and {$sensitiveSubjectId}.",
            ));
        $mappingService->expects($this->never())->method('provisionOwnSubject');
        app()->instance(MappingServiceContract::class, $mappingService);
        Log::spy();

        $exitCode = Artisan::call('elyo:provision-subjects');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Users scanned: 1', $output);
        $this->assertStringContainsString('Failed: 1', $output);
        $this->assertStringNotContainsString($user->email, $output);
        $this->assertStringNotContainsString($sensitiveSubjectId, $output);
        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Health subject provisioning failed during elyo:provision-subjects sweep.');
    }
}
