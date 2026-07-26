# Task: Fix runtime-split boundary defects revealed by prompt 15

## Goal

`bash infra/smoke-runtime-split.sh` is fully green, and every runtime container
works under its own restricted PostgreSQL role — proven by an automated test, not
only by the manual smoke script.

Concretely, after this task:

- `GET /api/employee/*` returns 200 for an authenticated employee (today: 500).
- `GET /api/auth/invite/verify` and `POST /api/auth/invite/accept` work in the
  identity runtime (today: 500 for both).
- The identity runtime declares and needs exactly one connection: `identity`.
- A regression test executes each runtime with its real role and fails if a
  runtime cannot serve a route it owns.

## Context

Relevant files:

- `apps/api-laravel/config/sanctum.php`
- `apps/api-laravel/app/Services/Invitations/InviteAcceptanceService.php`
- `apps/api-laravel/app/Runtime/RuntimeProfile.php`
- `apps/api-laravel/tests/Feature/Runtime/RuntimeProfileBootTest.php`
- `apps/api-laravel/tests/Feature/AuthTest.php`
- `docker-compose.yml`
- `infra/smoke-runtime-split.sh`
- `docs/adr-documents/ADR-003-Deployment-Topologie-Pilot.md`
- `README.md`

Relevant docs:

- AGENTS.md
- `docs/ai-tasks/2026-07-19-00-elyo-91-execution-plan.md` (drift rules; D2, D5)
- `docs/ai-results/2026-07-19-15-compose-runtime-split.md` (blocker evidence)
- `docs/api/openapi.yaml` (no change expected — see Constraints)

Background:

- **One root cause.** The test suite runs `ELYO_RUNTIME=full` with every role
  available (`tests/bootstrap.php:10-24`), and until prompt 15 the API ran as a
  single container with every credential. Prompt 15 is therefore the first thing
  that ever executed this code under a restricted credential set, and it exposed
  three independent full-profile assumptions at once.

- **Defect A — Sanctum.** `Guard::updateLastUsedAt` writes `last_used_at` on
  every authenticated request (`vendor/laravel/sanctum/src/Guard.php:56-58,
  162-173`). `elyo_employee_rt` is granted SELECT only on `elyo_identity`
  (`infra/postgres/initdb/01-databases-and-roles.sh:83-85`, comment: "employee:
  read identity for auth only"). Every authenticated employee request therefore
  fails with `SQLSTATE[42501] … permission denied for table
  personal_access_tokens`. Sanctum 4.3.1 reads `config('sanctum.last_used_at',
  true)` (`SanctumServiceProvider.php:112`), so this is a supported toggle, not a
  vendor patch. Nothing in this codebase reads the column — only the migrations
  define it and `AccountDeletionService.php:179` deletes the rows. (The
  `last_used_at` references in the QR-checkin docs are a different table,
  `measure_checkin_tokens`.)

- **Defect B — invite/mapping coupling.** `InviteController` constructor-injects
  `InviteAcceptanceService`, which constructor-injects `MappingServiceContract`
  → `MappingService` → `MappingCryptography`, which throws
  `MAPPING_HMAC_KEY must not be empty` at construction. So both
  `/api/auth/invite/verify` and `/api/auth/invite/accept` 500 in the identity
  runtime, even though `verify` never touches mapping. Supplying the keys would
  not fix it: `RuntimeProfile.php:42` gives identity no `mapping` connection, so
  the failure would just move to `Database connection [mapping] not configured`.
  The real problem is that invite-accept is an identity-runtime HTTP route
  performing a health-domain write (`InviteAcceptanceService.php:105`).

- **Correctness is already covered elsewhere.** `ResolvesOwnSubject::resolveSubjectId`
  (`app/Services/Health/ResolvesOwnSubject.php:32-48`) catches
  `MappingNotFoundException` and provisions in place before retrying; all six
  health services use the trait. `elyo:provision-subjects` sweeps in bulk. And
  `tests/Feature/AuthTest.php:788` already specifies that invite acceptance
  survives a provisioning failure. Invite-time provisioning is therefore a
  pre-warm, not a correctness requirement.

- **Defect C — identity audit allowlist.** `RuntimeProfile.php:42` declares
  `['identity', 'audit']` for the identity profile, but `elyo_identity_rt` has no
  CONNECT on `elyo_audit` (`initdb:126` grants it to migrator, employee_rt,
  company_rt and mapping_svc only). Nothing in the identity runtime writes audit
  today, and after Defect B is fixed nothing ever will. The `company` profile has
  the same allowlist entry but *does* hold the grant, so it is not affected.

- **Supersedes a delivered criterion.** Prompt 05 delivered "synchronous
  provisioning after invite-accept identity commit" (execution plan, prompt 05
  row). This task reverses that deliberately; see Requirement 2.

- **Known risk.** Removing the invite-time call means an invited user has no
  `health_subject` until their first health access. That is intended: it is
  better data minimisation, since users who never open a health feature never get
  a health-domain row.

## Scope

Change only:

- `apps/api-laravel/config/sanctum.php`
- `apps/api-laravel/app/Services/Invitations/InviteAcceptanceService.php`
- `apps/api-laravel/app/Runtime/RuntimeProfile.php`
- `apps/api-laravel/tests/` (runtime credential tests, `RuntimeProfileBootTest`,
  `AuthTest` invite tests)
- `docker-compose.yml` (`api-identity` env only)
- `infra/smoke-runtime-split.sh`
- `docs/adr-documents/ADR-003-Deployment-Topologie-Pilot.md`
- `docs/further_docs/` retention doc (append the `audit_migrator` constraint)
- `README.md` (stale pre-ELYO-104 env block in "First Clone Setup")

