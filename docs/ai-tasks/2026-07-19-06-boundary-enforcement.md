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

## Implementation Plan

### Desired behavior and assumptions

- Treat the Dockerized `elyo_*_test` PostgreSQL databases as mandatory. Boundary tests must fail on missing databases, roles, credentials, schema, or connectivity; they must not call `markTestSkipped()`, use environment-based skip conditions, or fall back to SQLite.
- Use `elyo_identity_rt`, `elyo_company_rt`, `elyo_employee_rt`, and `elyo_mapping_svc` for grant assertions. Use `elyo_migrator` only to build/clean the test schema and any audit probe table, never for the operation whose denial is being asserted.
- Keep all fixtures synthetic and assert mapping fields individually so PHPUnit failure output cannot dump a complete mapping row.
- No CI workflow currently exists. Follow the task fallback: add local Composer/PHPUnit commands to the root `AGENTS.md` validation section instead of creating a new CI system. Do not change a README unless implementation discovers a repository-owned CI entry point not visible during planning.
- `MappingService` currently imports both `SubjectMapping` and `HealthSubject` because it is the approved privacy boundary that provisions health subjects. Model the Deptrac rules so the Privacy layer may depend on HealthDomain, while AppServices and Http may not access either protected model layer directly. This preserves the current ADR-backed seam without an application refactor.

### Test-first implementation sequence

1. Add `tests/Boundary/BoundaryTestCase.php` as a dedicated PostgreSQL test base.
   - Reuse the normal multi-database migration setup from `Tests\TestCase`.
   - Build temporary Laravel connection definitions from the existing PostgreSQL host/port/database settings plus each runtime role's explicit credentials.
   - Purge each temporary connection before/after use so a pooled PDO connection cannot accidentally retain a different role.
   - Provide narrowly scoped helpers for asserting connection success and expected `PDOException`/query failures without logging SQL parameters or mapping records.
   - Create an audit probe table through `audit_migrator` in setup and remove it through `audit_migrator` in teardown; exercise the probe only through runtime-role connections.

2. Add failing grant tests under `tests/Boundary/PostgresRoleBoundaryTest.php`.
   - Prove `elyo_identity_rt` cannot connect to/read `subject_mappings`.
   - Prove `elyo_company_rt` cannot connect to the health test database.
   - Prove every audit-capable runtime role (`elyo_employee_rt`, `elyo_company_rt`, and `elyo_mapping_svc`) can insert into the synthetic audit probe but cannot update or delete it.
   - Prove `elyo_mapping_svc` can use the mapping database but cannot connect to/read identity tables.
   - Include positive controls for the allowed mapping and health paths so a denial cannot pass merely because PostgreSQL is wholly unavailable.
   - Assert PostgreSQL identity for each successful connection with `current_user`, ensuring the tested login is the runtime role rather than migrator/superuser.

3. Add a failing storage-format test under `tests/Boundary/SubjectMappingStorageBoundaryTest.php`.
   - Provision a synthetic numeric user through `MappingService` with the required purpose code.
   - Query only `user_id_hmac` and `user_id_encrypted` from the mapping runtime connection.
   - Assert no `user_id` column exists, encrypted storage is not equal to the plaintext ID, the stored HMAC equals `MappingCryptography::userIdHmac()`, and the encrypted value round-trips through `decryptUserId()`.
   - Use scalar assertions and non-sensitive assertion messages; never include the selected row or ciphertext in failure output.

4. Register a separate `boundary` testsuite in `apps/api-laravel/phpunit.xml`.
   - Point it only at `tests/Boundary`.
   - Add forced test role names where needed, while sourcing passwords from the Docker/container environment rather than hardcoding new secrets.
   - Keep Unit and Feature suite definitions unchanged; verify the default full run still includes Boundary, or explicitly adjust PHPUnit suite inclusion if PHPUnit excludes separately named suites from the no-filter run.

5. Add Deptrac test coverage before finalizing its configuration.
   - Add a small boundary test that runs the configured Composer command against a temporary controller fixture importing `App\Models\Privacy\SubjectMapping`, expects failure, then removes the fixture. This proves the review-checklist example is actually rejected without leaving a baseline entry.
   - If invoking Deptrac from PHPUnit is too brittle in the container, retain the configuration-level verification as the dedicated Composer command and document that limitation; do not weaken production rules or add a baseline.

6. Add `qossmic/deptrac` as the sole new dev dependency in `apps/api-laravel/composer.json` and update `apps/api-laravel/composer.lock`.
   - Add a `deptrac` Composer script invoking the pinned binary/configuration with failure on architecture violations. Uncovered third-party/framework dependencies are reported but are not baseline entries or skipped violations.
   - Do not add plugins, formatters, or unrelated Composer updates.

