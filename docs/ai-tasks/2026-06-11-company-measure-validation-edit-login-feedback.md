# Task: Company Measure Validation, Edit Flow, QR Audit, and Login Feedback

Date: 2026-06-11

## Context

Manual QA found several product and validation issues:

1. Company measure creation currently allows illogical date/time input.
   - End date/time can be before start date/time.
2. Duration handling is unclear.
   - For scheduled company measures, duration should be derived from start and end date/time where this is semantically correct.
3. Company measures are currently not editable.
   - Editing must be supported for realistic company workflows.
4. QR check-in status is unclear.
   - QR_CODE backend behavior may already exist, but the current implementation/UI status must be audited.
5. Login without entered credentials currently shows no visible feedback.
6. General form standard:
   - Every form must have required-field validation and logical cross-field validation in frontend and backend where applicable.
   - No form should fail silently.

Read and follow:

- `AGENTS.md`
- `docs/ai-context/*`
- existing Laravel request/controller/service conventions
- existing Angular form/service conventions
- existing OpenAPI conventions

Do not modify legacy `../ELYO`.

Do not run destructive commands:

- no `migrate:fresh`
- no `db:wipe`
- no `docker compose down -v`
- no destructive git reset/checkout commands

## Goal

Improve Company Measure creation/editing and Login feedback so forms behave predictably:

- frontend gives immediate validation feedback
- backend remains source of truth
- cross-field rules are enforced server-side
- backend validation errors are displayed in the UI
- no silent submit failures
- QR_CODE implementation status is clarified without accidental scope creep

## Scope

Implement only:

1. Company Measure logical validation
2. Company Measure edit support
3. Scheduled duration derivation/display where semantically correct
4. QR_CODE audit and minimal UI/service wiring if backend already exists
5. Login form validation and visible error feedback
6. OpenAPI updates if request/response contracts change
7. Backend and Angular tests for touched behavior
8. Small project guideline note for form validation if a suitable existing docs file exists

Do not implement:

- new recommendation logic
- new user assignment system
- new system measure template logic
- new point/streak behavior
- new survey behavior
- unrelated UI redesigns
- new QR architecture beyond minimal wiring/audit
- unrelated refactors

---

## Part A: Company Measure Date/Time Validation

### Backend Requirements

Inspect the current Company Measure create flow.

Use the existing field names from the codebase. Do not invent parallel fields if fields already exist.

Add or verify backend validation:

- required fields must be explicit
- start date/time field must be valid if present
- end date/time field must be valid if present
- if both start and end are present:
  - end must be after or equal to start if zero-duration is allowed
  - prefer end strictly after start for scheduled events unless existing product behavior clearly requires equality
- invalid logical combinations return `422` with field-level validation errors

The final rule must be documented in the handoff.

### Frontend Requirements

In the Company Measure create form:

- add required validators for required fields
- add cross-field validation for start/end logic
- show visible validation messages near the affected fields
- block submit while the form is invalid
- display backend 422 validation errors if returned

### Tests

Backend:

- create with end before start returns 422
- create with valid start/end succeeds
- missing required fields return 422

Angular:

- end before start shows validation error
- empty required fields show validation errors
- invalid form does not call API service
- backend 422 is displayed to the user

---

## Part B: Duration Handling

### Product Decision for v1

For scheduled company measures:

- if start and end date/time are present, derive duration in minutes from them
- show the derived duration in the UI
- do not require manual duration input for scheduled measures

Important distinction:

- If a measure represents a single appointment/event, duration = end - start.
- If a measure represents a multi-day availability window or challenge period, start/end may describe availability, not session duration.
- Inspect the existing domain/model naming before implementing storage changes.

### Implementation Preference

Prefer not to store redundant duration if the backend can derive it from start/end for the response.

Acceptable approaches:

1. Add a derived response field such as `durationMinutes` / `computedDurationMinutes`, if the API resource already has a suitable pattern.
2. Or calculate a frontend-only preview while keeping backend validation as source of truth.

If a duration field already exists and is part of the API contract:

- ensure it is consistent with start/end
- or document why it remains manually editable

Do not add broad schema changes unless clearly required by existing model structure.

### Tests

Backend:

- response contains correct derived duration if a response field is added
- negative duration is impossible because validation rejects it

Angular:

