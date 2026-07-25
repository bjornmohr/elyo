<?php

namespace Tests\Boundary;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeptracBoundaryTest extends TestCase
{
    public function test_deptrac_rejects_an_http_import_of_subject_mapping(): void
    {
        $output = $this->analyseFixture(
            'App/Http/Controllers',
            'ForbiddenMappingController.php',
            <<<'PHP'
<?php

namespace App\Http\Controllers;

use App\Models\Privacy\SubjectMapping;

final class ForbiddenMappingController
{
    public function mapping(): SubjectMapping
    {
        return new SubjectMapping();
    }
}
PHP,
        );

        $this->assertStringContainsString('ForbiddenMappingController', $output);
        $this->assertStringContainsString('SubjectMapping', $output);
    }

    /**
     * The models moved in prompt 08a (ADR-003 D8) are covered by the
     * `HealthModels` layer: identity-side services reach them only through
     * `App\Services\Health\*`, never directly.
     */
    public function test_deptrac_rejects_an_identity_service_import_of_a_health_model(): void
    {
        $output = $this->analyseFixture(
            'App/Services',
            'ForbiddenAnamnesisService.php',
            <<<'PHP'
<?php

namespace App\Services;

use App\Models\Health\AnamnesisProfile;

final class ForbiddenAnamnesisService
{
    public function profile(): AnamnesisProfile
    {
        return new AnamnesisProfile();
    }
}
PHP,
        );

        $this->assertStringContainsString('ForbiddenAnamnesisService', $output);
        $this->assertStringContainsString('AnamnesisProfile', $output);
    }

    /**
     * Runs deptrac over the real ruleset plus one temporary fixture file and
     * asserts that the analysis fails. Returns the combined output.
     */
    private function analyseFixture(string $namespaceDirectory, string $fileName, string $source): string
    {
        $temporaryDirectory = sys_get_temp_dir().'/elyo-deptrac-'.bin2hex(random_bytes(8));
        $fixtureDirectory = $temporaryDirectory.'/'.$namespaceDirectory;
        $configurationPath = $temporaryDirectory.'/deptrac.yaml';
        $fixturePath = $fixtureDirectory.'/'.$fileName;

        mkdir($fixtureDirectory, 0700, true);

        try {
            file_put_contents($fixturePath, $source);

            $configuration = file_get_contents(base_path('deptrac.yaml'));
            $this->assertIsString($configuration);
            $configuration = str_replace(
                "  paths:\n    - ./app",
                "  paths:\n    - ".base_path('app')."\n    - {$temporaryDirectory}/App",
                $configuration,
            );
            file_put_contents($configurationPath, $configuration);

            $process = new Process([
                base_path('vendor/bin/deptrac'),
                'analyse',
                "--config-file={$configurationPath}",
                '--no-cache',
            ], base_path());
            $process->run();

            $output = $process->getOutput().$process->getErrorOutput();
            $this->assertNotSame(0, $process->getExitCode(), $output);

            return $output;
        } finally {
            @unlink($fixturePath);
            @unlink($configurationPath);

            for ($directory = $fixtureDirectory; str_starts_with($directory, $temporaryDirectory); $directory = dirname($directory)) {
                @rmdir($directory);
            }
        }
    }
}
