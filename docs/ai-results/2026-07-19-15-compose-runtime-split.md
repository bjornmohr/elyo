# Result: Compose runtime split with nginx path routing (ELYO-106, prompt 15)

Task: `docs/ai-tasks/2026-07-19-15-compose-runtime-split.md`

**Status: implemented, one requirement blocked by a defect outside this task's Scope.**
Requirements 2, 3, 4 and the routing half of 1 and 5 are done and smoke-verified.
Requirement 5's employee half fails on a missing PostgreSQL grant that originates
in prompt 02. See [Blocker](#blocker).

## 1. Files changed

| File | Change |
| --- | --- |
| `docker-compose.yml` | `api` replaced by `api-identity`, `api-employee`, `api-company` from one image tag (`elyo-api-laravel:local`) via YAML anchors; one-shot `migrate` service (`tools` profile); local-only `api-tooling` service; `reporting-worker` and `api-privacy` placeholders (`future` profile) |
| `infra/docker/nginx/default.conf` | Three FastCGI upstreams + `map $request_uri` prefix routing; single server block, unchanged public port 8080 |
| `infra/smoke-runtime-split.sh` | New. Credential isolation, per-runtime route topology, nginx path routing, cross-runtime session continuity |
| `.env.example` | Documents that per-service DB credentials now live in compose; mapping keys marked as employee/migrate/tooling only; `NG_APP_API_URL` and `APP_URL` corrected from port 8000 to 8080 |
| `Makefile` | `exec api` → `exec api-tooling`; migrations via `docker compose run --rm migrate`; new `test`, `test-boundary`, `deptrac`, `smoke`, `check-grants`, `logs` targets |
| `AGENTS.md` | Validation commands retargeted to `api-tooling`; migration command retargeted to the `migrate` service; new service/credential matrix and runtime-split rules under Docker Rules |
| `README.md` | New "API Runtime Split" section; service list, logs, restart, migration, reset, and troubleshooting commands retargeted; stale-config-cache troubleshooting added |

No file under `apps/` was touched. `git status --porcelain apps/` is empty.

## 2. Service / credential matrix

| Service | `ELYO_RUNTIME` | nginx routes to it | identity | mapping | health | audit | migrator | mapping keys |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `api-identity` | `identity` | `/api/auth/*`, `/api/admin/*`, `/api/partner/*`, `/api/health`, default | `elyo_identity_rt` | — | — | `elyo_identity_rt` | — | — |
| `api-employee` | `employee` | `/api/employee/*` | `elyo_employee_rt` | `elyo_mapping_svc` | `elyo_employee_rt` | `elyo_employee_rt` | — | yes |
| `api-company` | `company` | `/api/company/*` | `elyo_company_rt` | — | — | `elyo_company_rt` | — | — |
| `migrate` (one-shot) | `full` | never | `elyo_migrator` | `elyo_migrator` | `elyo_migrator` | `elyo_migrator` | `elyo_migrator` | yes |
| `api-tooling` (local only) | `full` | never | `elyo_identity_rt` | `elyo_mapping_svc` | `elyo_employee_rt` | `elyo_employee_rt` | `elyo_migrator` | yes |
| `reporting-worker`, `api-privacy` | `full` | never | — | — | — | — | — | — |

No runtime container holds migrator credentials, foreign-domain credentials, or —
for identity and company — mapping key material. Verified by section 1 of the
smoke script.

### Decisions

- **Tooling service (requirement 4).** The test suite needs every route and every
  connection, which only `ELYO_RUNTIME=full` provides, and `full` is rejected
  outside `local`/`testing` by `RuntimeProfile::validate()`. A dedicated
  `api-tooling` service (`sleep infinity`, no port, no nginx upstream) is
  therefore the test home. It is the one container holding every credential, so
  it is documented in AGENTS.md and README as local-only and never deployable.
  It runs in the default compose set so the task's `docker compose up -d` +
  `docker compose exec api-tooling php artisan test` sequence works verbatim.
- **Migration (requirement 3).** `docker compose run --rm migrate` runs
  `php artisan elyo:migrate-fresh --seed`. Every connection in that service uses
  `elyo_migrator`, because the seeders write through the identity connection and
  provision subjects through the mapping connection; the migrator owns all four
  schemas. The service sits behind the `tools` profile so `docker compose up`
  never starts it, while `docker compose run` activates the profile implicitly.
- **One image (constraint).** All API services share the `elyo-api-laravel:local`
  tag through a YAML anchor, so `docker compose up -d --build` builds once.
  Confirmed: one image ID for five services.
- **Resource footprint (constraint).** One bind mount (`./apps/api-laravel:/var/www`)
  shared by all API containers instead of per-runtime named volumes; `vendor/` is
  installed in the image but shadowed by the host copy, as before the split.
  Trade-off: the containers also share `bootstrap/cache/`, so a config cache
  built under one `ELYO_RUNTIME` would be rejected by the others on boot
  (`RuntimeProfile::assertMatchesEnvironment`). This resolves prompt 14's open
  question 2: no `config:cache`/`optimize` runs at build or start, and README
  documents `config:clear` as the fix. A deployed topology with per-runtime
  images can cache safely.
- **nginx (privacy requirement).** Routing decided by `map $request_uri` because
  Laravel's front-controller rewrite would otherwise reduce every path to
  `/index.php` before the FastCGI location is evaluated. Only the standard
  FastCGI parameters are forwarded; no header exposing the runtime topology is
  added to upstream requests or responses.
- **`/api/health`** is registered by every profile and routed to identity as the
  default upstream, per the task's path map.

## 3. Smoke script output

`bash infra/smoke-runtime-split.sh` — 40 of 41 checks pass.

```
== 1. credential isolation ==========================================
PASS: api-identity has no migrator credentials
PASS: api-employee has no migrator credentials
PASS: api-company has no migrator credentials
PASS: api-identity has ELYO_RUNTIME=identity
PASS: api-identity has the identity runtime role
PASS: api-identity has no mapping credentials
PASS: api-identity has no health credentials
PASS: api-identity has no mapping key material
PASS: api-identity has no foreign runtime roles
PASS: api-employee has ELYO_RUNTIME=employee
PASS: api-employee has the health connection
PASS: api-employee has the mapping connection
PASS: api-employee has no foreign runtime roles
PASS: api-company has ELYO_RUNTIME=company
PASS: api-company has the company runtime role
PASS: api-company has no mapping credentials
PASS: api-company has no health credentials
PASS: api-company has no mapping key material
PASS: api-company has no foreign runtime roles

== 2. route topology per runtime ====================================
PASS: api-identity serves api/auth/login
PASS: api-identity serves api/admin/companies
PASS: api-identity serves api/partner/login
PASS: api-identity serves api/health
PASS: api-identity serves no routes matching api/employee
PASS: api-identity serves no routes matching api/company
PASS: api-employee serves api/employee/dashboard
PASS: api-employee serves api/health
PASS: api-employee serves no routes matching api/auth
PASS: api-employee serves no routes matching api/admin
PASS: api-employee serves no routes matching api/company
PASS: api-employee serves no routes matching api/partner
PASS: api-company serves api/company/dashboard
PASS: api-company serves api/health
PASS: api-company serves no routes matching api/auth
PASS: api-company serves no routes matching api/admin
PASS: api-company serves no routes matching api/employee
PASS: api-company serves no routes matching api/partner

== 3. nginx path routing (single base URL) ==========================
PASS: GET /health served by the identity runtime: {"status":"up","runtime":"identity"}
PASS: GET /auth/me -> 401
PASS: GET /employee/dashboard -> 401
PASS: GET /company/dashboard -> 401
PASS: GET /employee/not-a-route -> 404
PASS: GET /company/not-a-route -> 404
PASS: GET /auth/not-a-route -> 404
PASS: GET /admin/not-a-route -> 404

== 4. session continuity across runtimes ============================
PASS: identity runtime issued a Sanctum token for employee1@demo.de
FAIL: GET /employee/dashboard -> 500 (expected 200)
PASS: GET /company/dashboard -> 403
PASS: identity runtime issued a Sanctum token for admin@demo.de
PASS: GET /company/dashboard -> 200
PASS: GET /employee/dashboard -> 403

runtime split smoke test FAILED (1 check(s))
```

The `401` assertions in section 3 are the "correct runtime" proof: a route that
is not registered in a runtime answers `404`, so a `401` on `/api/employee/...`
can only come from a container that owns the employee route file.

The `403` assertions in section 4 are the session-continuity proof for the
company runtime: the token was minted by `api-identity`, and `api-company`
validated it before rejecting on role. A rejected token would have produced `401`.

## Blocker

`GET /api/employee/*` returns `500` for any authenticated request:

```
SQLSTATE[42501]: Insufficient privilege: 7 ERROR:  permission denied for table personal_access_tokens
```

Cause: Laravel Sanctum writes `last_used_at` on every authenticated request
(`vendor/laravel/sanctum/src/Guard.php:167,171`), but the employee runtime holds
`elyo_employee_rt`, which is granted **SELECT only** on `elyo_identity`
(`infra/postgres/initdb/01-databases-and-roles.sh:83-85`, comment
"employee: read identity for auth only"). The identity and company runtimes are
unaffected: `elyo_identity_rt` and `elyo_company_rt` both have RW on identity.

The defect was invisible before this task. Under `ELYO_RUNTIME=full` the whole
suite and the previous single `api` container connect to identity as
`elyo_identity_rt`, so no test ever authenticates through `elyo_employee_rt`.
Splitting the runtimes is what first exercises that path.

**Scope of the fix is outside this task.** The task's Scope says "Do not change:
Laravel code, Postgres init (roles exist)", and both candidate fixes land there:

1. `GRANT UPDATE ON personal_access_tokens TO elyo_employee_rt` in
   `infra/postgres/initdb/01-databases-and-roles.sh` (Postgres init), or
2. suppressing Sanctum's `last_used_at` write for the employee runtime (Laravel
   code).

