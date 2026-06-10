# Task: Measure Slice Final Review & QA Handoff

Date: 2026-06-10

## Goal

Perform a final architecture, API-contract, privacy, and QA review of the current Measures slice before starting QR Check-in v1.

This is a review and validation task only. Do not implement new product behavior.

The Measures slice currently includes:

- Company Measures
- Employee Measures listing
- Employee self-report participation
- Measure domain metadata
- Participation verification metadata
- Company participation summary
- OpenAPI updates
- Privacy/threshold protections

This task must determine whether the current slice is consistent, testable, privacy-safe, and ready to serve as the foundation for QR Check-in v1.

## Scope

Inspect and review the current implementation across backend, frontend, OpenAPI, migrations, tests, and task documentation.

Focus on:

- consistency
- scoping
- API contract correctness
- privacy boundaries
- migration safety
- test coverage
- readiness for QR Check-in v1

Do not implement QR, admin confirmation, partner confirmation, recommendations, templates, Measures Hub restructuring, questionnaire/check-in changes, point-award changes, or new participation flows.

## Required Areas to Inspect

Inspect at least:

- `apps/api-laravel/routes/api.php`
- `apps/api-laravel/app/Http/Controllers/Company/MeasureController.php`
- `apps/api-laravel/app/Http/Controllers/Employee/EmployeeController.php`
- `apps/api-laravel/app/Http/Requests/Company/CreateMeasureRequest.php`
- `apps/api-laravel/app/Http/Requests/Company/PatchMeasureRequest.php`
- `apps/api-laravel/app/Http/Resources/Company/MeasureResource.php`
- `apps/api-laravel/app/Http/Resources/Employee/MeasureResource.php`
- `apps/api-laravel/app/Models/Measure.php`
- `apps/api-laravel/app/Models/MeasureParticipation.php`
- `apps/api-laravel/app/Services/MeasureParticipationService.php`
- `apps/api-laravel/app/Services/MeasureParticipationSummaryService.php`
- `apps/api-laravel/app/Services/PointsService.php`
- migrations touching `measures`
- migrations touching `measure_participations`
- factories/seeders relevant to measures/participations
- `apps/api-laravel/tests/Feature/CompanyTest.php`
- `apps/api-laravel/tests/Feature/EmployeeTest.php`
- `apps/api-laravel/tests/Feature/MeasureParticipationSummaryTest.php`
- `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts`
- `apps/web-angular/src/app/features/employee/pages/measures/measures.component.ts`
- relevant Angular services/types used by those pages
- `docs/api/openapi.yaml`
- relevant `docs/ai-context/*`
- relevant `docs/ai-tasks/*`
- `AGENTS.md` if present

## Review Questions

### 1. Data Model Consistency

Confirm:

- `measures` fields and defaults are internally consistent.
- `measure_origin` is server-derived for company-created measures.
- `visibility_scope` cannot be client-forged and stays aligned with `team_id`.
- `verification_requirement` is currently limited to `SELF_REPORT` for create/update.
- `measure_participations.verification_type` is currently runtime-produced as `SELF_REPORTED`.
- `verified_at` is set for self-report participation.
- `verified_by_user_id` remains null for self-report participation.
- `points_override` is stored/exposed only and does not change current points behavior.

Check for:

- duplicated enum literals that should be centralized before QR work
- inconsistent naming between DB snake_case, API camelCase, Angular types, and OpenAPI
- fields added but not exposed or documented where they should be

### 2. API Contract Review

Confirm OpenAPI matches runtime behavior for:

- company measure create
- company measure patch
- company measure list/detail if present
- employee measure list
- employee measure participation
- company participation summary

Check:

- request fields
- response fields
- enum values
- nullable fields
- default assumptions
- validation responses
- invalid transition responses
- not-found/forbidden responses where applicable

Important:

- Do not document unimplemented QR/admin/partner behavior.
- Do not document unsupported request-side verification values.
- Company summary must stay aggregate-only.

### 3. Privacy and Scoping Review

Confirm:

- company users do not receive individual participation rows
- company users do not receive employee names/emails/user IDs through participation summary
- company users do not receive `verificationType`, `verifiedAt`, `verifiedBy`, or individual `participatedAt` values through summary endpoints
- employee identity for participation is derived from auth, not request body
- company/team/manager scoping remains intact
- team-layer disabled behavior remains intact
- cross-company and cross-team measure participation is blocked

### 4. Points Behavior Review

Confirm:

- self-report participation still awards points through the existing `measure_participation` reason
- duplicate participation does not award points again
- `points_override` is not accidentally used yet
- no new verification metadata changes points behavior

### 5. Frontend Review

Confirm:

- Company Measures UI remains minimal and is not a Measures Hub
- unsupported verification options are not exposed
- `measureOrigin` is not sent from frontend create/update
- Employee Measures UI does not imply QR/admin/partner behavior
- Employee participation flow remains unchanged
- UI does not display confusing pointsOverride behavior if it is not used by backend

### 6. Migration / Commit Safety Review

Confirm:

- all required migrations are tracked
- all required task docs are tracked if repo workflow expects them
- no `.DS_Store` or unrelated files are included
- no old migrations were modified unnecessarily
- migrations are additive and non-destructive
- no destructive commands were run or recommended

### 7. Test Coverage Review

Check whether tests cover:

- company measure create/update with domain fields
- invalid enum/date/integer validation
- measureOrigin not forgeable
- visibilityScope derived server-side
- employee listing exposes expected fields
- employee self-report participation metadata
- duplicate participation 409
- points awarded once
- company summary aggregate-only and threshold-protected
- absence of verification metadata from company summary

Identify missing test coverage explicitly.

### 8. QR Readiness Assessment

Assess whether the current Measures slice is ready for a future QR Check-in v1 task.

Specifically answer:

- Where should QR token generation attach?
- Should QR be attached to `measures` directly or to a separate check-in token table?
- Which existing fields are already sufficient?
- Which new fields/tables will QR v1 likely need?
- What must not be reused or overloaded?
- What risk remains before starting QR v1?

Do not implement QR in this task.

## Validation Commands

Run non-destructive validation only.

Preferred commands:

- `git status --short`
- `git diff --check`
- `git diff --cached --check`
- relevant Laravel feature tests:
  - Company measures tests
  - Employee measures/participation tests
  - Measure participation summary tests
- Angular build or targeted tests only if frontend files changed in the current branch
- OpenAPI validation command only if an existing project command is available

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands
- unrelated full-system destructive commands

If some validation command cannot be run, state why.

## Expected Output

Create a handoff/review document under:

`docs/ai-context/measure-slice-final-review-qa.md`

The document must include:

1. Executive verdict
   - ready for QR v1 / not ready / ready with fixes

2. Files inspected

3. Findings grouped by severity:
   - Must-fix before merge
   - Should-fix before QR
   - Nice-to-have
   - Explicitly accepted risks

4. Architecture review

5. API contract review

6. Privacy/scoping review

7. Points behavior review

8. Frontend review

9. Migration/commit safety review

10. Test coverage review

11. QR readiness assessment

12. Validation commands and results

13. Recommended next task

## Constraints

- Do not implement feature changes.
- Do not change runtime behavior.
- Only create/update the review handoff document unless a tiny documentation correction is absolutely necessary and explicitly justified.
- Do not modify `../ELYO`.
- Preserve all current security, privacy, scoping, and aggregation boundaries.
