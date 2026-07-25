# Handoff: Fix company reporting-pending review findings

## Summary

The review findings are fixed without changing routes, request validation, response shapes, authorization, migrations, or the OpenAPI contract.

- Company/Admin source boundaries now follow transitive application dependencies. The regression test initially exposed `AdminPointsController -> PointsService -> WellbeingService`.
- Point configuration was separated into `PointSettingsService`, so the admin API retains its response contract without acquiring a Health dependency.
- The no-wellbeing-values assertion now treats numeric strings like JSON numbers and requires the same explicit identity-side allowlist.
- The shared anonymity threshold is resolved by the purpose-named `Company\AnonymityThreshold`; the empty `AnonymityService` was removed.
- The branch's Angular pending-object adapter and truthful pending labels were retained as necessary contract compatibility, not expanded.

## Files changed

### Production

- `app/Http/Controllers/Admin/AdminPointsController.php`
- `app/Http/Controllers/Company/CompanySurveyController.php`
- `app/Http/Controllers/Company/MeasureController.php`
- `app/Services/AnonymityService.php` (removed)
- `app/Services/Company/AnonymityThreshold.php`
- `app/Services/PointSettingsService.php`
- `app/Services/PointsService.php`
- `database/seeders/PointSettingsSeeder.php`

### Tests and support

- `tests/Boundary/SourceBoundaryTest.php`
- `tests/Feature/CompanyTest.php`
- `tests/Support/AssertsNoWellbeingValues.php`
- `tests/Unit/Services/Company/AnonymityThresholdTest.php`
- `tests/Unit/Support/AssertsNoWellbeingValuesTest.php`

### Documentation

- `docs/ai-tasks/2026-07-26-fix-company-aggregates-review-findings.md`
- `docs/ai-tasks/2026-07-26-fix-company-aggregates-review-findings-handoff.md`

## Behavior changed

- A Company/Admin controller, resource, or company service fails the boundary suite if any reachable application dependency reads Health.
- Numeric strings such as `"4.8"` fail the reusable privacy response assertion unless their JSON path is explicitly allowlisted as identity-side data.
- Admin point configuration and point awards still resolve the same default and configured values, through a health-independent point-settings service.
- Survey and measure anonymity thresholds retain the configured-company-or-default-5 behavior.

## Tests & Validation

- Test-first applied: yes
- Tests added/updated:
  - Numeric-string rejection and allowlisted numeric-string acceptance.
  - Transitive Company/Admin dependency reachability.
  - Configured and default anonymity-threshold resolution.
  - Accurate runtime scope name/comment for the two reporting endpoints changed by this transition.
- ACs covered by tests:
  - Transitive Health access is rejected.
  - Numeric wellbeing values cannot bypass the helper through JSON string encoding.
  - Existing admin points, survey threshold, measure threshold, and Angular pending contracts remain stable.
- Red evidence:
  - Numeric-string test failed because no exception was thrown.
  - Boundary test failed with `AdminPointsController.php -> PointsService.php`.
  - Threshold tests failed because `Company\AnonymityThreshold` did not exist.
- Validation commands executed:
  - `docker compose exec api php artisan test` — pass, 423 tests / 1,990 assertions.
  - `docker compose exec api php artisan test --testsuite=boundary` — pass, 21 tests / 97 assertions.
  - `docker compose exec api composer deptrac` — pass, 0 violations / 0 errors.
  - `docker compose exec api php artisan route:list --except-vendor` — pass, 74 routes.
  - `docker compose exec web npm test -- --watch=false --include='src/app/features/company/pages/dashboard/dashboard.component.spec.ts'` — pass, 2 tests.
  - `docker compose exec web npm run build` — pass.
  - `docker compose config --quiet` — pass.
  - OpenAPI YAML parse — pass.
  - Targeted Pint check for materially changed implementation/support files — pass.
- Known gaps / intentionally not tested:
  - Runtime numeric-sweep tests remain scoped to `/api/company/dashboard` and `/api/company/reports`, the endpoints changed by prompt 09. Universal Company/Admin Health isolation is enforced by transitive source analysis plus the PostgreSQL company-role boundary.
  - A broader Pint check still reports pre-existing branch style issues in `CompanySurveyController`, `MeasureController`, and `CompanyTest`; this patch does not format unrelated lines in those files.

## Open questions

None.

## Intentional deviations

- `scripts/codex-plan.sh` was attempted, but its interactive Codex session kept inspecting without completing and was interrupted. The reviewed task file and in-session plan were used instead.
- No new Angular files were changed in this follow-up. The existing branch changes are explicitly accepted because the OpenAPI trend contract changed from an array to a reporting-pending object and the old labels would make false threshold/participation claims.
- `docs/api/openapi.yaml` was not changed in this follow-up because external API behavior is unchanged.
