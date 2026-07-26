# Result: Fix runtime-split boundary defects (ELYO-106, fix-forward on prompt 15)

Task: `docs/ai-tasks/2026-07-26-fix-runtime-split-boundary-defects.md`

**Status: complete. Smoke script green (55 checks, exit 0), suite green (514), no
PostgreSQL grant widened, no Angular change.**

## 1. Files changed

| File | Change |
| --- | --- |
| `apps/api-laravel/config/sanctum.php` | `'last_used_at' => false` with the boundary rationale |
| `apps/api-laravel/app/Services/Invitations/InviteAcceptanceService.php` | Dropped the `MappingServiceContract` dependency and the `provisionOwnSubject` call; class docblock records the supersession |
| `apps/api-laravel/app/Runtime/RuntimeProfile.php` | Identity profile connections `['identity', 'audit']` → `['identity']` |
| `apps/api-laravel/tests/Feature/Runtime/RuntimeCredentialTest.php` | **New.** Executes each runtime under its real restricted role |
| `apps/api-laravel/tests/Feature/Runtime/RuntimeProfileBootTest.php` | Identity connection assertion updated to `['identity']` |
| `apps/api-laravel/tests/Feature/AuthTest.php` | Replaced the provisioning-failure test with three tests for the new contract; two pre-existing invite tests re-pointed from "a subject exists" to "the derived subject id never appears in the response" |
| `docker-compose.yml` | `DB_AUDIT_*` removed from `api-identity` |
| `infra/docker/nginx/default.conf` | Static `upstream` blocks replaced by Docker DNS with per-request re-resolution |
| `infra/smoke-runtime-split.sh` | Added: identity-has-no-audit-credentials, migrator-uniqueness, invite verify (unknown + seeded) |
| `docs/adr-documents/ADR-003-Deployment-Topologie-Pilot.md` | D2: identity-connection note + retention constraint sharpened; D5: provisioning-timing supersession |
| `docs/further-docs/retention-matrix.md` | New section on where `elyo:enforce-retention` can run and why |
| `README.md` | Replaced the pre-ELYO-104 env block in "First Clone Setup" |

## 2. Behavior changed

**Defect A — Sanctum.** `last_used_at` is no longer written on authentication.
Every authenticated request in `api-employee` used to fail with
`SQLSTATE[42501] … permission denied for table personal_access_tokens`, because
`elyo_employee_rt` is SELECT-only on identity by design. Turned off globally, not
per runtime, so authentication cannot behave differently in `api-tooling` than in
a deployed runtime. Nothing reads the column.

**Defect B — invite/mapping coupling.** `/api/auth/invite/verify` and
`/api/auth/invite/accept` both returned 500 in the identity runtime
(`MAPPING_HMAC_KEY must not be empty`), because `InviteController`
constructor-injects `InviteAcceptanceService`, which constructor-injected the
mapping service. Provisioning now happens only where the credentials legitimately
exist: `ResolvesOwnSubject` at first health access in the employee runtime, and
`elyo:provision-subjects` in bulk. An invited user who never opens a health
feature no longer gets a health-domain row — better data minimisation, and it
supersedes prompt 05's synchronous provisioning (recorded in ADR-003 D5).

**Defect C — identity allowlist.** The identity profile declared an `audit`
connection that `elyo_identity_rt` cannot open. Removed; `company` keeps its
entry because `elyo_company_rt` does hold the grant.

**Defect D — nginx upstream caching (found during validation, not in the task).**
Static `upstream` blocks resolve service names once at config load. After
`docker compose up -d` recreated the API containers, nginx kept sending to the
old IPs, which by then belonged to *different* runtimes —
`/api/health` reported `runtime: employee`, and `/api/employee/*` returned 404.
This is a misrouting-across-runtime-boundaries bug, not a dev annoyance, so it is
fixed here rather than deferred: `resolver 127.0.0.11 ipv6=off valid=10s` with
the service names carried in the existing `map` variable, so nginx re-resolves
per request. Verified by force-recreating `api-employee` without touching nginx
and confirming routing stayed correct.

No route, request/response shape, validation rule, error response or ID format
changed. `docs/api/openapi.yaml` is untouched, correctly.

## 3. Tests added or updated

**Added — `tests/Feature/Runtime/RuntimeCredentialTest.php` (3 tests).** Boots a
subprocess per runtime with the credential set the matching compose service
actually receives, and executes a real request:

| Test | Guards |
| --- | --- |
| `employee runtime serves its own routes with only its own role` | Defect A |
| `identity runtime serves invite routes without mapping credentials` | Defect B (`verify`) |
| `identity runtime accepts an invite without provisioning a subject` | Defect B (`accept`) |

**Both regression tests were proven to fail on revert**, not merely to pass now:

- Flipping `last_used_at` back to `true` → the employee test fails with the
  original `permission denied for table personal_access_tokens`.
- Restoring the previous `InviteAcceptanceService` from git → both identity tests
  fail with the original `MAPPING_HMAC_KEY must not be empty`.

