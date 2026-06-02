# Handoff: Fix Laravel Test Environment Isolation

## Root Cause

`docker compose exec api php artisan test --filter=MeasureParticipation` was booting Laravel with the API container environment, not the intended isolated PHPUnit database configuration.

The effective pre-fix values observed from the exact `php artisan test --filter=MeasureParticipation` path were:

- `APP_ENV=local`
- `DB_CONNECTION=pgsql`
- `DB_DATABASE=elyo`

The static container environment also exposes:

- `APP_ENV=local`
- `DB_CONNECTION=pgsql`
- `DB_DATABASE=elyo`
- `DB_HOST=postgres`
- `DB_PORT=5432`

`apps/api-laravel/phpunit.xml` already declared SQLite in-memory, but those values were not enough to override the Docker-provided process environment for Laravel bootstrap.

## Effective Test Configuration Before Fix

Observed with a focused environment-isolation assertion included in the exact filtered command:

```text
docker compose exec api php artisan test --filter=MeasureParticipation
```

The assertion reported:

```text
app_env: local
db_connection: pgsql
db_database: elyo
```

Other observed runtime context:

- `.env.testing` is absent.
- `bootstrap/cache/config.php` was absent before the fix.
- The issue was not caused by an existing config cache in this workspace state.
- A stale config cache would still be risky, so the test bootstrap now removes it before Laravel boots.

## Chosen Strategy

Use SQLite in-memory for Laravel tests:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

This is the least invasive strategy because the targeted Measure Participation tests and the full Laravel backend suite pass with the existing migrations on SQLite.

No dedicated PostgreSQL test database was added because SQLite compatibility was confirmed.

## Files Changed

- `apps/api-laravel/phpunit.xml`
- `apps/api-laravel/tests/bootstrap.php`
- `apps/api-laravel/tests/Feature/MeasureParticipationTestEnvironmentIsolationTest.php`
- `docs/ai-tasks/2026-06-02-12-fix-laravel-test-environment-isolation-handoff.md`

## Changes Made

- Changed PHPUnit bootstrap from `vendor/autoload.php` to `tests/bootstrap.php`.
- Added `force="true"` to PHPUnit test environment variables.
- Added a test bootstrap that forces test-only process variables before Laravel boots:
  - `APP_ENV=testing`
  - `DB_CONNECTION=sqlite`
  - `DB_DATABASE=:memory:`
  - `DB_URL=`
- Added non-destructive stale config-cache protection by deleting `bootstrap/cache/config.php` during PHPUnit bootstrap if it exists.
- Added a regression test included by the `MeasureParticipation` filter that asserts the effective Laravel runtime database configuration.

## Effective Test Configuration After Fix

Confirmed by:

```text
docker compose exec api php artisan test --filter=MeasureParticipation
```

The environment-isolation regression test passes only when the effective values are:

```text
app_env: testing
db_connection: sqlite
db_database: :memory:
```

## Commands Run

Inspection:

- `sed -n '1,260p' .codex/skills/elyo-laravel-api-task/SKILL.md`
- `sed -n '1,260p' docs/ai-tasks/2026-06-02-12-fix-laravel-test-environment-isolation.md`
- `sed -n '1,240p' AGENTS.md`
- `rg --files docs/ai-context`
- `sed -n '1,220p' docs/ai-context/codex-workflow.md`
- `sed -n '1,220p' docs/ai-context/architecture-decisions.md`
- `sed -n '1,220p' docs/ai-context/current-known-issues.md`
- `sed -n '1,220p' docs/ai-context/api-contract-rules.md`
- `sed -n '1,220p' docs/ai-context/health-data-guardrails.md`
- `sed -n '1,220p' docs/ai-context/auth-and-roles.md`
- `sed -n '1,220p' apps/api-laravel/phpunit.xml`
- `sed -n '1,220p' apps/api-laravel/.env`
- `sed -n '1,260p' apps/api-laravel/config/database.php`
- `sed -n '1,260p' docker-compose.yml`
- `rg -n "MeasureParticipation|RefreshDatabase|DatabaseMigrations|DB_CONNECTION|DB_DATABASE|php artisan test|APP_ENV" apps/api-laravel tests docs scripts .github -g '!vendor'`
- `rg --files apps/api-laravel | rg '(^|/)(TestCase|Pest|phpunit|\.env\.testing|tests/|database/migrations/)'`
- `ls -la apps/api-laravel/bootstrap/cache`
- `docker compose exec api ./vendor/bin/phpunit --version`
- `docker compose exec api php artisan env`
- `docker compose exec api printenv APP_ENV DB_CONNECTION DB_DATABASE DB_HOST DB_PORT`
- `docker compose exec api test -f .env.testing`
- `docker compose exec api test -f bootstrap/cache/config.php`

Validation:

- `docker compose exec api php artisan test --filter=MeasureParticipation`
  - pre-fix proof run: failed only the new environment assertion, proving `local` / `pgsql` / `elyo`
  - final run: passed, 22 tests and 86 assertions
- `docker compose exec api php artisan test --filter=MeasureParticipationSummary`
  - final run: passed, 10 tests and 53 assertions
- `docker compose exec api php artisan test`
  - final run: passed, 174 tests and 651 assertions
- `git diff --check`
  - passed

## Dev Data Protection

No destructive database or Docker reset commands were run.

Not run:

- `php artisan migrate:fresh`
- `php artisan db:wipe`
- `docker compose down -v`
- destructive Docker volume resets

The pre-fix proof showed the exact filtered test command was targeting the normal `elyo` PostgreSQL database. The final configuration prevents that path from using the dev/demo database by forcing SQLite in-memory before Laravel boots.

## Remaining Risks

- `.env.testing` is still absent. The test bootstrap now owns the critical test runtime overrides, so this is not currently blocking.
- If future tests require PostgreSQL-specific SQL behavior, this SQLite strategy may need to be replaced with a clearly isolated PostgreSQL test database.
- The PHPUnit bootstrap removes `bootstrap/cache/config.php` during tests if present. This is intentional and non-destructive, but developers relying on a local config cache may need to regenerate it after test runs.
