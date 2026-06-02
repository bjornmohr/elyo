# Task: Add Company Measure Participation Summary API

## Goal

Add a company-facing aggregate API for measure participation summaries.

Company users must be able to see participation metrics for company health measures, but must never receive individual employee participation records.

This task must implement only the backend company summary API, privacy/threshold handling, OpenAPI documentation, and backend tests.

Do not implement Angular UI in this task.

## Context

Previous tasks added:

- measure_participations persistence
- tenant-consistent MeasureParticipation factory
- employee measure participation API
- employee participation state in employee measure responses
- measure_participation points awarding
- OpenAPI updates for employee measure participation

Current known deferred issue:

- Admin Points UI does not yet send measure_participation.
- This is intentionally deferred to Task 4 because Angular is out of scope for Task 3.
- Do not fix Admin Points UI in this task.
- Task 4 must explicitly include Admin Points UI support for measure_participation.

Existing architecture findings:

- Company measure routes exist under /api/company/measures.
- Company measure listing is scoped by authenticated company.
- Manager/team-layer restrictions already exist for company measure listing.
- Measure targeting uses nullable measures.team_id:
  - null means company-wide/all teams
  - non-null means team-specific
- Participation records store:
  - measure_id
  - user_id
  - company_id
  - team_id
  - participated_at
- Participation team_id records the authenticated employee's team_id at participation time.
- Existing privacy/aggregation patterns are in:
  - AnonymityService
  - SurveyResultsAggregationService
- Company-facing measure participation must reuse the existing threshold/suppression style where possible.

## Scope

Implement:

1. Company-facing participation summary endpoint
2. Aggregate-only summary service/query logic
3. Privacy threshold/suppression handling
4. Manager/team visibility scoping
5. Backend tests
6. OpenAPI updates

Do not implement:

- Angular UI
- Admin Points UI fix
- QR code
- attendance verification
- wallet redemption
- screening/profile/scoring logic
- medical recommendations
- n8n logic

## Endpoint

Add:

GET /api/company/measures/{measure}/participation-summary

Use the existing company route group and middleware.

The endpoint must be available only to authenticated company portal users according to existing route conventions.

## Access Control Requirements

### Company scoping

The measure lookup must be scoped to the authenticated user's company.

A company user must not access summaries for measures from another company.

Foreign-company measures should return 404 or the existing non-leaking project convention.

### Manager/team-layer scoping

Respect the same manager/team-layer visibility rules already used by company measure listing.

Before implementing, inspect the existing company measure listing behavior and mirror it.

Expected behavior:

- Company owner/admin can access company-wide and company-owned visible measure summaries.
- Company manager visibility must align with existing measure listing rules.
- If managers are restricted to their team layer, they must not see summaries outside their allowed team scope.

Do not invent a broader manager visibility model in this task.

## Privacy Requirements

Hard rule:

- Never return individual participation rows.
- Never return user IDs.
- Never return employee names.
- Never return employee emails.
- Never return per-employee participated_at timestamps.
- Never eager-load or serialize participants into company-facing resources.

Return aggregate-only data.

Apply anonymity threshold/suppression for company-facing participation metrics.

Use existing threshold style from AnonymityService / SurveyResultsAggregationService where possible.

If a metric is suppressed, return:

- the metric as null where appropriate
- a clear threshold/suppression flag or reason code consistent with existing API style

## Suggested Response Shape

Use existing API response casing conventions. If company resources use camelCase, use camelCase.

Suggested fields:

measureId
isAboveThreshold
eligibleCount
participantCount
participationRate
suppressionReason
teamBreakdown

### Notes

- eligibleCount should represent the number of employees eligible for the measure based on company/team targeting and manager visibility scope.
- participantCount should count participation records for the measure within the allowed company/team scope.
- participationRate should be participantCount / eligibleCount if not suppressed and eligibleCount > 0.
- suppressionReason should be null if not suppressed.
- teamBreakdown should only be returned when safe and consistent with existing threshold rules.
- If teamBreakdown is suppressed, do not return raw team-level counts.

Suggested suppression code if no existing constant exists:

ANONYMITY_THRESHOLD_NOT_MET

Follow existing project naming if different.

## Team Breakdown

