# Task: Fix QR Check-in Contract and Gating

Date: 2026-06-10

## Goal

Fix the QR Check-in v1 implementation before merge.

The current QR implementation added token generation and redemption, but the verification requirement contract is inconsistent:

- Company measure create/update still rejects `QR_CODE`
- OpenAPI only documents `SELF_REPORT`
- QR redemption currently works for existing `SELF_REPORT` measures
- Self-report participation still works for measures that should require QR

This task must make the QR requirement contract honest and enforce it end to end.

## Scope

This is a corrective patch on top of QR Check-in v1.

Implement only:

1. Allow `verificationRequirement = QR_CODE` for company measure create/update.
2. Enforce that self-report participation only works for `SELF_REPORT` measures.
3. Enforce that QR redemption only works for `QR_CODE` measures.
4. Fix OpenAPI to document `SELF_REPORT` and `QR_CODE` where they are now supported.
5. Fix employee/company UI behavior around QR-required measures.
6. Stabilize check-in URL contract.
7. Add missing focused backend/frontend tests.

Do not implement:

- Admin confirmation
- Partner confirmation
- Public anonymous check-in
- Event calendar
- Measures Hub
- Personas
- Recommendations
- Point policy changes
- `points_override` behavior
- Individual participation lists for company users
- DB check constraints unless already naturally present and low-risk

## Required Contract

### Measure verification requirement

Company measure create/update must allow exactly:

- `SELF_REPORT`
- `QR_CODE`

Do not allow:

- `NONE`
- `ADMIN_CONFIRMATION`
- `PARTNER_CONFIRMATION`

### Participation behavior

For:

`POST /api/employee/measures/{measure}/participate`

If the measure has:

- `verification_requirement = SELF_REPORT`
  - allow existing self-report behavior
  - create participation with `verification_type = SELF_REPORTED`
  - award points once

- `verification_requirement = QR_CODE`
  - reject with `409 Conflict`
  - return error code `MEASURE_REQUIRES_QR_CHECKIN`
  - do not create participation
  - do not award points

### QR redemption behavior

For:

`POST /api/employee/measure-checkins/{token}`

If the token resolves to a measure with:

- `verification_requirement = QR_CODE`
  - allow redemption if all token/scope/status checks pass
  - create participation with `verification_type = QR_CHECKIN`
  - award points once

