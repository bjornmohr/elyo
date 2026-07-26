<?php

namespace Tests\Feature\Runtime;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Executes each runtime with its REAL, restricted PostgreSQL role.
 *
 * `RuntimeProfileBootTest` proves which routes and connections a profile
 * registers. It cannot prove that a runtime can actually serve them, because
 * the suite runs with every role available (tests/bootstrap.php). That gap hid
 * two defects until the compose split first exercised the runtimes separately:
 *
 *   - Sanctum wrote `last_used_at` on every authenticated request, which the
 *     employee runtime cannot do (SELECT-only on identity by design).
 *   - Invite acceptance constructor-injected the mapping service, which the
 *     identity runtime has neither credentials nor a connection for.
 *
 * Each test here boots a subprocess with the credential set the corresponding
 * compose service actually receives, so a regression fails in CI rather than in
 * a container.
 */
class RuntimeCredentialTest extends TestCase
{
    /**
     * Credentials handed to `api-employee` in docker-compose.yml: identity is
     * reached as `elyo_employee_rt`, which has SELECT and nothing more.
     *
     * @return array<string, string>
     */
    private function employeeRuntimeCredentials(): array
    {
        return [
            'DB_IDENTITY_USERNAME' => 'elyo_employee_rt',
            'DB_IDENTITY_PASSWORD' => $this->passwordFor('DB_HEALTH_PASSWORD', 'employee_rt_dev'),
        ];
    }

    /**
     * Credentials handed to `api-identity`: one role, one connection. Mapping
     * credentials and key material are blanked to match the container.
     *
     * @return array<string, string>
     */
    private function identityRuntimeCredentials(): array
    {
        return [
            'DB_MAPPING_USERNAME' => '',
            'DB_MAPPING_PASSWORD' => '',
            'MAPPING_ENCRYPTION_KEY' => '',
            'MAPPING_HMAC_KEY' => '',
            'MAPPING_SUBJECT_DERIVATION_KEY' => '',
        ];
    }

    public function test_employee_runtime_serves_its_own_routes_with_only_its_own_role(): void
    {
        $email = 'runtime-credential-employee@test.local';

        try {
            $token = $this->seedEmployeeToken($email);

            $response = $this->responseFor(
                'employee',
                '/api/employee/dashboard',
                $this->employeeRuntimeCredentials(),
                $token,
            );

            // 500 here means the employee runtime cannot serve a route it owns
            // with the credentials it is actually given — the Sanctum defect.
            $this->assertSame(200, $response['status'], json_encode($response['body']));
        } finally {
            $this->deleteUser($email);
        }
    }

    public function test_identity_runtime_serves_invite_routes_without_mapping_credentials(): void
    {
        $response = $this->responseFor(
            'identity',
            '/api/auth/invite/verify?token=no-such-invite-token',
            $this->identityRuntimeCredentials(),
        );

        // An unknown token is a 404 by contract. A 500 means the identity
        // runtime tried to build the mapping service it has no credentials for.
        $this->assertSame(404, $response['status'], json_encode($response['body']));
    }

    public function test_identity_runtime_accepts_an_invite_without_provisioning_a_subject(): void
    {
        $email = 'runtime-credential-invite@test.local';
        $rawToken = 'runtime-credential-invite-token';

        try {
            $this->seedPendingInvite($email, $rawToken);

            $response = $this->postFor(
                'identity',
                '/api/auth/invite/accept',
                $this->identityRuntimeCredentials(),
                [
                    'token' => $rawToken,
                    'name' => 'Runtime Credential Invitee',
                    'password' => 'Str0ng-Passw0rd!',
                    'password_confirmation' => 'Str0ng-Passw0rd!',
                ],
            );

            $this->assertContains(
                $response['status'],
                [200, 201],
                json_encode($response['body']),
            );
        } finally {
            $this->deleteUser($email);
            $this->deleteInvite($email);
        }
    }

    private function passwordFor(string $key, string $fallback): string
    {
        $value = getenv($key);

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /**
     * Creates a committed employee with a Sanctum token, so a separate process
     * can authenticate against it. Runs under `full` with the suite's own
     * credentials; only the request under test uses a restricted role.
     */
    private function seedEmployeeToken(string $email): string
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = \App\Models\Company::factory()->create();

$user = \App\Models\User::create([
    'name' => 'Runtime Credential Employee',
    'email' => $argv[1],
    'password' => 'Str0ng-Passw0rd!',
    'company_id' => $company->id,
    'team_id' => null,
]);

\App\Models\UserRole::create(['user_id' => $user->id, 'role' => 'EMPLOYEE']);

fwrite(STDOUT, $user->createToken('runtime-credential-test')->plainTextToken);
PHP;

        $process = $this->runPhp($code, 'full', [], [$email]);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());

