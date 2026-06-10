# Measure Slice Final Review and QA Handoff

Date: 2026-06-10

## 1. Executive Verdict

Verdict: ready with fixes before QR v1.

The current Measures slice is consistent enough to keep as the foundation for QR Check-in v1. Backend scoping, employee identity derivation, self-report verification metadata, duplicate protection, points-once behavior, and company aggregate privacy protections are covered by implementation and focused feature tests.

The slice should not start QR implementation until two contract/UX cleanups are handled: remove or reconcile stale OpenAPI `/measures` paths that do not exist in Laravel routes, and hide or clarify `pointsOverride` in the company UI while it remains stored-only and not used for point awards.

## 2. Files Inspected

- `AGENTS.md`
- `docs/ai-context/codex-workflow.md`
- `docs/ai-context/api-contract-rules.md`
- `docs/ai-context/measure-domain-concept-v1.md`
- `docs/ai-tasks/2026-06-10-measure-domain-fields-v1.md`
- `docs/ai-tasks/2026-06-10-measure-participation-verification-v1.md`
- `docs/ai-tasks/2026-06-10-fix-participation-verification-review-findings.md`
- `docs/ai-tasks/2026-06-10-measure-slice-final-review-qa.md`
- `docs/api/openapi.yaml`
- `apps/api-laravel/routes/api.php`
- `apps/api-laravel/app/Http/Controllers/Company/MeasureController.php`
- `apps/api-laravel/app/Http/Controllers/Employee/EmployeeController.php`
- `apps/api-laravel/app/Http/Requests/Company/CreateMeasureRequest.php`
- `apps/api-laravel/app/Http/Requests/Company/PatchMeasureRequest.php`
- `apps/api-laravel/app/Http/Resources/Company/MeasureResource.php`
- `apps/api-laravel/app/Http/Resources/Company/MeasureParticipationSummaryResource.php`
- `apps/api-laravel/app/Http/Resources/Employee/MeasureResource.php`
- `apps/api-laravel/app/Models/Measure.php`
- `apps/api-laravel/app/Models/MeasureParticipation.php`
- `apps/api-laravel/app/Services/MeasureParticipationService.php`
- `apps/api-laravel/app/Services/MeasureParticipationSummaryService.php`
- `apps/api-laravel/app/Services/PointsService.php`
- `apps/api-laravel/database/migrations/2024_01_01_000005_create_remaining_tables.php`
- `apps/api-laravel/database/migrations/2026_06_01_020000_create_measure_participations_table.php`
- `apps/api-laravel/database/migrations/2026_06_10_000000_add_domain_fields_to_measures_table.php`
- `apps/api-laravel/database/migrations/2026_06_10_010000_add_verification_fields_to_measure_participations_table.php`
- `apps/api-laravel/database/factories/MeasureFactory.php`
- `apps/api-laravel/database/factories/MeasureParticipationFactory.php`
- `apps/api-laravel/database/seeders/DemoDataSeeder.php`
- `apps/api-laravel/tests/Feature/CompanyTest.php`
- `apps/api-laravel/tests/Feature/EmployeeTest.php`
- `apps/api-laravel/tests/Feature/MeasureParticipationSummaryTest.php`
- `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts`
- `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.spec.ts`
- `apps/web-angular/src/app/features/employee/pages/measures/measures.component.ts`
- `apps/web-angular/src/app/features/employee/pages/measures/measures.component.spec.ts`
- `apps/web-angular/src/app/features/employee/services/employee.service.ts`
- `apps/web-angular/src/app/features/employee/services/employee.service.spec.ts`

## 3. Findings by Severity

### Must-fix before merge

None found for the current self-report Measures slice.

### Should-fix before QR

1. `docs/api/openapi.yaml` still documents top-level `/measures` and `/measures/{id}` paths that are not present in `apps/api-laravel/routes/api.php`. The current implemented routes are under `/company/measures` and `/employee/measures`. Remove or mark the stale paths before QR work so the contract does not imply unsupported measure APIs.

2. `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts` exposes a "Punkte-Override" input even though `points_override` is stored/exposed only and `PointsService` still awards `measure_participation` from point settings. Hide it or add product-approved wording before QR to avoid company users assuming it affects awards.