- duration preview updates when start/end changes
- duration is not manually required when start/end are valid

---

## Part C: Company Measure Edit Flow

### Backend Requirements

Inspect whether a Company Measure update endpoint already exists.

If missing, add one following existing route/auth/service conventions.

Required behavior:

- Company admin/manager can update measures only within existing company/team scope rules.
- Users cannot update measures from another company.
- Managers cannot update measures outside their managed team scope if team scoping exists.
- Update validates the same logical rules as create.
- Update must not allow unsafe changes if participations already exist unless explicitly allowed.

V1 editability rule:

- `SUGGESTED` and `ACTIVE` measures are editable.
- `COMPLETED` and `DISMISSED` measures should be either not editable or only limited-field editable.
- Choose the safer existing-product-compatible behavior and document it.

If a measure already has participation records:

- do not allow changing verification type between `SELF_REPORT` and `QR_CODE` unless existing product logic explicitly allows it
- prefer returning `422` for unsafe changes

### Frontend Requirements

Company Measures UI:

- add an edit action for editable measures
- reuse the create form where practical
- prefill existing values
- save via update endpoint/service method
- show success/error feedback
- apply the same frontend validation as create
- hide or disable edit action for non-editable statuses if backend forbids them

### Tests

Backend:

- authorized company role can update own measure
- cannot update another company measure
- update with invalid start/end returns 422
- completed/dismissed edit behavior is tested according to chosen rule
- unsafe verification type change with participations is rejected if implemented

Angular:

- clicking edit opens populated form
- saving valid changes calls update service
- invalid edit form blocks submit
- non-editable measures do not show edit action or show disabled state

---

## Part D: QR Check-in Audit and Minimal UI Wiring

### Audit Requirements

Check current QR_CODE implementation:

- backend endpoint for token generation/rotation
- backend endpoint for check-in by token
- token hash storage
- raw token one-time return
- QR_CODE vs SELF_REPORT separation
- duplicate participation handling
- OpenAPI documentation
- Angular service/UI coverage

### If backend already exists

Do not reimplement it.

Ensure Company UI has clear actions for QR_CODE measures where currently missing:

- generate QR code/token if none exists
- display QR code or token URL after generation
- rotate token with confirmation
- communicate that raw token is only shown after generation/rotation if applicable

Ensure Employee UI/check-in flow status is documented in the handoff.

### If backend is missing

Do not build the whole QR flow in this task unless the missing piece is tiny.

Document the gap as a follow-up.

### Tests

Only add tests for small UI/API wiring changes actually made in this task.

Do not expand QR behavior beyond current implementation without a separate task.

---

## Part E: Login Form Feedback

### Frontend Requirements

Login form must show visible validation feedback.

Rules:

- email is required
- email must have valid email format if the app already expects email login
- password is required

Behavior:

- submit with empty fields:
  - does not call login API
  - shows field-level messages
- invalid credentials:
  - shows a general error message, for example `E-Mail oder Passwort ist ungültig.`
- backend validation failure:
  - shows field-level messages where possible
- no silent failure

### Backend Requirements

Verify login endpoint validation.

If currently missing or weak:

- empty login payload returns `422`
- missing email returns `422`
- missing password returns `422`
- invalid credentials return the existing auth failure status/message

Do not change auth architecture.

### Tests

Backend:

- empty login payload returns 422
- missing email returns 422
- missing password returns 422
- invalid credentials return expected auth failure

Angular:

- empty submit shows email/password errors
- invalid email format shows error if validator exists
- invalid credentials show general error
- no API call on invalid local form

---

## Part F: General Form Validation Standard

If a suitable existing documentation file exists, add a short note there.

Preferred location:

- existing frontend or AI context guideline file

Content:

- Forms must validate required fields in frontend and backend.
- Cross-field logical rules must exist in backend and should be mirrored in frontend.
- Backend is source of truth.
- Frontend must display backend validation errors.
- No form submit should fail silently.

Do not create a large new documentation system.

---

## OpenAPI

Update `docs/api/openapi.yaml` if API contracts change:

- Company Measure create/update validation
- update endpoint if newly added
- derived duration field if added to response
- Login validation responses if currently undocumented
- QR endpoint docs only if touched

Keep OpenAPI and runtime consistent.

---

## Validation

Run non-destructive validation.

Backend:

- relevant Company Measure tests
- relevant Auth/Login tests
- exact filters should be selected after inspection

Frontend:

- relevant Angular specs for Company Measures
- relevant Angular specs for Login/Auth
- `docker compose exec web npm run build`

Diff hygiene:

- `git diff --check`
- `git status --short`

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

---

## Expected Handoff

Return:

- summary
- files changed
- validation rules added
- duration behavior decision
- edit behavior decision
- QR implementation status
- login feedback behavior
- OpenAPI updates
- tests added/updated
- commands run
- test/build results
- remaining risks/follow-ups

## Implementation Plan

### Read-only findings to carry into patch mode

- Company measure APIs already exist under `/api/company/measures`.
  - `POST /company/measures` uses `CreateMeasureRequest`.
  - `PATCH /company/measures/{id}` already exists and uses `PatchMeasureRequest`.
  - `POST /company/measures/{measure}/checkin-token` already exists for QR token rotation.
- Backend date validation already checks `endsAt` against `startsAt`, but create/update semantics still need to be tightened, tested, and mirrored in Angular.
- `duration_minutes` already exists in the model/resource/contract surface. Avoid schema changes; normalize or derive behavior with the existing field unless inspection during patch mode proves that unsafe.
- QR check-in is not missing at backend level: token generation/rotation, hash storage, raw-token return, employee token redemption, and duplicate handling already exist. Treat QR work as audit plus small UI clarity only.
- Login backend already validates `email`, `password`, and `requested_portal`. The visible feedback gap is mainly Angular-side, with focused backend tests added if current assertions do not cover empty/missing payloads.

### 1. Backend company measure validation

- Keep validation in Laravel Form Requests, not controllers or Angular-only logic.
- In `CreateMeasureRequest` and `PatchMeasureRequest`, enforce a single shared start/end rule:
  - `startsAt` and `endsAt` remain nullable valid dates.
  - when both effective values are present, `endsAt` must be strictly after `startsAt` for scheduled measures.
  - for PATCH, compare submitted values against existing persisted values when only one side is sent.
- Prefer strict `after` rather than `after_or_equal`, because existing `durationMinutes` has `min:1` and scheduled events should not be zero length in v1.
- Keep validation errors field-level on `endsAt` and return standard Laravel `422` validation responses.
- Add or update focused feature tests in `CompanyTest` for:
  - create rejects end before start.
  - create rejects equal start/end if strict rule is implemented.
  - create accepts valid start/end.
  - PATCH rejects invalid effective start/end combinations.
  - missing required create fields still return `422`.

### 2. Duration behavior

- Do not add a migration.
- Use the existing `duration_minutes` field as the response field for now.
- Add a small backend helper near measure payload mapping to compute duration minutes from `startsAt` and `endsAt` when both are present and the measure is semantically scheduled.
- Treat `EVENT_PARTICIPATION` and `GUIDED_SESSION` as scheduled duration types for v1.
- For `CHALLENGE`, `SELF_REPORTED_ACTION`, and `INFORMATION_ONLY`, do not force start/end into a session duration unless existing product behavior says otherwise.
- When duration is derived, ignore or overwrite a conflicting manual `durationMinutes` value rather than storing inconsistent data.
- Document in the handoff that duration is derived for scheduled measures and that non-session window semantics remain a follow-up/product decision.
- Add backend tests for derived duration and for rejecting impossible negative duration through validation.

### 3. Backend edit behavior

- Reuse the existing `PATCH /company/measures/{id}` route; do not add a parallel update endpoint.
- Preserve current company/team scoping:
  - admin/owner scoped by company.
  - manager scoped to the managed team.
  - team-layer-disabled behavior remains guarded by `TeamLayerGuard`.
- Expand PATCH behavior from status/domain-field patching into v1 edit support:
  - `SUGGESTED` and `ACTIVE` measures can edit allowed mutable fields.
  - `COMPLETED` and `DISMISSED` measures cannot edit mutable fields in v1.
  - existing allowed status transitions remain available.
  - invalid status transitions continue to be rejected.
