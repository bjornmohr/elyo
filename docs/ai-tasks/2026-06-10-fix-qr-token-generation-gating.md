# Task: Fix QR Token Generation Gating

Date: 2026-06-10

## Goal

Fix the remaining QR Check-in v1 contract issue before merge.

The current implementation correctly rejects QR redemption for non-QR measures, but the company token endpoint can still generate active check-in tokens for `SELF_REPORT` measures. That creates unusable tokens and makes the API contract inconsistent.

This task must make company token generation respect the same QR requirement contract as redemption.

## Current Problem

Company endpoint:

`POST /api/company/measures/{measure}/checkin-token`

currently can generate active check-in tokens for measures with:

`verification_requirement = SELF_REPORT`

But employee redemption rejects those measures because QR redemption should only work for:

`verification_requirement = QR_CODE`

This means company users can generate links that employees cannot use.

## Required Behavior

For company token generation:

If the measure has:

- `verification_requirement = QR_CODE`
  - allow token generation/rotation if all existing scope/status checks pass

- `verification_requirement = SELF_REPORT`
  - reject token generation
  - return `409 Conflict`
  - use error code `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
  - do not create a token
  - do not revoke existing tokens
  - do not return raw token/checkinPath/checkinUrl

Do not allow or document:

- `NONE`
- `ADMIN_CONFIRMATION`
- `PARTNER_CONFIRMATION`

## Backend Requirements

### 1. Add server-side generation gate

Add a server-side check before token rotation.

Preferred location:

- `MeasureCheckinTokenService::rotate(...)`

or another central Laravel-owned service location, so the rule cannot be bypassed if another controller calls token rotation later.

The check must enforce:

- only `Measure::VERIFICATION_REQUIREMENT_QR_CODE` measures can generate QR check-in tokens

Return or throw using the existing project error style.

Use:

- HTTP status `409`
- error code `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`

Do not implement QR for `SELF_REPORT`.

### 2. Preserve existing checks

Keep all existing checks intact:

- company scope
- manager/team scope
- active measure status
- token hash storage
- rotate-on-request semantics
- revoke existing active tokens only when generating a new valid QR token
- no plaintext token storage
- no token hash exposure

Important:

If the measure is `SELF_REPORT`, do not revoke existing tokens. Reject before rotation side effects.

### 3. OpenAPI update

Update `docs/api/openapi.yaml` for:

`POST /company/measures/{measure}/checkin-token`

Document:

- `409 MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`

Do not document unimplemented admin/partner behavior.

### 4. Tests

Add focused backend tests proving:

- company token generation for `QR_CODE` measure succeeds
- company token generation for `SELF_REPORT` measure returns `409`
- failed generation for `SELF_REPORT` does not create a token
- failed generation for `SELF_REPORT` does not revoke an existing token, if such a token exists in setup
- OpenAPI/error contract uses `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`

Add or complete token lifecycle tests for:

- revoked token redemption returns `CHECKIN_TOKEN_REVOKED`
- expired token redemption returns `CHECKIN_TOKEN_EXPIRED`
- not-yet-valid token redemption returns `CHECKIN_TOKEN_NOT_YET_VALID`

Keep existing QR/self-report gating tests passing:

- QR_CODE cannot self-report
- SELF_REPORT cannot QR redeem
- QR_CODE can QR redeem
- duplicate QR redemption returns duplicate conflict
- points are awarded once

### 5. Migration nice-to-have

Inspect the new `measure_checkin_tokens` migration.

If `created_by_user_id` currently cascades delete and the migration is still part of the current unmerged QR slice, change it to null-on-delete if that matches the existing project migration style.

Reason:

- `created_by_user_id` is audit metadata
- deleting the creator user should not automatically delete active check-in links

Do not create a new migration solely for this nice-to-have unless the current migration is already committed/merged.

### 6. Frontend

If the company UI calls token generation for `SELF_REPORT` measures, prevent or handle it cleanly.

Preferred behavior:

- only show the generate/copy QR action for `QR_CODE` measures

For `SELF_REPORT` measures:

- hide the QR token generation action
- do not call the endpoint

If endpoint error still occurs, show a clear non-technical error.

Update focused Angular tests if frontend behavior changes.

### 7. Validation

Run non-destructive validation only:

- focused Laravel QR/token feature tests
- existing company/employee/summary tests if touched
- Angular focused tests or build if frontend changes
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
- token generation gating behavior
- error code/status used
- whether SELF_REPORT generation avoids token creation/revocation side effects
- token lifecycle test coverage added
- frontend behavior for SELF_REPORT measures
- migration decision for `created_by_user_id`
- tests/validation results
- remaining risks/open questions

## Implementation Plan

### Scope and constraints

- Keep the change small and limited to the existing QR Check-in v1 slice.
- Do not modify legacy `../ELYO`.
- Keep the business rule in Laravel, not Angular.
- Preserve company, manager/team, active-status, token hash, plaintext-token, and rotate-on-request behavior.
- Treat OpenAPI as the API contract if backend error behavior changes.
- Do not introduce `NONE`, `ADMIN_CONFIRMATION`, or `PARTNER_CONFIRMATION` behavior.

### 1. Backend generation gate

- Update `apps/api-laravel/app/Services/MeasureCheckinTokenService.php`.
- Add a check in `MeasureCheckinTokenService::rotate(...)` before the database transaction and before any token revocation.
- Allow rotation only when:
  - `$measure->verification_requirement === Measure::VERIFICATION_REQUIREMENT_QR_CODE`
- For any other value, throw the existing project-style conflict exception with:
  - HTTP status: `409`
  - error code/message key: `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
