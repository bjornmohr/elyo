# Task: Manual QA Plan for Measure Participation MVP

## Goal

Create and execute a focused manual QA checklist for the completed Measure Participation MVP.

This task is not a feature implementation task. It should verify the full vertical flow across Admin, Company, and Employee portals after the backend and Angular implementation.

## Context

The Measure Participation MVP now includes:

- MeasureParticipation persistence
- Employee participation API
- Points awarding for `measure_participation`
- Company aggregate participation summary API
- Angular Employee Measures participation UI
- Angular Company Measures summary UI
- Angular Admin Points UI support for `measure_participation`
- Focused Angular tests for the participation UI integration

Known previous risks:

- Admin Points UI originally did not send `measure_participation`; this should now be fixed.
- Company summary must never expose individual participation data.
- Suppressed summaries must not show raw counts or rates.
- Employee participation POST must not send user/company/team/timestamp identity fields.
- Company summary uses current user/team scope, not historical participation team scope.

## Scope

Create a manual QA checklist and execute it locally if possible.

Do not implement new product behavior in this task.

Only make code changes if a directly verified bug is found and the fix is tiny and clearly in scope. Otherwise document the bug as a follow-up task.

## QA Areas

### 1. Admin Points Config

Verify:

- Admin can open the Points Config page.
- `measure_participation` is visible with label `Maßnahmen-Teilnahme` or equivalent.
- Existing fields are still visible:
  - daily_checkin
  - streak_7days
  - streak_30days
  - anamnesis_completed
  - medical_document_upload
  - measure_participation
- Saving the form succeeds.
- The save payload includes `measure_participation`.
- Reloading the page keeps the saved value.

### 2. Company Measures

Verify as Company Owner/Admin:

- Company user can create or activate a measure.
- Existing company measure list still loads.
- Company-wide measure is visible.
- Team-specific measure is visible according to existing company/team-layer rules.
- Participation summary area loads without breaking the measure list.
- If threshold is met:
  - participant count is shown
  - eligible count is shown
  - participation rate is shown
- If threshold is not met:
  - raw counts are not shown
  - rate is not shown
  - privacy-safe message is shown

### 3. Employee Measures

Verify as Employee:

- Employee can open the Measures page.
- Employee sees active company-wide measures from own company.
- Employee sees active team-specific measures only for own team.
- Employee does not see inactive/completed/dismissed measures.
- Employee can click `Teilnehmen`.
- Request body is empty or `{}` only.
- No user_id, company_id, team_id, or participated_at is sent by the frontend.
- Success state changes to `Teilgenommen`.
- Button is disabled or replaced after participation.
- Refreshing the page keeps participation state.

### 4. Duplicate Participation

Verify:

- Employee cannot participate twice.
- Duplicate attempt does not award points again.
- UI handles duplicate 409 `MEASURE_ALREADY_PARTICIPATED` as already participated, not as a severe error.

### 5. Points

Verify:

- Employee receives configured `measure_participation` points after first participation.
- Points are awarded exactly once.
- Changing Admin Points Config affects future participation awards if that is the expected backend behavior.
- Existing daily check-in points still work.

### 6. Company Summary Privacy

Verify:

- Company summary does not show:
  - user IDs
  - names
  - emails
  - raw participation rows
  - per-user participated timestamps
  - non-null `teamBreakdown` counts, rates, participant data, identifiable response data, or individual health data
- `teamBreakdown` may be present as `null`; non-null team breakdown data requires a separate privacy-reviewed feature.
- Below threshold:
  - eligibleCount is not visible
  - participantCount is not visible
  - participationRate is not visible
- Manager users cannot see summaries outside their allowed team/company scope.

### 7. Team Transfer Regression

Verify if practical:

- Employee participates while in Team A.
- Employee is moved to Team B.
- Team A manager summary no longer counts that employee.
- Participation rate does not exceed 100%.

### 8. Browser/UX Smoke Test

Verify:

- No visible console errors during normal flow.
- Loading states behave reasonably.
- Failed summary request does not break the whole company measures page.
- German UI labels are understandable.
- Empty states are acceptable.

## Commands

Run frontend build:

- docker compose exec web npm run build

Run frontend tests if available:

- docker compose exec web npm test

Run targeted backend tests:

- docker compose exec api php artisan test --filter=MeasureParticipation
- docker compose exec api php artisan test --filter=MeasureParticipationSummary

Run git checks:

