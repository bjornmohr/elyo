<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Database\Seeders\DemoDataSeeder;
use RuntimeException;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

class DemoDataSeederSubjectProvisioningTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('demo-seeder-test');
    }

    public function test_every_seeded_identity_has_a_stable_health_subject_across_reruns(): void
    {
        $this->seed(DemoDataSeeder::class);

        $mappingService = app(MappingServiceContract::class);
        $firstSubjects = User::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->email => $mappingService->resolveOwnSubject(
                    $user->id,
                    PurposeCode::HEALTH_SELF_READ,
                ),
            ])
            ->all();

        $this->assertNotEmpty($firstSubjects);
        $this->seed(DemoDataSeeder::class);

        $secondSubjects = User::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->email => $mappingService->resolveOwnSubject(
                    $user->id,
                    PurposeCode::HEALTH_SELF_READ,
                ),
            ])
            ->all();

        $this->assertSame($firstSubjects, $secondSubjects);
    }

    public function test_subject_provisioning_failure_does_not_expose_identifiers(): void
    {
        $sensitiveSubjectId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $sensitiveEmail = 'sensitive-seeder-user@example.test';
        $mappingService = $this->createMock(MappingServiceContract::class);
        $mappingService->expects($this->once())
            ->method('provisionOwnSubject')
            ->willThrowException(new RuntimeException(
                "Provisioning failed for {$sensitiveEmail} and {$sensitiveSubjectId}.",
            ));
        app()->instance(MappingServiceContract::class, $mappingService);

        try {
            $this->seed(DemoDataSeeder::class);
            $this->fail('Demo seeding should fail when subject provisioning fails.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Health subject provisioning failed during demo seeding.',
                $exception->getMessage(),
            );
            $this->assertStringNotContainsString($sensitiveEmail, $exception->getMessage());
            $this->assertStringNotContainsString($sensitiveSubjectId, $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }
}
