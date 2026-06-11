# Task: Fix QR Frontend Service Boundary and Specs

Date: 2026-06-10

## Goal

Clean up the remaining QR Check-in v1 frontend architecture/test issues before merge.

The current QR implementation is functionally and privacy-wise acceptable, but review found two should-fix items:

1. Company QR token generation is called directly from `company-measures.component.ts` via `ApiClient`, which conflicts with the project rule that Angular API calls should go through services.
2. The new employee measure check-in route/component has no dedicated spec for important success/conflict/error behavior.

This task must keep the QR feature behavior unchanged and only improve frontend service boundaries and focused test coverage.

## Scope

Implement only:

- Move company QR token generation API call out of the component into the existing Angular service/API wrapper pattern.
- Update the company component to call that service method.
- Add or update focused specs for the employee measure check-in component.
- Include relevant task files as workflow artifacts if they are intentional.

Do not implement:

- new QR backend behavior
- new API endpoints
- new database migrations
- QR rendering library
- admin confirmation
- partner confirmation
- public anonymous check-in
- measures hub restructuring
- point policy changes
- recommendation/persona logic

## 1. Company QR token service boundary

### Problem

`apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts`

currently calls the QR token endpoint directly through `ApiClient`.

This matches some local component style, but conflicts with `AGENTS.md`, which expects Angular API calls to go through services.

### Required behavior

Move the QR token generation call into the appropriate company measures service or existing Angular API wrapper.

Preferred:

- find the existing company measure API service/type location
- add a method such as `generateMeasureCheckinToken(measureId: number | string)`
- return the existing response shape unchanged

The component should:

- call the service method
- keep existing UI behavior unchanged
- continue hiding/showing QR generation only for `QR_CODE` measures
- continue composing/displaying the check-in URL as currently intended
- not call `ApiClient` directly for this new QR endpoint

Do not refactor unrelated component API calls in this task.

## 2. Employee check-in component specs

### Problem

The new employee check-in route/component has no dedicated spec.

Important behavior in:

`apps/web-angular/src/app/features/employee/pages/measure-checkin/measure-checkin.component.ts`

is currently untested, especially error mapping and duplicate-participation redirect behavior.

### Required specs

Add a focused spec file for the employee measure check-in component if none exists.

Cover at least:

- reads token from route params
- calls the employee check-in service/API wrapper with the token
- success state/result behavior
- duplicate participation conflict behavior, especially `MEASURE_ALREADY_PARTICIPATED`
- invalid/expired/revoked/not-yet-valid token error mapping if the component maps these errors
- generic error fallback
- no raw token or technical error details are displayed unnecessarily

Use the existing Angular test style in the repo.

Do not add large integration routing tests unless that is the established local pattern.

## 3. Company component specs

If moving the QR call into a service requires updating company component specs, update them narrowly.

Cover or preserve:

- QR generate action uses the service
- QR action remains unavailable for non-`QR_CODE` measures
- generated check-in link display/copy behavior still works if currently tested

Do not add unrelated UI tests.

## 4. Untracked task files

There are currently untracked task files:

- `docs/ai-tasks/2026-06-10-fix-qr-checkin-contract-gating.md`
- `docs/ai-tasks/2026-06-10-fix-qr-token-generation-gating.md`

Decide whether they are intentional workflow artifacts.

Given the project workflow, include them unless there is a clear reason not to.

Do not delete them silently.

## Validation

Run non-destructive validation only:

- focused Angular specs for touched components/services if available
- `docker compose exec web npm run build`
- relevant Laravel tests only if backend files unexpectedly change
- `git diff --check`
- `git diff --cached --check` if staging is used
- `git status --short`

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

Report:

- summary
- files changed
- where the QR token API call now lives
- company component behavior confirmation
- employee check-in specs added and cases covered
- task file decision
- tests/build run and results
- remaining risks/open questions

## Implementation Plan

1. Keep the implementation scoped to Angular frontend service boundaries and focused specs only.
   - Do not modify Laravel, OpenAPI, migrations, Docker config, or unrelated documentation.
   - Preserve the existing QR check-in behavior, response shapes, messages, and route paths.
   - Treat the untracked task files listed above as intentional workflow artifacts unless `git status --short` later shows a clear reason to exclude them.

