# Task: Fix System Measure Template Reorder Validation

Date: 2026-06-11

## Context

The Platform Admin System Measure Template Builder v1 implementation was reviewed.

Both reviews identified a reorder API correctness issue.

The current reorder endpoint can accept partial payloads. If a submitted row is moved to a sortOrder already held by a non-submitted row, the database unique index on `(system_measure_template_id, position)` can throw a QueryException instead of returning a clean validation error.

This breaks the API contract.

Read and follow:

- `AGENTS.md`
- existing Laravel conventions
- existing OpenAPI contract rules
- existing test style

Do not modify legacy `../ELYO`.

## Goal

Make reorder validation explicit, complete, and contract-safe.

## Product/API Decision

For v1, reorder requires the complete ordered set of template exercises for the template.

Partial reorder payloads are not supported.

The request payload must include every template exercise row belonging to the template exactly once.

## Scope

Implement only:

1. Fix reorder validation in:
   - `apps/api-laravel/app/Http/Controllers/Admin/SystemMeasureTemplateExerciseController.php`
   - and/or `apps/api-laravel/app/Http/Requests/Admin/ReorderSystemMeasureTemplateExercisesRequest.php`

2. Add focused backend tests in:
   - `apps/api-laravel/tests/Feature/AdminSystemMeasureTemplateTest.php`

3. Update OpenAPI reorder documentation if needed:
   - `docs/api/openapi.yaml`

4. Optionally add missing pagination controls to the Angular template list only if this is tiny and isolated.

Do not change:

- system measurement data model
- user assignment snapshot tables
- QR/check-in behavior
- company measure behavior
- survey behavior
- points/streak logic
- recommendation logic
- workflow scripts
- unrelated Angular UI

## Required Reorder Behavior

Endpoint:

- `POST /api/admin/system-measure-templates/{systemMeasureTemplate}/exercises/reorder`

Validation requirements:

- `items` is required and must be an array.
- Each item must contain:
  - `id`
  - `sortOrder`
- Every `id` must belong to the given `systemMeasureTemplate`.
- The payload must include the complete set of template exercise IDs for that template.
- No duplicate IDs.
- No duplicate `sortOrder` values.
- `sortOrder` must be integer >= 1.
- If validation fails, return `422` with normal Laravel validation error shape.
- If an ID belongs to another template, return `404` or validation failure according to the existing convention, but avoid database exceptions.
- Do not partially update if validation fails.
- Use a transaction for the write.

Preferred implementation:

- Fetch existing template exercise IDs for the template.
- Compare them against submitted IDs.
- Reject if submitted ID set differs from existing ID set.
- Validate submitted sortOrder values are distinct.
- Only after full validation, update positions in a transaction.

## Required Tests

Add focused tests:

1. Partial reorder is rejected:
   - template has at least 3 exercises
   - request sends only one or two items
   - response is `422`
   - existing positions remain unchanged

2. Reorder with duplicate sortOrder is rejected:
   - response is `422`
   - positions remain unchanged

3. Reorder with a template exercise from another template is rejected:
   - response is `404` or `422`, matching implementation convention
   - positions remain unchanged

4. Valid complete reorder still succeeds:
   - response is successful
   - positions are updated as expected

## OpenAPI

Update reorder endpoint documentation if needed.

Document clearly:

- reorder requires the complete set of template exercise IDs for the template
- partial payloads return `422`
- duplicate IDs or duplicate sortOrder values return `422`

Do not document partial reorder as supported.

## Optional Tiny Angular Fix

If the template list page already has page/meta state but lacks visible pagination controls, add simple Previous/Next controls matching the System Exercises page.

Only do this if it is isolated and small.

Do not expand the exercise picker in this task.

## Validation

Run non-destructive validation only:

- `docker compose exec api php artisan test --filter=AdminSystemMeasureTemplateTest`
- `git diff --check`
- `git diff --cached --check` if staging is used
- `git status --short`

If Angular pagination controls are changed, also run the relevant Angular test/build command already used in this branch.

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

Report:

- summary
- files changed
- reorder validation behavior
- whether partial reorder is rejected
- OpenAPI updates
- tests added/updated
- commands run
- test/build result
- remaining risks/open questions

## Implementation Plan

1. Keep the scope backend-first and do not include the optional Angular pagination change unless the later patch review finds it already trivial and isolated.

2. Update reorder validation so the endpoint rejects incomplete payloads before any write:
   - In `SystemMeasureTemplateExerciseController::reorder`, fetch the full existing template exercise ID set for the selected `SystemMeasureTemplate`.
   - Keep the existing convention that submitted IDs outside the template return `404`.
   - After ownership is confirmed, compare the submitted ID set with the existing ID set and throw a Laravel `ValidationException` on `items` when the sets differ.
   - Preserve the existing `ReorderSystemMeasureTemplateExercisesRequest` rules for required `items`, required integer `id`, required integer `sortOrder`, distinct IDs, distinct `sortOrder`, and `sortOrder >= 1`.
   - Leave the transaction-based two-phase position update in place so valid complete reorders remain safe against the `(system_measure_template_id, position)` unique index.

3. Add focused feature coverage in `AdminSystemMeasureTemplateTest`:
   - Add a partial reorder rejection test with at least three template exercise rows, assert `422`, and assert all original positions remain unchanged.
   - Add or isolate a duplicate `sortOrder` rejection test, assert `422`, and assert positions remain unchanged.
   - Keep or extend the cross-template row test to assert the existing `404` convention and unchanged positions.
   - Keep or extend the valid complete reorder test to assert the response order and persisted positions.

4. Update `docs/api/openapi.yaml` for the reorder contract:
   - Document that `items` must contain every template exercise ID for the template exactly once.
   - Document that partial payloads return `422`.
   - Document that duplicate IDs or duplicate `sortOrder` values return `422`.
   - Keep the existing `404` documentation for child rows outside the template.

5. Validation to run in the later patch phase only:
   - `docker compose exec api php artisan test --filter=AdminSystemMeasureTemplateTest`
   - `git diff --check`
   - `git status --short`
   - Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, or unrelated build/test commands for this task.

6. Handoff checks for the later patch phase:
   - Confirm architecture is preserved: validation/request/controller behavior stays in Laravel, OpenAPI remains the contract, and no unrelated frontend/backend/model changes are included.
   - Confirm no health-data or company-reporting behavior is touched.
   - Report files changed, behavior changed, commands run, test result, open questions, and intentional deviations.
