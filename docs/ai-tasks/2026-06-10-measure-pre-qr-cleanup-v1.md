# Task: Measure Pre-QR Cleanup v1

Date: 2026-06-10

## Goal

Perform a small cleanup pass before starting QR Check-in v1.

The Measure Slice Final Review found no must-fix runtime, architecture, or privacy blockers, but identified a few should-fix items that should be handled before QR expands the measure/participation domain.

This task must remain narrow. It should clean up API contract drift, frontend UX/type consistency, and small backend constant drift. It must not implement QR behavior.

## Context

The current Measures slice includes:

- Company Measures
- Employee Measures listing
- Employee self-report participation
- Measure domain metadata
- Participation verification metadata
- Company participation summary
- OpenAPI updates
- Privacy/threshold protections

The final review verdict was:

- ready with fixes before QR v1

Required pre-QR cleanup items:

1. Remove or reconcile stale OpenAPI `/measures` and `/measures/{id}` paths that are not present in Laravel routes.
2. Hide or clarify `pointsOverride` in the company Measures UI while it remains stored-only and not used for point awards.
3. Align frontend `EmployeeMeasureParticipation` type with runtime/OpenAPI verification metadata.
4. Introduce minimal centralized backend constants for current verification/domain values before QR adds more vocabulary.

## Scope

Implement only the four cleanup items listed above.

Do not implement:

- QR token generation
- QR scan endpoints
- Admin confirmation
- Partner confirmation
- Recommendation logic
- Measure templates
- Measures Hub restructuring
- Questionnaire/check-in changes
- Point-award behavior changes
- Participation behavior changes
- New participation flows
- DB check constraints for verification enums
- Destructive database commands

## 1. OpenAPI stale route cleanup

### Problem

`docs/api/openapi.yaml` still documents top-level:

- `/measures`
- `/measures/{id}`

but Laravel routes expose implemented measure behavior under:

- `/company/measures`
- `/employee/measures`

### Required behavior

Inspect `apps/api-laravel/routes/api.php` and confirm whether top-level `/measures` and `/measures/{id}` exist.

If they do not exist:

- remove the stale top-level `/measures` and `/measures/{id}` paths from `docs/api/openapi.yaml`
- do not replace them with unimplemented behavior
- keep implemented `/company/measures` and `/employee/measures` docs intact

If they do exist under a different route group because of OpenAPI base path semantics, document that explicitly in the handoff and avoid deleting valid contract sections.

## 2. Company UI pointsOverride cleanup

### Problem

The Company Measures UI exposes `pointsOverride`, but backend point awarding still uses the existing `measure_participation` points setting and does not read `points_override`.

This can mislead company users into thinking the field affects awards.

### Required behavior

Prefer hiding/removing the editable `pointsOverride` control from the Company Measures UI until backend point behavior actually uses it.

Backend and OpenAPI may keep `pointsOverride` stored/exposed as future-facing metadata.

Do not implement point-award behavior for `pointsOverride`.

Do not remove the database/API field.

Do not change `PointsService`.

If the UI cannot remove the field cleanly without a larger refactor, change the label/help text to explicitly state that it is informational/future-facing and currently does not affect awarded points. Prefer removal.

## 3. Frontend employee participation type alignment

### Problem

`EmployeeMeasureParticipation` currently only includes:

- `isParticipating`
- `participatedAt`

but runtime/OpenAPI employee participation response also includes:

- `verificationType`
- `verifiedAt`

### Required behavior

Update the relevant Angular TypeScript interface/type to include:

- `verificationType?: 'SELF_REPORTED'`
- `verifiedAt?: string | null`

or the local project’s equivalent style.

Do not change employee UI behavior unless required by type checks.

Do not add QR/admin/partner UI.

Do not broaden frontend types to unimplemented QR/admin/partner values unless the project already models future response vocabulary in types. Current runtime only returns `SELF_REPORTED`.

## 4. Minimal backend constants

### Problem

Verification/domain values are split across request constants, model defaults, migrations, OpenAPI, Angular labels, and tests. QR will add more vocabulary, so the current self-report value should be centralized before behavior expands.

### Required behavior

Introduce minimal constants in the smallest appropriate Laravel-owned location.

Preferred:

- `MeasureParticipation::VERIFICATION_TYPE_SELF_REPORTED = 'SELF_REPORTED'`

Use this constant in:

- `MeasureParticipationFactory`
- `MeasureParticipationService`
- tests where practical

Optional, only if already natural in touched code:

- minimal constants for current measure verification requirement `SELF_REPORT`

Do not introduce a broad enum layer, policy layer, or DB check constraint in this task.

Do not add runtime QR/admin/partner values.

Do not change OpenAPI response enum unless the runtime contract changes, which it should not.

## Tests

Update or run focused tests as needed.

Expected test impact:

- Angular specs may need updates if the Company Measures form test expected `pointsOverride`.
- Laravel tests may need small updates if constants are introduced.

Do not add large unrelated tests.

Do not implement QR tests.

## Validation

Run non-destructive validation only:

- `git status --short`
- `git diff --check`
- `git diff --cached --check` if staging is used
- relevant Angular build/tests if frontend files change
- relevant Laravel tests if backend constants/tests change
- OpenAPI validation command if an existing project command exists

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

