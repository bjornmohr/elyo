# Task: Fix Measure Domain Fields Review Findings

Date: 2026-06-10

## Goal

Fix the review findings from the Measure Domain Fields v1 implementation pass.

This must remain a narrow corrective patch. Do not expand scope into QR flows, admin confirmation, partner confirmation, recommendations, templates, Measures Hub restructuring, questionnaire/check-in changes, or participation verification fields.

## Review Verdict

The previous implementation is mostly aligned, but has one must-fix API behavior issue and several should-fix consistency issues.

## Constraints

- Keep this patch additive/backward-compatible where possible.
- Do not modify `../ELYO`.
- Do not change `measure_participations` behavior.
- Do not change point-award behavior.
- Do not expose individual employee health data, raw free text health answers, or identifiable participation data to company users.
- Do not run destructive database commands such as `migrate:fresh`, `db:wipe`, or `docker compose down -v`.
- Do not implement QR token generation, QR scan endpoints, admin confirmation, partner confirmation, recommendation logic, templates, Measures Hub restructuring, questionnaire/check-in changes, or participation verification fields.

## Must-Fix 1: Unsupported verification requirements

### Problem

The API/UI currently accept and display future verification requirements:

- `QR_CODE`
- `ADMIN_CONFIRMATION`
- `PARTNER_CONFIRMATION`

But `/employee/measures/{measure}/participate` still creates the existing normal self-report participation.

This creates a misleading contract: a company can create a measure that says QR/admin/partner confirmation is required, while employees can still complete it through the existing self-report button.

### Decision

Do not enforce QR/admin/partner flows in this task.

Instead, restrict currently accepted/maintainable verification requirements to the only implemented behavior:

- `SELF_REPORT`

Keep the database column and resource field future-ready, but do not let Company create/patch endpoints or frontend forms expose unsupported values yet.

### Required Changes

Backend:

- Update `CreateMeasureRequest` so `verificationRequirement` only accepts `SELF_REPORT`.
- Update `PatchMeasureRequest` so `verificationRequirement` only accepts `SELF_REPORT`.
- Keep default `verification_requirement = SELF_REPORT`.
- Do not change employee participation behavior.
- Do not add participation verification fields.

Frontend:

- Update Company Measures UI verification requirement options to only expose `SELF_REPORT`.
- Employee UI may display the value neutrally, but must not imply QR/admin/partner flows exist.
- Remove or hide unsupported options:
  - `QR_CODE`
  - `ADMIN_CONFIRMATION`
  - `PARTNER_CONFIRMATION`
  - `NONE`, unless the existing participate flow is explicitly changed, which is out of scope.

OpenAPI:

- Update create/update request schemas so `verificationRequirement` only documents `SELF_REPORT`.
- Keep response schema consistent with currently supported behavior.
- Do not document unimplemented QR/admin/partner verification behavior.

Tests:

- Add or update backend tests proving unsupported verification requirements are rejected for create and patch.
- Add or update tests proving self-report participation still works when `verificationRequirement = SELF_REPORT`.
- Duplicate participation must still return `409`.

## Should-Fix 1: Patch validation can create invalid date ranges

### Problem

`endsAt >= startsAt` is only checked when both fields are present in the same request.

A patch can still create invalid persisted ranges, for example:

- existing `starts_at = 2026-06-20`
- patch `endsAt = 2026-06-19`

or:

- existing `ends_at = 2026-06-20`
- patch `startsAt = 2026-06-21`

### Required Changes

Fix partial update validation so the effective final date range is valid.

Implementation can be in request validation, controller/service validation, or a small helper, depending on existing repository conventions.

The final effective values must be checked:

- `effectiveStartsAt = payload.startsAt if present else existing measure.starts_at`
- `effectiveEndsAt = payload.endsAt if present else existing measure.ends_at`
- if both are present, `effectiveEndsAt >= effectiveStartsAt`

Return the existing validation error style used by the project.

Tests:

- Add patch test: updating only `endsAt` before existing `starts_at` is rejected.
- Add patch test: updating only `startsAt` after existing `ends_at` is rejected.
- Add patch test: valid partial date update succeeds.

## Should-Fix 2: measureOrigin is client-forgeable

### Problem

Company create/patch currently accepts `ELYO_TEMPLATE`, even though this slice does not implement template cloning/provenance.

`measureOrigin` should distinguish trusted source/provenance, not arbitrary frontend input.

### Decision

For Company Measure create/update endpoints in this slice:

- Always derive `measure_origin = COMPANY_CREATED` server-side.
- Do not accept `measureOrigin` / `measure_origin` from client payloads.
- Keep `measureOrigin` exposed as read-only in responses.

### Required Changes

Backend:

- Remove `measureOrigin` from create request validation.
- Remove `measureOrigin` from patch request validation.
- Remove `measureOrigin` from request-to-column mapping.
- Ensure store always creates company measures as `COMPANY_CREATED`.
- Patch must not change `measure_origin`.