3. `apps/web-angular/src/app/features/employee/services/employee.service.ts` has `EmployeeMeasureParticipation` typed with only `isParticipating` and `participatedAt`, while the runtime/OpenAPI employee participation response also includes `verificationType` and `verifiedAt`. This is low runtime risk today because the UI does not consume those fields, but the frontend type should match the API before QR adds more verification states.

4. Enum values are still split across request constants, model defaults, migrations, OpenAPI, Angular labels, and tests. This is acceptable for the current narrow self-report slice, but QR will add more verification vocabulary and should introduce centralized backend enums/constants before expanding behavior.

### Nice-to-have

- Add a focused OpenAPI validation script to the repo. No existing validator command was found during this review.
- Consider a dedicated employee measure participation controller later; current behavior is service-backed, but `EmployeeController` is growing.
- Align `Measure.id` in OpenAPI with runtime numeric IDs; current schema uses `type: string` in the base `Measure` schema while tests/runtime return integers.

### Explicitly accepted risks

- `Measure` string enum columns are database strings without DB-level check constraints. Laravel validation and model defaults currently guard runtime behavior.
- `points_override` remains stored-only by design and is accepted as a future-facing field.
- Company summary `teamBreakdown` is documented and returned as `null`; non-null team breakdown remains future privacy-reviewed work.

## 4. Architecture Review

Architecture is preserved.

- Laravel remains the business logic boundary. Company measure creation/update, employee participation, duplicate handling, summary aggregation, privacy suppression, and points awarding are implemented in controllers, requests, resources, and services.
- Angular uses `ApiClient`/`EmployeeService`; no direct fetch calls were found in the inspected measure pages.
- Routes preserve portal boundaries: company users use `/company/measures`, employees use `/employee/measures`, and company users cannot call the employee participation endpoint.
- Team-layer behavior remains enforced through `TeamLayerGuard` in company list/update/summary paths.
- No microservices, database switch, n8n business logic, or legacy `../ELYO` changes were observed.

## 5. API Contract Review

Current implemented measure APIs are mostly aligned with `docs/api/openapi.yaml`:

- Company create documents required fields, domain fields, `teamId`, status, validation errors, and unsupported `verificationRequirement`.
- Company patch documents domain fields, valid status values, invalid transitions, and validation errors.
- Employee list documents active visible measures and current employee-only participation state.
- Employee participation documents body ignored/server-derived identity and `409` duplicate/inactive errors.
- Company participation summary documents aggregate-only response, threshold suppression, and `teamBreakdown: null`.

Contract gaps:

- Stale `/measures` and `/measures/{id}` paths remain documented but are not routed in Laravel.
- Base `Measure.id` is documented as string; runtime returns numeric IDs.
- Frontend employee measure type lags the runtime/OpenAPI participation metadata.

No unimplemented QR/admin/partner request-side verification behavior is documented for the current employee participation endpoint.

## 6. Privacy and Scoping Review

Privacy verdict: acceptable for the current slice.

- Company participation summary returns only `measureId`, threshold state, aggregate counts/rate when allowed, suppression reason, and `teamBreakdown: null`.
- Summary does not expose employee names, emails, user IDs, raw participation rows, `verificationType`, `verifiedAt`, `verifiedBy`, or individual `participatedAt`.
- Employee participation identity is derived from the authenticated user; request body identity/timestamp/verification fields are ignored.
- Cross-company and cross-team employee participation is blocked by `MeasureParticipationService::findEmployeeVisibleMeasure()`.
- Manager summary scope is narrowed to the current managed team, including for company-wide measures.
- Disabled team-layer behavior blocks manager-only company access and team-scoped measure summary/update cases.

## 7. Points Behavior Review

Points behavior is stable.

- Self-report participation awards points through the existing `measure_participation` reason in `PointsService`.
- Duplicate participation returns `409` and does not award a second transaction.
- `points_override` is not read by `PointsService` and therefore does not affect current awards.
- Verification metadata does not alter point calculation.

## 8. Frontend Review

- Company Measures UI remains a minimal list/create surface, not a Measures Hub.
- Company UI exposes only `SELF_REPORT` in the verification select.
- Company create payload does not send `measureOrigin`; the backend derives it.
- Employee Measures UI remains a simple list and self-report participation action.
- Employee UI does not claim QR/admin/partner behavior.
- Company UI suppresses below-threshold summary counts/rates and does not render team breakdowns or individual participant fields.
- Main frontend concern is the visible `pointsOverride` form field while backend points ignore it.

