<?php

namespace Tests\Feature\Runtime;

use App\Runtime\RuntimeProfile;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class RuntimeProfileBootTest extends TestCase
{
    public function test_identity_runtime_registers_only_identity_routes_and_connections(): void
    {
        $routes = $this->apiRoutesFor('identity');
        $uris = array_column($routes, 'uri');

        $this->assertCount(35, $routes);
        $this->assertContains('api/health', $uris);
        $this->assertContains('api/auth/login', $uris);
        $this->assertContains('api/admin/companies', $uris);
        $this->assertContains('api/admin/system-exercises', $uris);
        $this->assertContains('api/partner/login', $uris);
        $this->assertNotContains('api/company/dashboard', $uris);
        $this->assertNotContains('api/employee/dashboard', $uris);

        $health = $this->databaseStateFor('identity', 'health');
        $mapping = $this->databaseStateFor('identity', 'mapping');

        // Identity reaches exactly one database. `audit` was removed together
        // with the last identity-side audit write: elyo_identity_rt has no
        // CONNECT on elyo_audit, so declaring it promised a connection the role
        // cannot open.
        $this->assertSame(['identity'], $health['connections']);
        $this->assertFalse($health['configured']);
        $this->assertSame(\InvalidArgumentException::class, $health['exception']);
        $this->assertFalse($mapping['configured']);
        $this->assertSame(\InvalidArgumentException::class, $mapping['exception']);
    }

    public function test_employee_runtime_registers_only_employee_routes_and_connections(): void
    {
        $routes = $this->apiRoutesFor('employee');
        $uris = array_column($routes, 'uri');

        $this->assertCount(19, $routes);
        $this->assertContains('api/health', $uris);
        $this->assertContains('api/employee/dashboard', $uris);
        $this->assertContains('api/employee/lab-markers', $uris);
        $this->assertNotContains('api/auth/login', $uris);
        $this->assertNotContains('api/admin/companies', $uris);
        $this->assertNotContains('api/company/dashboard', $uris);
        $this->assertNotContains('api/partner/login', $uris);
        $this->assertSame(404, $this->statusFor('employee', '/api/company/dashboard'));

        $database = $this->databaseStateFor('employee', 'health');

        $this->assertSame(['audit', 'health', 'identity', 'mapping'], $database['connections']);
        $this->assertTrue($database['configured']);
        $this->assertNull($database['exception']);
    }

    public function test_company_runtime_registers_only_company_routes_and_connections(): void
    {
        $routes = $this->apiRoutesFor('company');
        $uris = array_column($routes, 'uri');

        $this->assertCount(25, $routes);
        $this->assertContains('api/health', $uris);
        $this->assertContains('api/company/dashboard', $uris);
        $this->assertNotContains('api/auth/login', $uris);
        $this->assertNotContains('api/admin/companies', $uris);
        $this->assertNotContains('api/employee/dashboard', $uris);
        $this->assertNotContains('api/partner/login', $uris);
        $this->assertSame(404, $this->statusFor('company', '/api/employee/dashboard'));

        $health = $this->databaseStateFor('company', 'health');
        $mapping = $this->databaseStateFor('company', 'mapping');

        $this->assertSame(['audit', 'identity'], $health['connections']);
        $this->assertFalse($health['configured']);
        $this->assertSame(\InvalidArgumentException::class, $health['exception']);
        $this->assertStringContainsString(
            'Database connection [health] not configured.',
            $health['message'],
        );
        $this->assertFalse($mapping['configured']);
        $this->assertSame(\InvalidArgumentException::class, $mapping['exception']);

        $response = $this->responseFor('company', '/api/health');

        $this->assertSame(200, $response['status']);
        $this->assertSame(['status' => 'up', 'runtime' => 'company'], $response['body']);
    }

    public function test_full_runtime_preserves_all_routes_and_connections_in_testing(): void
    {
        $routes = $this->apiRoutesFor('full');
        $uris = array_column($routes, 'uri');

        $this->assertCount(77, $routes);
        $this->assertContains('api/health', $uris);
        $this->assertContains('api/auth/login', $uris);
        $this->assertContains('api/admin/companies', $uris);
        $this->assertContains('api/company/dashboard', $uris);
        $this->assertContains('api/employee/dashboard', $uris);
        $this->assertContains('api/partner/login', $uris);

        $registeredUris = $this->registeredApiUrisFor('full');

        $this->assertGreaterThan(
            array_search('api/employee/surveys/{id}/respond', $registeredUris, true),
            array_search('api/partner/register', $registeredUris, true),
        );

        $database = $this->databaseStateFor('full', 'health');

        $this->assertSame([
            'audit',
            'audit_migrator',
            'health',
            'health_migrator',
            'identity',
            'identity_migrator',
            'mapping',
            'mapping_migrator',
            'mariadb',
            'mysql',
            'pgsql',
            'sqlite',
            'sqlsrv',
        ], $database['connections']);
        $this->assertTrue($database['configured']);
        $this->assertNull($database['exception']);
    }

    public function test_empty_runtime_defaults_to_full_only_in_local_environment(): void
    {
        $this->assertCount(77, $this->apiRoutesFor('', 'local'));

        $failure = $this->runArtisan(['route:list'], '', 'production');

        $this->assertNotSame(0, $failure->getExitCode());
        $this->assertStringContainsString(
            'ELYO_RUNTIME is required outside local/testing environments.',
            $failure->getErrorOutput().$failure->getOutput(),
        );
    }

    public function test_unknown_runtime_fails_boot_with_clear_message(): void
    {
        $failure = $this->runArtisan(['route:list'], 'unexpected', 'testing');

        $this->assertNotSame(0, $failure->getExitCode());
        $this->assertStringContainsString(
            'Invalid ELYO_RUNTIME [unexpected].',
            $failure->getErrorOutput().$failure->getOutput(),
        );
    }

    public function test_full_runtime_fails_boot_outside_local_and_testing(): void
    {
        $failure = $this->runArtisan(['route:list'], 'full', 'production');

        $this->assertNotSame(0, $failure->getExitCode());
        $this->assertStringContainsString(
            'ELYO_RUNTIME [full] is allowed only when APP_ENV is local or testing.',
            $failure->getErrorOutput().$failure->getOutput(),
        );
    }

    public function test_cached_configuration_from_another_runtime_fails_boot(): void
    {
        $cachePath = dirname(__DIR__, 3).'/bootstrap/cache/config-runtime-profile-test.php';

        try {
            $cache = $this->runArtisan(['config:cache'], 'full', 'testing', ['APP_CONFIG_CACHE' => $cachePath]);

            $this->assertSame(0, $cache->getExitCode(), $cache->getErrorOutput().$cache->getOutput());

            $failure = $this->runArtisan(['route:list'], 'company', 'testing', ['APP_CONFIG_CACHE' => $cachePath]);

            $this->assertNotSame(0, $failure->getExitCode());
            $this->assertStringContainsString(
                'Cached configuration was built with ELYO_RUNTIME [full] but this process runs with [company].',
                $failure->getErrorOutput().$failure->getOutput(),
            );
        } finally {
            @unlink($cachePath);
        }
    }

    public function test_unknown_runtime_has_no_route_or_connection_set(): void
    {
        foreach ([
            fn (): array => RuntimeProfile::routeFiles('privacy'),
            fn (): array => RuntimeProfile::connections('privacy'),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected an unknown runtime profile to be rejected.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Unknown runtime profile [privacy]', $exception->getMessage());
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function apiRoutesFor(string $runtime, string $appEnvironment = 'testing'): array
    {
        return array_values(array_filter(
            $this->routesFor($runtime, $appEnvironment),
            fn (array $route): bool => str_starts_with($route['uri'], 'api/'),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function routesFor(string $runtime, string $appEnvironment = 'testing'): array
    {
        $process = $this->runArtisan(
            ['route:list', '--json'],
            $runtime,
            $appEnvironment,
        );

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput().$process->getOutput(),
        );

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function statusFor(string $runtime, string $uri): int
    {
        return $this->responseFor($runtime, $uri)['status'];
    }

    /**
     * @return array{status: int, body: mixed}
     */
    private function responseFor(string $runtime, string $uri): array
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create($argv[1], 'GET');
$response = $kernel->handle($request);
fwrite(STDOUT, json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR));
$kernel->terminate($request, $response);
PHP;

        $process = $this->runPhp($code, $runtime, 'testing', [$uri]);

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput().$process->getOutput(),
        );

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<int, string>
     */
    private function registeredApiUrisFor(string $runtime): array
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$uris = [];
foreach ($app['router']->getRoutes()->getRoutes() as $route) {
    if (str_starts_with($route->uri(), 'api/')) {
        $uris[] = $route->uri();
    }
}
fwrite(STDOUT, json_encode($uris, JSON_THROW_ON_ERROR));
PHP;

        $process = $this->runPhp($code, $runtime, 'testing');

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getErrorOutput().$process->getOutput(),
        );

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{connections: array<int, string>, configured: bool, exception: ?string, message: ?string}
     */
    private function databaseStateFor(string $runtime, string $connection): array
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connections = array_keys(array_filter(
    $app['config']->get('database.connections'),
    is_array(...),
));
sort($connections);
$state = [
    'connections' => $connections,
    'configured' => $app['config']->get('database.connections.'.$argv[1]) !== null,
    'exception' => null,
    'message' => null,
];
try {
    $app['db']->connection($argv[1]);
} catch (\Throwable $exception) {
    $state['exception'] = $exception::class;
    $state['message'] = $exception->getMessage();
}
fwrite(STDOUT, json_encode($state, JSON_THROW_ON_ERROR));
PHP;

        $process = $this->runPhp($code, $runtime, 'testing', [$connection]);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<int, string>  $arguments
     * @param  array<string, string>  $environment
     */
    private function runArtisan(
        array $arguments,
        string $runtime,
        string $appEnvironment,
        array $environment = [],
    ): Process {
        return $this->runProcess(
            [PHP_BINARY, 'artisan', ...$arguments],
            $runtime,
            $appEnvironment,
            $environment,
        );
    }

    /**
     * @param  array<int, string>  $arguments
     */
    private function runPhp(
        string $code,
        string $runtime,
        string $appEnvironment,
        array $arguments = [],
    ): Process {
        return $this->runProcess(
            [PHP_BINARY, '-r', $code, ...$arguments],
            $runtime,
            $appEnvironment,
        );
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<string, string>  $environment
     */
    private function runProcess(
        array $command,
        string $runtime,
        string $appEnvironment,
        array $environment = [],
    ): Process {
        $process = new Process(
            $command,
            dirname(__DIR__, 3),
            [
                'APP_ENV' => $appEnvironment,
                'ELYO_RUNTIME' => $runtime,
                // Keep the console from wrapping asserted failure messages.
                'COLUMNS' => '400',
                ...$environment,
            ],
        );
        $process->setTimeout(30);
        $process->run();

        return $process;
    }
}
