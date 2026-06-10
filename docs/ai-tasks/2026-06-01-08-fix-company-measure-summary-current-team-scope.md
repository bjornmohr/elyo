# Task: Fix Company Measure Summary Current-Team Scoping

## Goal

Fix the company measure participation summary so eligibleCount and participantCount use the same current-user/team scope.

The current implementation mixes:
- eligibleCount based on current users.team_id
- participantCount based on historical measure_participations.team_id

This can produce incorrect participation rates over 100% and can allow managers to see aggregate participation from employees who are no longer in their managed team.

## Decision

Use current team membership for company participation summaries.

The summary represents the current company/manager visibility scope, not a historical team-at-participation cohort.

Therefore:

- eligibleCount must be based on current eligible users.
- participantCount must count participations joined through current eligible users.
- measure_participations.team_id remains historical/audit context.
- measure_participations.team_id must not be used as the primary scoping filter for company summary participantCount.
- A historical cohort model is out of scope for this MVP task.

## Context

Task 3 added:

- GET /api/company/measures/{measure}/participation-summary
- MeasureParticipationSummaryService
- MeasureParticipationSummaryResource
- OpenAPI documentation
- backend feature tests

Review finding:

- apps/api-laravel/app/Services/MeasureParticipationSummaryService.php currently scopes eligible employees by current users.team_id.
- participantCount scopes by historical measure_participations.team_id.
- If an employee changes teams after participating, numerator and denominator can diverge.
- This can produce rates over 100%.
- This can leak aggregate participation into a manager scope where the employee no longer belongs.

## Scope

Fix only the company participation summary backend behavior, tests, and OpenAPI issues found in review.

Relevant files:

- apps/api-laravel/app/Services/MeasureParticipationSummaryService.php
- apps/api-laravel/tests/Feature/MeasureParticipationSummaryTest.php
- docs/api/openapi.yaml

Only touch controller/resource/route if strictly necessary, but no behavior expansion is expected there.

## Requirements

### 1. Fix participantCount scoping

Update participantCount logic so it counts only participations for users who are currently eligible in the same scope used by eligibleCount.

Expected direction:

- Build or reuse the eligible users query.
- Count distinct measure_participations.user_id joined/scoped against current eligible users.
- Keep measure_id and company_id constraints.
- Do not rely on measure_participations.team_id for current manager/team scoping.
- Avoid loading individual users into memory if a query-based approach is straightforward.

The count must ignore:

- participations for other measures
- participations for other companies
- participations by users no longer in the current eligible/manager scope
- participations by users outside the measure target scope

### 2. Add regression test for team transfer

Add a test proving the bug is fixed.

Test intent:

- Create company with team A and team B.
- Create manager scoped to team A if existing manager/team-layer behavior requires it.
- Create an active team-specific measure for team A, or company-wide measure depending on the current manager visibility model.
- Create an employee originally in team A.
- Employee participates.
- Move employee to team B.
- Fetch summary as team A manager or under a scope where only current team A users should count.
- Assert participantCount does not include the moved employee.
- Assert participationRate cannot exceed 100%.
- Assert suppressed/non-suppressed behavior is handled according to threshold setup in the test.

Use enough eligible employees to satisfy anonymity threshold if the test asserts raw counts.

### 3. Add team-layer-disabled coverage

Add a test for team-specific measure when team_layer_enabled is false.

Expected behavior should follow existing company measure behavior:

- When team layer is disabled, team-specific measure summary access should be blocked or return the existing project-consistent failure.
- Do not broaden access.
- Do not return unsafe aggregate data.

### 4. Manager-without-managed-team coverage

Add coverage for a manager without a managed team if current behavior is known.

Review note:

- Existing list returns no measures.
- Summary endpoint currently returns 403.

Either:

- Keep 403 and test it explicitly as current endpoint behavior.
- Or align to list behavior if there is already a stronger project convention.

Do not broaden manager access.

### 5. OpenAPI fixes

Fix OpenAPI issues from review:

1. `teamBreakdown` currently has `nullable: true` but no type.

Since implementation always returns null in this task, document it with an explicit type.

Acceptable options:

- type: array
  nullable: true
  items:
    type: object
    properties:
      teamId: { type: integer }
      teamName: { type: string }
      isAboveThreshold: { type: boolean }
      eligibleCount: { type: integer, nullable: true }
      participantCount: { type: integer, nullable: true }
      participationRate: { type: number, nullable: true }
      suppressionReason: { type: string, nullable: true }

or another clearly typed nullable object/array schema consistent with the API style.

2. Add 401 response to the new authenticated endpoint for consistency with other authenticated endpoints.

Do not document individual participant fields.

## Privacy Rules

Hard rules:

- Do not return user IDs.
- Do not return names.
- Do not return emails.
- Do not return raw participation rows.
- Do not return per-user timestamps.
- Do not expose historical team participation into a manager's current restricted scope.
- Below threshold, do not return raw eligibleCount, participantCount, or participationRate.

