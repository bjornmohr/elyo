<?php

namespace Tests\Boundary;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeptracBoundaryTest extends TestCase
{
    public function test_deptrac_rejects_an_http_import_of_subject_mapping(): void
    {
        $temporaryDirectory = sys_get_temp_dir().'/elyo-deptrac-'.bin2hex(random_bytes(8));
        $fixtureDirectory = $temporaryDirectory.'/App/Http/Controllers';
        $configurationPath = $temporaryDirectory.'/deptrac.yaml';
        $fixturePath = $fixtureDirectory.'/ForbiddenMappingController.php';

        mkdir($fixtureDirectory, 0700, true);

        try {
            file_put_contents(
                $fixturePath,
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
            $this->assertStringContainsString('ForbiddenMappingController', $output);
            $this->assertStringContainsString('SubjectMapping', $output);
        } finally {
            @unlink($fixturePath);
            @unlink($configurationPath);
            @rmdir($fixtureDirectory);
            @rmdir(dirname($fixtureDirectory));
            @rmdir(dirname(dirname($fixtureDirectory)));
            @rmdir($temporaryDirectory);
        }
    }
}
