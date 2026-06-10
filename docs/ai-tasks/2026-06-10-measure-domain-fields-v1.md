# Task: Measure Domain Fields v1

Date: 2026-06-10

## Goal

Extend the existing measures domain with stable foundational fields that prepare the current Company Measures and Employee Measures flow for future QR verification, guided remote measures, onsite measures, admin confirmation, and later persona-based recommendations.

This task must be additive and backward-compatible. It must preserve the existing behavior:

- Company users can create, list, and update measures.
- Employees can list active measures in their company/team scope.
- Employees can participate in measures.
- Duplicate participation still returns 409.
- Points are still awarded once for participation.
- Company participation summaries still work and keep privacy thresholds intact.

This task must not implement the Measures Hub, QR check-in, admin confirmation, persona logic, recommendation logic, measure templates, or questionnaire/check-in changes.

## Background

A previous concept task produced a measure domain analysis. The result is accepted with terminology corrections.

Important terminology decisions for this task:

### Measure origin

`measure_origin` describes where the measure definition comes from.

Allowed values for now:

- `COMPANY_CREATED`
- `ELYO_TEMPLATE`

Do not use recommendation-related values here. A recommendation is a relationship between a user and a measure, not the origin of the measure.

### Delivery type

`delivery_type` describes where/how the measure is delivered.

Allowed values:

- `REMOTE`
- `ONSITE`
- `HYBRID`

Do not use `SELF_GUIDED` here. Self-guided is an execution style, not a delivery channel.

### Execution type

`execution_type` describes the execution pattern of the measure.

Allowed values:

- `INFORMATION_ONLY`
- `GUIDED_SESSION`
- `SELF_REPORTED_ACTION`
- `EVENT_PARTICIPATION`
- `CHALLENGE`

Do not use recurrence values like `ONE_TIME`, `RECURRING`, or `OPEN_ENDED` here. Recurrence is separate and out of scope for this task.

### Verification requirement

`verification_requirement` describes which kind of proof the measure expects.

Allowed values:

- `NONE`
- `SELF_REPORT`
- `QR_CODE`
- `ADMIN_CONFIRMATION`
- `PARTNER_CONFIRMATION`

Do not add actual participation verification fields in this task. Actual participation verification will later belong to `measure_participations`, likely as `verification_type`.

### Visibility scope

`visibility_scope` describes whether a company measure is company-wide or team-specific.

Allowed values:

- `COMPANY`
- `TEAM`

Do not add `PRIVATE_USER` in this task. Individual user recommendations/assignments must remain separate and are out of scope.

## Scope

Implement stable foundational fields on the existing measures model.

Add the following fields to measures:

- `measure_origin`
  - enum/string
  - default: `COMPANY_CREATED`

- `delivery_type`
  - enum/string
  - default: `ONSITE` or another default that best preserves current behavior

- `execution_type`
  - enum/string
  - default: `EVENT_PARTICIPATION` or another default that best preserves current behavior

- `verification_requirement`
  - enum/string
  - default: `SELF_REPORT`

- `visibility_scope`
  - enum/string
  - derived from team assignment:
    - `COMPANY` if `team_id` is null
    - `TEAM` if `team_id` is set
  - keep behavior consistent with existing `team_id` scoping

- `starts_at`
  - nullable datetime

- `ends_at`
  - nullable datetime

- `duration_minutes`
  - nullable integer

- `instructions`
  - nullable text

- `location_name`
  - nullable string

- `location_address`
  - nullable string/text

- `capacity`
  - nullable integer

- `points_override`
  - nullable integer

## Explicitly Out of Scope

Do not implement:

- QR token generation
- QR scan endpoints
- Admin confirmation of participation
- Partner confirmation
- Changes to `measure_participations`
- Actual `verification_type` on participations
- Persona tables
- Health path tables
- User health profiles
- Measure template pool
- User measure recommendations
- Recommendation engine
- Measures Hub restructuring
- Check-in structured issues
- Questionnaire / anamnesis scoring
- AI/video generation logic
- Destructive database resets

## Backend Requirements

### Migration