**Replaced.** `test_invite_accept_succeeds_after_provisioning_failure_and_command_repairs_mapping`
mocked the synchronous provisioning call failing. That call no longer exists, so
it was replaced by three tests pinning the new contract: acceptance creates no
mapping (asserted via `MappingNotFoundException` on the service boundary, not by
importing a privacy model), first health access provisions lazily, and
`elyo:provision-subjects` backfills without leaking the subject id or email.
Justification: this is stronger coverage — the old test asserted a failure path
around a call, the new ones assert the absence of the coupling itself.

**Adjusted.** Two pre-existing invite tests asserted `resolveOwnSubject` succeeds
after acceptance. They now assert that the *deterministically derived* subject id
never appears in the response, which preserves the leak check they were really
protecting without requiring provisioning to have happened.

**Test-isolation note.** `RuntimeCredentialTest` commits rows so a subprocess can
see them, which sits outside `RefreshDatabase`. The first run leaked a lazily
provisioned mapping and health subject and broke three boundary tests that assert
those tables are empty. Cleanup now removes the mapping row, the health subject,
the identity rows and the company. Audit events are deliberately left in place —
the audit log is append-only and deleting from it would defeat its purpose.

## 4. Commands run and results

| Command | Result |
| --- | --- |
| `docker compose config` | OK |
| `docker compose up -d` + `docker compose run --rm migrate` | 4 domains migrated in order, seeded |
| `bash infra/smoke-runtime-split.sh` | **55 checks, all PASS, exit 0** |
| `docker compose exec api-tooling php artisan test` | **514 passed** (2926 assertions) |
| `... --testsuite=boundary` | **21 passed** (97 assertions) |
| `... composer deptrac` | 0 errors, 0 warnings |
| `docker compose exec web npm run build` | OK — `Output location: /app/dist/web-angular` |
| `bash infra/postgres/check-grants.sh` | all grant checks passed — **grant matrix unchanged** |
| `git diff --check` | clean |
| `git status --porcelain apps/web-angular` | empty — zero Angular changes |

Revert probes (temporary, reverted immediately) are documented in section 3.

## 5. Open questions

1. **Privacy runtime remains unbuilt.** `elyo:enforce-retention` needs
   `audit_migrator` because the audit database is append-only for every runtime
   role. Recorded in ADR-003 D2 and `docs/further-docs/retention-matrix.md`; the
   scheduler entry stays commented. Before enabling it, decide whether the
   privacy runtime receives `audit_migrator` or a narrower `elyo_audit_pruner`
   role scoped to pruning alone.
2. **Identity auth auditing is still unimplemented.** `audit_events.user_ref`
   exists for it, but no identity-side event is emitted, which is why the
   connection was removed rather than granted. If login/logout/invite-accept
   auditing is DSFA-relevant, it needs its own task — grant, allowlist entry and
   emitting code in one patch.
3. **`api-tooling` still holds every credential** and runs by default so the
   documented dev loop stays one command. Now guarded by a smoke assertion that
   it is the *only* container with migrator credentials, so the concentration
   cannot silently spread.
4. **nginx `valid=10s`** bounds how long a stale DNS mapping can survive. Fine
   for local development; a deployed topology should confirm the value against
   its own service-discovery behavior.

## 6. Intentional deviations

- **Fixed Defect D outside the task's stated Requirements.** The nginx upstream
  caching bug was found while validating, and it misroutes traffic between
  runtimes — the exact property this whole work stream exists to guarantee.
  Leaving it for another task would have meant shipping a green smoke script that
  only passes when nginx happens to have been restarted last.
- **Two pre-existing `AuthTest` tests were adjusted** although the task named only
  the one replacement. They asserted a side effect that Requirement 2 removes;
  leaving them would have meant a red suite. Their original intent (no subject id
  in the response) is preserved and now checked more directly.

## Tests & Validation

- Test-first applied: **yes, for the regression net.** `RuntimeCredentialTest`
  encodes the two defects as failing assertions derived from the observed
  production errors, and both were confirmed to fail against the unfixed code
  before being confirmed green against the fixed code.
- Tests added/updated:
  - `tests/Feature/Runtime/RuntimeCredentialTest.php` (new, 3 tests)
  - `tests/Feature/AuthTest.php` (1 test replaced by 3; 2 adjusted)
  - `tests/Feature/Runtime/RuntimeProfileBootTest.php` (identity connection set)
  - `infra/smoke-runtime-split.sh` (+3 assertions, 55 total)
- ACs covered by tests:
  - Requirement 1 (Sanctum) — `employee runtime serves its own routes with only its own role`
  - Requirement 2 (identity holds no mapping path) — both identity credential
    tests + `invite accept creates the user without provisioning a subject`
  - Requirement 3 (allowlist matches grants) — `RuntimeProfileBootTest` + smoke
    `api-identity has no audit credentials`
  - Requirement 5 (new invite contract) — the three AuthTest replacements
  - Requirement 6 (smoke coverage) — smoke sections 1 and 3, exit 0
- Known gaps / intentionally not tested:
  - No automated test asserts nginx adds no topology-revealing headers; the
    config adds none.
  - No automated test covers the DNS re-resolution fix; verified manually by
    force-recreating a container without restarting nginx.
  - `future`-profile placeholders remain untested — they have no behavior.
