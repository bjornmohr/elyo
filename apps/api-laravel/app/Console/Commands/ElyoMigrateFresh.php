<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Rebuilds every domain database from its per-connection baseline in dependency
 * order, then (optionally) seeds. This is the canonical reset path for the
 * multi-database ELYO topology (ELYO-104 / ADR-001). No production data exists.
 *
 * Schema DDL runs as the `elyo_migrator` role via the `*_migrator` connections;
 * seeding runs on the default runtime connection (identity), so grants stay
 * exercised end to end.
 */
class ElyoMigrateFresh extends Command
{
    protected $signature = 'elyo:migrate-fresh
        {--seed : Run database seeders after all connections are migrated}';

    protected $description = 'Fresh-migrate every ELYO domain database (identity, mapping, health, audit) in order, then optionally seed';

    /**
     * Domains in dependency order. `connection` is the migrator login used for
     * DDL; `path` is that domain\'s migration directory. Later prompts add
     * baselines under mapping/health/audit — the directories are wired here.
     */
    private const DOMAINS = [
        ['name' => 'identity', 'connection' => 'identity_migrator', 'path' => 'database/migrations/identity'],
        ['name' => 'mapping', 'connection' => 'mapping_migrator', 'path' => 'database/migrations/mapping'],
        ['name' => 'health', 'connection' => 'health_migrator', 'path' => 'database/migrations/health'],
        ['name' => 'audit', 'connection' => 'audit_migrator', 'path' => 'database/migrations/audit'],
    ];

    public function handle(): int
    {
        foreach (self::DOMAINS as $domain) {
            $this->components->info("Fresh-migrating [{$domain['name']}] on connection [{$domain['connection']}]");

            $exit = $this->call('migrate:fresh', [
                '--database' => $domain['connection'],
                '--path' => $domain['path'],
                '--force' => true,
            ]);

            if ($exit !== self::SUCCESS) {
                $this->components->error("Migration failed for domain [{$domain['name']}].");

                return self::FAILURE;
            }
        }

        if ($this->option('seed')) {
            $this->components->info('Seeding (default identity connection)');

            $exit = $this->call('db:seed', ['--force' => true]);

            if ($exit !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
