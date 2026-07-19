# Task: Boundary enforcement — Postgres grants tests and Deptrac rules

## Goal

Prove and freeze the mapping boundary: a Postgres-backed `boundary` test suite asserting real role grants, plus Deptrac rules failing CI when mapping/health code is imported outside its domain.

## Context

Relevant files:

- infra/postgres/initdb/01-databases-and-roles.sql, infra/postgres/check-grants.sh (prompt 02)
- app/Services/Privacy/, app/Models/Privacy/, app/Models/Health/ (prompt 04)
- apps/api-laravel/phpunit.xml
- ADR-001 §2.10 (boundary tests), Jira ELYO-106, ADR-003 (D6, D9)

Background:

- Since prompt 03 the whole test suite runs against the Postgres test databases with the real runtime roles (D9) — the boundary suite reuses that setup, additionally opening connections with each role's credentials. In CI it runs as a dedicated job.
- Deptrac (dev dependency) enforces: `App\Models\Privacy\*` and the mapping connection are only referenced from `App\Services\Privacy\*`; `App\Models\Health\*` only from health-domain services; controllers never touch either directly.

## Scope

Change only:

- composer.json / composer.lock (deptrac dev dependency)
- New: deptrac.yaml (layers: Privacy, HealthDomain, AppServices, Http; ruleset)
- phpunit.xml (new testsuite `boundary`)
- New: tests/Boundary/ (PG-backed grant tests)
- CI workflow file (add boundary job + deptrac step) — if no CI config exists, add scripts + document invocation in README/AGENTS.md instead
- tests/TestCase or a dedicated BoundaryTestCase for the PG connections

Do not change:

- Grants themselves (fix findings via prompt 02 follow-up, do not loosen)
- Application code (except if Deptrac reveals an existing violation — report it, fix only if trivial and in-scope)

## Requirements

1. Boundary tests (each with the standard app credentials, not migrator): identity-role connection SELECT on `subject_mappings` fails; company-role connection cannot connect to health DB; audit UPDATE/DELETE fails for every runtime role; mapping-service role cannot read identity tables beyond what's granted.
2. A test asserting `subject_mappings` contains no plaintext user ids (sample row round-trip: stored value != user id, HMAC matches).
3. Deptrac ruleset with the layers above; violation baseline must be empty.
4. `composer deptrac` script + phpunit `--testsuite=boundary` documented in AGENTS.md validation commands.
5. No skip paths: the suite requires the Postgres test env like every other suite and fails (not skips) when it is missing.

## Constraints

- Deptrac is the only new package (dev).
- Keep the patch minimal; do not refactor app code to satisfy nicer layering than specified.

## Privacy and Security Requirements

- Tests use synthetic data only.
- No test dumps mapping rows into output on failure.

## Validation

Run:

    docker compose exec api composer deptrac
    docker compose exec api php artisan test --testsuite=boundary
    docker compose exec api php artisan test

Expected result:

- Deptrac: 0 violations. Boundary suite green against dockerized PG. Default suite unaffected.

## Output Required

1. Files changed
2. Deptrac layer/rule summary
3. Grant assertions list with pass/fail
4. Any existing violations found and how handled
5. Commands run and results

## Review Checklist

- Do boundary tests use runtime credentials (not migrator/superuser)?
- Does the suite fail loudly (no skip paths at all)?
- Would a new `use App\Models\Privacy\SubjectMapping` in a controller break the build?
