# Task: Fix employee runtime Sanctum token-touch grant

## Goal

Restore Task 17 session continuity through the split runtimes without widening
the employee runtime's identity-domain access beyond the two columns Sanctum
must touch after successful bearer-token authentication.

## Reproduction

After a fresh Task 17 migration and seed:

```bash
docker compose run --rm migrate
bash infra/smoke-runtime-split.sh
```

The identity runtime issues a valid employee token, but the employee runtime
returns `500` for `GET /api/employee/dashboard`. The application log reports
that `elyo_employee_rt` cannot update `personal_access_tokens.last_used_at` and
`personal_access_tokens.updated_at`.

All preceding Task 17 checks passed:

- Full Laravel suite: `591 passed (7604 assertions)`.
- Boundary suite: `21 passed (97 assertions)`.
- Privacy suite: `71 passed (371 assertions)`.
- Deptrac: `Violations 0`, `Errors 0`.
- Runtime smoke: every check passed except employee dashboard session
  continuity.

## Functional behavior

1. A Sanctum token issued by `api-identity` authenticates successfully in
   `api-employee`.
2. `elyo_employee_rt` may update only `last_used_at` and `updated_at` on
   `personal_access_tokens`.
3. `elyo_employee_rt` remains unable to update token identity, abilities,
   expiry, ownership, or any ordinary identity-domain record.
4. Company, identity, mapping, health, and audit credential boundaries remain
   unchanged.

## Test seam

- PostgreSQL role behavior through `PostgresRoleBoundaryTest`.
- End-to-end bearer-token continuity through `infra/smoke-runtime-split.sh`.

## Test-first slices

1. Add a boundary test that attempts the real two-column token-touch update as
   `elyo_employee_rt`; confirm it fails before the grant change.
2. Add negative boundary assertions proving that the same role cannot update a
   protected token column or an ordinary identity table.
3. Add the narrow PostgreSQL column grant where the identity framework table is
   created.
4. Run the boundary test until green, then rerun the complete Task 17 battery
   from the beginning.

## Scope

Change only:

- `apps/api-laravel/tests/Boundary/PostgresRoleBoundaryTest.php`
- Identity framework migration or an equally narrow schema/grant definition
  used by both fresh test and local databases
- Documentation/handoff files required by the repository workflow

Do not:

- Give `elyo_employee_rt` table-wide `UPDATE`.
- Give any runtime migrator credentials.
- Disable Sanctum's `last_used_at` behavior.
- Change routes, response schemas, OpenAPI, Angular, nginx, or the smoke test.

## Validation

```bash
docker compose exec api-tooling php artisan test --testsuite=boundary
docker compose config
docker compose run --rm migrate
docker compose exec api-tooling php artisan test
docker compose exec api-tooling php artisan test --testsuite=boundary
docker compose exec api-tooling php artisan test --testsuite=privacy
docker compose exec api-tooling composer deptrac
bash infra/smoke-runtime-split.sh
docker compose exec web npm run build
git diff --check HEAD
```

Task 17 route/OpenAPI parity and schema audits must also pass before its
documentation work resumes.

## OpenAPI

No API route, payload, validation, response, error, or identifier behavior
changes. No OpenAPI update is required.

## Known assumptions

- Recording token use is required security behavior and should not be disabled
  in non-identity runtimes.
- PostgreSQL column-level `UPDATE` is the smallest compatible privilege.

## Implementation Plan

### Desired behavior

- Keep bearer-token creation and ownership in `api-identity`.
- Let `api-employee` authenticate an identity-issued Sanctum token and persist
  Sanctum's normal token-use timestamps.
- Grant `elyo_employee_rt` `UPDATE` only on
  `personal_access_tokens.last_used_at` and
  `personal_access_tokens.updated_at`.
- Preserve read-only employee-runtime access to every other identity-domain
  column and table. Do not change routes, HTTP behavior, OpenAPI, Angular,
  nginx, Sanctum configuration, runtime credentials, or the smoke script.

### Test-first implementation

1. Extend `PostgresRoleBoundaryTest` with a synthetic
   `personal_access_tokens` fixture created and removed through
   `identity_migrator`. Use a separately configured identity-database
   connection authenticated as `elyo_employee_rt` for every operation under
   test, and assert the active PostgreSQL role before testing privileges.
2. Add the positive boundary case first: perform the same two-column update
   Sanctum requires, assert one token row was updated, and verify through the
   migrator connection that `last_used_at` and `updated_at` changed. Run the
   focused boundary test before adding the grant and record the expected
   permission-denied failure.
3. Add negative boundary coverage proving the role cannot update protected
   token data. Cover token identity, abilities, expiry, and ownership columns
   with actual denied updates, and assert that the role has no table-wide
   `UPDATE` privilege. Keep fixture values synthetic and clean them in a
   `finally` block so the non-transactional migrator connection cannot leak
   state between tests.
4. Add a separate denied update against an ordinary identity table such as
   `users`. This proves the new exception is limited to the Sanctum token table
   and does not widen the employee runtime's identity-domain write access.
5. Treat the new tests as the functional contract. Do not weaken negative
   assertions after implementation; if PostgreSQL reports an unexpected
   privilege path, correct the grant rather than adapting tests to broader
   access.

### Grant implementation

1. Add a new migration under `database/migrations/identity/`, ordered after the
   framework-table migration. Do not edit the reviewed consolidated baseline;
   the migration README requires post-baseline schema changes to use new
   domain-specific migrations.
