# Task: Measure Participation Verification v1

Date: 2026-06-10

## Goal

Add verification metadata to existing measure participations while preserving the current self-report participation flow.

This task prepares the existing Measures feature for later QR check-in, admin confirmation, and partner confirmation, but must not implement those flows yet.

The existing behavior must remain unchanged:

- Employees can participate in active measures through the existing endpoint.
- Participation remains self-reported for now.
- Duplicate participation still returns `409`.
- Points are still awarded once through the existing points behavior.
- Company participation summaries remain aggregate-only and threshold-protected.
- No individual participation rows or employee health data are exposed to company users.

## Current Context

Measure Domain Fields v1 has already added measure metadata such as:

- `delivery_type`
- `execution_type`
- `verification_requirement`
- `visibility_scope`
- scheduling/location/instructions/capacity/points override fields

For now, company create/update only supports:

- `verificationRequirement = SELF_REPORT`

The next safe step is to store how an actual participation was verified.

## Scope

Add participation verification metadata to `measure_participations`.

New fields:

- `verification_type`
  - string
  - default: `SELF_REPORTED`

- `verified_at`
  - nullable timestamp

- `verified_by_user_id`
  - nullable foreign key to `users.id`

Existing self-report participation should store:

- `verification_type = SELF_REPORTED`
- `verified_at = participated_at`
- `verified_by_user_id = null`

## Allowed Verification Types

For this task, only the current runtime behavior should be actively produced:

- `SELF_REPORTED`

The following values may be documented or modeled as future enum vocabulary only if needed for clarity, but must not be produced by any endpoint in this task:

- `QR_CHECKIN`
- `ADMIN_CONFIRMED`
- `PARTNER_CONFIRMED`

Prefer keeping request-side behavior restricted to `SELF_REPORTED` only.

## Explicitly Out of Scope

Do not implement:

- QR token generation
- QR scan endpoints
- Admin confirmation endpoints
- Partner confirmation
- Any new participation creation flow besides the existing employee self-report flow
- Changes to point-award behavior
- Changes to participation summary behavior
- Measures Hub restructuring
- Recommendations
- Personas
- Measure templates
- Questionnaire/check-in changes
- AI/video generation logic
- Destructive database reset commands

## Backend Requirements

### Migration

Add one new non-destructive Laravel migration for `measure_participations`.

Add:

- `verification_type` string default `SELF_REPORTED`
- `verified_at` nullable timestamp
- `verified_by_user_id` nullable foreign key to `users.id`, null on delete

Backfill existing rows:

- `verification_type = SELF_REPORTED`
- `verified_at = participated_at`
- `verified_by_user_id = null`

Do not modify old migrations unless the repository convention explicitly requires it. Prefer an additive migration.

