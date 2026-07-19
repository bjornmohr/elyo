<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case for the multi-database ELYO topology (D9 — Postgres-only).
 *
 * The suite runs against the `elyo_*_test` databases in the docker Postgres
 * container (phpunit.xml points each connection at its *_test database). Schema
 * is built once per test process with the `elyo_migrator` role via the
 * `*_migrator` connections; every test then runs inside a transaction on the
 * RUNTIME connections (identity/mapping/health/audit) and is rolled back, so
 * the real per-role grants are exercised on every query.
 *
 * Concrete test classes no longer apply `RefreshDatabase` themselves — this
 * base class owns it and overrides the fresh-migration step to cover all four
 * connections. Running the suite requires docker:
 *   docker compose exec api php artisan test
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Runtime connections wrapped in a rolled-back transaction per test. These
     * use the *_rt / *_svc roles, so grant violations surface in tests.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = ['identity', 'mapping', 'health', 'audit'];

    /**
     * Migrator connection => migration path, in dependency order. DDL runs as
     * `elyo_migrator`; tables it creates inherit the runtime grants configured
     * in infra/postgres/initdb.
     *
     * @var array<string, string>
     */
    private const MIGRATOR_PATHS = [
        'identity_migrator' => 'database/migrations/identity',
        'mapping_migrator' => 'database/migrations/mapping',
        'health_migrator' => 'database/migrations/health',
        'audit_migrator' => 'database/migrations/audit',
    ];

    /**
     * Fresh-migrate every domain database once per process, then begin the
     * per-connection transactions. Overrides RefreshDatabase's single-connection
     * default (which would run as a runtime role that cannot create tables).
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            foreach (self::MIGRATOR_PATHS as $connection => $path) {
                $this->artisan('migrate:fresh', [
                    '--database' => $connection,
                    '--path' => $path,
                    '--force' => true,
                ]);
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