- If a measure has participations, reject unsafe `verificationRequirement` changes between `SELF_REPORT` and `QR_CODE` with `422`.
- Keep this logic in the request/service/controller boundary already used by the slice; avoid adding unrelated abstractions unless the controller becomes materially clearer.
- Add feature tests for:
  - authorized company admin updates own measure.
  - manager cannot update a measure outside managed team scope.
  - foreign-company measure update remains unavailable.
  - invalid update dates return `422`.
  - completed/dismissed mutable edit rejection.
  - verification requirement change with participations is rejected.

### 4. Angular company measure form and service

- Keep API calls in Angular services where practical.
  - Add company measure create/update methods to `CompanyMeasuresService`, or move current direct measure API calls there in a narrow way.
  - Do not introduce direct `fetch`.
- Reuse the existing company measures form for create and edit mode.
- Add component state for `editingMeasureId` and a clear form mode.
- Prefill the form when editing a measure and restore create defaults when closing/resetting.
- Add a shared cross-field validator to the reactive form:
  - when both start and end are present, end must be after start.
  - mark/show the error near the end field and/or schedule group.
- Show field-level required/min/max errors for required fields and backend `422` errors.
- Block API calls when invalid by marking all controls touched and returning before service invocation.
- For scheduled duration:
  - hide or disable manual duration input when both start and end are present for scheduled measure types.
  - show a read-only derived duration preview.
  - include derived duration in the payload only if backend contract still expects it; otherwise let backend derive and use the response.
- Add edit actions in the measures table only for backend-editable statuses, or render a disabled state with no misleading action.
- Add Angular tests for:
  - invalid start/end displays an error.
  - invalid form does not call the API service.
  - backend `422` validation errors are visible.
  - edit opens a populated form.
  - valid edit calls update service.
  - completed/dismissed measures do not expose an active edit action.
  - duration preview updates from start/end.

### 5. QR audit and minimal UI wiring

- Audit current QR implementation during patch mode against the checklist in Part D.
- Do not reimplement QR token storage or redemption.
- Keep company UI QR behavior limited to:
  - create/generate link for active QR measures.
  - rotate link with explicit confirmation before replacing an existing link.
  - show that the link/token is only available immediately after generation or rotation.
  - keep self-report measures from showing QR actions.
- Verify employee route `/employee/measure-checkins/:token` remains wired to the redemption component.
- Add tests only for UI/service changes made in this task.
- Record the QR status and any follow-up gaps in the handoff.

### 6. Login feedback

- Keep backend auth architecture unchanged.
- Add missing Auth feature tests only where current `AuthTest` does not already cover the behavior:
  - empty login payload returns `422`.
  - missing email returns `422`.
  - missing password returns `422`.
  - invalid credentials continue to return the existing auth failure status.
- In `LoginComponent`:
  - keep reactive validators for required email, valid email format, and required password.
  - allow submit attempts while not loading, even if the form is invalid, so empty-submit feedback can appear.
  - on invalid local submit, call `markAllAsTouched`, show field messages, and do not call `AuthService.login`.
  - show backend validation errors as field-level messages when possible.
  - show a general invalid-credentials error for credential failures.
- Add a new focused login component spec if none exists.

### 7. OpenAPI and documentation

- Update `docs/api/openapi.yaml` if patch-mode changes alter:
  - measure create/update validation semantics.
  - measure update request fields.
  - duration behavior/response description.
  - login validation responses.
  - QR endpoint behavior touched by UI/service changes.
- Add the short general form validation note to an existing AI context guideline file only if still appropriate during patch mode; preferred target is `docs/ai-context/api-contract-rules.md`.
- Do not create a new documentation system.

### 8. Validation commands for patch mode

- Backend targeted tests:
  - `docker compose exec api php artisan test --filter='CompanyTest|AuthTest'`
- Frontend targeted specs:
  - run the existing Angular test command with filters for company measures and login once the package scripts are inspected.
- Build:
  - `docker compose exec web npm run build`
- Diff hygiene:
  - `git diff --check`
  - `git status --short`
- Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, or destructive git reset/checkout commands.

### 9. Privacy and architecture checks before handoff

- Confirm company users still receive only aggregate participation summaries.
- Confirm no QR/check-in UI exposes individual employee participation records.
- Confirm manager/team/company scoping is preserved for update and QR actions.
- Confirm Angular keeps API access in services and business rules in Laravel.
- Confirm OpenAPI matches runtime for any changed API behavior.
- Explicitly document unknowns and follow-ups instead of inventing legacy behavior.
