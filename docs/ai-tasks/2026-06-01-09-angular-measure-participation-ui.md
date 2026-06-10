# Task: Add Angular Measure Participation UI and Admin Points Support

## Goal

Implement the Angular UI integration for the measure participation MVP.

This task connects the existing backend APIs to the frontend:

1. Employee users can see participation state and participate in eligible measures.
2. Company users can see aggregate participation summaries for measures.
3. Admin users can configure the new `measure_participation` point setting in the Admin Points UI.

This is an Angular-focused task. Backend behavior should only be changed if a direct frontend integration bug reveals a blocking contract mismatch.

## Context

Previous backend tasks added:

- `measure_participations` persistence.
- Employee participation API:
  - `POST /api/employee/measures/{measure}/participate`
- Employee measure participation state in:
  - `GET /api/employee/measures`
- Points action:
  - `measure_participation`
- Company participation summary API:
  - `GET /api/company/measures/{measure}/participation-summary`
- OpenAPI documentation for the backend APIs.

Known deferred issue from Task 2:

- The backend now knows the point action `measure_participation`.
- The current Angular Admin Points UI does not display or submit `measure_participation`.
- This must be fixed in this task.

## Scope

Implement Angular UI only for:

1. Employee Measures participation button/state.
2. Company Measures aggregate participation summary display.
3. Admin Points UI support for `measure_participation`.

Do not add:

- new backend endpoints
- company individual participant views
- QR code logic
- attendance verification
- wallet redemption
- screening/profile/scoring logic
- medical recommendations
- n8n logic

## Relevant Frontend Areas

Inspect and update as needed:

- `apps/web-angular/src/app/features/employee/pages/measures/*`
- `apps/web-angular/src/app/features/company/pages/measures/*`
- `apps/web-angular/src/app/features/admin/pages/points/admin-points.component.ts`
- related templates/styles if split from components
- related API services using `ApiClient`
- related TypeScript interfaces/models

Use existing Angular style and architecture.

Do not introduce direct `fetch` calls if the project uses `ApiClient`.

## Part 1: Employee Measures UI

### Requirements

Update the employee measures page so employees can:

- see available measures
- see whether they have already participated
- click a button to participate
- see success/error feedback
- see the button disabled or replaced after participation

Use the existing `GET /api/employee/measures` response participation state.

Expected participation response fields, depending on backend casing:

- `participation.isParticipating`
- `participation.participatedAt`

Follow the actual API casing already used in the code/OpenAPI.

### Add API service method

Add a method in the existing employee API service pattern:

- `participateInMeasure(measureId: number)`

It should call:

- `POST /api/employee/measures/{measureId}/participate`

The request body should be empty.

Do not send:

- `user_id`
- `userId`
- `company_id`
- `companyId`
- `team_id`
- `teamId`
- `participated_at`
- `participatedAt`

The backend owns identity and tenant derivation. The frontend is not trusted with this, because apparently we must keep reminding browsers that they are not the government.

### Employee UI behavior

For each measure:

- If not participated:
  - show button, e.g. `Teilnehmen`
- If already participated:
  - show state, e.g. `Teilgenommen`
  - optionally show `participatedAt` if existing UI date formatting patterns exist
- On click:
  - set loading state for that measure only
  - call `participateInMeasure`
  - update local measure participation state from response or refetch list
  - show success feedback using existing UI convention
- On duplicate 409:
  - treat as already participated if error code is `MEASURE_ALREADY_PARTICIPATED`
  - avoid a scary failure message
- On inactive 409:
  - show a clear message if error code is `MEASURE_NOT_ACTIVE`
- On forbidden/not found:
  - show existing generic error behavior

Do not add employee access to other users' participation data.

## Part 2: Company Measures Summary UI

### Requirements

Update the company measures page so company users can see aggregate participation information for each measure.

Use:

- `GET /api/company/measures/{measureId}/participation-summary`

Display only aggregate fields.

Expected response fields:

- `measureId`
- `isAboveThreshold`
- `eligibleCount`
- `participantCount`
- `participationRate`
- `suppressionReason`
- `teamBreakdown`

Current backend intentionally returns:

- `teamBreakdown: null`

Do not build a team breakdown UI in this task.

### Summary loading

Use a simple, reviewable approach:

Option A:

- Load summaries for measures after loading the measure list.
- Store summaries in a map keyed by measureId.

Option B:

- Lazy-load summary when a measure card/row is expanded, if the current UI has expansion behavior.

Prefer the simpler approach consistent with the current UI.

Avoid complicated global state unless the project already uses it.

### Display behavior

For each measure summary:

If `isAboveThreshold = true`:

- show participant count
- show eligible count
- show participation rate

Example German labels:

- `Teilnahmen`
- `Teilnahmequote`
- `Berechtigte Mitarbeitende`

If `isAboveThreshold = false`:

- do not show raw counts or rate
- show privacy-safe message, for example:
  - `Aus Datenschutzgründen ausgeblendet`
  - `Mindestgruppengröße nicht erreicht`