- git status --short
- git diff --check

Do not run destructive commands:

- php artisan migrate:fresh
- php artisan db:wipe
- docker compose down -v
- any destructive database or Docker reset

## Expected Output

Create a QA handoff note under:

docs/ai-tasks/

Suggested file:

docs/ai-tasks/2026-06-01-11-manual-qa-measure-participation-flow-handoff.md

The handoff should include:

- QA scenarios executed
- User roles tested
- Test data used
- Commands run
- Pass/fail result per area
- Screenshots if useful, but not required
- Bugs found
- Follow-up tasks needed
- Explicit confirmation that no individual participation data is visible in company UI
- Explicit confirmation that Admin Points UI saves `measure_participation`

## Implementation Plan

1. Confirm environment and scope before QA:
   - Inspect current git status to record pre-existing local changes.
   - Start from the existing Docker Compose stack only if it is already available or can be started without destructive reset.
   - Do not run destructive database or Docker commands.
   - Treat this as manual QA and documentation, not product implementation.

2. Prepare test roles and data:
   - Identify or create non-sensitive local test users for Admin, Company Owner/Admin, Company Manager, and Employee flows.
   - Use existing seed/demo data where practical instead of adding migrations or broad fixtures.
   - Record company, team, measure, and user identifiers used for QA in the handoff, but do not expose real personal health data.

3. Verify Admin Points Config:
   - Open the Admin Points Config UI and confirm `measure_participation` is visible with a German label equivalent to `Maßnahmen-Teilnahme`.
   - Save a test value and verify the outgoing payload includes `measure_participation`.
   - Reload the page and confirm the value persists.
   - Check existing point keys still render and save normally.

4. Verify Employee Measures participation:
   - Log in as an employee and confirm only active measures for the employee's company and eligible team are visible.
   - Trigger `Teilnehmen` and inspect the network request body; it must be empty or `{}` only.
   - Confirm no frontend request sends `user_id`, `company_id`, `team_id`, or `participated_at`.
   - Confirm success changes the UI to an already-participated state and persists after refresh.

5. Verify duplicate participation and points:
   - Attempt duplicate participation through the UI or direct API request.
   - Confirm duplicate participation returns/handles `MEASURE_ALREADY_PARTICIPATED` as already participated.
   - Confirm points are awarded exactly once for the first participation.
   - If practical, adjust Admin Points Config and verify future participation uses the configured value.
   - Smoke-check that existing daily check-in point awarding still works.

6. Verify Company Measures summary and privacy:
   - Log in as Company Owner/Admin and confirm measure list and participation summary both load.
   - Test threshold-met summary display for participant count, eligible count, and rate.
   - Test below-threshold summary display and confirm raw counts and rate are hidden.
   - Inspect UI and API responses for absence of user IDs, names, emails, raw rows, per-user timestamps, and non-null `teamBreakdown` data.
   - `teamBreakdown` may be present as `null`; non-null team breakdown data remains out of scope and requires a separate privacy-reviewed feature.
   - Verify manager users cannot view summaries outside their allowed company/team scope.

7. Verify team transfer regression if practical:
   - Create or identify an employee in Team A and record participation.
   - Move the employee to Team B using existing admin/company tooling or safe local setup.
   - Confirm Team A manager summary no longer counts that employee and participation rate does not exceed 100%.
   - If setup is too costly, document this as not executed with the reason.

8. Run permitted validation only during QA execution:
   - `docker compose exec web npm run build`
   - `docker compose exec web npm test`
   - `docker compose exec api php artisan test --filter=MeasureParticipation`
   - `docker compose exec api php artisan test --filter=MeasureParticipationSummary`
   - `git status --short`
   - `git diff --check`
   - Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, or destructive reset commands.

9. Document results in the handoff:
   - Create `docs/ai-tasks/2026-06-01-11-manual-qa-measure-participation-flow-handoff.md`.
   - Include scenarios executed, roles tested, test data used, commands run, pass/fail status, bugs found, and follow-up tasks.
   - Explicitly state whether company UI exposes no individual participation data.
   - Explicitly state whether Admin Points UI saves `measure_participation`.

10. Handle bugs conservatively:
   - If a tiny directly verified bug blocks QA and is clearly in scope, fix it in a small patch with focused validation.
   - Otherwise do not modify product code; document the issue as a follow-up task with reproduction steps.
   - Update OpenAPI only if a later verified code change alters API behavior.
