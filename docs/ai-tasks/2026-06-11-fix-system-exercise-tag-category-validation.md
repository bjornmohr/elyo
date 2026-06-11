# Task: Fix System Exercise Tag Category Validation

Date: 2026-06-11

## Context

The Platform Admin System Exercise Catalog v1 implementation was reviewed.

Only one review finding should be fixed in this task:

- `tagCategory` validation in the admin system exercise list endpoint does not match OpenAPI.

The unrelated workflow script changes are intentionally out of scope and will be handled manually.

Read and follow:

- `AGENTS.md`
- existing Laravel conventions
- existing OpenAPI contract rules
- existing test style

Do not modify legacy `../ELYO`.

## Goal

Align backend validation with the OpenAPI contract for the System Exercise Catalog list filter `tagCategory`.

## Scope

Implement only:

1. Add `tagCategory` validation to:
   - `apps/api-laravel/app/Http/Controllers/Admin/SystemExerciseController.php`

2. Add focused backend test coverage for invalid `tagCategory`.

3. Add optional explicit `ELYO_SUPPORT` access coverage only if it is very small and naturally fits the existing test setup.

Do not modify:

- `scripts/codex-plan.sh`
- `scripts/codex-task.sh`
- Claude scripts
- Angular UI
- OpenAPI unless the existing enum documentation is incomplete
- migrations
- system measurement data model
- QR/company measure behavior
- recommendation logic

## Current Problem

OpenAPI documents `tagCategory` as `SystemExerciseTagCategory` and advertises `422` validation errors for invalid filters.

But the backend currently treats `tagCategory` as any string.

Example mismatch:

- `GET /api/admin/system-exercises?tagCategory=BOGUS`
- backend currently returns `200` with no matches
- OpenAPI expects invalid filter values to be rejected with `422`

## Required Behavior

Update the list endpoint validation so `tagCategory` must be one of the allowed `SystemExerciseTag` category constants.

Use the actual constants that exist in the codebase.

Expected allowed values are likely:

- `BODY_REGION`
- `GOAL`
- `SETTING`
- `EQUIPMENT`
- `CONTRAINDICATION`
- `PERSONA_HINT`
- `HEALTH_FOCUS`

But do not hardcode blindly if model constants already exist. Prefer using those constants.

Invalid `tagCategory` must return:

- HTTP `422`
- normal Laravel validation error shape
- validation errors should include `tagCategory`

Do not change `tagKey` behavior.

Keep combined `tagCategory + tagKey` behavior unchanged:

- both filters must match the same tag row through the existing tag filter logic.

## Backend Implementation Notes

In `SystemExerciseController@index`, update request/query validation so:

- `tagCategory` is nullable or sometimes string
- if present, it must be `Rule::in([...])` using `SystemExerciseTag` category constants

Do not add a new request class unless the controller already uses one for list validation or the change stays very small.

Do not change unrelated filters.

## Tests

Add focused Laravel feature test coverage.

Required test:

- Authenticate as platform admin.
- Request:
  - `GET /api/admin/system-exercises?tagCategory=BOGUS`
- Assert:
  - response status `422`
  - validation errors include `tagCategory`

Also ensure existing valid tag filter tests still pass.

Optional, only if very small:

- assert `ELYO_SUPPORT` can access:
  - `GET /api/admin/system-exercises`
  - `GET /api/admin/system-exercise-tags`

Do not add broad unrelated role-matrix tests.

## OpenAPI

No OpenAPI change is expected if `tagCategory` is already documented as `SystemExerciseTagCategory`.

Only update OpenAPI if the enum or validation response is actually incomplete.

Do not document behavior that is not implemented.

## Validation

Run non-destructive validation only:

- focused Laravel test for `AdminSystemExerciseTest`
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
- `tagCategory` validation behavior
- tests added/updated
- commands run
- test results
- remaining risks/open questions

## Implementation Plan