If implemented in this task, team breakdown must obey threshold rules.

For each team-level slice:

- suppress counts/rates if eligible or participant population is below threshold according to existing anonymity policy
- never return individual participants
- never return tiny group data that allows inference

If team breakdown makes the task too large, implement only top-level aggregate summary and document team breakdown as a follow-up. However, do not expose unsafe team-level counts.

## Service / Query Design

Prefer a dedicated service if consistent with existing architecture.

Suggested name:

App\Services\MeasureParticipationSummaryService

Responsibilities:

- resolve measure visibility for company user
- compute eligible employees
- compute participation counts
- apply threshold rules
- return aggregate DTO/array for controller/resource
- keep controller thin

Do not place privacy-sensitive aggregation directly in Angular or API resource serialization.

## Tests

Add backend feature tests covering:

1. Company admin can fetch participation summary for own company measure.
2. Company admin cannot fetch summary for foreign-company measure.
3. Manager visibility follows existing company measure listing rules.
4. Company-wide measure eligibleCount counts eligible company employees according to existing active/user conventions.
5. Team-specific measure eligibleCount counts only eligible team employees.
6. participantCount counts only participations for the scoped measure/company.
7. participationRate is calculated correctly when not suppressed.
8. Summary suppresses metrics when anonymity threshold is not met.
9. Team breakdown is suppressed for small teams if team breakdown is implemented.
10. Response does not contain user IDs, names, emails, raw participation rows, or per-user timestamps.
11. Employee users cannot access the company summary endpoint unless they also satisfy existing company route access rules.
12. Wrong-company data is not leaked through error details.

Use existing test helpers/factories and keep data tenant-consistent.

## OpenAPI

Update docs/api/openapi.yaml or the existing OpenAPI source.

Document:

- GET /api/company/measures/{measure}/participation-summary
- success response schema
- suppression fields
- 401/403/404 behavior according to project conventions
- no individual participant fields

Do not document Angular behavior in this task.

## Out of Scope

Do not change:

- Angular
- Admin Points UI
- employee participation service behavior unless a test reveals a direct bug blocking summary correctness
- employee measure resources
- points awarding logic
- MeasureParticipation migration
- QR code logic
- wallet logic
- screening/profile/scoring logic
- medical recommendation logic
- n8n workflows

Do not run destructive commands:

- php artisan migrate:fresh
- php artisan db:wipe
- docker compose down -v
- any destructive database or Docker reset

## Validation

Run targeted backend tests first:

- docker compose exec api php artisan test --filter=MeasureParticipation
- docker compose exec api php artisan test --filter=Company

If targeted tests pass and time permits:

- docker compose exec api php artisan test

Inspect routes:

- docker compose exec api php artisan route:list | grep measures

If OpenAPI validation tooling exists, run it. If no validation command exists, document manual OpenAPI update.

Do not run Angular build in this task because Angular must not be changed.

## Expected Handoff

Return:

- Files changed
- Route/controller/service/resource changes
- Summary response shape
- Threshold/suppression behavior
- Manager/team scoping behavior
- OpenAPI changes
- Tests added/updated
- Validation commands run
- Confirmation that no Angular, Admin Points UI, QR, wallet, screening, profile, or medical logic was added
- Confirmation that no individual participation data is exposed to company routes
- Open questions for Task 4

## Reminder for Task 4

Task 4 must include Angular UI work for:

1. Employee measure participation button/state.
2. Company measure participation summary display.
3. Admin Points UI support for measure_participation.

The Admin Points UI currently does not send measure_participation and must be fixed in Task 4.

## Implementation Plan

1. Inspect current company measure and privacy patterns before patching.
   - Confirm the company route group and middleware in `apps/api-laravel/routes/api.php`.
   - Reuse the visibility behavior from `App\Http\Controllers\Company\MeasureController::index` and `update`:
     - scope measure lookup by `company_id`
     - reject manager measure workflows when the team layer is disabled
     - when team layer is disabled, only allow company-wide measures
     - for manager-only users, allow only the manager's team-scoped measure where current measure routes require that, and do not broaden access
   - Reuse anonymity conventions from `AnonymityService` and `SurveyResultsAggregationService`, including `ANONYMITY_THRESHOLD_NOT_MET`.