        return trim($process->getOutput());
    }

    private function seedPendingInvite(string $email, string $rawToken): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = \App\Models\Company::factory()->create();

\Illuminate\Support\Facades\DB::connection('identity')->table('invite_tokens')->insert([
    'email' => $argv[1],
    'company_id' => $company->id,
    'team_id' => null,
    'role' => 'EMPLOYEE',
    'token_hash' => hash('sha256', $argv[2]),
    'status' => 'pending',
    'invited_by_user_id' => null,
    'expires_at' => now()->addDay(),
    'created_at' => now(),
    'updated_at' => now(),
]);
PHP;

        $process = $this->runPhp($code, 'full', [], [$email, $rawToken]);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }

    private function deleteUser(string $email): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$identity = \Illuminate\Support\Facades\DB::connection('identity');
$rows = $identity->table('users')->where('email', $argv[1])->get(['id', 'company_id']);

// The employee request lazily provisions a subject (ResolvesOwnSubject), so the
// mapping and health rows must go too — boundary tests assert both tables are
// empty. Audit events are deliberately left: the audit log is append-only and
// deleting from it would defeat its purpose.
$crypto = app(\App\Services\Privacy\MappingCryptography::class);

foreach ($rows as $row) {
    \Illuminate\Support\Facades\DB::connection('mapping')
        ->table('subject_mappings')
        ->where('user_id_hmac', $crypto->userIdHmac((int) $row->id))
        ->delete();

    \Illuminate\Support\Facades\DB::connection('health')
        ->table('health_subjects')
        ->where('id', $crypto->healthSubjectIdForUserId((int) $row->id))
        ->delete();
}

$ids = $rows->pluck('id')->all();
$companyIds = $rows->pluck('company_id')->filter()->unique()->all();

if ($ids !== []) {
    $identity->table('personal_access_tokens')
        ->where('tokenable_type', \App\Models\User::class)
        ->whereIn('tokenable_id', $ids)
        ->delete();
    $identity->table('user_roles')->whereIn('user_id', $ids)->delete();
    $identity->table('users')->whereIn('id', $ids)->delete();
}

if ($companyIds !== []) {
    $identity->table('companies')->whereIn('id', $companyIds)->delete();
}
PHP;

        $this->runPhp($code, 'full', [], [$email]);
    }

    private function deleteInvite(string $email): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$identity = \Illuminate\Support\Facades\DB::connection('identity');

// Also drop the company the invite was seeded against, in case acceptance
// failed and deleteUser() therefore found no user to clean it up through.
$companyIds = $identity->table('invite_tokens')
    ->where('email', $argv[1])->pluck('company_id')->filter()->unique()->all();

$identity->table('invite_tokens')->where('email', $argv[1])->delete();

if ($companyIds !== []) {
    $identity->table('companies')->whereIn('id', $companyIds)->delete();
}
PHP;

        $this->runPhp($code, 'full', [], [$email]);
    }

    /**
     * @param  array<string, string>  $credentials
     * @return array{status: int, body: mixed}
     */
    private function responseFor(
        string $runtime,
        string $uri,
        array $credentials,
        ?string $bearerToken = null,
    ): array {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$server = ['HTTP_ACCEPT' => 'application/json'];
if (($argv[2] ?? '') !== '') {
    $server['HTTP_AUTHORIZATION'] = 'Bearer '.$argv[2];
}
$request = \Illuminate\Http\Request::create($argv[1], 'GET', [], [], [], $server);
$response = $kernel->handle($request);
fwrite(STDOUT, json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR));
$kernel->terminate($request, $response);
PHP;

        $process = $this->runPhp($code, $runtime, $credentials, [$uri, $bearerToken ?? '']);

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: mixed}
     */
    private function postFor(string $runtime, string $uri, array $credentials, array $payload): array
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create(
    $argv[1],
    'POST',
    json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR),
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json'],
);
$response = $kernel->handle($request);
fwrite(STDOUT, json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR));
$kernel->terminate($request, $response);
PHP;

        $process = $this->runPhp(
            $code,
            $runtime,
            $credentials,
            [$uri, json_encode($payload, JSON_THROW_ON_ERROR)],
        );

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, string>  $environment
     * @param  array<int, string>  $arguments
     */
    private function runPhp(string $code, string $runtime, array $environment, array $arguments = []): Process
    {
        $process = new Process(
            [PHP_BINARY, '-r', $code, ...$arguments],
            dirname(__DIR__, 3),
            [
                'APP_ENV' => 'testing',
                'ELYO_RUNTIME' => $runtime,
                'COLUMNS' => '400',
                ...$environment,
            ],
        );
        $process->setTimeout(60);
        $process->run();

        return $process;
    }
}