Per the execution plan's drift rules this needs a fix-forward micro-task against
prompt 02. Option 1 is the smaller change; note it grants UPDATE on a token
table, not on health or profile data, so it does not widen the domain boundary —
but the choice is a review decision, not this session's.

**Verified extent of the defect.** A temporary probe on the local dev database
(applied, measured, then reverted — `infra/postgres/check-grants.sh` passes again
and the `500` reproduces) showed this single grant is the only blocker. With
`GRANT UPDATE ON personal_access_tokens TO elyo_employee_rt` in place:

| Request (employee token, through nginx) | Status |
| --- | --- |
| `GET /api/employee/dashboard` | 200 |
| `GET /api/employee/checkin/status` | 200 |
| `GET /api/employee/history` | 200 |
| `GET /api/employee/lab-markers` | 200 |
| `POST /api/employee/checkin` | 409 `CHECKIN_ALREADY_DONE` (business rule; the demo seed already checked in today) |

So requirement 1's end-to-end flow (login → employee dashboard → check-in) and
requirement 5 both pass through path routing once that one grant exists; the
mapping and health connections in the employee runtime work as designed. The
repository is left in its checked-in grant state, so the smoke script currently
reports the failure rather than hiding it.

## 4. Commands run and results

| Command | Result |
| --- | --- |
| `docker compose config` | OK |
| `docker compose up -d --remove-orphans` | 10 services running; one image ID for all 5 API services |
| `docker compose run --rm migrate` | identity/mapping/health/audit fresh-migrated in order, then seeded (`Demo data seeded: admin@demo.de, … / demo1234`) |
| `bash infra/smoke-runtime-split.sh` | 40/41 pass, 1 fail (the blocker above) |
| `docker compose exec api-tooling php artisan test` | **509 passed** (2919 assertions), 21.70s |
| `docker compose exec api-tooling php artisan test --testsuite=boundary` | **21 passed** (97 assertions) |
| `docker compose exec api-tooling composer deptrac` | 0 errors, 0 warnings, 549 allowed |
| `docker compose exec web npm run build` | OK — `Output location: /app/dist/web-angular` |
| `bash infra/postgres/check-grants.sh` | all grant checks passed (re-run after the probe was reverted) |

