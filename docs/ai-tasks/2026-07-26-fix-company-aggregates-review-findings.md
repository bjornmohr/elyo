# Task: Fix company reporting-pending review findings

## Goal

Close the review findings on `elyo-91/09-company-aggregates-transition` without changing the reporting-pending API payload or weakening health-data isolation.

## Desired behavior

1. Company/Admin HTTP paths cannot acquire a transitive read path to Health through a generic application service.
2. The reusable response privacy assertion rejects generic numeric wellbeing values whether JSON numbers or numeric strings.
3. Tests prove the no-wellbeing-values rule across the affected Company reporting endpoints and source-level Company/Admin response paths; future privacy prompt 16 may broaden this to route-by-route runtime coverage.
4. Angular accepts the pending trend object without rendering health values or claiming an anonymity threshold was met. Only compatibility and truthful empty-state behavior are authorized.
5. The shared anonymity threshold has a name and location matching its actual role; no empty “service” remains.

## Test seams

- Laravel feature seam: authenticated `/api/company/dashboard` and `/api/company/reports` JSON.
- Laravel test-support seam: a `TestResponse` passed to the reusable privacy assertion.
- Architecture seam: Dockerized boundary suite against parsed dependency paths and PostgreSQL runtime grants.
- Angular seam: rendered standalone company dashboard component receiving the documented pending payload.
- Existing survey and measure feature tests: shared threshold behavior remains unchanged.

## Test-first slices

1. Add a failing helper test for a numeric string under a generic field.
2. Strengthen the boundary test until the existing Admin points path proves the transitive dependency leak.
3. Add/adjust source coverage for all Company/Admin response-producing namespaces and document runtime endpoint scope accurately.
4. Validate the existing Angular test covers only required compatibility and truthful pending-state rendering.
5. Move the shared threshold to a purpose-named policy/value object and update callers after existing tests demonstrate unchanged behavior.

## Scope

- `apps/api-laravel` boundary, feature, and test-support code.
- Minimal Company dashboard Angular compatibility code and tests.
- `docs/api/openapi.yaml` only if response behavior changes; expected unchanged.
- Task/handoff documentation.

## Validation

```bash
docker compose exec api php artisan test
docker compose exec api php artisan test --testsuite=boundary
docker compose exec api composer deptrac
docker compose exec web npm test -- --watch=false --include='src/app/features/company/pages/dashboard/dashboard.component.spec.ts'
docker compose exec web npm run build
docker compose config
git diff --check
```

## Assumptions

- The two runtime wellbeing endpoints are `/api/company/dashboard` and `/api/company/reports`; universal Company/Admin protection is additionally enforced at source and database-role boundaries.
- Angular changes are an explicit correction to the prior task’s contradictory scope: its pending-object contract cannot be consumed safely by the old array iteration.
- No routes, request validation, response shapes, authorization rules, or migrations change.