## 9. Migration and Commit Safety Review

- Required additive migrations are present for measure domain fields and participation verification fields.
- Original measure table and participation table migrations were not identified as destructively modified during this review.
- Migrations are additive in `up()` and have reversible `down()` paths.
- Participation table has unique `measure_id,user_id` duplicate protection plus aggregate indexes.
- No `.DS_Store` or unrelated tracked file was found in `git status --short`.
- No destructive commands were run or recommended.
- Current status before this handoff showed the task file as untracked; it was treated as user-provided input and left unchanged.

## 10. Test Coverage Review

Covered:

- Company measure create/update with domain fields.
- Invalid enum/date/integer validation.
- `measureOrigin` not forgeable on create/update.
- `visibilityScope` derived from `team_id`.
- Employee measure listing exposes expected domain fields.
- Employee self-report participation writes `SELF_REPORTED`, sets `verified_at`, keeps `verified_by_user_id` null.
- Duplicate participation returns `409`.
- Points awarded once.
- Cross-company and cross-team participation blocked.
- Company summary aggregate-only, threshold-protected, manager-scoped, team-layer aware.
- Verification metadata and individual participation fields absent from company summary.
- Angular company summary does not render suppressed counts, team breakdowns, or individual participant fields.
- Angular employee participation posts only the measure ID through the service.

Missing or advisable before QR:

- OpenAPI validation in CI or local script.
- Frontend type assertion for `verificationType`/`verifiedAt` on `EmployeeMeasureParticipation`.
- A focused test that `pointsOverride` does not change awarded points would make the stored-only contract explicit.

## 11. QR Readiness Assessment

QR v1 should attach token generation to measures through a separate token/check-in table, not by overloading the `measures` row directly.

Recommended shape:

- Add a separate table such as `measure_checkin_tokens` or `measure_participation_tokens`.
- Include `measure_id`, `company_id`, optional `team_id`, token hash, status/revocation fields, expiration, created-by user, created-at/used-at metadata, and rotation/audit fields as needed.
- Scan/claim should resolve token to a visible active measure, derive employee identity from auth, then create a normal `measure_participations` row with a QR-specific verification type.

Already sufficient fields:

- `measures.id`, `company_id`, `team_id`, `status`, `verification_requirement`, `visibility_scope`, `starts_at`, `ends_at`, and current participation unique key.
- `measure_participations.verification_type`, `verified_at`, and `verified_by_user_id`.

Likely new fields/tables:

- Token table for QR issuance and rotation.
- Verification enum/value for runtime QR check-in, only once implemented.
- Optional scan audit table if product/security needs scan history separate from successful participation.

Do not reuse or overload:

- Do not store raw QR token material on `measures`.
- Do not overload `points_override` for QR behavior.
- Do not overload `measure_origin` or `visibility_scope` for verification mode.
- Do not expose individual participation rows to company users for QR operations.

Remaining risk before QR:

- Contract cleanup and enum centralization should happen first so QR does not multiply stale route and enum drift.

## 12. Validation Commands and Results

Run:

- `git status --short`
  - Result before this handoff: only `?? docs/ai-tasks/2026-06-10-measure-slice-final-review-qa.md`.
- `git diff --check`
  - Result before this handoff: passed, no output.
- `git diff --cached --check`
  - Result before this handoff: passed, no output.
- `docker compose exec api php artisan test --filter='CompanyTest|EmployeeTest|MeasureParticipationSummaryTest'`
  - Result: passed, 98 tests, 483 assertions.
- `rg -n "openapi|swagger|redoc|spectral|lint" apps/web-angular/package.json apps/api-laravel/composer.json docs scripts`
  - Result: no existing OpenAPI validation command found.

Not run:

- Angular build was not run because this review task did not change frontend code.
- `migrate:fresh` was not run because the task explicitly disallows destructive validation.

## 13. Recommended Next Task

Create a small pre-QR cleanup task:

1. Remove or reconcile stale OpenAPI `/measures` and `/measures/{id}` paths.
2. Hide or clarify `pointsOverride` in the company Measures UI until backend behavior uses it.
3. Align frontend `EmployeeMeasureParticipation` type with runtime/OpenAPI verification metadata.
4. Introduce centralized backend enum/constants for measure verification and domain values before adding QR-specific values.