### Angular verification (report only, requirement of Scope)

No Angular file was changed. The base URL still matches:

- `apps/web-angular/src/environments/environment.ts:3` → `apiBaseUrl: 'http://localhost:8080/api'`
- `apps/web-angular/src/environments/environment.development.ts:3` → identical
- Sole consumer: `apps/web-angular/src/app/core/services/api-client.service.ts:11`

nginx still publishes `8080:80`, and no Angular service hardcodes an `/api/...`
prefix — all paths are relative to `apiBaseUrl`. The prefixes Angular calls
(`/health`, `/auth`, `/admin`, `/company`, `/employee`) are exactly the prefixes
the nginx map routes; Angular makes no `/partner` API calls.

## 5. Open questions

1. **Blocker ownership.** Which fix for the `personal_access_tokens` grant, and
   under which micro-task file? (See [Blocker](#blocker).)
2. **`identity` runtime and the audit connection.** `RuntimeProfile` lists
   `audit` in the identity profile's allowlist, but `elyo_identity_rt` has no
   `CONNECT` on `elyo_audit`
   (`infra/postgres/initdb/01-databases-and-roles.sh:126`, which grants CONNECT
   to migrator, employee_rt, company_rt and mapping_svc only). Per the task's
   credential matrix, `api-identity` is given its own role for the audit
   connection, so any audit write from the identity runtime will fail at connect
   time. Nothing currently writes audit from identity, so no test detects it.
   Same class of defect as the blocker; same prompt-02 origin.
3. **`api-tooling` holds every credential.** Accepted deliberately so that
   `docker compose up -d && docker compose exec api-tooling php artisan test`
   works as the task specifies. If a reviewer prefers strict ADR-001 §2.10
   adherence even locally, move it behind a `tooling` compose profile and change
   the documented dev flow to `docker compose --profile tooling up -d`.
4. **Prompt 14 open question 1 is unchanged.** `elyo:enforce-retention` needs
   `audit_migrator`, which no deployable profile has. The `api-privacy`
   placeholder is where it will live; it has no credentials yet on purpose.
5. **Stale README env block.** `README.md` "First Clone Setup" still shows the
   pre-ELYO-104 `DB_CONNECTION=pgsql` / `DB_DATABASE=elyo` values. Pre-existing
   drift from prompt 02, unrelated to the runtime split, left untouched.

## Tests & Validation

- Test-first applied: **partly, with justification.** This task changes no
  application behavior — it is an infrastructure split of an existing image, so
  it falls under the AGENTS.md "refactorings with no behavior change" exception
  for unit/feature tests. The behavior it *does* introduce (credential
  isolation, path routing, session continuity) is asserted by
  `infra/smoke-runtime-split.sh`, which was written from the task's Requirements
  before the stack was brought up, and whose first run failed on exactly the
  requirement that is genuinely broken.
- Tests added/updated:
  - `infra/smoke-runtime-split.sh` — 41 assertions across credential isolation,
    per-runtime route topology, nginx path routing, and cross-runtime Sanctum
    session continuity.
- ACs covered by tests:
  - Requirement 2 (no mapping/health/migrator credentials in `api-company`, curl
    per path prefix, cross-path 404s) — smoke sections 1–3.
  - Requirement 3 (migrator only in the one-shot service) — smoke section 1.
  - Requirement 5 (identity-issued token accepted by employee/company runtimes) —
    smoke section 4; the company half passes, the employee half is the blocker.
  - Requirement 1 (end-to-end login → dashboard → check-in) — verified manually
    through nginx; blocked on the same grant (evidence table above).
- Validation commands executed: see section 4. All green except the smoke
  script's single blocked assertion.
- Known gaps / intentionally not tested:
  - No automated test asserts that nginx adds no topology-revealing headers; the
    config simply adds none.
  - The `future`-profile placeholders are not smoke-tested — they have no
    behavior yet by design.
  - No OpenAPI change: this task alters no route, request/response shape,
    validation rule, error response or ID format. `docs/api/openapi.yaml` is
    unaffected.