2. Add the company summary route and thin controller entry point.
   - Add `GET /api/company/measures/{measure}/participation-summary` to the existing company measures route group.
   - Add a method on `App\Http\Controllers\Company\MeasureController`, or introduce a small dedicated company controller only if the existing controller becomes noisy.
   - Keep route-model binding out of the public contract unless it can still enforce scoped lookup safely; otherwise query by id and `company_id`, returning the existing non-leaking 404 behavior for foreign-company measures.

3. Implement aggregate-only domain logic in Laravel.
   - Add `App\Services\MeasureParticipationSummaryService`.
   - Inputs: authenticated company user, measure id or resolved `Measure`, company anonymity threshold.
   - Responsibilities:
     - resolve/validate the visible measure under company and manager/team-layer rules
     - determine the allowed team scope for the requesting user and measure
     - count eligible active employee users with the `EMPLOYEE` role in the allowed company/team scope
     - count distinct participation users for the measure, company, and allowed team scope
     - calculate `participationRate` as a percentage rounded consistently with existing survey participation rates
     - apply threshold suppression before returning company-facing metrics
   - Do not eager-load users or serialize `MeasureParticipation` rows.

4. Define the response resource/shape.
   - Add a company resource such as `App\Http\Resources\Company\MeasureParticipationSummaryResource` if useful for stable casing.
   - Return camelCase fields:
     - `measureId`
     - `isAboveThreshold`
     - `eligibleCount`
     - `participantCount`
     - `participationRate`
     - `suppressionReason`
     - `teamBreakdown`
   - When below threshold, return aggregate metrics as `null` where disclosure would be unsafe and set `suppressionReason` to `ANONYMITY_THRESHOLD_NOT_MET`.
   - Do not include participant arrays, user identifiers, names, emails, or per-user timestamps.
   - Defer `teamBreakdown` to a follow-up unless it can be implemented with bucket-level suppression without exposing tiny groups; if deferred, return `teamBreakdown: null`.

5. Add focused backend feature tests.
   - Prefer adding a new `MeasureParticipationSummaryTest` under `apps/api-laravel/tests/Feature` to keep this slice reviewable.
   - Cover:
     - company admin can fetch own-company measure summary
     - foreign-company measure returns the existing non-leaking failure
     - manager/team-layer visibility mirrors existing company measure behavior
     - company-wide eligible count includes active employee users in the company
     - team-specific eligible count includes only active employee users in the target team
     - participant count ignores other measures, other companies, and out-of-scope teams
     - participation rate is correct above threshold
     - below-threshold response suppresses metrics
     - employee-only users cannot access the company endpoint
     - response JSON does not contain `user_id`, `userId`, `name`, `email`, `participated_at`, `participatedAt`, or raw participation rows
   - Use existing factories and tenant-consistent setup; avoid migration changes unless a direct blocking schema bug is discovered.

6. Update OpenAPI only for this backend route.
   - Add `/company/measures/{measure}/participation-summary` to `docs/api/openapi.yaml`.
   - Document auth, path parameter, success schema, suppression fields, and 401/403/404 responses according to existing project conventions.
   - Make the schema aggregate-only and exclude individual participant fields.

7. Validate in patch mode after implementation.
   - Run targeted backend tests first:
     - `docker compose exec api php artisan test --filter=MeasureParticipation`
     - `docker compose exec api php artisan test --filter=Company`
   - Run route inspection:
     - `docker compose exec api php artisan route:list`
   - If targeted tests pass and time permits, run:
     - `docker compose exec api php artisan test`
   - Run OpenAPI validation only if an existing project command is available; otherwise document manual OpenAPI inspection.
   - Do not run Angular build for this backend-only task.
   - Do not run destructive database or Docker commands.

8. Final review checklist for the implementation.
   - Laravel owns all aggregation and privacy logic.
   - Company, manager, team, and user scoping are preserved.
   - Company users receive only aggregate data above threshold.
   - Suppressed responses do not leak counts that identify tiny groups.
   - OpenAPI matches the implemented route and response.
   - No Angular, Admin Points UI, QR, wallet, screening, profile, medical recommendation, n8n, migration, or unrelated cleanup changes are included.