7. Add `apps/api-laravel/deptrac.yaml` with no baseline and no skipped violations.
   - Analyze `app/` only; exclude framework/bootstrap, vendor, database, and tests.
   - Define `Privacy` for `App\Models\Privacy\*` and `App\Services\Privacy\*`, `HealthDomain` for `App\Models\Health\*` and health-domain services, `AppServices` for remaining `App\Services\*`, and `Http` for `App\Http\*`.
   - Permit Privacy to use HealthDomain for the approved mapping/provisioning seam.
   - Permit ordinary service/framework dependencies required by existing code, but forbid AppServices and Http from directly depending on protected Privacy or HealthDomain internals.
   - Ensure a controller import of `SubjectMapping` produces a violation and keep `skip_violations` absent/empty.
   - Run the initial analysis before changing application code. Report every existing violation; fix only a trivial in-scope import/connection access if required, otherwise stop and return the finding for a follow-up rather than broadening this patch.

8. Document validation commands in root `AGENTS.md`.
   - Add `docker compose exec api composer deptrac`.
   - Add `docker compose exec api php artisan test --testsuite=boundary`.
   - Keep existing full-suite guidance and state that the boundary suite requires Dockerized PostgreSQL and fails when unavailable.

9. Validate in increasing scope.
   - Run the new boundary suite first and confirm the initial tests fail for the expected missing implementation/configuration before completing helpers and rules.
   - Run `docker compose exec api composer deptrac` and require zero violations, zero skipped violations, and no baseline.
   - Run `docker compose exec api php artisan test --testsuite=boundary`.
   - Run `docker compose exec api php artisan test`.
   - Run `docker compose config` only if environment/Compose wiring unexpectedly needs an in-scope adjustment; no destructive Docker/database commands.
   - Run `git diff --check` and review the final diff for scope, credential leakage, accidental application changes, and generated artifacts.

### Planned files

- Modify `apps/api-laravel/composer.json`.
- Modify `apps/api-laravel/composer.lock`.
- Add `apps/api-laravel/deptrac.yaml`.
- Modify `apps/api-laravel/phpunit.xml`.
- Add `apps/api-laravel/tests/Boundary/BoundaryTestCase.php`.
- Add `apps/api-laravel/tests/Boundary/PostgresRoleBoundaryTest.php`.
- Add `apps/api-laravel/tests/Boundary/SubjectMappingStorageBoundaryTest.php`.
- Optionally add one focused Deptrac enforcement test in `apps/api-laravel/tests/Boundary/` if it can be deterministic without modifying tracked application files.
- Modify root `AGENTS.md` because the repository has no CI workflow.

### Acceptance-criteria coverage

- Runtime-role connection and `current_user` assertions cover use of real standard credentials rather than migrator/superuser.
- Negative connection/table tests cover identity-to-mapping, company-to-health, mapping-to-identity, and append-only audit grants.
- Mapping storage test covers absence of plaintext IDs, HMAC correctness, and encrypted round-trip behavior.
- Deptrac command, protected-layer rules, controller-import proof, empty baseline, and zero skipped violations cover static boundary enforcement.
- Dedicated PHPUnit suite plus mandatory-PostgreSQL setup covers the no-skip requirement.
- Composer and PHPUnit commands in `AGENTS.md` cover the no-CI documentation fallback.

### Known risks and open questions to resolve during implementation

- The API container must expose the company runtime password to PHPUnit. If it does not, add only the minimum test-environment wiring permitted by the task; do not hardcode a password in tests or loosen grants.
- Audit migrations are currently empty, so the append-only assertions need a synthetic migrator-owned probe table. Cleanup must be reliable even after assertion failure and must never use a runtime role for DDL.
- Deptrac cannot express “only this one class inside a layer” as cleanly as a runtime policy. Prefer the narrowest collector/ruleset that preserves the legitimate `MappingService` health provisioning dependency; document any layer-level exception explicitly.
- Adding the Boundary suite may make it part of the unfiltered PHPUnit run. Confirm actual PHPUnit behavior so the required full suite executes boundary tests exactly once.

## Tests & Validation

- Test-first applied: yes, planned; tests will be written and observed failing before helpers/configuration are completed.
- Tests added/updated:
  - PostgreSQL runtime-role grant boundary tests.
  - Mapping ciphertext/HMAC storage boundary test.
  - Optional deterministic Deptrac controller-import enforcement test.
- ACs covered by tests:
  - All grant assertions in requirement 1.
  - Plaintext/HMAC/round-trip assertions in requirement 2.
  - No-skip PostgreSQL enforcement in requirement 5.
  - Controller-to-Privacy import failure from the review checklist.
- Validation commands to execute:
  - `docker compose exec api composer deptrac`
  - `docker compose exec api php artisan test --testsuite=boundary`
  - `docker compose exec api php artisan test`
  - `git diff --check`
- Known gaps / intentionally not tested:
  - CI job execution is not applicable because no CI configuration exists; command documentation is the specified fallback.
  - Grants themselves are not changed by this task. Any failing grant is reported for the prompt 02 follow-up rather than loosened here.
