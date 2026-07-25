<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Privacy\AuditLoggerContract;
use App\Services\Privacy\AuditOutcome;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use App\Services\Privacy\SubjectProvisioningState;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionMissingHealthSubjects extends Command
{
    protected $signature = 'elyo:provision-subjects
        {--dry-run : Count users without an active subject mapping without provisioning}';

    protected $description = 'Provision missing health subjects for identity users';

    public function handle(Container $container): int
    {
        $mappingService = $container->make(MappingServiceContract::class);
        $auditLogger = $container->make(AuditLoggerContract::class);
        $dryRun = (bool) $this->option('dry-run');
        $scanned = 0;
        $missing = 0;
        $active = 0;
        $revoked = 0;
        $provisioned = 0;
        $failed = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($users) use (
                &$active,
                &$failed,
                &$missing,
                &$provisioned,
                &$revoked,
                &$scanned,
                $dryRun,
                $mappingService,
            ): void {
                foreach ($users as $user) {
                    $scanned++;

                    try {
                        $state = $mappingService->provisioningStateForUser(
                            $user->id,
                            PurposeCode::PROVISIONING,
                        );
                    } catch (Throwable) {
                        $failed = $this->recordFailure($failed);

                        continue;
                    }

                    if ($state === SubjectProvisioningState::ACTIVE) {
                        $active++;

                        continue;
                    }

                    if ($state === SubjectProvisioningState::REVOKED) {
                        $revoked++;

                        continue;
                    }

                    $missing++;

                    if ($dryRun) {
                        continue;
                    }

                    try {
                        $mappingService->provisionOwnSubject($user->id, PurposeCode::PROVISIONING);
                        $provisioned++;
                    } catch (Throwable) {
                        $failed = $this->recordFailure($failed);
                    }
                }
            });

        $auditLogger->logProvisioningBackfill([
            'scanned' => $scanned,
            'missing' => $missing,
            'active' => $active,
            'revoked' => $revoked,
            'provisioned' => $provisioned,
            'failed' => $failed,
            'dry_run' => $dryRun,
        ], $failed === 0
            ? AuditOutcome::SUCCESS
            : AuditOutcome::FAILED);

        $this->line("Users scanned: {$scanned}");

        if ($dryRun) {
            $this->line("Missing: {$missing}");
            $this->line("Active: {$active}");
            $this->line("Revoked: {$revoked}");
            $this->line("Failed: {$failed}");

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        }

        $this->line("Missing before: {$missing}");
        $this->line("Active: {$active}");
        $this->line("Revoked: {$revoked}");
        $this->line("Provisioned: {$provisioned}");
        $this->line("Failed: {$failed}");
        $this->line('Missing after: '.max(0, $missing - $provisioned));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function recordFailure(int $failed): int
    {
        Log::warning('Health subject provisioning failed during elyo:provision-subjects sweep.');

        return $failed + 1;
    }
}