OpenAPI:

- Remove `measureOrigin` from company create/update request schemas.
- Keep `measureOrigin` in response schemas as read-only/current-state field.
- Response enum should not imply clients can create `ELYO_TEMPLATE` through Company endpoints.

Frontend:

- Remove any editable `measureOrigin` input if one exists.
- Do not send `measureOrigin` in create/update payloads.

Tests:

- Add or update backend tests proving client-provided `measureOrigin` is ignored or rejected.
- Prefer rejection if the existing validation style rejects unknown fields; otherwise assert persisted value remains `COMPANY_CREATED`.
- Add/update patch test proving `measureOrigin` cannot be changed to `ELYO_TEMPLATE`.

## Should-Fix 3: Remove .DS_Store files

### Problem

Untracked macOS `.DS_Store` files are present:

- `.DS_Store`
- `apps/.DS_Store`
- `docs/.DS_Store`
- `docs/ai-context/.DS_Store`
- `docs/ai-tasks/.DS_Store`

### Required Changes

- Remove these untracked files from the working tree.
- Do not commit `.DS_Store`.
- If the repository already has `.gitignore`, ensure `.DS_Store` is ignored.
- If `.gitignore` does not already ignore `.DS_Store`, add it only if consistent with repo conventions.

## Validation

Run non-destructive validation only.

Required if available:

- Relevant Laravel feature tests for Company Measures, Employee Measures, participation, and participation summary.
- Angular tests/build relevant to changed measure UI.
- OpenAPI validation if a project command exists.
- `git diff --check`.
- `git status --short` to confirm `.DS_Store` files are gone/untracked cleanup is handled.

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- any destructive reset command

## Expected Handoff

The final handoff must include:

- Summary
- Files changed
- Must-fix resolution
- Should-fix resolutions
- Tests added/updated
- Validation commands run
- Remaining risks/open questions

## Implementation Plan

### Scope and Safety

- Treat this as a narrow corrective patch on top of the existing Measure Domain Fields v1 changes.
- Do not touch `../ELYO`.
- Do not change migrations, participation persistence schema, point-award behavior, QR/admin/partner flows, recommendation logic, templates, questionnaire/check-in behavior, or participation verification fields.
- Preserve current company/team/manager scoping and the existing aggregate-only company reporting boundary.
- Remove only the listed untracked `.DS_Store` files during implementation, and update `.gitignore` only if `.DS_Store` is not already covered by repository conventions.

### Backend Plan

1. Update company measure request validation.
   - In `apps/api-laravel/app/Http/Requests/Company/CreateMeasureRequest.php`, restrict `VERIFICATION_REQUIREMENTS` to `['SELF_REPORT']`.
   - Remove `measureOrigin` from create validation so company clients cannot provide source/provenance.
   - Keep create defaults aligned with current behavior: omitted `verificationRequirement` persists as `SELF_REPORT`, and omitted origin persists as or is set to `COMPANY_CREATED`.
   - In `apps/api-laravel/app/Http/Requests/Company/PatchMeasureRequest.php`, remove `measureOrigin` from patch validation.
   - Keep `verificationRequirement` patch validation, but only allow `SELF_REPORT`.

2. Fix effective partial date range validation.
   - Implement validation where it can see the existing measure, most likely in `PatchMeasureRequest::withValidator()` after route/model lookup or in `MeasureController::update()` before `$measure->update()`.
   - Compute:
     - `effectiveStartsAt = startsAt from payload if present, otherwise $measure->starts_at`
     - `effectiveEndsAt = endsAt from payload if present, otherwise $measure->ends_at`
   - If both effective values are non-null and `effectiveEndsAt < effectiveStartsAt`, return the project’s existing 422 validation error style.
   - Keep create validation for same-payload `startsAt`/`endsAt` ranges.

3. Derive measure origin server-side.
   - In `apps/api-laravel/app/Http/Controllers/Company/MeasureController.php`, remove `measureOrigin => measure_origin` from the request-to-column mapping.
   - Ensure `store()` always creates company measures with `measure_origin = COMPANY_CREATED` explicitly or via the existing model/database default.
   - Ensure `update()` never changes `measure_origin`, even if a client sends `measureOrigin`.

4. Preserve participation behavior.
   - Do not modify `MeasureParticipationService` except if a test setup needs `verification_requirement = SELF_REPORT`.
   - Confirm existing employee self-report participation still creates one participation, awards the existing points action once, and duplicate participation still returns `409`.

### Frontend Plan

1. Update Company Measures form behavior in `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts`.
   - Remove unsupported verification options from the select:
     - `NONE`
     - `QR_CODE`
     - `ADMIN_CONFIRMATION`
     - `PARTNER_CONFIRMATION`
   - Keep only `SELF_REPORT` exposed and defaulted.
   - Ensure create payloads do not include `measureOrigin`; if there is no origin form control, no change is needed.

