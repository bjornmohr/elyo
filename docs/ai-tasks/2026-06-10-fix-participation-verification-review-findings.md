# Task: Fix Participation Verification Review Findings

Date: 2026-06-10

## Goal

Fix the remaining review findings for Measure Participation Verification v1 without expanding scope.

This is a narrow corrective patch. Do not implement QR check-in, admin confirmation, partner confirmation, recommendation logic, Measures Hub restructuring, questionnaire/check-in changes, point-award changes, or participation behavior changes.

## Findings to Address

### 1. Required untracked files must be included

The previous diff contains required untracked files:

- `apps/api-laravel/database/migrations/2026_06_10_010000_add_verification_fields_to_measure_participations_table.php`
- `docs/ai-tasks/2026-06-10-measure-participation-verification-v1.md`

Ensure these files are included in the final commit/handoff. Do not rely only on `git diff --name-only`.

### 2. Verification type contract consistency

Current runtime only produces:

- `SELF_REPORTED`

OpenAPI currently documents only `SELF_REPORTED` for the existing employee participation response.

Do not make QR/admin/partner values reachable through current API behavior.

Do not add QR/admin/partner endpoints.

Do not add request-side verification fields.

If adding a DB check constraint would create immediate churn for the next planned QR task, do not add it in this corrective patch. Instead, centralize the current produced value through a constant if this fits project style, and document the remaining DB constraint question in the handoff.

### 3. Privacy regression assertion

Add a focused regression assertion if practical:

Company participation summary responses must not expose individual participation verification metadata, including:

- `verificationType`
- `verifiedAt`
- `verifiedBy`
- `user_id`
- `email`
- individual participation rows

Keep company summary aggregate-only and threshold-protected.

## Constraints

- Do not modify old migrations.
- Do not run destructive database commands.
- Do not change point-award behavior.
- Do not change duplicate participation behavior.
- Do not expose identifiable participation data to company users.
- Do not alter Angular UI unless a test requires it.

## Validation

Run non-destructive validation only:

- relevant Laravel feature tests for Employee participation and MeasureParticipationSummary
- `git diff --check`
- `git status --short`

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`

## Expected Handoff

Report:

- files changed
- confirmation that the migration and task file are tracked/included
- whether a DB check constraint was intentionally deferred
- privacy regression coverage
- validation commands and results

## Implementation Plan

1. Confirm repository state before patching
   - Run `git status --short` in the implementation turn to identify tracked, modified, and untracked files.
   - Specifically confirm whether these required files are present and included in the final diff or staging set:
     - `apps/api-laravel/database/migrations/2026_06_10_010000_add_verification_fields_to_measure_participations_table.php`
     - `docs/ai-tasks/2026-06-10-measure-participation-verification-v1.md`
   - Do not modify or rewrite either file unless inspection shows a narrow issue directly tied to this task.

2. Inspect the current verification implementation
   - Read the employee measure participation resource/service path and the participation migration/model changes from the previous slice.
   - Verify that current runtime behavior only produces `SELF_REPORTED`.
   - If the value is duplicated as a string literal and project style supports it, centralize it in the smallest appropriate Laravel-owned location, such as the model or service layer.
   - Do not add QR, admin, partner, system-confirmation behavior, request fields, routes, policies, or UI.

3. Preserve API contract scope
   - Check `docs/api/openapi.yaml` only to confirm it already documents the current employee participation response as `SELF_REPORTED` only.
   - Do not broaden the OpenAPI enum to future verification modes.
   - Only update OpenAPI if the implementation patch actually changes the current API response contract; the expected corrective patch should avoid such a contract change.

4. Add focused privacy regression coverage
   - Update the existing Laravel feature test coverage for company measure participation summaries if practical.
   - Assert that company summary responses remain aggregate-only and do not contain individual verification or identity fields, including `verificationType`, `verifiedAt`, `verifiedBy`, `user_id`, `email`, or individual participation rows.
   - Keep the assertion focused on the existing summary endpoint and current threshold-protected aggregate response shape.

5. Avoid deferred verification schema churn
   - Do not add a database check constraint for verification type in this corrective patch if it would create immediate churn for the planned QR/admin/partner work.
   - Record the DB constraint question as intentionally deferred in the final handoff.
   - Do not modify old migrations.

6. Run only allowed validation in the implementation turn
   - Run relevant Laravel feature tests for employee measure participation and measure participation summary.
   - Run `git diff --check`.
   - Run `git status --short`.
   - Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, frontend builds, full Docker destructive commands, or unrelated validation.

7. Final handoff requirements
   - Report files changed.
   - Confirm whether the required migration and previous task file are tracked/included.
   - State whether DB check constraint work was intentionally deferred.
   - Summarize privacy regression coverage.
   - Include validation commands and results.
   - List open questions and intentional deviations, if any.

## Final Clarification Before Implementation

- If centralizing `SELF_REPORTED`, keep it minimal. A model constant or service-local constant is enough.
- Do not introduce a broad enum layer, policy layer, request validation layer, or database check constraint in this corrective patch.
- Do not change OpenAPI unless the response contract actually changes.