If no OpenAPI validation command exists, state that explicitly.

## Expected Handoff

Report:

- summary
- files changed
- stale OpenAPI paths removed or justified
- pointsOverride UI behavior
- frontend type alignment
- backend constants introduced
- tests/validation commands and results
- remaining risks/open questions
- recommended next task

## Recommended Next Task

If this cleanup succeeds, the next likely task is:

`QR Check-in v1`

That task should add QR token generation and scan/claim behavior using a separate token table and the existing participation verification metadata.

## Implementation Plan

### Guardrails

- Keep this as a narrow pre-QR cleanup only.
- Do not implement QR token generation, scan/claim flows, admin confirmation, partner confirmation, recommendation logic, point-award behavior changes, migrations, or DB constraints.
- Do not modify `../ELYO`.
- Preserve portal boundaries: company users see only aggregate measure participation data, employees see only their own participation state.
- Keep business logic in Laravel services/resources/requests, not Angular or n8n.
- Update OpenAPI only to remove or reconcile stale contract paths; do not document unimplemented behavior.
- Run only non-destructive validation during implementation. Do not run tests or builds during plan-only mode.

### 1. Confirm Route and Contract Drift

1. Inspect `apps/api-laravel/routes/api.php` for top-level `/measures` and `/measures/{id}` route registrations.
2. Inspect `docs/api/openapi.yaml` for the stale top-level path entries.
3. If Laravel has no matching top-level routes, remove only the stale `/measures` and `/measures/{id}` OpenAPI path blocks.
4. Keep implemented `/company/measures`, `/company/measures/{id}`, `/company/measures/{id}/participation-summary`, `/employee/measures`, and `/employee/measures/{measure}/participate` contract sections intact.
5. If the route inspection reveals valid top-level routes through grouping or base-path semantics, do not delete the path blocks; document the reason in the handoff instead.

### 2. Hide Company UI `pointsOverride`

1. Inspect `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts` and its spec for the `pointsOverride` form control, template usage, payload construction, labels, and tests.
2. Prefer removing the editable `pointsOverride` input from the company Measures UI while leaving the backend/API/database field untouched.
3. Keep backend `points_override` storage and OpenAPI metadata unless the stale route cleanup requires nearby contract edits.
4. Do not change `PointsService`, award amounts, or participation behavior.
5. Update focused Angular specs only if they assert the visible field or form payload behavior.
6. If clean removal would require a broader UI refactor, change the label/help text to make the field explicitly informational/future-facing and state that it does not affect awarded points; document this deviation in the handoff.

### 3. Align Employee Participation Frontend Type

1. Inspect `apps/web-angular/src/app/features/employee/services/employee.service.ts` for `EmployeeMeasureParticipation`.
2. Extend the type with the current runtime/OpenAPI participation metadata:
   - `verificationType?: 'SELF_REPORTED'`
   - `verifiedAt?: string | null`
3. Keep the type limited to currently implemented runtime values unless existing local conventions already model future vocabulary elsewhere.
4. Avoid employee UI behavior changes unless TypeScript or tests require a small adjustment.
5. Do not add QR/admin/partner frontend UI, labels, states, or flows.

### 4. Add Minimal Backend Constants

1. Inspect `apps/api-laravel/app/Models/MeasureParticipation.php`, `apps/api-laravel/app/Services/MeasureParticipationService.php`, `apps/api-laravel/database/factories/MeasureParticipationFactory.php`, and focused Laravel tests that assert verification metadata.
2. Add the smallest useful Laravel-owned constant, preferred location:
   - `MeasureParticipation::VERIFICATION_TYPE_SELF_REPORTED = 'SELF_REPORTED'`
3. Replace practical hardcoded backend uses of the current self-report verification value in the model default path, factory, service, and focused tests.
4. Optionally add a minimal measure verification requirement constant only if it naturally fits code already touched and avoids introducing a broader enum layer.
5. Do not add QR/admin/partner constants, PHP enums, policy layers, DB check constraints, migrations, or contract behavior changes.

### 5. Focused Validation for Patch Mode

Run only non-destructive checks after implementation:

- `git status --short`
- `git diff --check`
- `git diff --cached --check` if staging is used
- Focused Angular test/build command only if frontend files change and the local project setup supports it.
- Focused Laravel tests only if backend constants/tests change.
- Existing OpenAPI validation command only if one is found in the repo.

Do not run:

- `docker compose exec api php artisan migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

### 6. Completion and Handoff Notes

The handoff should explicitly report:

- Whether stale top-level OpenAPI `/measures` paths were removed or justified.
- How the company UI now handles `pointsOverride`.
- The final `EmployeeMeasureParticipation` type shape.
- The backend constant or constants introduced and where they are used.
- Commands run and validation results.
- Open questions, including whether no OpenAPI validation command exists.
- Intentional deviations, if any.

## Final Clarification Before Implementation

- If `pointsOverride` is removed from the Company Measures UI, also remove it from the Company Measures create/update form payload. The backend/API field may remain available, but the current UI must not silently send or imply an effective points override.