2. Keep employee display neutral.
   - In `apps/web-angular/src/app/features/employee/pages/measures/measures.component.ts`, ensure the verification label for `SELF_REPORT` does not imply QR/admin/partner confirmation exists.
   - Do not add unsupported employee actions or flows.

### OpenAPI Plan

1. Update `docs/api/openapi.yaml`.
   - In company create/update request schemas, remove `measureOrigin`.
   - Restrict request-side `verificationRequirement` enum to `SELF_REPORT`.
   - Keep response schemas exposing `measureOrigin` as read-only/current-state data.
   - Keep response-side `verificationRequirement` consistent with supported behavior and avoid documenting unimplemented QR/admin/partner behavior for company create/update.

### Test Plan

1. Update backend feature tests in `apps/api-laravel/tests/Feature/CompanyTest.php`.
   - Adjust existing domain-field tests so create payloads no longer send `measureOrigin`.
   - Replace any accepted `verificationRequirement = NONE` patch/create expectation with `SELF_REPORT`.
   - Add or update tests proving create rejects unsupported verification requirements such as `QR_CODE`, `ADMIN_CONFIRMATION`, `PARTNER_CONFIRMATION`, and `NONE`.
   - Add or update tests proving patch rejects unsupported verification requirements.
   - Add or update tests proving client-provided `measureOrigin = ELYO_TEMPLATE` is rejected if unknown-field validation applies; otherwise, assert the persisted value remains `COMPANY_CREATED`.
   - Add or update patch test proving `measureOrigin` cannot be changed to `ELYO_TEMPLATE`.
   - Add tests for partial date updates:
     - patching only `endsAt` before existing `starts_at` returns `422`
     - patching only `startsAt` after existing `ends_at` returns `422`
     - a valid partial date update succeeds

2. Update backend participation tests in `apps/api-laravel/tests/Feature/EmployeeTest.php`.
   - Ensure at least one successful participation test uses or asserts `verification_requirement = SELF_REPORT`.
   - Keep duplicate participation `409` coverage intact.
   - Do not add tests for QR/admin/partner verification flows.

3. Update Angular tests only if existing specs assert the removed company verification options or payload shape.
   - Prefer a focused expectation that the company create payload contains `verificationRequirement: 'SELF_REPORT'` and does not contain `measureOrigin`, if the current spec structure supports it.

### Validation Plan

- Run only non-destructive validation after implementation:
  - targeted Laravel feature tests for company measures and employee measure participation, if available through `docker compose exec api php artisan test --filter=...`
  - `docker compose exec web npm run build`
  - OpenAPI validation command if one exists in the repository scripts or package metadata
  - `git diff --check`
  - `git status --short`
- Do not run:
  - `docker compose exec api php artisan migrate:fresh`
  - `db:wipe`
  - `docker compose down -v`
  - destructive git reset/checkout commands

### Handoff Plan

- Final handoff should report:
  - files changed
  - behavior changed
  - commands run and results
  - test/build result
  - open questions
  - intentional deviations, especially any validation command skipped because no project command exists or because the user explicitly forbids it in the patch prompt

## Final Clarifications Before Implementation

- `verificationRequirement = SELF_REPORT` is the only supported create/patch value in this slice.
- `NONE` must be rejected for create/patch because the current employee participation flow still records a self-report participation.
- `QR_CODE`, `ADMIN_CONFIRMATION`, and `PARTNER_CONFIRMATION` must be rejected for create/patch until the corresponding flows exist.
- `measureOrigin` must be treated as read-only for company create/patch endpoints.
- Prefer returning `422` when `measureOrigin` is provided by a client. If the existing request validation style does not reject unknown fields, then explicitly ignore it and assert that the persisted value remains `COMPANY_CREATED`.
- Do not silently introduce template semantics. `ELYO_TEMPLATE` remains response/domain vocabulary for future use only, not a company-create capability.

## Final Clarifications Before Implementation

- `verificationRequirement = SELF_REPORT` is the only supported create/patch value in this slice.
- `NONE` must be rejected for create/patch because the current employee participation flow still records a self-report participation.
- `QR_CODE`, `ADMIN_CONFIRMATION`, and `PARTNER_CONFIRMATION` must be rejected for create/patch until the corresponding flows exist.
- `measureOrigin` must be treated as read-only for company create/patch endpoints.
- Prefer returning `422` when `measureOrigin` is provided by a client. If the existing request validation style does not reject unknown fields, then explicitly ignore it and assert that the persisted value remains `COMPANY_CREATED`.
- Do not silently introduce template semantics. `ELYO_TEMPLATE` remains response/domain vocabulary for future use only, not a company-create capability.
