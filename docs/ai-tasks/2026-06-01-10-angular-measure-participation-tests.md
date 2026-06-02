# Task: Add Angular Tests for Measure Participation UI

## Goal

Add focused Angular tests for the measure participation frontend changes introduced in Task 4.

This is a tech-debt hardening task. It must not change product behavior.

The goal is to cover the riskiest frontend integration points:

1. Employee participation API call sends an empty body.
2. Employee UI handles successful participation.
3. Employee UI handles duplicate participation as already participated.
4. Company UI displays aggregate summaries only.
5. Company UI suppresses counts/rates below anonymity threshold.
6. Admin Points UI includes `measure_participation` in the save payload.

## Context

Task 4 added Angular frontend integration for:

- Employee measure participation button/state.
- Company measure participation summaries.
- Admin Points UI support for `measure_participation`.

Review result:

- No must-fix findings.
- Frontend-only changes.
- Privacy boundaries preserved.
- Participation POST sends `{}` only.
- Company UI renders only aggregate/suppressed values.
- Admin Points UI includes `measure_participation`.
- No feature tests were added because the repo currently appears to only have `app.spec.ts`.

This task should introduce a minimal, practical test pattern only for the touched frontend areas.

## Scope

Add or update Angular tests only.

Relevant files to inspect:

- `apps/web-angular/src/app/app.spec.ts`
- `apps/web-angular/src/app/features/employee/services/employee.service.ts`
- `apps/web-angular/src/app/features/employee/pages/measures/measures.component.ts`
- `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts`
- `apps/web-angular/src/app/features/admin/pages/points/admin-points.component.ts`
- existing Angular test configuration files
- `apps/web-angular/package.json`

## Requirements

### 1. Inspect existing Angular test setup

Before adding tests, inspect:

- current test runner
- current Angular testing configuration
- existing `app.spec.ts`
- available scripts in `package.json`
- whether components use standalone Angular APIs, signals, or module-based setup

Do not invent a large testing framework or broad test harness.

Keep tests focused and local.

### 2. EmployeeService test

Add a focused service test if practical.

Cover:

- `participateInMeasure(measureId)` calls:
  - `POST /employee/measures/{measureId}/participate`
  - with an empty object body `{}`
- It must not send:
  - `user_id`
  - `userId`
  - `company_id`
  - `companyId`
  - `team_id`
  - `teamId`
  - `participated_at`
  - `participatedAt`

Use the existing `ApiClient` test/mocking pattern if available.

If no practical ApiClient mocking pattern exists, document why this service test was skipped and cover behavior at component level if easier.

### 3. Employee Measures component tests

Add focused tests for the employee measures component if practical.

Cover:

1. A measure with `participation.isParticipating = false` shows a `Teilnehmen` action.
2. A successful participation updates the local state to participated or renders `Teilgenommen`.
3. A duplicate `409` with error code `MEASURE_ALREADY_PARTICIPATED` is treated as already participated, not as a scary generic error.
4. The component never constructs a participation payload containing user/company/team/timestamp fields.

Mock `EmployeeService`.

Do not call real HTTP.

### 4. Company Measures component tests

Add focused tests for aggregate/suppressed summary display if practical.

Cover:

1. Above-threshold summary displays aggregate count/rate only.
2. Below-threshold summary displays a privacy-safe suppression message.
3. Below-threshold summary does not render raw `eligibleCount`, `participantCount`, or `participationRate`.
4. Component does not render `teamBreakdown` or individual participant fields.

Mock the existing company API calls.

Do not call real HTTP.

### 5. Admin Points component tests

Add focused tests for the Admin Points UI.

Cover:

1. The form includes `measure_participation`.
2. Loading backend data patches `measure_participation`.
3. Save payload includes `measure_participation`.
4. Existing point fields are preserved:
   - `daily_checkin`
   - `streak_7days`
   - `streak_30days`
   - `anamnesis_completed`
   - `medical_document_upload`
   - `measure_participation`

Mock API calls.

Do not call real HTTP.

## Constraints

Do not change:

- Laravel backend
- OpenAPI
- migrations
- Docker
- n8n
- production frontend behavior unless a tiny testability-only adjustment is unavoidable
- API URLs
- API payload semantics
- privacy behavior
- visual redesign

If tiny testability adjustments are needed, keep them minimal and explain them in the handoff.

## Validation

Run the frontend test command discovered in `package.json`.

Likely commands to inspect/use:

- `docker compose exec web npm test`
- or the actual test script from `apps/web-angular/package.json`

Run build as a safety check:

- `docker compose exec web npm run build`

Run:

- `git diff --check`

Do not run backend tests unless backend files were unexpectedly changed.

Do not run destructive commands:

- `php artisan migrate:fresh`
- `php artisan db:wipe`
- `docker compose down -v`
- any destructive database or Docker reset

## Expected Handoff

Return:

- Files changed
- Test files added/updated
- Testing approach used
- Any tiny production-code adjustments made for testability
- Validation commands run
- Confirmation that frontend behavior did not intentionally change
- Confirmation that no backend/OpenAPI/Docker/n8n files changed
- Confirmation that employee participation POST remains empty-body only
- Confirmation that company summary UI remains aggregate/suppressed only
- Confirmation that Admin Points save includes `measure_participation`
- Any remaining test gaps

## Implementation Plan

1. Inspect the Angular test setup before patching:
   - Read `apps/web-angular/package.json`, Angular test config files, and `apps/web-angular/src/app/app.spec.ts`.
   - Confirm the active test runner, available test script, standalone/component setup, and existing mocking style.
   - Inspect the three target components and related services only enough to identify dependencies, observable flows, templates, and test seams.

2. Add focused EmployeeService coverage if the existing setup supports it cleanly:
   - Test `participateInMeasure(measureId)` against a mocked `ApiClient` or Angular HTTP testing utility, following the local pattern discovered in step 1.
   - Assert the endpoint is `/employee/measures/{measureId}/participate`.
   - Assert the request body is exactly `{}` and does not include user, company, team, or timestamp fields.
   - If a service-level mock would require a broad new harness, document the skip in the handoff and cover payload behavior through the component mock instead.

3. Add focused employee measures component tests:
   - Mock `EmployeeService`; do not call real HTTP.
   - Cover rendering of the `Teilnehmen` action for a non-participating measure.
   - Cover successful participation updating local UI state to participated or rendering `Teilgenommen`.
   - Cover duplicate `409` with code `MEASURE_ALREADY_PARTICIPATED` as an already-participated state, not a generic error path.
   - Assert the component does not construct or pass a participation payload containing user, company, team, or timestamp fields.

4. Add focused company measures component tests:
   - Mock the existing company measure API/service dependency; do not call real HTTP.
   - Cover above-threshold aggregate display with count/rate only.
   - Cover below-threshold suppression messaging.
   - Assert suppressed summaries do not render raw `eligibleCount`, `participantCount`, or `participationRate`.
   - Assert the component does not render `teamBreakdown` or individual participant fields.

5. Add focused Admin Points component tests:
   - Mock API calls; do not call real HTTP.
   - Verify the form includes `measure_participation`.
   - Verify backend data patches `measure_participation`.
   - Verify save payload includes `measure_participation`.
   - Verify existing point fields remain present: `daily_checkin`, `streak_7days`, `streak_30days`, `anamnesis_completed`, `medical_document_upload`, and `measure_participation`.

6. Keep implementation boundaries tight:
   - Prefer adding or updating Angular spec files only.
   - Avoid production-code changes unless a tiny testability-only adjustment is unavoidable.
   - Do not change Laravel, OpenAPI, migrations, Docker, n8n, API URLs, payload semantics, or privacy behavior.
   - Do not introduce broad testing framework changes or unrelated cleanup.

7. Validate after the future implementation pass:
   - Run the frontend test command discovered from `apps/web-angular/package.json`.
   - Run `docker compose exec web npm run build`.
   - Run `git diff --check`.
   - Do not run backend tests unless backend files were unexpectedly changed.
   - Do not run destructive database or Docker commands.