1. Inspect the existing admin system exercise list validation and tag category constants.
   - Use `apps/api-laravel/app/Http/Controllers/Admin/SystemExerciseController.php` as the only production target.
   - Use `apps/api-laravel/app/Models/SystemExerciseTag.php` category constants instead of duplicating string literals blindly.
   - Compare with `SystemExerciseTagController@index`, which already validates tag categories with `Rule::in([...])`.

2. Update `SystemExerciseController@index` validation only.
   - Add `use App\Models\SystemExerciseTag;`.
   - Replace the current `tagCategory` rule `['sometimes', 'string']` with `['sometimes', Rule::in([...])]`.
   - Include these existing constants: `CATEGORY_BODY_REGION`, `CATEGORY_GOAL`, `CATEGORY_SETTING`, `CATEGORY_EQUIPMENT`, `CATEGORY_CONTRAINDICATION`, `CATEGORY_PERSONA_HINT`, and `CATEGORY_HEALTH_FOCUS`.
   - Leave `tagKey` validation unchanged as `['sometimes', 'string']`.
   - Leave the current `whereHas('tags', ...)` query logic unchanged so combined `tagCategory + tagKey` filters continue to match the same tag row.

3. Add focused feature test coverage in `apps/api-laravel/tests/Feature/AdminSystemExerciseTest.php`.
   - Add a list-filter validation test near the existing invalid filter tests.
   - Authenticate with the existing `$this->platformAdmin`.
   - Request `GET /api/admin/system-exercises?tagCategory=BOGUS`.
   - Assert HTTP `422`.
   - Assert validation errors include `tagCategory`, preferably with `assertJsonValidationErrors(['tagCategory'])` to match Laravel's validation response shape.

4. Preserve existing behavior.
   - Do not change valid `tagCategory` filtering.
   - Do not change `tagKey` filtering.
   - Do not change response resources, pagination, sorting, routes, authorization, OpenAPI, migrations, frontend code, workflow scripts, or system measurement data model.
   - Do not add a Form Request unless the surrounding controller pattern changes unexpectedly; the expected change is small enough to stay inline.

5. Optional support-role coverage only if it remains tiny.
   - If adding coverage does not require new helpers or broader role-matrix changes, add a small test proving `Role::ELYO_SUPPORT` can access `GET /api/admin/system-exercises` and/or `GET /api/admin/system-exercise-tags`.
   - Skip this optional coverage if it distracts from the validation fix or requires unrelated setup changes.

6. OpenAPI decision.
   - Do not edit `docs/api/openapi.yaml` unless inspection shows `tagCategory` is not already documented as `SystemExerciseTagCategory` with a `422` response.
   - Current inspection indicates OpenAPI already defines the enum and documents `tagCategory`, so no OpenAPI change is expected.

7. Planned validation for the implementation turn.
   - Run the focused Laravel feature test for `AdminSystemExerciseTest`.
   - Run `git diff --check`.
   - Run `git status --short`.
   - Run `git diff --cached --check` only if files are staged.
   - Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, frontend builds, or destructive git commands.

8. Handoff expectations for the implementation turn.
   - Report files changed, behavior changed, validation logic changed, tests added or skipped, commands run, test results, open questions, and intentional deviations.
   - Explicitly state that company/employee health-data boundaries are unaffected because this is platform-admin catalog filter validation only.

## Final Implementation Clarification

Keep this patch strictly limited to the `tagCategory` validation mismatch.

Do not modify workflow scripts, Angular files, OpenAPI, migrations, routes, resources, or unrelated tests.

The expected production diff should be limited to:

- `apps/api-laravel/app/Http/Controllers/Admin/SystemExerciseController.php`

The expected test diff should be limited to:

- `apps/api-laravel/tests/Feature/AdminSystemExerciseTest.php`

Only add `ELYO_SUPPORT` coverage if it is a very small addition in the same test file.