Do not show suppressed values if they are null.

Do not expose:

- user IDs
- names
- emails
- raw participation rows
- per-user timestamps

### Error handling

If summary endpoint returns 403/404 for a measure due to manager/team visibility:

- do not crash the page
- show no summary or a neutral unavailable state
- follow existing error handling style

## Part 3: Admin Points UI for `measure_participation`

### Context

This is mandatory in Task 4.

The backend point config now includes:

- `measure_participation`

The current Angular Admin Points UI still sends only the previous fields. That can break saving if the backend requires or expects the new key.

### Requirements

Update:

- `apps/web-angular/src/app/features/admin/pages/points/admin-points.component.ts`
- related template if separate
- related TypeScript models/services if present

The Admin Points UI must:

- load `measure_participation`
- display an editable input for `measure_participation`
- include `measure_participation` in the save payload
- preserve all existing fields

Expected full set:

- `daily_checkin`
- `streak_7days`
- `streak_30days`
- `anamnesis_completed`
- `medical_document_upload`
- `measure_participation`

Use the existing UI style.

Suggested German label:

- `Maßnahmen-Teilnahme`

Do not rename the API key.

Do not remove any existing point setting.

## TypeScript Models

Update typed interfaces where present.

Avoid using `any` unless the existing code already does so in this exact area.

Suggested interfaces if needed:

Employee measure participation state:

- `participation: { isParticipating: boolean; participatedAt: string | null }`

Company participation summary:

- `measureId: number`
- `isAboveThreshold: boolean`
- `eligibleCount: number | null`
- `participantCount: number | null`
- `participationRate: number | null`
- `suppressionReason: string | null`
- `teamBreakdown: null`

Admin points config:

- `measure_participation: number`

Follow actual casing and conventions from existing frontend services.

## Tests

Inspect existing Angular test patterns.

If component/service tests exist and are lightweight, add/update tests for:

1. Employee service sends empty POST to participation endpoint.
2. Employee measures UI updates participation state after successful participation.
3. Duplicate participation 409 is handled as already participated.
4. Company measures UI displays aggregate summary above threshold.
5. Company measures UI displays privacy message below threshold.
6. Admin points UI includes `measure_participation` in save payload.

If there are no existing practical test patterns, do not invent a large test harness in this task. Document that no matching test pattern exists.

## Validation

Run Angular build:

- `docker compose exec web npm run build`

If frontend tests exist and are practical:

- run the relevant frontend test command

Run backend tests only if backend files are changed unexpectedly:

- `docker compose exec api php artisan test --filter=MeasureParticipation`
- `docker compose exec api php artisan test --filter=MeasureParticipationSummary`

Do not run destructive commands:

- `php artisan migrate:fresh`
- `php artisan db:wipe`
- `docker compose down -v`
- any destructive database or Docker reset

## Out of Scope

Do not change:

- Laravel service behavior
- Laravel routes
- Laravel migrations
- Laravel point awarding logic
- company summary API behavior
- employee participation API behavior
- OpenAPI unless a direct mismatch is found
- QR code logic
- attendance verification
- wallet logic
- screening/profile/scoring logic
- medical recommendation logic
- n8n workflows

## Expected Handoff

Return:

- Files changed
- Employee Measures UI changes
- Employee API service changes
- Company Measures Summary UI changes
- Company API service changes
- Admin Points UI changes
- TypeScript model changes
- Tests added/updated or reason none were added
- Validation commands run
- Confirmation that Angular build passed
- Confirmation that no backend behavior changed unless explicitly necessary
- Confirmation that frontend does not send user_id, company_id, team_id, or participated_at for participation
- Confirmation that company UI displays aggregate/suppressed summary only
- Open questions

## Implementation Plan

### Constraints for Patch Mode

- Modify only Angular frontend files needed for this task unless a verified blocking API contract mismatch is found.
- Do not change Laravel routes, services, migrations, OpenAPI, Docker, n8n, or unrelated documentation.
- Keep all API calls behind existing Angular services or `ApiClient`; do not add direct `fetch` calls.
- Preserve company privacy boundaries: company users must see only aggregate/suppressed participation summary values, never individual participants, timestamps, names, emails, user IDs, or raw participation rows.
- Participation requests must send an empty body and must not send `user_id`, `userId`, `company_id`, `companyId`, `team_id`, `teamId`, `participated_at`, or `participatedAt`.

### 1. Employee Measures Participation

1. Update `apps/web-angular/src/app/features/employee/services/employee.service.ts`.
   - Add typed measure interfaces if practical in this file:
     - `EmployeeMeasureParticipation`
     - `EmployeeMeasure`
   - Keep existing response casing from the backend/OpenAPI, expected as `participation.isParticipating` and `participation.participatedAt`.
   - Change `getMeasures()` from `Observable<any[]>` to the new measure type if this does not create broad churn.
   - Add `participateInMeasure(measureId: number)` using:
     - `this.api.post<{ data: EmployeeMeasure }>(\`/employee/measures/${measureId}/participate\`, {})`
   - Map only if needed to match the existing frontend data shape.

