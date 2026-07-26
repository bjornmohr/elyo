<?php

namespace App\Runtime;

use RuntimeException;

/**
 * Resolves the ELYO_RUNTIME start profile (ADR-001 §2.4, ADR-003 D2).
 *
 * One image boots as a restricted runtime: a profile requires only its own
 * route files, so an excluded endpoint is never registered and cannot leak,
 * and its connection set only contains the databases its PostgreSQL role holds
 * credentials for.
 *
 * The `*_migrator` connections are deliberately absent from every deployable
 * profile: ADR-001 §2.4 requires the migration role to never live in a runtime
 * container. Schema migrations and the retention job therefore run outside the
 * runtime containers (locally under `full`, in the pilot as a separate ops
 * step) until the Privacy/Admin runtime from ADR-003 is delivered.
 */
final class RuntimeProfile
{
    public const IDENTITY = 'identity';

    public const EMPLOYEE = 'employee';

    public const COMPANY = 'company';

    public const FULL = 'full';

    public const PROFILES = [self::IDENTITY, self::EMPLOYEE, self::COMPANY, self::FULL];

    /**
     * Environments in which the unrestricted `full` profile is permitted.
     */
    private const LOCAL_ENVIRONMENTS = ['local', 'testing'];

    /**
     * @var array<string, array<int, string>>
     */
    private const CONNECTIONS = [
        self::IDENTITY => ['identity', 'audit'],
        self::EMPLOYEE => ['identity', 'mapping', 'health', 'audit'],
        self::COMPANY => ['identity', 'audit'],
        self::FULL => [
            'sqlite',
            'mysql',
            'mariadb',
            'identity',
            'mapping',
            'health',
            'audit',
            'identity_migrator',
            'mapping_migrator',
            'health_migrator',
            'audit_migrator',
            'pgsql',
            'sqlsrv',
        ],
    ];

    /**
     * Route files under routes/api/, registered in this order. The shared
     * health check is registered by routes/api.php for every profile.
     *
     * @var array<string, array<int, string>>
     */
    private const ROUTE_FILES = [
        self::IDENTITY => ['identity', 'identity-partner'],
        self::EMPLOYEE => ['employee'],
        self::COMPANY => ['company'],
        self::FULL => ['identity', 'company', 'employee', 'identity-partner'],
    ];

    /**
     * Resolve and validate the profile from the process environment.
     */
    public static function resolve(): string
    {
        return self::validate(
            trim((string) env('ELYO_RUNTIME', '')),
            trim((string) env('APP_ENV', 'production')),
        );
    }

    /**
     * Fail-safe validation: an unknown or missing profile must never fall back
     * to an unrestricted runtime outside local/testing.
     */
    public static function validate(string $runtime, string $appEnvironment): string
    {
        $isLocal = in_array($appEnvironment, self::LOCAL_ENVIRONMENTS, true);

        if ($runtime === '') {
            if (! $isLocal) {
                throw new RuntimeException(
                    'ELYO_RUNTIME is required outside local/testing environments. Expected one of: identity, employee, company.',
                );
            }

            return self::FULL;
        }

        if (! in_array($runtime, self::PROFILES, true)) {
            throw new RuntimeException(
                "Invalid ELYO_RUNTIME [{$runtime}]. Expected one of: identity, employee, company, full.",
            );
        }

        if ($runtime === self::FULL && ! $isLocal) {
            throw new RuntimeException(
                'ELYO_RUNTIME [full] is allowed only when APP_ENV is local or testing.',
            );
        }

        return $runtime;
    }

    /**
     * @return array<int, string>
     */
    public static function connections(string $runtime): array
    {
        return self::CONNECTIONS[$runtime]
            ?? throw new RuntimeException("Unknown runtime profile [{$runtime}]: no connection set defined.");
    }

    /**
     * @return array<int, string>
     */
    public static function routeFiles(string $runtime): array
    {
        return self::ROUTE_FILES[$runtime]
            ?? throw new RuntimeException("Unknown runtime profile [{$runtime}]: no route set defined.");
    }

    /**
     * Guard against a cached configuration built for a different runtime.
     *
     * `php artisan config:cache` freezes the resolved profile and its
     * connection allowlist, and the config files are no longer executed — so
     * the checks in validate() would silently not run. A cache built under
     * `full` and shipped in a `company` container would hand that container
     * mapping and health credentials, which is exactly what the profiles
     * exist to prevent.
     */
    public static function assertMatchesEnvironment(string $configuredProfile, string $configuredEnvironment): void
    {
        $expectations = [
            'ELYO_RUNTIME' => [$configuredProfile, trim((string) env('ELYO_RUNTIME', ''))],
            'APP_ENV' => [$configuredEnvironment, trim((string) env('APP_ENV', ''))],
        ];

        foreach ($expectations as $variable => [$configured, $actual]) {
            if ($actual !== '' && $actual !== $configured) {
                throw new RuntimeException(
                    "Cached configuration was built with {$variable} [{$configured}] but this process runs with [{$actual}]. "
                    .'Run `php artisan config:clear` or build the configuration cache per runtime.',
                );
            }
        }
    }
}
