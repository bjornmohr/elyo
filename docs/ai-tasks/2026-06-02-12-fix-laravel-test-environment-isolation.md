# Task: Fix Laravel Test Environment Isolation

## Goal

Make Laravel backend tests run reliably in Docker using the intended test database configuration.

Current QA found that filtered backend tests failed due to database/runtime issues even though `apps/api-laravel/phpunit.xml` declares SQLite in-memory. The test runner appears to use or collide with the local PostgreSQL database instead of an isolated test database.

This is a test-environment hardening task. It must not change product behavior.

## Context

Manual QA for the Measure Participation MVP showed:

- Frontend build passed.
- Angular unit tests passed.
- API smoke checks passed.
- Laravel route list passed.
- `docker compose exec api php artisan test --filter=MeasureParticipation` failed due to test database/runtime issues.
- `docker compose exec api php artisan test --filter=MeasureParticipationSummary` failed due to test database/runtime issues.
- Observed failures included missing/duplicate PostgreSQL tables and migration table errors.
- `apps/api-laravel/phpunit.xml` declares SQLite in-memory, but Docker test execution did not reliably isolate from PostgreSQL.
- Running filtered backend tests concurrently may have amplified collisions, but the core issue is test DB isolation/configuration.

## Scope

Investigate and fix Laravel test environment configuration so backend tests run reliably inside Docker.

Relevant areas to inspect:

- `apps/api-laravel/phpunit.xml`
- `apps/api-laravel/.env`
- `apps/api-laravel/.env.testing` if present
- Laravel database config
- Docker Compose environment variables for the API container
- PHPUnit/Pest configuration
- existing test bootstrap traits
- migration/test database setup
- CI/test scripts if present
- any `RefreshDatabase` / `DatabaseMigrations` usage in tests

## Requirements

### 1. Identify the effective test database configuration

Determine what database connection is actually used when running:

- `docker compose exec api php artisan test --filter=MeasureParticipation`
- `docker compose exec api php artisan test --filter=MeasureParticipationSummary`

Confirm:

- `APP_ENV`
- `DB_CONNECTION`
- `DB_DATABASE`
- whether `.env.testing` exists and is loaded
- whether Docker environment variables override `phpunit.xml`
- whether config cache affects test behavior

Do not guess. Document the observed effective values.

### 2. Choose a reliable test database strategy

Prefer the least invasive strategy consistent with the current project.

Acceptable options:

#### Option A: SQLite in-memory

Use if the existing migrations/tests are compatible with SQLite.

Expected configuration:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

#### Option B: Dedicated PostgreSQL test database

Use if SQLite compatibility is poor because migrations or SQL features depend on PostgreSQL.

Expected configuration:

- test database separated from local dev database
- deterministic setup
- no collision with demo/local data
- no destructive commands against dev DB

If choosing PostgreSQL, do not wipe the normal dev database.

### 3. Fix configuration only as needed

Allowed changes may include:

- `.env.testing.example` or `.env.testing`
- `phpunit.xml`
- Docker Compose test env overrides
- package/composer/npm script adjustments if existing project conventions use them
- test bootstrap configuration
- documentation in `docs/ai-tasks/...handoff.md`

Avoid broad Docker rewrites.

Do not modify application behavior.

### 4. Validate backend tests

After fixing configuration, run:

- `docker compose exec api php artisan test --filter=MeasureParticipation`
- `docker compose exec api php artisan test --filter=MeasureParticipationSummary`

If those pass, run a broader backend test:

- `docker compose exec api php artisan test`

If full suite fails for unrelated existing tests, document exact failures and confirm the targeted Measure Participation tests pass in the corrected test environment.

### 5. Protect local data

Do not run destructive commands against the dev database.

Do not run:

- `php artisan migrate:fresh` against the normal dev database
- `php artisan db:wipe` against the normal dev database
- `docker compose down -v`
- destructive Docker volume resets

If a dedicated test database needs to be reset, make sure it is clearly isolated and document the command.

## Out of Scope

Do not change:

- Measure Participation product logic
- Laravel routes
- Laravel services
- OpenAPI contracts
- Angular frontend
- n8n
- migrations unless a migration is directly incompatible with the chosen test database and the fix is safe
- seed/demo data except if needed for isolated test setup documentation

Do not add new product tests beyond what is needed to verify the environment.

## Expected Handoff

Create or update:

`docs/ai-tasks/2026-06-02-12-fix-laravel-test-environment-isolation-handoff.md`

