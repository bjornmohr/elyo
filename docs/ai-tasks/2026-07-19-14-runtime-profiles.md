# Task: Laravel runtime profiles (ELYO_RUNTIME) — route subsets and connection allowlists

## Goal

Introduce `ELYO_RUNTIME` (identity | employee | company | full) so one codebase boots as a restricted runtime: only the profile's routes register and only its DB connections are configured. `full` (dev default) preserves today's behavior.

## Context

Relevant files:

- routes/api.php (monolithic), bootstrap/app.php / RouteServiceProvider equivalent
- config/database.php
- ADR-001 §2.4 (runtimes, per-runtime roles, no runtime-to-runtime communication), ADR-003 (D2)

Background:

- Profiles and their scope: `identity` → auth/invite/admin/partner routes, connections identity(+audit); `employee` → /employee routes, connections identity (auth/points), mapping, health, audit; `company` → /company routes, connections identity, audit (NO mapping/health); `full` → everything (local dev/tests).
- Route registration must be structural (separate route files per profile), not middleware-based filtering — an unregistered route cannot leak.
- Credentials per profile come via env (each compose service in prompt 15 injects its own role's credentials); in `company` runtime the mapping/health connection configs must be absent or point nowhere (no credentials present).

## Scope

Change only:

- Split routes/api.php into `routes/api/identity.php`, `routes/api/employee.php`, `routes/api/company.php` (+ shared health-check route); loader reads ELYO_RUNTIME
- config/database.php (connection set conditional on profile; fail hard if a runtime references an unconfigured connection)
- .env.example (ELYO_RUNTIME=full)
- phpunit: default suite runs `full`
- tests/Feature/Runtime/ (profile boot tests)

Do not change:

- Route paths, middleware, controllers (pure reorganization — `php artisan route:list` under `full` identical to before)
- docker-compose (prompt 15)

## Requirements

1. Unknown/empty ELYO_RUNTIME in non-local environments → boot failure with clear message (fail-safe); `full` allowed only when APP_ENV is local/testing — document and enforce.
2. Profile boot tests: for each profile, assert the expected route count/presence and the absence of the other profiles' routes (e.g. company runtime: /employee/* not registered → 404).
3. Company profile: config('database.connections.mapping') and health are not defined; a test asserts DB::connection('health') throws.
4. Admin routes: assign to `identity` profile for now (platform admin manages identity-domain resources); document as ADR-003 concretization; measures/exercises system catalogs stay where their tables live (identity).
5. `route:list` diff under `full` documented as unchanged.

## Constraints

- No behavior change under `full`.
- Keep the patch mechanical; no controller refactors.

## Privacy and Security Requirements

- No profile except employee can construct a mapping or health connection.
- Health-check endpoint reveals runtime name but no connection details.

## Validation

Run:

    docker compose exec api php artisan test
    ELYO_RUNTIME=company docker compose exec -e ELYO_RUNTIME=company api php artisan route:list
    docker compose exec api php artisan test --filter=Runtime

Expected result:

- Full suite green; company route:list shows no /employee routes; runtime tests green.

## Output Required

1. Files changed
2. Profile → routes/connections matrix
3. route:list evidence (counts per profile)
4. Commands run and results
5. Open questions

## Review Checklist

- Structural exclusion (routes not registered), not filtering?
- Fail-safe on missing/unknown profile?
- `full` byte-identical route table to pre-task state?