Do not run or suggest:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`

### Model

Update the `MeasureParticipation` model:

- fillable fields if applicable
- casts:
  - `participated_at` remains datetime
  - `verified_at` as datetime
- relationship:
  - optional `verifiedBy` relation to `User` if consistent with project style

### Service Logic

Update the existing participation creation logic so the current employee self-report flow writes:

- `verification_type = SELF_REPORTED`
- `verified_at = participated_at`
- `verified_by_user_id = null`

Do not change duplicate detection.

Do not change point awarding.

Do not enforce QR/admin/partner verification logic in this task.

### API Resources

If employee participation responses include participation metadata, expose:

- `verificationType`
- `verifiedAt`

Do not expose `verifiedBy` to employee unless already appropriate.

Do not expose identifiable participation rows to company users.

Company participation summary must remain aggregate-only.

### OpenAPI

Update `docs/api/openapi.yaml` only for existing participation response schemas if they include participation metadata.

Document:

- `verificationType`
- `verifiedAt`

Do not document QR/admin/partner endpoints.

Do not document request-side participation verification fields.

Do not document company access to individual participation rows.

## Frontend Requirements

Keep the current employee participation flow unchanged.

Only update frontend types/UI if the existing response shape requires it.

Optional:

- Employee UI may display a neutral self-report label such as `Selbst bestätigt` if already showing participation metadata.
- Do not add QR/admin/partner UI.
- Do not restructure the Measures page into a Hub.

## Tests

Add or update backend tests for:

- self-report participation stores `verification_type = SELF_REPORTED`
- self-report participation sets `verified_at`
- `verified_at` matches or is equivalent to `participated_at`
- `verified_by_user_id` is null for self-report
- duplicate participation still returns `409`
- points are still awarded once
- employee measure participation response includes verification metadata if the response schema exposes it
- company participation summary remains aggregate and threshold-protected
- no identifiable participation data is exposed to company users

Update Angular tests only if existing specs fail due to response/type changes.

## Validation

Run non-destructive validation only.

Suggested commands:

- relevant Laravel feature tests for employee participation and company participation summary
- relevant company/employee measure tests
- Angular build/tests only if frontend changed
- OpenAPI validation command if one exists
- `git diff --check`
- `git status --short`

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

The final handoff must include:

- Summary
- Files changed
- Migration details
- Backend behavior
- API/OpenAPI changes
- Frontend changes, if any
- Tests run
- Validation results
- Behavior preserved
- Risks / open questions
- Recommended next task

## Recommended Next Task After This

If this task succeeds, the likely next implementation slice is:

`QR Check-in v1`

That future task should add QR token generation and scan/claim behavior on top of the new participation verification metadata.

## Implementation Plan

### 1. Confirm current participation surfaces

- Re-read the existing employee participation flow before patching:
  - `apps/api-laravel/app/Services/MeasureParticipationService.php`
  - `apps/api-laravel/app/Models/MeasureParticipation.php`
  - `apps/api-laravel/app/Http/Resources/Employee/MeasureResource.php`
  - `apps/api-laravel/tests/Feature/EmployeeTest.php`
  - `apps/api-laravel/tests/Feature/MeasureParticipationSummaryTest.php`
  - `docs/api/openapi.yaml`
- Preserve the current endpoint, duplicate handling, employee scoping, point award behavior, and company aggregate-only summary behavior.
- Treat any QR/admin/partner values as future vocabulary only; do not add endpoints, request fields, UI flows, or alternate runtime creation paths.

### 2. Add an additive participation verification migration

- Create one new Laravel migration in `apps/api-laravel/database/migrations/`.
- Add these columns to `measure_participations`:
  - `verification_type` string defaulting to `SELF_REPORTED`
  - `verified_at` nullable timestamp
  - `verified_by_user_id` nullable unsigned bigint foreign key to `users.id` with `nullOnDelete()`
- Backfill existing rows in the migration `up()` method:
  - `verification_type = SELF_REPORTED`
  - `verified_at = participated_at`
  - `verified_by_user_id = null`
- In `down()`, drop the foreign key and columns added by this migration only.
- Do not modify historical migrations and do not run destructive migration/database commands.

### 3. Update Laravel model and factory defaults

- Update `MeasureParticipation`:
  - add `verification_type`, `verified_at`, and `verified_by_user_id` to `$fillable`
  - cast `verified_at` as `datetime`
  - keep `participated_at` as `datetime`
  - add a nullable `verifiedBy()` `BelongsTo` relation to `User` if it matches the local model style
- Update `MeasureParticipationFactory` so factory-created self-report rows include:
  - `verification_type = SELF_REPORTED`
  - `verified_at` aligned with `participated_at`
  - `verified_by_user_id = null`

### 4. Write self-report verification metadata in the service

- In `MeasureParticipationService::participate()`, capture a single timestamp for the current participation creation.
- Use that timestamp for both:
  - `participated_at`
  - `verified_at`
- Set:
  - `verification_type = SELF_REPORTED`
  - `verified_by_user_id = null`
- Leave duplicate detection, transaction boundaries, visible-measure lookup, and `PointsService::awardPoints($user, 'measure_participation')` behavior unchanged.
- Do not accept or process request body verification fields.

### 5. Update employee response contract only where already exposed

- Extend the existing employee `participation` object in `Employee\MeasureResource` with:
  - `verificationType`
  - `verifiedAt`
- Keep `verifiedBy` out of employee responses unless a later reviewed task explicitly requires it.
- Keep company measure resources and participation summaries aggregate-only; do not expose individual participation rows, participant IDs, employee names, emails, or per-user participation data to company users.
- Update `docs/api/openapi.yaml` only for the existing `EmployeeMeasure.participation` response schema:
  - add `verificationType`
  - add `verifiedAt`
  - do not add request-side verification fields
  - do not document QR/admin/partner endpoints

### 6. Update focused backend tests

- Extend existing employee participation tests in `apps/api-laravel/tests/Feature/EmployeeTest.php` to assert:
  - created self-report participation stores `verification_type = SELF_REPORTED`
  - `verified_at` is set
  - `verified_at` matches or is equivalent to `participated_at`
  - `verified_by_user_id` is null
  - employee response includes `participation.verificationType` and `participation.verifiedAt`
  - malicious request body verification fields, if sent, do not override server-derived self-report values
- Keep or strengthen existing assertions that:
  - duplicate participation still returns `409`
  - duplicate participation does not award points twice
  - inactive, cross-company, and cross-team participation behavior remains unchanged
- Extend the employee measure list participation-state test to cover verification metadata for the authenticated employee and null values for non-participating measures.
- Extend company summary privacy tests only if needed to explicitly assert the new verification fields are not exposed in company summary responses.

### 7. Frontend impact check

- Inspect Angular employee measure types after the API response change.
- If TypeScript requires the new response fields, update only the existing employee measure participation type/interface.
- Do not add QR/admin/partner UI.
- Do not restructure the employee Measures page or company Measures page.
- Only add a neutral self-report label if the current UI already naturally displays participation metadata and the change is minimal; otherwise leave UI behavior unchanged.

### 8. Non-destructive validation for the patch pass

- Run focused Laravel tests for employee participation and company participation summary.
- Run `git diff --check`.
- Run OpenAPI validation only if a local project command already exists.
- Run Angular build/tests only if frontend files are changed.
- Do not run:
  - `docker compose exec api php artisan migrate:fresh`
  - `db:wipe`
  - `docker compose down -v`
  - destructive git reset/checkout commands

### 9. Handoff expectations for the patch pass

- Report files changed, behavior changed, commands run, validation results, open questions, and intentional deviations.
- Explicitly state that the current employee self-report flow is preserved.
- Explicitly state that company users still receive only aggregate, threshold-protected participation summaries.
- Note any OpenAPI or frontend changes only if they were required by the existing response contract.

## Final Clarifications Before Implementation

- Backfill existing rows with `verified_at = participated_at`. If `participated_at` can ever be null in existing data, leave `verified_at` null for those rows.
- For the current response contract, document only `SELF_REPORTED` as the actually produced `verificationType` unless the existing OpenAPI convention clearly separates future enum vocabulary from reachable runtime behavior.
- Do not make `QR_CHECKIN`, `ADMIN_CONFIRMED`, or `PARTNER_CONFIRMED` appear reachable through the current employee participation API.
- Do not expose `verifiedBy` in employee or company responses in this slice.