2. Move company QR token generation behind an Angular service boundary.
   - Add or extend the narrowest existing company measures API wrapper pattern for company measure calls.
   - If no dedicated company measures service exists, create a small company measures service under the company feature area rather than broadening component responsibilities.
   - Add `generateMeasureCheckinToken(measureId: number | string)` that performs `POST /company/measures/{measureId}/checkin-token` with an empty body and returns the existing `{ data: ... }` response shape.
   - Move shared QR token response/link interfaces only as far as needed for typing; do not redesign company measure models in this task.

3. Update `CompanyMeasuresComponent` narrowly.
   - Inject the new/existing company measures service for QR token generation.
   - Replace only the direct `ApiClient.post('/company/measures/{id}/checkin-token', {})` call in `rotateCheckinLink`.
   - Keep existing direct `ApiClient` usage for unrelated company measure list/create/summary/team calls unless a local service already owns those calls and the change is minimal.
   - Preserve the existing guard that blocks non-`QR_CODE` measures before any API call.
   - Preserve current link composition with `window.location.origin + checkinPath`, loading state updates, and current success/error notifications.

4. Add focused specs for `EmployeeMeasureCheckinComponent`.
   - Create `apps/web-angular/src/app/features/employee/pages/measure-checkin/measure-checkin.component.spec.ts` following the existing Vitest/TestBed standalone component style.
   - Mock `ActivatedRoute.snapshot.paramMap`, `Router`, `EmployeeService`, and `NotificationService`.
   - Cover token extraction from route params and the call to `employeeService.redeemMeasureCheckin(token)`.
   - Cover successful redemption: loading stops, success state/content is shown, and success notification is emitted.
   - Cover missing token: no service call, loading stops, and the invalid-link message is shown.
   - Cover `MEASURE_ALREADY_PARTICIPATED`: success notification is emitted and navigation goes to `/employee/measures`.
   - Cover mapped error messages for `404`, `MEASURE_NOT_ACTIVE`, `CHECKIN_TOKEN_REVOKED`, `CHECKIN_TOKEN_NOT_YET_VALID`, and `CHECKIN_TOKEN_EXPIRED`.
   - Cover generic fallback behavior and assert the DOM does not expose the raw token or backend technical error code/details.

5. Update company component specs only where required by the service-boundary change.
   - Replace QR token generation expectations so the QR action asserts the company measures service method is called, not `ApiClient.post`.
   - Keep existing coverage that self-report measures do not generate QR tokens.
   - Keep or adapt the check-in link composition test so it still proves `checkinPath` is converted into the browser URL.
   - Keep the QR rejection/error-message test behavior unchanged.

6. Add service-level specs if a new company measures service is introduced.
   - Verify `generateMeasureCheckinToken(3)` calls `ApiClient.post('/company/measures/3/checkin-token', {})`.
   - Verify no identity, employee, company, timestamp, or participation data is sent in the request body.
   - Avoid broad tests for existing unrelated company APIs unless they are moved as part of this narrow change.

7. Validation to run during patch mode only, not during this plan-only step.
   - Focused Angular specs for the touched component/service files, if the project scripts support a focused run.
   - `docker compose exec web npm run build`.
   - `git diff --check`.
   - `git diff --cached --check` only if staging is used.
   - `git status --short`.
   - Do not run backend tests unless backend files unexpectedly change.
   - Do not run destructive database, Docker volume, or reset/checkout commands.

8. Final patch-mode handoff should report the required fields from `AGENTS.md`.
   - Files changed.
   - Behavior changed.
   - Commands run.
   - Test/build result.
   - Open questions.
   - Intentional deviations, if any.

## Final Clarification Before Implementation

If a new `CompanyMeasuresService` is introduced, keep it intentionally narrow for this patch.

It should only own the QR check-in token generation call unless an existing local service already owns company measure API calls.

Do not migrate unrelated company measure list/create/update/summary/team API calls in this task.
