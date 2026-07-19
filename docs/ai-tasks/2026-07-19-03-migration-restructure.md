# Task: Restructure migrations into per-domain directories

## Goal

Rebuild the migration set from scratch into per-connection directories (`identity`, `mapping`, `health`, `audit`) with a single consolidated baseline per domain, plus tooling to migrate/seed all connections in one command. Additionally: switch the test suite from sqlite in-memory to the Postgres test databases (D9 — one engine, production parity). App behavior unchanged; `wellbeing_entries` stays (temporarily) in identity.

## Context

Relevant files:

- apps/api-laravel/database/migrations/ (5 consolidated base files + ~20 incremental)
- apps/api-laravel/database/seeders/ (DatabaseSeeder, DemoDataSeeder, PointSettingsSeeder, SystemExerciseSeeder)
- apps/api-laravel/config/database.php (connections from prompt 02)
- ADR-003 (D1), execution plan D8

Background:

- The app is pre-production: full schema rebuild is explicitly allowed; no data migration needed.
- Everything currently in the schema is identity-domain for now (users, companies, teams, roles, invites, surveys, measures, points, documents, anamnesis, wearables, wellbeing) — health/mapping/audit start empty and are filled by prompts 04/07/08/11.
- Anamnesis/health documents/wearables are knowingly left in identity (execution plan D8) — flag, don't move.

## Scope

Change only:

- apps/api-laravel/database/migrations/** (restructure: `migrations/identity/`, `migrations/mapping/`, `migrations/health/`, `migrations/audit/`)
- apps/api-laravel/database/seeders/**
- apps/api-laravel/app/Providers or console config as needed to register per-connection migration paths
- Makefile / composer scripts: add `migrate-all` / `fresh-all` helpers
- apps/api-laravel/phpunit.xml + tests/TestCase.php: switch to the Postgres test databases (`elyo_*_test` from prompt 02)

Do not change:

- Models, services, controllers, routes (behavior frozen)
- OpenAPI
- apps/web-angular

## Requirements

1. One consolidated baseline migration set under `migrations/identity/` reproducing today's schema 1:1 (including framework tables: cache, jobs, sessions, personal_access_tokens, notifications), each `Schema::connection('identity')`-scoped or run with `--database=identity --path=...`.
2. `mapping/`, `health/`, `audit/` directories exist (empty baseline is fine) and are wired into the migrate tooling.
3. Provide `php artisan elyo:migrate-fresh` (new console command or composer script) that runs migrate:fresh for every connection in dependency order and then seeds.
4. Seeders run unchanged in behavior; factories keep working.
5. Test setup (D9, Postgres-only): phpunit env points every connection at its `elyo_*_test` database using the **runtime roles** (not migrator); `RefreshDatabase` (or an explicit fresh-migration bootstrap via migrator credentials before the run) is configured per connection and documented. Tests require the docker Postgres — matches the documented workflow (`docker compose exec api php artisan test`). Existing tests remain green; driver-specific workarounds that only existed for sqlite (e.g. sqlite branches in `WellbeingService::isUniqueConstraintViolation`) may be simplified to Postgres SQLSTATE handling — list every such simplification.
6. Old migration files are deleted (git history is the archive); document this in the migration README section.
7. Remove sqlite from the supported test path entirely: no `DB_CONNECTION=sqlite` remnants in phpunit.xml, .env.example, or docs.

## Constraints

- Zero behavior change: `php artisan route:list` identical, all existing tests green without modification (except test bootstrap for connections).
- Do not rename tables/columns in this prompt.
- No new packages.

## Privacy and Security Requirements

- Preserve company, team and user scoping exactly.
- No seed data with real-looking personal health values beyond what DemoDataSeeder already contains.

## Validation

Run:

    docker compose exec api php artisan elyo:migrate-fresh --seed
    docker compose exec api php artisan test
    docker compose exec api php artisan route:list | wc -l

Expected result:

- Fresh multi-connection migration + seed succeeds on Postgres; full suite green against the Postgres test databases; route count unchanged.

## Output Required

1. Files changed (tree of new migration layout)
2. Confirmation of schema parity (how verified, e.g. schema dump diff)
3. Commands run and results
4. Open questions

## Review Checklist

- Is schema parity with the previous baseline demonstrated, not asserted?
- Are framework tables on the correct (identity/default) connection?
- Do tests run with runtime-role credentials (so grants stay testable), migrations with migrator?
- Are all sqlite remnants gone?
- Is `wellbeing_entries` untouched (moves in prompt 08)?
