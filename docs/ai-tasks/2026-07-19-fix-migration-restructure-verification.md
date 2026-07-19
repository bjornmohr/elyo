# Task: Fix migration restructure verification findings

## Goal

Close every must-fix and should-fix finding from Docker verification of
`2026-07-19-03-migration-restructure.md` without changing application or API
behavior.

## Confirmed failures

1. `docker compose exec api php artisan test` uses `elyo_identity` instead of
   `elyo_identity_test`; the isolation test fails and `RefreshDatabase` wipes
   the development database.
2. `EmployeeTest::test_wrong_team_token_masks_lifecycle_state` creates several
   non-revoked tokens for one measure and violates the PostgreSQL partial unique
   index.
3. Schema parity is asserted in the migration README but lacks reproducible,
   repository-owned evidence.
4. Historical task/handoff documents still mention SQLite, while the active
   supported test path is PostgreSQL-only; the documentation boundary is not
   explicit.
5. The post-change route output was observed as 82 formatted terminal lines, but
   the canonical JSON route count and unchanged baseline were not recorded
   reproducibly.

## Test seams

- Public test command: `docker compose exec api php artisan test`.
- Existing database/role isolation feature test.
- Existing employee QR check-in HTTP feature test.
- Repository verification command comparing the consolidated PostgreSQL schema
  with the pre-restructure baseline commit and checking route definitions/count.

## Acceptance criteria

1. Filtered and full Laravel tests connect every runtime and migrator connection
   to the matching `elyo_*_test` database with the expected runtime/migrator role.
2. Running tests cannot migrate, truncate, or otherwise mutate the development
   domain databases.
3. Wrong-team lifecycle masking test passes without weakening the one-active-token
   database invariant.
4. A checked-in, deterministic verification command demonstrates schema parity
   against base commit `10cd1c6`, with any intentional schema delta explicitly
   allowlisted and reviewed.
5. Active documentation clearly states PostgreSQL-only support and explains that
   SQLite references in archived task/handoff records are historical, not active
   support.
6. Route evidence records both an unchanged route-definition diff against
   `10cd1c6` and the canonical JSON route count (78).
7. `docker compose config`, fresh migration/seed, full Laravel suite, route
   verification, and `git diff --check` pass.

## Test-first plan

1. Reuse the already-red isolation feature test; make only bootstrap/environment
   changes until it passes, then confirm development data remains untouched.
2. Reuse the already-red wrong-team lifecycle test; change only its fixture until
   it passes.
3. Add the parity/route verifier with a failing expected-diff check, capture and
   review the intentional partial-index delta, then make the verifier green with
   that exact allowlist.
4. Run focused tests after each slice, then full Docker validation.

## Constraints

- Do not change controllers, services, models, routes, OpenAPI, or Angular.
- Do not weaken PostgreSQL grants or token uniqueness.
- Preserve historical task/handoff documents; clarify their archival status
  rather than rewriting prior facts.
- Keep unrelated `run-codex-task` work out of this patch and commit.

## Implementation Plan

1. **Lock test database environment before Laravel boots**
   - Extend `tests/bootstrap.php` with forced runtime and migrator database/role
     variables for all four `elyo_*_test` databases.
   - Keep `phpunit.xml` as declarative documentation, but make bootstrap the
     authoritative guard against Docker Compose process variables.
   - Run the existing isolation test and query seeded development data before
     and after to prove the test command cannot mutate development databases.

2. **Repair PostgreSQL token lifecycle fixture**
   - Give each wrong-team lifecycle token its own team-scoped measure, matching
     the existing foreign-company fixture and the one-active-token invariant.
   - Run only that HTTP feature test first, then the complete `EmployeeTest`.

3. **Add reproducible migration verification**
   - Add a host-side verification script under `tests/scripts/` that compares
     current migration/route evidence with immutable base commit `10cd1c6`.
   - Rebuild legacy and consolidated schemas in disposable PostgreSQL databases,
     normalize schema-only dumps, and fail on any delta not matching the reviewed
     partial-index correction.
   - Verify route definitions are byte-identical to the base commit and current
   Artisan route-list JSON output contains 78 routes.
   - Always clean up disposable databases and temporary container files.

4. **Clarify active versus historical SQLite documentation**
   - Update the migration README to state that PostgreSQL is the only active test
     path and archived task/handoff references preserve historical facts only.
   - Document exact parity and route verification commands/results.

5. **Final validation and review**
   - Run Compose config, parity/route verifier, fresh migration/seed, focused and
     full Laravel tests, route list/count, and `git diff --check`.
   - Restore seeded development data after tests only if a failed guard mutates it.
   - Create a handoff, run repository review, stage only migration-task files,
     and commit without unrelated `run-codex-task` work.

### Workflow deviation

`./scripts/codex-plan.sh` was run as required but its nested Codex session remained
in an internal `Working` state for several minutes without writing a plan. It was
interrupted, and this plan was recorded manually before patch work began.

## Implementation result

- Test bootstrap now overrides Docker Compose development database variables
  before Laravel boots and pins all runtime/migrator connections to test databases.
- The wrong-team QR lifecycle fixture uses one measure per token, preserving the
  one-active-token database invariant.
- `make verify-migration-restructure` rebuilds legacy/current schemas in disposable
  PostgreSQL databases, checks routes against `10cd1c6`, and permits only the exact
  reviewed partial-index correction.
- Active documentation states PostgreSQL-only support; older SQLite references in
  task and handoff records remain historical evidence.

## Tests & Validation

- Test-first applied: yes
- Tests added/updated:
  - Existing environment-isolation feature test reused as the red/green guard.
  - Existing wrong-team lifecycle feature test reused as the red/green guard.
  - Added `tests/scripts/verify-migration-restructure.sh`; its initial schema diff
    failed on the expected full-constraint/partial-index delta before allowlisting.
- ACs covered by tests:
  - All test database names and runtime/migrator roles: 42 isolation assertions.
  - Wrong-team lifecycle masking without weakening token uniqueness: 9 assertions.
  - Schema parity and unchanged route definitions/count: repository verifier.
- Validation commands executed:
  - `docker compose config --quiet` — pass.
  - `docker compose exec api php artisan elyo:migrate-fresh --seed` — pass.
  - `docker compose exec api php artisan test` — 335 passed, 1,399 assertions.
  - `docker compose exec api php artisan route:list | wc -l` — 82 formatted lines.
  - `make verify-migration-restructure` equivalent direct script run — 78 JSON
    routes; schemas match except the allowlisted partial-index correction.
  - `git diff --cached --check` and verifier `bash -n` — pass.
- Development database safety proof:
  - Seeded company count was 5 before and after focused/full test runs.
- Known gaps / intentionally not tested:
  - Angular build not run: no frontend file or behavior changed.
  - OpenAPI not updated: no route, request, response, validation, error, or ID
    contract changed.