2. In `up()`, issue a PostgreSQL column-level grant equivalent to:

   ```sql
   GRANT UPDATE (last_used_at, updated_at)
   ON TABLE personal_access_tokens
   TO elyo_employee_rt;
   ```

3. In `down()`, revoke exactly those two column privileges. Do not add
   table-wide `UPDATE`, alter identity default privileges, grant sequence
   access, or give the runtime migrator credentials.
4. Keep the grant in the identity migration path so both
   `elyo_identity_test` and the local `elyo_identity` database receive it
   through their existing migrator flows.

### Verification sequence

1. Red phase:

   ```bash
   docker compose exec api-tooling php artisan test --testsuite=boundary --filter=PostgresRoleBoundaryTest
   ```

   Expected before the migration: the new two-column positive case fails with
   PostgreSQL permission denied; existing and new denial cases remain green.

2. Green phase: add the migration, rebuild the test schema through the normal
   boundary-suite bootstrap, rerun the focused test, then run the complete
   boundary suite.
3. Run the task's complete validation battery from the beginning and in the
   listed order:

   ```bash
   docker compose exec api-tooling php artisan test --testsuite=boundary
   docker compose config
   docker compose run --rm migrate
   docker compose exec api-tooling php artisan test
   docker compose exec api-tooling php artisan test --testsuite=boundary
   docker compose exec api-tooling php artisan test --testsuite=privacy
   docker compose exec api-tooling composer deptrac
   bash infra/smoke-runtime-split.sh
   docker compose exec web npm run build
   git diff --check HEAD
   ```

4. Confirm the smoke test proves both required outcomes: `api-identity` issues
   the employee token, and `GET /api/employee/dashboard` returns `200` through
   `api-employee`. Do not expose token values in validation evidence.
5. Run Task 17's route/OpenAPI parity and schema audits. This change must
   produce no route or OpenAPI delta; the schema/privilege evidence must show
   only the intended two-column grant.
6. Stop on the first failed final validation step. Document the failure and
   create a scoped fix-forward task instead of making unrelated application,
   infrastructure, contract, or audit-tool changes.

### Tests & Validation

- Test-first applied: yes.
- Tests added/updated:
  - Positive PostgreSQL boundary test for the two Sanctum timestamp columns.
  - Negative PostgreSQL boundary tests for protected token columns,
    table-wide token updates, and an ordinary identity table.
- Acceptance criteria covered:
  - Identity-issued employee tokens remain usable in the employee runtime.
  - Only `last_used_at` and `updated_at` are writable by
    `elyo_employee_rt`.
  - Token identity, abilities, expiry, ownership, and ordinary identity data
    remain non-writable.
  - Existing runtime credential and domain boundaries remain unchanged.
- Known gaps / intentionally not tested:
  - No new HTTP feature test is planned because the existing runtime smoke test
    is the end-to-end seam and must remain unchanged.
  - No OpenAPI test or update is planned because no API contract behavior
    changes.

### Documentation and handoff

- Update only repository-required handoff/evidence files allowed by this
  task after all validation is green.
- Record files changed, red/green evidence, every validation result, route and
  OpenAPI parity, schema/privilege audit results, and any remaining blocker.
- State explicitly that no OpenAPI, frontend, route, nginx, credential, or
  Sanctum-behavior change was made.

### Open questions to resolve during implementation

- Task 17 documents the route/OpenAPI comparison as an audit but no dedicated
  OpenAPI parity command was found. Record the exact reproducible comparison
  method used; do not invent a contract change to satisfy the audit.
- The checked-in migration parity verifier currently references the retired
  `api` Compose service. Confirm which Task 17 schema-audit command is
  authoritative before running it. If the required audit cannot run without
  out-of-scope tooling changes, stop and create the required fix-forward task.

## Implementation Result

### Behavior and files

- Added
  `database/migrations/identity/2026_07_26_000001_grant_employee_runtime_sanctum_token_touch.php`.
  It grants `elyo_employee_rt` column-level `UPDATE` only on
  `personal_access_tokens.last_used_at` and `updated_at`; `down()` revokes
  exactly those privileges.
- Extended `PostgresRoleBoundaryTest` with the positive Sanctum timestamp touch
  and negative coverage for table-wide updates, token identity, abilities,
  expiry, ownership, and ordinary Identity records.
- No route, payload, response, OpenAPI, Angular, nginx, runtime credential, or
  Sanctum behavior changed.

### Red/green evidence

- Before the migration, the focused positive test failed with PostgreSQL
  `permission denied for table personal_access_tokens`; the class result was
  `1 failed, 9 passed (72 assertions)`.
- After the migration,
  `PostgresRoleBoundaryTest` passed with `10 passed (75 assertions)`.
- The unchanged runtime smoke then authenticated an Identity-issued employee
  token through `api-employee` and received HTTP 200 from the employee
  dashboard without exposing the token.

### Final validation

- Full suite: `593 passed (7618 assertions)`.
- Boundary suite: `23 passed (111 assertions)`.
- Privacy suite: `71 passed (371 assertions)`.
- Deptrac: `Violations 0, Warnings 0, Errors 0`.
- Runtime split smoke and Angular production build: passed.
- Laravel/OpenAPI parity: `77/77`, missing `0`, stale `0`.

The known obsolete verifier still names the retired `api` Compose service; it
was not treated as authoritative. Task 17 used the current `api-tooling`
boundary suite, fresh migration, runtime smoke, and explicit semantic OpenAPI
audit instead.