- `verification_requirement = SELF_REPORT`
  - reject with `409 Conflict`
  - return error code `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
  - do not create participation
  - do not award points

Keep existing duplicate behavior:

- duplicate participation returns existing `409` duplicate error/code style
- duplicate redemption does not award points twice

## Backend Requirements

### 1. Company request validation

Update:

- `CreateMeasureRequest`
- `PatchMeasureRequest`

Allow `verificationRequirement` values:

- `SELF_REPORT`
- `QR_CODE`

Keep `measureOrigin` server-derived/read-only.

Do not add admin/partner/none values.

### 2. Measure model/constants

If constants exist, add/use:

- `Measure::VERIFICATION_REQUIREMENT_SELF_REPORT = 'SELF_REPORT'`
- `Measure::VERIFICATION_REQUIREMENT_QR_CODE = 'QR_CODE'`

Use constants in validation/tests where practical, but ensure at least one contract-level test still asserts the literal public wire values to avoid hiding public contract drift.

### 3. Participation service gating

Update `MeasureParticipationService`.

Self-report path:

- before creation, check measure verification requirement
- if not `SELF_REPORT`, reject with `409 MEASURE_REQUIRES_QR_CHECKIN`

QR path:

- before creation, check measure verification requirement
- if not `QR_CODE`, reject with `409 MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`

Keep shared creation logic and points behavior unchanged.

Do not accept verification fields from request bodies.

### 4. QR token service / controller behavior

Keep token security:

- raw token returned only after rotate/generation
- token hash stored only
- no plaintext token storage
- token revocation/expiry/not-yet-valid checks preserved

Check-in URL contract:

- Do not return an absolute `checkinUrl` built with API `url($path)` unless a reliable configured frontend base URL already exists.
- Prefer returning:
  - `token`
  - `checkinPath`
  - token metadata
- Let the Angular company UI compose an absolute URL from `window.location.origin + checkinPath`.
- If keeping `checkinUrl`, it must use a reliable frontend base URL configuration and tests/docs must reflect it.
- Do not return `token_hash`.

### 5. Employee UI behavior

Update Employee Measures UI:

- For `SELF_REPORT` measures:
  - keep the existing `Teilnehmen` action

- For `QR_CODE` measures:
  - hide/disable the normal self-report `Teilnehmen` action
  - display neutral text such as:
    - `QR-Check-in erforderlich`
    - `Teilnahme vor Ort per QR-Code`
  - do not imply self-report completion is possible

Do not build a full Measures Hub.

### 6. Company UI behavior

Update Company Measures UI:

- allow selecting `QR_CODE` as verification requirement
- keep only:
  - `SELF_REPORT`
  - `QR_CODE`

Do not expose:

- `ADMIN_CONFIRMATION`
- `PARTNER_CONFIRMATION`
- `NONE`

For QR token generation:

- use `checkinPath` and compose absolute link in frontend
- do not rely on API-origin `checkinUrl` if removed
- do not expose token hash

## OpenAPI Requirements

Update `docs/api/openapi.yaml`.

### Company measure create/update

Request-side `verificationRequirement` enum must include exactly:

- `SELF_REPORT`
- `QR_CODE`

Do not document:

- `NONE`
- `ADMIN_CONFIRMATION`
- `PARTNER_CONFIRMATION`

### Employee participation response

`participation.verificationType` enum must include:

- `SELF_REPORTED`
- `QR_CHECKIN`

### Existing self-report endpoint

Document:

- `409 MEASURE_REQUIRES_QR_CHECKIN`

### QR redemption endpoint

Document:

- success behavior
- duplicate `409`
- invalid/missing/revoked/expired/not-yet-valid token errors
- wrong company/team scope errors
- inactive measure error
- `409 MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`

### Company token endpoint

Document token rotation response.

Prefer response fields:

- `measureId`
- `token`
- `checkinPath`
- `validFrom`
- `validUntil`
- `revokedAt`

Do not document `tokenHash`.

Only document `checkinUrl` if it is produced from a reliable frontend base URL.

## Required Backend Tests

Add/update tests for:

- company create accepts `verificationRequirement = QR_CODE`
- company patch accepts `verificationRequirement = QR_CODE`
- company create/patch still rejects `NONE`, `ADMIN_CONFIRMATION`, `PARTNER_CONFIRMATION`
- QR_CODE measure cannot self-report
- SELF_REPORT measure can self-report
- SELF_REPORT measure cannot redeem through QR
- QR_CODE measure can redeem through QR
- QR redemption creates `verification_type = QR_CHECKIN`
- QR redemption sets `verified_at`
- QR redemption keeps `verified_by_user_id = null`
- points are awarded once for QR redemption
- duplicate QR redemption returns 409 and does not award points twice
- revoked token redemption is rejected
- expired token redemption is rejected
- not-yet-valid token redemption is rejected
- company participation summary remains aggregate-only with QR participation present
- company summary does not expose `verificationType`, `verifiedAt`, `verifiedBy`, user IDs, emails, or individual rows

Keep existing self-report tests passing.

## Required Frontend Tests

If frontend changed, add/update tests for:

- company form exposes only `SELF_REPORT` and `QR_CODE`
- company check-in link generation/copy behavior uses `checkinPath`/frontend-composed URL if applicable
- employee Measures UI hides or disables self-report for `QR_CODE` measures
- employee check-in route handles success/conflict/error states if route exists in this patch

## Validation

Run non-destructive validation only:

- relevant Laravel feature tests for company measures, employee measures, QR check-in, participation, and summary
- `docker compose exec api php artisan route:list` if routes changed
- `docker compose exec web npm run build` if frontend changed
- Angular focused tests if available/practical
- `git diff --check`
- `git status --short`
- OpenAPI validation command if one exists

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

Final handoff must include:

- summary
- files changed
- verification requirement contract fix
- self-report gating behavior
- QR redemption gating behavior
- check-in URL contract decision
- OpenAPI updates
- frontend behavior
- tests run
- validation results
- remaining risks/open questions

## Implementation Plan

### Constraints for the patch phase

- Keep the patch limited to the QR Check-in contract correction described in this task.
- Do not modify `../ELYO`.
- Do not introduce new services, new measure types, admin confirmation, partner confirmation, public anonymous check-in, Measures Hub behavior, point policy changes, or company-visible individual participation data.
- Keep business rules in Laravel services/controllers/requests/resources; keep Angular limited to API calls, routing, and presentation behavior.
- Update OpenAPI whenever request enums, response fields, or error behavior change.
- Preserve token security: plaintext token returned only on generation/rotation, hash stored only, no `tokenHash` in API responses or OpenAPI.
- Preserve health-data boundaries: company users may see aggregate summaries only, never individual participants or verification metadata rows.

### 1. Inspect current QR and measure implementation

Files to inspect first:

- `apps/api-laravel/app/Models/Measure.php`
- `apps/api-laravel/app/Models/MeasureParticipation.php`
- `apps/api-laravel/app/Http/Requests/Company/CreateMeasureRequest.php`
- `apps/api-laravel/app/Http/Requests/Company/PatchMeasureRequest.php`
- `apps/api-laravel/app/Http/Controllers/Company/MeasureController.php`
- `apps/api-laravel/app/Http/Controllers/Employee/*Checkin*`
- `apps/api-laravel/app/Services/MeasureParticipationService.php`
- QR token service/model/resource/controller files
- existing measure and QR feature tests
- `docs/api/openapi.yaml`
- Angular company/employee measure and check-in files

Confirm the current public field names before editing, especially whether the implementation uses `verificationRequirement`, `verification_requirement`, `verificationType`, `verification_type`, `checkinUrl`, or `checkinPath` at each layer.

### 2. Backend contract constants and validation

- Add or reuse measure verification requirement constants for the public wire values:
  - `SELF_REPORT`
  - `QR_CODE`
- Update `CreateMeasureRequest` and `PatchMeasureRequest` so company create/update accepts exactly `SELF_REPORT` and `QR_CODE`.
- Keep `NONE`, `ADMIN_CONFIRMATION`, and `PARTNER_CONFIRMATION` rejected.
- Keep `measureOrigin` and other server-derived fields read-only.
- Use constants in implementation where practical, while keeping at least one test asserting literal public values.

### 3. Backend self-report and QR gating

- Update `MeasureParticipationService` so self-report participation first verifies the measure requirement is `SELF_REPORT`.
- If a user self-reports a `QR_CODE` measure:
  - return `409 Conflict`
  - return code `MEASURE_REQUIRES_QR_CHECKIN`
  - create no participation
  - award no points
- Update the QR redemption path so it verifies the resolved measure requirement is `QR_CODE` before creating participation.
- If a user redeems QR for a `SELF_REPORT` measure:
  - return `409 Conflict`
  - return code `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
  - create no participation
  - award no points
- Preserve all existing scope, token, status, duplicate, and points-once behavior.
- Ensure QR-created participation uses `verification_type = QR_CHECKIN`, sets `verified_at`, and keeps `verified_by_user_id = null`.

### 4. Check-in URL response contract

- Prefer returning `token`, `checkinPath`, and token metadata from company token generation/rotation responses.
- Remove API-origin absolute `checkinUrl` if it is currently built from API `url($path)` without a reliable frontend base URL.
- If any `checkinUrl` field remains, make it explicitly frontend-origin based through reliable configuration and reflect that in tests/OpenAPI.
- Ensure neither resources nor OpenAPI expose `tokenHash`.

### 5. Backend tests

Add or update focused Laravel feature tests covering:

- company create accepts `verificationRequirement = QR_CODE`
- company patch accepts `verificationRequirement = QR_CODE`
- company create/patch reject `NONE`, `ADMIN_CONFIRMATION`, and `PARTNER_CONFIRMATION`
- `SELF_REPORT` measure can self-report
- `QR_CODE` measure cannot self-report and returns `MEASURE_REQUIRES_QR_CHECKIN`
- `QR_CODE` measure can redeem through QR and stores `QR_CHECKIN` verification metadata
- `SELF_REPORT` measure cannot redeem through QR and returns `MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
- QR redemption awards points once
- duplicate QR redemption returns existing duplicate `409` behavior and does not award points twice
- revoked, expired, and not-yet-valid tokens remain rejected
- company participation summary remains aggregate-only with QR participations present and does not expose user IDs, emails, individual rows, `verificationType`, `verifiedAt`, or `verifiedBy`

### 6. OpenAPI update

Update `docs/api/openapi.yaml` to match implemented behavior:

- company create/update request enum for `verificationRequirement` includes exactly `SELF_REPORT` and `QR_CODE`
- participation `verificationType` enum includes `SELF_REPORTED` and `QR_CHECKIN`
- self-report endpoint documents `409 MEASURE_REQUIRES_QR_CHECKIN`
- QR redemption endpoint documents success, duplicate `409`, token validity errors, scope/status errors, inactive measure errors, and `409 MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`
- company token endpoint documents rotation response with `measureId`, `token`, `checkinPath`, `validFrom`, `validUntil`, and `revokedAt`
- do not document `tokenHash`
- document `checkinUrl` only if the backend still returns a frontend-origin URL by explicit configuration

### 7. Frontend behavior

- Update company measure UI/types/forms so users can select only:
  - `SELF_REPORT`
  - `QR_CODE`
- Do not expose `NONE`, `ADMIN_CONFIRMATION`, or `PARTNER_CONFIRMATION`.
- Update company QR link handling to use `checkinPath` and compose the copyable absolute URL from `window.location.origin + checkinPath` if the backend no longer returns `checkinUrl`.
- Update employee measures UI so:
  - `SELF_REPORT` measures keep the existing `Teilnehmen` action
  - `QR_CODE` measures hide or disable normal self-report participation
  - QR-required measures show neutral text such as `QR-Check-in erforderlich` or `Teilnahme vor Ort per QR-Code`
- Keep direct API calls in Angular services, not components.

### 8. Frontend tests

If frontend files change, add or update focused Angular tests for:

- company form exposes only `SELF_REPORT` and `QR_CODE`
- company check-in link generation/copy behavior uses `checkinPath` and frontend-composed absolute URLs when applicable
- employee measures UI hides or disables self-report action for `QR_CODE`
- employee check-in route preserves success, conflict, and error display behavior if touched

### 9. Validation for patch phase

Run only non-destructive validation after implementation:

- relevant Laravel feature tests for company measures, employee measures, QR check-in, participation, and summary
- `docker compose exec api php artisan route:list` only if routes changed
- `docker compose exec web npm run build` if frontend changed
- focused Angular tests if available and practical
- OpenAPI validation command if one exists
- `git diff --check`
- `git status --short`

Do not run:

- `docker compose exec api php artisan migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

### 10. Review and handoff

- Review the diff for architecture boundaries, role/company/team scoping, OpenAPI alignment, privacy constraints, and test coverage.
- Confirm no company response exposes individual employee health data or individual participation details.
- Confirm no unrelated cleanup or broad refactor is included.
- Create the required handoff with summary, files changed, contract behavior, gating behavior, check-in URL decision, OpenAPI updates, frontend behavior, tests run, validation results, open questions, and intentional deviations.

### Open questions for patch phase

- Unknown: whether a reliable frontend base URL configuration already exists for backend-composed `checkinUrl`; inspect before choosing removal versus configured URL generation.
- Unknown: whether focused Angular test infrastructure already covers the measure pages; if not, prefer small component/service tests that fit the current setup.
- Unknown: whether OpenAPI validation has a project-specific command; search scripts/package configuration before deciding.

## Execution Clarification

The final clarifications override any earlier ambiguity in this task.

For this patch, implement the complete QR requirement contract:

- Company create/patch supports exactly `SELF_REPORT` and `QR_CODE`.
- `SELF_REPORT` measures can only be completed through the existing self-report participation endpoint.
- `QR_CODE` measures can only be completed through the QR redemption endpoint.
- Self-reporting a `QR_CODE` measure must return `409 MEASURE_REQUIRES_QR_CHECKIN`.
- QR-redeeming a `SELF_REPORT` measure must return `409 MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`.
- QR redemption must create participation with `verification_type = QR_CHECKIN`.
- Existing self-report behavior must stay unchanged for `SELF_REPORT` measures.
- OpenAPI, backend tests, frontend options, and employee UI behavior must reflect this contract.