Do not change:

- `infra/postgres/initdb/` — the grant matrix is correct as designed; both
  defects are fixed on the application side so the roles stay as narrow as they
  are.
- `apps/web-angular/` — no frontend change; `/api/auth/invite/*` keeps its
  request and response contract.
- Routes, controllers, request/response shapes.
- `apps/api-laravel/app/Services/Privacy/` and `app/Services/Health/` — the lazy
  repair path is already correct and stays untouched.

## Requirements

1. **Sanctum stops writing on authentication.** `config/sanctum.php` sets
   `'last_used_at' => false`, with a comment naming the reason (employee runtime
   is SELECT-only on identity by design). Applied globally, so no runtime
   authenticates differently from another — a test passing under `api-tooling`
   must mean the same thing in `api-employee`.

2. **The identity runtime holds no mapping code path.**
   `InviteAcceptanceService` no longer constructor-injects
   `MappingServiceContract` and no longer calls `provisionOwnSubject`. Invite
   acceptance commits the identity user and returns. Document in the class
   docblock that provisioning is now lazy (`ResolvesOwnSubject`) plus the
   `elyo:provision-subjects` sweep, and that this supersedes prompt 05's
   synchronous provisioning.

3. **Identity's allowlist matches its grants.** `RuntimeProfile::CONNECTIONS`
   gives `identity` exactly `['identity']`. `docker-compose.yml` drops
   `DB_AUDIT_DATABASE`, `DB_AUDIT_USERNAME` and `DB_AUDIT_PASSWORD` from
   `api-identity`. The `company` profile is left unchanged.

4. **Restricted-credential regression test.** Extend the
   `RuntimeProfileBootTest` harness (which already shells out per profile and
   asserts `statusFor()`) with variants that additionally override
   `DB_*_USERNAME`/`DB_*_PASSWORD` to the runtime's real role, then execute an
   authenticated request against a route that runtime owns. The employee case
   must fail if Requirement 1 is reverted, and the identity case must fail if
   Requirement 2 is reverted. `initdb` applies the same grant matrix to the
   `elyo_*_test` databases, so the real roles work in the test lane.

5. **Invite tests express the new contract.** Replace
   `test_invite_accept_succeeds_after_provisioning_failure_and_command_repairs_mapping`
   with tests asserting: invite acceptance creates the user and no mapping; the
   first health access provisions the subject; `elyo:provision-subjects`
   backfills. Justify the replacement in the handoff — it encodes the new
   functional expectation, it does not weaken coverage.

6. **Smoke script covers what it missed.** Add the auth/invite flow
   (`GET /api/auth/invite/verify`, `POST /api/auth/invite/accept`) and an
   assertion that `api-tooling` is the only container holding migrator
   credentials. The script must exit 0 on a correctly built stack.

7. **Deferred work recorded where it will be found.** ADR-003 states, next to the
   `api-privacy` placeholder, that `elyo:enforce-retention` needs
   `audit_migrator` and therefore cannot run in the identity, employee or company
   runtime; the schedule stays commented out until a privacy runtime exists.
   Mirror the constraint in the retention doc. ADR-003 also records that prompt
   05's synchronous provisioning is superseded by D2, with the reason.

8. **README correction.** The "First Clone Setup" env block still shows
   pre-ELYO-104 values (`DB_CONNECTION=pgsql`, `DB_DATABASE=elyo`,
   `DB_USERNAME=elyo`). Replace with the domain-separated reality.

## Constraints

- Keep the patch minimal.
- Do not change unrelated areas.
- Do not introduce new packages.
- Do not weaken existing tests.
- Preserve existing API response shapes. `/api/auth/invite/accept` keeps its
  status code and body; only the side effect changes.
- No OpenAPI change: no route, request/response shape, validation rule, error
  response or ID format is altered.
- No new compose service, no new runtime profile, no queue worker — the lazy
  repair path already covers correctness.

## Privacy and Security Requirements

- Do not widen any PostgreSQL grant. Both defects are fixed by removing work from
  the wrong runtime, never by giving a runtime more access.
- The identity runtime must end up with no mapping connection, no mapping key
  material and no audit connection.
- Do not hardcode secrets.
- Do not leak internal exception details.
- Preserve company, team and user scoping.

## Validation

Run these commands:

    docker compose config

    docker compose up -d && docker compose run --rm migrate

    bash infra/smoke-runtime-split.sh

    docker compose exec api-tooling php artisan test

    docker compose exec api-tooling php artisan test --testsuite=boundary

    docker compose exec api-tooling composer deptrac

    docker compose exec web npm run build

    bash infra/postgres/check-grants.sh

    git diff --check

Expected result:

- Smoke script green, exit 0.
- Full Laravel suite green, including the new restricted-credential tests.
- Boundary suite and deptrac green.
- Angular build passes with no frontend change.
- Grant checks pass — the initdb matrix is unchanged.
- Diff whitespace check passes.

## Output Required

At the end, report:

1. Files changed
2. Behavior changed
3. Tests added or updated
4. Commands run and results
5. Open questions
6. Intentional deviations, if any

## Review Checklist

Before considering the task done, check:

- Does any runtime container hold credentials it does not need?
- Does the identity runtime still reference mapping in any code path?
- Would the new regression test actually fail if either fix were reverted?
- Is the prompt-05 supersession recorded where a reviewer will find it?
- Are relevant tests included or updated, and is the replaced invite test
  justified?
- Does Angular still build, with no frontend change?
- Does OpenAPI need an update? (Expected: no.)
- Is the diff small enough to review safely?
