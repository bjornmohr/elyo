# Task: Restore Laravel route and OpenAPI operation parity

## Goal

Bring `docs/api/openapi.yaml` back into operation-level parity with the
authoritative Laravel route inventory so Task 17 can resume.

## Context

Task 17's required parity audit was run on 2026-07-26 after the runtime split
smoke test passed. The audit canonicalized path-parameter names and compared
HTTP method plus path for every `api/*` route from the `full` runtime against
the OpenAPI `paths` map.

Observed result:

- Laravel API operations: 77.
- OpenAPI operations: 71.
- Missing from OpenAPI: 13.
- Stale in OpenAPI: 7.

Missing operations:

- `GET /api/admin/points-config`
- `PUT /api/admin/points-config`
- `POST /api/admin/companies/{company}/invite-company-admin`
- `GET /api/auth/invite/verify`
- `POST /api/auth/logout`
- `GET /api/company/surveys/{id}`
- `POST /api/company/surveys/{id}/activate`
- `GET /api/company/users`
- `GET /api/employee/surveys/{id}/result`
- `GET /api/health`
- `POST /api/partner/documents`
- `POST /api/partner/logout`
- `POST /api/partner/register`

Stale operations:

- `GET /measures`
- `POST /measures`
- `PATCH /measures/{id}`
- `GET /points/me`
- `POST /auth/register`
- `POST /cron`
- `POST /webhooks/terra`

## Desired behavior

1. Every Laravel `api/*` method/path operation is represented by OpenAPI.
2. Every OpenAPI method/path operation corresponds to a registered Laravel
   route in the `full` runtime.
3. Added descriptions match observable controller/request/resource behavior;
   unknown behavior is not invented.
4. Removing stale contract entries does not add replacement application
   behavior.
5. Existing health-data, role, company-scope, and privacy constraints remain
   explicit in the contract.

## Scope

Change:

- `docs/api/openapi.yaml`
- Focused contract-validation coverage or scripts only if needed to make the
  parity check reproducible.
- This task file with implementation and validation evidence.

Do not change:

- Laravel or Angular behavior.
- Runtime topology or database grants.
- ADR-001 or ADR-002.
- Unrelated OpenAPI schemas.

## Test-first workflow

1. Preserve the failing parity output above as the red baseline.
2. Inspect each registered route and its existing request, resource, feature
   tests, and controller behavior.
3. Add the 13 missing operations using only verified behavior.
4. Remove the 7 stale operations after confirming that no registered route
   owns them.
5. Re-run the exact operation-parity audit and require zero missing and zero
   stale operations.
6. Parse the OpenAPI YAML and run relevant tests before resuming Task 17.

## OpenAPI impact

This task changes only the binding contract to match existing API behavior. It
does not introduce a route, validation rule, response shape, error response, or
ID-format change in application code.

## Validation

Run:

```bash
docker compose exec api-tooling php artisan route:list
ruby -e "require 'yaml'; YAML.load_file('docs/api/openapi.yaml'); puts 'OpenAPI YAML parse: pass'"
# Repeat the method/path parity audit recorded in the implementation plan.
git diff --check
```

After parity passes, restart the complete Task 17 validation battery from the
beginning.

## Output Required

1. Files changed.
2. Red and green parity counts.
3. Contract operations added and removed.
4. Commands run and results.
5. Open questions or intentionally undocumented behavior.

## Implementation Plan

### Functional target and boundaries

- Treat the `full` runtime's `api/*` method/path inventory as the route source
  of truth and `docs/api/openapi.yaml` as the contract under test. Normalize
  away the Laravel `/api` prefix and path-parameter names before comparing.
- Preserve the recorded red baseline: 77 Laravel operations, 71 OpenAPI
  operations, 13 missing operations, and 7 stale operations. If a fresh audit
  differs before editing, stop and reconcile the changed route inventory
  instead of forcing the recorded counts.
- Make a contract-only patch. Do not change Laravel, Angular, routes, runtime
  profiles, database grants, migrations, or application tests.
- Do not add a permanent parity script initially. The exact audit below is the
  public test seam and is reproducible from `route:list --json` plus the parsed
  OpenAPI document. Add focused tooling only if this command cannot be made
  deterministic without it; do not broaden scope silently.
- Do not clean up unrelated OpenAPI schemas, naming, security definitions, or
  response descriptions discovered during the work.

### Verified contract sources

Inspect each operation immediately before documenting it, using the route,
middleware, controller or route closure, request validation, resource, and
existing feature tests as applicable:

1. `GET /health`: `routes/api/health.php` and
   `RuntimeProfileBootTest`; document the unauthenticated `status`/`runtime`
   response for both `200` and `503`.
2. `GET /auth/invite/verify` and `POST /auth/logout`:
   `InviteController`, `AuthController`, and auth feature coverage; document
   the required query token, verified invite shape, invalid/expired response,
   Sanctum requirement, and logout message without inventing errors.
3. `GET|PUT /admin/points-config` and
   `POST /admin/companies/{company}/invite-company-admin`:
   identity route middleware, `AdminPointsController`,
   `UpdatePointSettingsRequest`, `PointSettingsService`,
   `AdminCompanyController`, and `AuthTest`; preserve ELYO-admin authorization,
   exact point keys and limits, invite validation/conflict behavior, and the
   currently observable development invite-token response.
4. `GET /company/surveys/{id}`,
   `POST /company/surveys/{id}/activate`, and `GET /company/users`:
   company middleware, `CompanySurveyController`, `SurveyResource`,
   `CompanyInvitationController`, `CompanyTest`, `AuthTest`, and
   `TenantScopeTest`; document company/manager scope, team-layer restrictions,
   survey resource shape, activation-without-questions error, and the scoped
   identity directory. The directory contract must not add health fields.
5. `GET /employee/surveys/{id}/result`: employee middleware,
   `SurveyController`, and `EmployeeTest`; document only the authenticated
   employee's submitted answers and the observed not-found cases. Keep this
   employee-only response distinct from company aggregate survey results.
6. `POST /partner/register`, `POST /partner/logout`, and
   `POST /partner/documents`: partner routes, `PartnerAuthController`,
   `PartnerRegisterRequest`, and `IntegrationTest`. Registration documents the
   validated payload and `201` token response. Logout documents authenticated
   bearer-token deletion and its message. The documents route is only a
   placeholder returning `{"message":"Document uploaded"}`; do not invent a
   multipart body, persistence, validation, or document resource.

For authenticated partner additions, describe the observed personal-access
token behavior from `auth:partner`; do not copy the existing cookie wording
from `/partner/me` when it conflicts with the controller. Any correction to
existing partner login/profile contract text remains outside this
operation-parity patch.

### Red-green implementation sequence

1. Run the exact parity audit before editing and retain its complete counts and
   operation lists as red evidence:

   ```bash
   rtk docker compose exec api-tooling php artisan route:list --json |
     rtk ruby -ryaml -rjson -e '
       routes = JSON.parse(STDIN.read)
       spec = YAML.load_file(ARGV[0])
       canon = ->(path) {
         "/" + path.sub(%r{^/?api/?}, "").split("/").reject(&:empty?).map {
           |segment| segment.match?(/^\{[^}]+\}$/) ? "{}" : segment
         }.join("/")
       }
       laravel = {}
       routes.each do |route|
         next unless route["uri"].start_with?("api/")
         route["method"].split("|").reject { |method|
           method == "HEAD" || method == "OPTIONS"
         }.each { |method|
           laravel[[method.downcase, canon.call(route["uri"])]] =
             "#{method} /#{route["uri"]}"
         }
       end
       operations = %w[get post put patch delete options head trace]
       openapi = {}
       spec.fetch("paths", {}).each do |path, item|
         item.each_key do |method|
           next unless operations.include?(method.to_s.downcase)
           openapi[[method.to_s.downcase, canon.call(path)]] =
             "#{method.to_s.upcase} #{path}"
         end
       end
       missing = laravel.keys - openapi.keys
       stale = openapi.keys - laravel.keys
       puts "Laravel API operations: #{laravel.length}"
       puts "OpenAPI operations: #{openapi.length}"
       puts "Missing from OpenAPI: #{missing.length}"
       missing.sort.each { |key| puts "  #{laravel[key]}" }
       puts "Stale in OpenAPI: #{stale.length}"
       stale.sort.each { |key| puts "  #{openapi[key]}" }
       exit(missing.empty? && stale.empty? ? 0 : 1)
     ' docs/api/openapi.yaml
   ```

2. Add one missing operation at a time to `docs/api/openapi.yaml`, placing it
   beside its existing domain paths and adding only verified parameters,
   security, request fields, response statuses, and response shapes. Where a
   path already exists, add only the missing method, notably `GET` on
   `/company/surveys/{id}`. Use the existing `{surveyId}` convention for the
   employee survey path; parameter-name normalization makes it equivalent to
   Laravel's `{id}`.
3. After each operation or tightly coupled path item, parse the YAML and rerun
   the parity audit. Each added operation must reduce the missing count by
   exactly one without changing Laravel counts or introducing a stale
   operation.