Include:

- root cause
- effective test DB configuration before fix
- chosen strategy
- files changed
- commands run
- targeted test results
- full backend test result, if run
- confirmation that dev/demo data is not touched by test runs
- any remaining test-environment risks

## Implementation Plan

### Constraints for Patch Mode

- Keep the patch limited to Laravel test-environment isolation.
- Do not modify Measure Participation product logic, routes, OpenAPI, Angular, n8n, seed/demo data, or unrelated documentation.
- Do not run destructive commands against the normal development database.
- Do not use `docker compose down -v`, dev database `migrate:fresh`, or dev database `db:wipe`.
- Treat `../ELYO` as read-only reference material.

### 1. Inspect Effective Test Configuration

Read the relevant configuration files before changing anything:

- `apps/api-laravel/phpunit.xml`
- `apps/api-laravel/.env`
- `apps/api-laravel/.env.testing`, if present
- `apps/api-laravel/config/database.php`
- Docker Compose API service environment and env-file wiring
- PHPUnit/Pest bootstrap and base test case files
- existing Measure Participation tests and their database traits
- existing scripts or CI commands that invoke Laravel tests

Use non-destructive commands only to inspect the effective runtime values inside the API container, such as `php artisan tinker` or a one-off PHP command that prints `app()->environment()`, `config('database.default')`, `config('database.connections.*.database')`, and relevant `env()` values under the same command shape used by `php artisan test`.

Document the observed values in the handoff:

- `APP_ENV`
- effective default database connection
- effective database name/path
- whether `.env.testing` exists and is loaded
- whether container-level environment variables override `phpunit.xml`
- whether cached config is present and influencing test runs

### 2. Identify the Isolation Failure

Determine why the Docker test run is not reliably using the intended isolated test database. Check for:

- Docker environment variables overriding PHPUnit `<env>` values.
- Laravel config cache causing stale database settings.
- `.env.testing` absence or mismatched values.
- test commands executed with the wrong working directory or environment.
- tests using traits or setup code that force PostgreSQL or reuse the default dev connection.
- concurrent filtered test runs colliding on a shared PostgreSQL database.

Record the root cause explicitly. If multiple contributors exist, separate the primary cause from secondary risks.

### 3. Choose the Smallest Reliable Strategy

Prefer SQLite in-memory if existing migrations and the targeted tests are compatible:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

Use a dedicated PostgreSQL test database only if SQLite compatibility is poor or project code depends on PostgreSQL-specific behavior:

- use a database name clearly separate from the development database
- avoid commands that can wipe normal local data
- make setup deterministic and documented
- avoid broad Docker rewrites

Document why the chosen strategy is safer for this project.

### 4. Patch Configuration Only as Needed

Apply the minimal configuration changes required for deterministic test isolation. Candidate files are limited to relevant Laravel test configuration, Docker test environment wiring, existing test scripts, or test bootstrap files.

Potential fixes may include:

- adding or correcting `.env.testing` values
- adjusting `phpunit.xml` test environment values
- changing API container test env precedence only where needed
- ensuring test bootstrap clears or bypasses stale config cache for tests
- adding a dedicated test database connection name or script only if PostgreSQL is chosen

Do not change application runtime behavior outside tests.

### 5. Validate in Patch Mode

After configuration changes, run the targeted backend tests:

- `docker compose exec api php artisan test --filter=MeasureParticipation`
- `docker compose exec api php artisan test --filter=MeasureParticipationSummary`

If both pass, run:

- `docker compose exec api php artisan test`

If the full suite fails for unrelated pre-existing reasons, keep the patch focused and document the exact failures while confirming the targeted Measure Participation tests pass with the corrected test environment.

Do not run frontend builds or Angular tests for this backend-only environment fix unless later scope changes require it.

### 6. Handoff and Review

Create or update:

- `docs/ai-tasks/2026-06-02-12-fix-laravel-test-environment-isolation-handoff.md`

Include:

- effective test DB configuration before the fix
- root cause
- chosen strategy and rationale
- files changed
- commands run and results
- targeted test results
- full backend test result, if run
- confirmation that dev/demo data was not touched
- remaining risks or unknowns

Before finishing patch mode, review the diff for unrelated changes and confirm:

- architecture remains unchanged
- local Docker stack remains valid
- no product behavior changed
- no health data, company reporting, portal boundary, or OpenAPI behavior changed
