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