4. Remove only these unsupported OpenAPI operations:
   `GET /measures`, `POST /measures`, `PATCH /measures/{id}`,
   `GET /points/me`, `POST /auth/register`, `POST /cron`, and
   `POST /webhooks/terra`. Re-run the audit after each removal; each must reduce
   the stale count by exactly one. Do not add replacement routes or redirect
   the entries to superficially similar company/employee APIs.
5. Review all newly documented company/admin responses for company and manager
   scoping and absence of individual health data. Keep company survey results
   aggregate-only and employee survey results own-data-only.

### Tests and validation

- Test-first applied: yes. The observable seam is the comparison of Laravel's
  public route inventory with OpenAPI's public operation inventory. No new
  PHPUnit test is planned because application behavior does not change.
- Required green result: 77 Laravel operations, 77 OpenAPI operations,
  0 missing, and 0 stale.
- Parse and whitespace-check the final files:

  ```bash
  rtk ruby -e "require 'yaml'; YAML.load_file('docs/api/openapi.yaml'); puts 'OpenAPI YAML parse: pass'"
  rtk git diff --check -- docs/api/openapi.yaml docs/ai-tasks/2026-07-26-fix-openapi-route-parity.md
  ```

- Run focused existing behavior coverage before declaring the contract patch
  complete:

  ```bash
  rtk docker compose exec api-tooling php artisan test --filter='AuthTest|IntegrationTest|CompanyTest|EmployeeTest|TenantScopeTest|RuntimeProfileBootTest'
  rtk docker compose exec api-tooling php artisan test --testsuite=privacy --filter=CompanyAdminRoutePrivacyTest
  ```

- Inspect the path-limited diff and confirm that implementation changes include
  only `docs/api/openapi.yaml` plus validation evidence in this task file.
- Stop on any YAML parse, parity, focused test, privacy, or diff-check failure.
  Diagnose the mismatch and create a scoped fix-forward task if resolving it
  would require application, route, runtime, database, or unrelated contract
  changes.
- After this task is green, restart the complete Task 17 validation battery
  from the beginning as required by
  `2026-07-19-17-docs-closure-and-verification.md`; do not reuse partial
  validation evidence.

### Completion evidence

Record in this task file:

- files changed;
- the red `77/71/13/7` and green `77/77/0/0` parity counts;
- the exact 13 operations added and 7 removed;
- YAML parse, focused tests, privacy check, parity audit, and diff-check
  commands with results;
- any behavior intentionally left undocumented because no verified source
  exists;
- the known partner placeholder limitation and confirmation that no Laravel,
  Angular, route, runtime, database, migration, or unrelated OpenAPI behavior
  changed.

## Implementation Result

### Files changed

- `docs/api/openapi.yaml`
- `docs/ai-tasks/2026-07-26-fix-openapi-route-parity.md`

No Laravel, Angular, route, runtime, database, or migration behavior changed.

### Red and green evidence

| Audit | Laravel operations | OpenAPI operations | Missing | Stale |
| --- | ---: | ---: | ---: | ---: |
| Before | 77 | 71 | 13 | 7 |
| After | 77 | 77 | 0 | 0 |

Added:

- `GET /health`
- `GET /auth/invite/verify`
- `POST /auth/logout`
- `GET /admin/points-config`
- `PUT /admin/points-config`
- `POST /admin/companies/{company}/invite-company-admin`
- `GET /company/users`
- `GET /company/surveys/{id}`
- `POST /company/surveys/{id}/activate`
- `GET /employee/surveys/{surveyId}/result`
- `POST /partner/register`
- `POST /partner/logout`
- `POST /partner/documents`

Removed:

- `GET /measures`
- `POST /measures`
- `PATCH /measures/{id}`
- `GET /points/me`
- `POST /auth/register`
- `POST /cron`
- `POST /webhooks/terra`

### Validation

- OpenAPI YAML parse: pass.
- Route/OpenAPI parity: `77/77`, missing `0`, stale `0`.
- Focused API behavior:
  `203 passed (975 assertions)`.
- Focused company/admin privacy gate:
  `5 passed (184 assertions)`.

The partner documents operation is explicitly documented as its current
placeholder behavior: no request schema, validation, or persistence is
claimed. Existing partner login/profile wording was not changed because it is
outside this parity task.

The final contract review also aligned only the newly documented observable
primary-key fields with Laravel: partner registration `partnerId` and the
company/employee survey and question IDs are integers. Opaque Health-domain
ULIDs remain strings.