## Out of Scope

Do not change:

- Angular
- Admin Points UI
- employee participation API behavior
- points awarding logic
- MeasureParticipation migration
- measure participation persistence
- QR code logic
- wallet logic
- screening/profile/scoring logic
- medical recommendation logic
- n8n workflows

Do not add teamBreakdown implementation in this task.

Do not run destructive commands:

- php artisan migrate:fresh
- php artisan db:wipe
- docker compose down -v
- any destructive database or Docker reset

## Validation

Run targeted backend tests:

- docker compose exec api php artisan test --filter=MeasureParticipationSummary

If relevant, also run:

- docker compose exec api php artisan test --filter=MeasureParticipation
- docker compose exec api php artisan test --filter=Company

If targeted tests pass and time permits:

- docker compose exec api php artisan test

Inspect route if needed:

- docker compose exec api php artisan route:list | grep measures

If OpenAPI validation tooling exists, run it. Otherwise document manual OpenAPI inspection.

Do not run Angular build because Angular must not be changed.

## Expected Handoff

Return:

- Files changed
- Explanation of current-team scoping fix
- Tests added/updated
- OpenAPI fixes
- Validation commands run
- Confirmation that participantCount and eligibleCount now use the same current-user/team scope
- Confirmation that moved employees are not counted in old manager/team scopes
- Confirmation that teamBreakdown is still not implemented and remains null
- Confirmation that no Angular, Admin Points UI, employee API, points, QR, wallet, screening, profile, medical, or n8n logic changed

## Implementation Plan

1. Inspect the existing participation summary path before editing:
   - Confirm the route still resolves to the existing company participation summary controller/service/resource flow.
   - Confirm `MeasureParticipationSummaryService` is the only production code that needs behavior changes.
   - Confirm the existing response resource still always returns `teamBreakdown: null`, so no team breakdown implementation is introduced.

2. Fix participant counting in `apps/api-laravel/app/Services/MeasureParticipationSummaryService.php`:
   - Reuse the same current eligible employee scope used for `eligibleCount` when computing `participantCount`.
   - Change the participant count helper to accept or build an eligible users query for the current company and scoped current `users.team_id` values.
   - Count distinct `measure_participations.user_id` for the requested measure and company only when the participation belongs to a user inside that current eligible scope.
   - Stop using historical `measure_participations.team_id` as the primary manager/team scope filter.
   - Keep existing active employee, employee-role, measure, company, manager-team, team-layer, threshold, suppression, and response-shape behavior intact.

3. Add focused backend regression tests in `apps/api-laravel/tests/Feature/MeasureParticipationSummaryTest.php`:
   - Add a team-transfer regression where an employee participates while in team A, is moved to team B, and a team A manager or team-scoped summary no longer counts that employee in `participantCount`.
   - Use enough current eligible employees and participations to make the summary unsuppressed where raw count assertions are needed.
   - Assert `participantCount` and `participationRate` reflect the current eligible scope and cannot exceed 100%.
   - Add coverage for a team-specific measure when `team_layer_enabled` is false, preserving the existing blocked/failure behavior and not broadening access.
   - Add explicit coverage for a manager without a managed team, preserving the current 403 summary endpoint behavior unless inspection reveals a stronger established convention.
   - Preserve privacy assertions that the response does not expose user IDs, names, emails, raw participation rows, or per-user timestamps.

4. Update the OpenAPI contract in `docs/api/openapi.yaml`:
   - Give `MeasureParticipationSummary.teamBreakdown` an explicit nullable type while documenting that it remains null in this backend slice.
   - Add the missing `401` response to `GET /company/measures/{id}/participation-summary` for authenticated endpoint consistency.
   - Do not add individual participant fields or document any raw participation data.

5. Validate with backend-only checks after implementation:
   - Run `docker compose exec api php artisan test --filter=MeasureParticipationSummary`.
   - If failures or touched behavior suggest wider impact, run `docker compose exec api php artisan test --filter=MeasureParticipation` and/or `docker compose exec api php artisan test --filter=Company`.
   - If time permits after targeted tests pass, run `docker compose exec api php artisan test`.
   - Inspect routes with `docker compose exec api php artisan route:list | grep measures` only if route behavior is touched or uncertain.
   - Run OpenAPI validation if tooling exists; otherwise document manual inspection of the changed schema and response entries.
   - Do not run Angular build, destructive database commands, or destructive Docker commands for this task.

6. Final review and handoff:
   - Review the diff to confirm only the service, feature test, and OpenAPI files changed during implementation.
   - Confirm architecture boundaries remain intact: Laravel owns the business logic, Angular/n8n are untouched, and OpenAPI remains the contract.
   - Confirm portal boundaries and current company/team/user scoping are preserved.
   - Confirm company/manager users still receive only aggregate data above the anonymity threshold.
   - Report files changed, behavior changed, tests added or updated, OpenAPI fixes, validation commands and results, open questions, and intentional deviations.