- Keep the existing active-status check intact.
- Order the checks so `SELF_REPORT` rejection has no side effects:
  - no new `measure_checkin_tokens` row
  - no existing active token revocation
  - no raw `token`, `checkinPath`, or `checkinUrl` response data

### 2. Backend tests

- Update focused tests in `apps/api-laravel/tests/Feature/CompanyTest.php`.
- Ensure token rotation success fixtures explicitly set:
  - `verification_requirement = QR_CODE`
- Add or update a company token generation test for `SELF_REPORT` measures that asserts:
  - response status is `409`
  - `error.code` is `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
  - no token response fields are returned under `data`
  - no token row is created
- Add or update a side-effect test where a pre-existing token exists for a `SELF_REPORT` measure and failed rotation asserts:
  - existing token remains unrevoked
  - token count for the measure does not increase
- Keep existing company scope, manager/team scope, inactive measure, plaintext-token, token-hash, and rotation tests passing.

### 3. Employee token lifecycle tests

- Review `apps/api-laravel/tests/Feature/EmployeeTest.php` for existing QR redemption coverage.
- Add or complete focused tests for:
  - revoked token redemption returns `409` with `CHECKIN_TOKEN_REVOKED`
  - expired token redemption returns `409` with `CHECKIN_TOKEN_EXPIRED`
  - not-yet-valid token redemption returns `409` with `CHECKIN_TOKEN_NOT_YET_VALID`
- Keep existing tests for:
  - `QR_CODE` cannot self-report
  - `SELF_REPORT` cannot QR redeem
  - `QR_CODE` can QR redeem
  - duplicate QR redemption returns a duplicate conflict
  - points are awarded once

### 4. OpenAPI contract

- Update `docs/api/openapi.yaml` for `POST /company/measures/{measure}/checkin-token`.
- Change the endpoint description so token generation is explicitly for active `QR_CODE` measures.
- Document the `409` response as covering both:
  - `MEASURE_NOT_ACTIVE`
  - `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
- Reuse an existing compatible error schema if appropriate, or add a small dedicated schema for the generation conflict if that keeps the contract clearer.
- Do not document unimplemented admin/partner/none verification behavior.

### 5. Migration audit

- Inspect `apps/api-laravel/database/migrations/2026_06_10_020000_create_measure_checkin_tokens_table.php`.
- If this migration is still part of the unmerged QR slice, consider changing `created_by_user_id` from cascade delete to nullable/null-on-delete because it is audit metadata.
- If the migration has already been merged/applied in shared environments, do not create a new migration for this nice-to-have as part of this bug fix.
- Record the decision in the handoff.

### 6. Frontend check

- Verify `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts` still hides or blocks QR token generation for non-`QR_CODE` measures.
- If no frontend behavior change is needed, leave frontend files untouched and record that the existing UI guard already avoids calling the endpoint for `SELF_REPORT`.
- If the endpoint error can still surface through stale data or future UI changes, add a non-technical message for `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN` and update the focused Angular spec.

### 7. Validation for patch phase

- Run only non-destructive validation after implementation:
  - focused Laravel feature tests covering company token generation and employee QR token lifecycle
  - broader company/employee feature tests if touched behavior is shared
  - Angular focused tests or `docker compose exec web npm run build` only if frontend changes
  - `git diff --check`
  - `git status --short`
- Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, destructive git commands, or unrelated cleanup.

## Final Clarification Before Implementation

- Use `409 MEASURE_DOES_NOT_ALLOW_QR_CHECKIN` when company token generation is attempted for a non-QR measure.
- Perform this check before any token revocation or token creation side effects.
- If the current `measure_checkin_tokens` migration is still part of this unmerged QR slice, change `created_by_user_id` to nullable/null-on-delete because it is audit metadata and should not delete active check-in links when the creator user is deleted.
- Do not add a new migration solely for the `created_by_user_id` nice-to-have.