2. Update `apps/web-angular/src/app/features/employee/pages/measures/measures.component.ts`.
   - Keep the component presentation-focused.
   - Inject `NotificationService`.
   - Add per-measure loading state, for example `participatingMeasureIds = signal<Set<number>>(new Set())`, with helper methods to add/remove IDs immutably.
   - Render participation UI inside each measure card:
     - If `measure.participation?.isParticipating` is true, show `Teilgenommen`.
     - Show `participatedAt` only if existing date formatting can be used without adding a large new pattern.
     - If not participating, show a `Teilnehmen` button.
     - Disable only the clicked measure while its request is pending.
   - On successful participation:
     - Prefer updating the matching measure from the response `data` if returned.
     - If the response does not contain a usable measure object, update only the local `participation` state for that measure with `isParticipating: true`.
     - Show a success notification.
   - On duplicate participation `409` with code `MEASURE_ALREADY_PARTICIPATED`:
     - Treat the measure as already participated locally.
     - Show a neutral/success-style message instead of a scary error.
   - On inactive measure `409` with code `MEASURE_NOT_ACTIVE`:
     - Show a clear error message.
   - For other errors:
     - Use existing generic notification behavior and leave the list stable.

### 2. Company Measures Aggregate Summary

1. Update `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts`.
   - Add a local `MeasureParticipationSummary` interface in the component file unless a shared frontend model already exists during patch-mode inspection.
   - Add state:
     - `participationSummaries = signal<Record<number, MeasureParticipationSummary | null>>({})`
     - optionally `summaryLoadingIds = signal<Set<number>>(new Set())` if the UI needs row-level loading indicators.
   - After `loadMeasures()` receives the measure list, load summaries for those measures using `GET /company/measures/{measureId}/participation-summary`.
   - Use simple per-measure requests and store each result by `measureId`; avoid introducing global state.

2. Display summary data in the existing table.
   - Add a compact column such as `Teilnahme` or `Teilnahmequote`.
   - If `isAboveThreshold` is true:
     - Show participant count, eligible count, and participation rate using German labels or compact table text.
     - Handle `null` values defensively.
   - If `isAboveThreshold` is false:
     - Do not show counts or rate.
     - Show a privacy-safe message such as `Mindestgruppengröße nicht erreicht` or `Aus Datenschutzgründen ausgeblendet`.
   - If summary loading fails with 403/404 or another error:
     - Do not crash or block the measures list.
     - Store `null` or an unavailable marker and show a neutral unavailable state.
   - Do not render `teamBreakdown`; the backend currently returns `null` and team breakdown UI is out of scope.

### 3. Admin Points `measure_participation`

1. Update `apps/web-angular/src/app/features/admin/pages/points/admin-points.component.ts`.
   - Add `{ key: 'measure_participation', label: 'Maßnahmen-Teilnahme' }` to `fields`.
   - Add `measure_participation: [0, [Validators.required, Validators.min(0)]]` to the reactive form.
   - Keep `this.form.patchValue(res.data ?? {})` so loading preserves all existing backend-provided values.
   - Keep save payload as `this.form.value` so `measure_participation` is submitted with the existing point settings.
   - Preserve all existing fields:
     - `daily_checkin`
     - `streak_7days`
     - `streak_30days`
     - `anamnesis_completed`
     - `medical_document_upload`
     - `measure_participation`

### 4. Tests and Validation for Patch Mode

1. Inspect the current Angular test setup before adding tests.
   - Current known state from planning inspection: only `apps/web-angular/src/app/app.spec.ts` exists, with no feature/component service test pattern found yet.
   - If patch-mode inspection confirms there is still no practical existing pattern, do not invent a large test harness in this task; document the gap in the handoff.
   - If a lightweight pattern is available after further inspection, add focused tests for service payload shape, duplicate participation handling, suppressed company summaries, and Admin Points payload inclusion.

2. Run validation only in patch mode, not during this planning update.
   - Required after implementation:
     - `docker compose exec web npm run build`
     - `git diff --check`
   - Run frontend tests only if a practical test command/pattern exists.
   - Run backend tests only if backend files are changed unexpectedly.
   - Do not run destructive Docker or database commands.

### 5. Handoff Checks

- Report exact files changed.
- Confirm employee participation POST sends an empty body and no identity/tenant/timestamp fields.
- Confirm company UI shows only aggregate values above threshold and privacy-safe suppression messaging below threshold.
- Confirm Admin Points loads and saves `measure_participation` without removing existing point settings.
- Confirm no backend behavior, migrations, OpenAPI, Docker config, or n8n workflows changed unless a blocking mismatch required it.
- Mark unknowns explicitly, especially API response casing if implementation discovers a mismatch between current frontend assumptions and OpenAPI/backend behavior.