Add a new non-destructive migration for the new measure fields.

Do not modify old migrations unless the repository convention explicitly requires it and the current project phase allows it. Prefer an additive migration for this task.

Do not run or suggest destructive commands such as:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`

### Model

Update the Measure model:

- fillable fields
- casts for datetime and integer fields
- any enum handling used in the project style

If the codebase already uses PHP enums for similar fields, follow the existing style. Otherwise, use validated strings consistently.

### Validation

Update company measure create/update request validation.

Validation rules should include:

- allowed enum values
- nullable dates
- `ends_at` must not be before `starts_at` if both are provided
- `duration_minutes` must be positive if provided
- `capacity` must be positive if provided
- `points_override` must be zero or positive if provided
- `visibility_scope` must remain consistent with `team_id` where feasible

Important:
Do not allow frontend-provided fields to bypass company/team scoping. Company identity must continue to come from the authenticated user context.

### API Resources / Responses

Expose the new fields in:

- company measure list/create/update responses
- employee measure list responses
- any relevant measure detail response if present

Existing clients should not break when fields are absent or null.

### Service Logic

Preserve existing behavior.

If defaults are needed:

- Existing company measures should behave as onsite/event/self-report unless a better compatibility-preserving default is found.
- Existing employee participation flow should continue unchanged.
- Participation points should still use the existing measure participation points config. Do not implement `points_override` behavior yet unless it is already trivial and does not alter existing tests. Prefer exposing/storing it only in this task.

### OpenAPI

Update `docs/api/openapi.yaml` for:

- new request fields
- new response fields
- enum values
- nullable fields

Keep existing endpoints and response contracts backward-compatible.

## Frontend Requirements

Update the Company Measures UI minimally so the new fields can be maintained where appropriate.

Required:

- delivery type
- execution type
- verification requirement
- starts_at
- ends_at
- duration_minutes
- instructions
- location_name
- location_address
- capacity
- points_override

Do not redesign the whole page.

Update Employee Measures UI minimally so relevant fields can be displayed.

Suggested display:

- delivery type badge
- execution type or simple label
- schedule if starts_at/ends_at exists
- location if delivery_type is ONSITE or HYBRID
- duration if present
- instructions if present
- verification requirement if useful

Do not create a full Measures Hub in this task.

## Tests

Update or add backend tests for:

- company measure creation with new fields
- company measure update with new fields
- invalid enum validation
- invalid date range validation
- employee measure list exposes new fields
- existing employee participation still works
- duplicate participation still returns 409
- company/team scoping remains intact
- participation summary remains intact

Update Angular tests if existing tests fail due to new fields or updated UI.

## Validation Commands

Run non-destructive validation only.

Suggested commands, adapted to the repo setup:

- Backend tests relevant to measures/participation
- Angular tests relevant to employee/company measures
- OpenAPI validation if available
- Type checks/builds if reasonably scoped

Do not run destructive database reset commands.

## Expected Handoff

The final handoff must include:

- Summary
- Files changed
- Backend changes
- Frontend changes
- OpenAPI changes
- Tests run
- Validation result
- Behavior preserved
- Risks / open questions
- Recommended next task

## Recommended Next Task After This

Do not implement this now, but if this task succeeds, the likely next task is:

`Measure Participation Verification v1`

That future task should add actual participation verification fields to `measure_participations`, such as:

- `verification_type`
- `verified_at`
- `verified_by_user_id`

and should preserve the current self-report participation as the default behavior.

## Implementation Plan

### Constraints for the implementation pass

- Keep this as an additive, backward-compatible vertical slice.
- Do not modify `../ELYO`.
- Do not implement QR flows, admin confirmation, partner confirmation, recommendations, templates, Measures Hub restructuring, questionnaire/check-in changes, or participation verification fields.
- Do not change `measure_participations` behavior or points-award behavior in this task.
- Do not expose individual employee health data, raw free text health answers, or identifiable participation data to company users.
- Do not run destructive database commands such as `migrate:fresh`, `db:wipe`, or `docker compose down -v`.

### 1. Backend schema

- Add one new non-destructive Laravel migration for `measures`.
- Add nullable/defaulted columns:
  - `measure_origin` string default `COMPANY_CREATED`
  - `delivery_type` string default `ONSITE`
  - `execution_type` string default `EVENT_PARTICIPATION`
  - `verification_requirement` string default `SELF_REPORT`
  - `visibility_scope` string default `COMPANY`
  - `starts_at` nullable timestamp
  - `ends_at` nullable timestamp
  - `duration_minutes` nullable unsigned integer
  - `instructions` nullable text
  - `location_name` nullable string
  - `location_address` nullable text
  - `capacity` nullable unsigned integer
  - `points_override` nullable unsigned integer
- Backfill `visibility_scope` in the migration based on current `team_id`:
  - `COMPANY` when `team_id` is null
  - `TEAM` when `team_id` is set
- Add useful indexes only if they support existing or newly exposed query/filter patterns; avoid speculative indexes.

### 2. Backend model and constants

- Update `App\Models\Measure` fillable fields with the new measure columns.
- Add casts:
  - `starts_at`, `ends_at` as `datetime`
  - `duration_minutes`, `capacity`, `points_override` as `integer`
- Check whether the project already uses PHP enums for comparable API fields. If not, use request validation constants or private arrays in the relevant Form Request classes rather than introducing a broad enum layer for this slice.
- Keep default behavior model-compatible with existing factories and seeders; update `MeasureFactory` only as needed to make test data explicit and stable.

### 3. Backend validation

- Extend `CreateMeasureRequest` to accept the new request fields with strict enum validation.
- Extend `PatchMeasureRequest` so company users can update the new maintainable measure fields as well as status.
- Preserve current status-transition validation in the controller/service layer; do not let a general patch payload bypass the existing transition rules.
- Validate:
  - `measureOrigin` or `measure_origin` according to the existing API naming style chosen for this endpoint.
  - `deliveryType`: `REMOTE`, `ONSITE`, `HYBRID`
  - `executionType`: `INFORMATION_ONLY`, `GUIDED_SESSION`, `SELF_REPORTED_ACTION`, `EVENT_PARTICIPATION`, `CHALLENGE`
  - `verificationRequirement`: `NONE`, `SELF_REPORT`, `QR_CODE`, `ADMIN_CONFIRMATION`, `PARTNER_CONFIRMATION`
  - `startsAt`, `endsAt` as nullable dates, with `endsAt` not before `startsAt` when both are present
  - `durationMinutes` as nullable positive integer
  - `capacity` as nullable positive integer
  - `pointsOverride` as nullable integer with minimum `0`
  - text/string length limits for `instructions`, `locationName`, and `locationAddress`
- Derive or enforce `visibility_scope` from `team_id`; do not trust a client-provided value that conflicts with the server-derived team scope.
- Keep company identity, manager team scope, and team-layer restrictions derived from authenticated user context.

### 4. Backend controller/resource behavior

- In company create:
  - Continue deriving `company_id`, `team_id`, and `created_by` server-side.
  - Apply defaults for omitted new fields.
  - Store `visibility_scope` from the final server-side `team_id`, including manager-only team assignment.
- In company update:
  - Keep company/team access checks unchanged.
  - Apply status changes only through the existing allowed transition map.
  - Update non-status measure fields when present in the validated payload.
  - Recompute `visibility_scope` if `team_id` can ever be changed in this endpoint; otherwise leave it consistent with the existing `team_id`.
- Update company and employee `MeasureResource` classes to expose stable camelCase API fields matching OpenAPI:
  - `measureOrigin`
  - `deliveryType`
  - `executionType`
  - `verificationRequirement`
  - `visibilityScope`
  - `startsAt`
  - `endsAt`
  - `durationMinutes`
  - `instructions`
  - `locationName`
  - `locationAddress`
  - `capacity`
  - `pointsOverride`
- Ensure participation responses returned after `POST /employee/measures/{measure}/participate` include the same new employee measure fields without changing duplicate-conflict behavior or point awarding.
- Leave participation summary logic untouched except for test fixture compatibility if needed.

### 5. OpenAPI contract

- Update `docs/api/openapi.yaml` for company measure create/update request bodies.
- Update company and employee measure response schemas with the new camelCase fields, enum values, nullability, and defaults where appropriate.
- Keep existing endpoints and error behavior documented as backward-compatible.
- Do not document unimplemented QR/admin/partner verification endpoints or participation verification fields.

### 6. Frontend types and API usage

- Add or extend local TypeScript interfaces for company and employee measures instead of continuing to rely on broad `any` where the touched UI needs the new fields.
- Keep API calls in existing Angular services or the existing `ApiClient` usage pattern; do not add direct `fetch` calls.
- Keep backend-owned scoping and defaults; the frontend should send only editable fields and should not infer company identity.

### 7. Company Measures UI

- Extend the existing create form minimally with:
  - delivery type
  - execution type
  - verification requirement
  - starts/end date-time fields
  - duration
  - instructions
  - location name
  - location address
  - capacity
  - points override
- Keep the existing title/category/description/team/status workflow intact.
- Add client-side validators that mirror backend constraints where practical, but treat backend validation as authoritative.
- Display selected scheduling/location/type fields in the existing measures list without redesigning the page.
- Preserve participation summary display and anonymity-threshold suppression behavior exactly.

### 8. Employee Measures UI

- Extend the existing employee measure cards minimally to display relevant new fields:
  - delivery type badge
  - execution type label
  - schedule when present
  - location for `ONSITE` or `HYBRID`
  - duration when present
  - instructions when present
  - verification requirement only as neutral information, without implying implemented QR/admin confirmation flows
- Keep the existing `Teilnehmen` flow unchanged.
- Keep duplicate participation and inactive-measure error handling unchanged.

### 9. Tests

- Add or update backend feature tests for:
  - creating a company measure with the new fields
  - default values when new fields are omitted
  - updating maintainable new fields
  - invalid enum validation
  - invalid date range validation
  - positive integer validation for duration/capacity and non-negative validation for points override
  - employee measure list response includes the new fields
  - existing participation success still awards points once
  - duplicate participation still returns `409`
  - company/team scoping remains intact, including manager/team-layer behavior where existing tests cover it
  - participation summary remains aggregate and threshold-protected
- Update Angular tests only where the new form controls or displayed fields require it.

### 10. Validation for the implementation pass

- Run non-destructive validation only:
  - relevant Laravel feature tests for company measures, employee measures, participation, and participation summary
  - Angular tests/build relevant to changed measure UI, or `docker compose exec web npm run build` if that is the established project check for the patch
  - OpenAPI validation if a project command exists
  - `git diff --check`
- Do not run `migrate:fresh` for this task unless the user explicitly changes the constraints.

### 11. Review checklist before handoff

- Confirm OpenAPI matches request and response behavior.
- Confirm no company response exposes individual participation rows or employee health data.
- Confirm `visibility_scope` cannot be client-forged against `team_id`.
- Confirm `points_override` is stored/exposed only and does not change current point-award behavior.
- Confirm defaults preserve existing measures as company-created onsite event participation with self-report verification.
- Confirm frontend changes are minimal and do not create a Measures Hub.
- Mark any uncertain existing repository convention explicitly in the handoff.

## Plan Adjustments Before Implementation

- Do not accept `visibilityScope` / `visibility_scope` from create or patch request payloads. Always derive `visibility_scope` server-side from the final `team_id`: `COMPANY` when `team_id` is null, `TEAM` when `team_id` is set.
- Use `ONSITE` + `EVENT_PARTICIPATION` + `SELF_REPORT` as compatibility-preserving defaults for the current company-measure slice, unless code inspection clearly shows that existing seed/test measures are remote-like.
- Do not make `team_id` editable in this task unless it is already editable in the current implementation.
- Keep optional frontend fields compact. If the existing Company Measures UI becomes noisy, place the new optional fields in an "Erweiterte Angaben" section instead of redesigning the page.
- Do not prominently display `pointsOverride` in the Employee UI until point-award behavior actually uses it. It may be exposed in the API for future compatibility.
