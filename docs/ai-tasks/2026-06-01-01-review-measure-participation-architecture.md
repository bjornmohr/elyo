
# Task: Review Measure Participation Architecture

## Goal

Review the current codebase and define the clean implementation plan for the Measure Participation MVP before writing feature code.

The goal is to avoid building a large mixed diff and to split the feature into safe subtasks.

## Context

We want to implement employee participation in company health measures.

The final medical screening concept is not ready yet, so this feature must not depend on screening, scoring, profiling, or medically reviewed recommendations.

Target future flow:

- Company creates a measure.

- Employee sees available measures.

- Employee can participate once.

- Participation may award points.

- Company can see aggregated participation counts only.

- Company must not see individual employee participation records.

## Review Scope

Inspect the current implementation of:

- apps/api-laravel/routes/api.php

- Measure model

- Measure migration/table structure

- Company MeasureController

- Employee measure endpoint/controller

- Measure resources/DTOs if present

- PointsService and point settings/seeding

- User/company/team relationships

- Existing privacy threshold / aggregation patterns

- Existing backend tests

- Angular company measures page

- Angular employee measures page

- Existing API service patterns in Angular

## Questions to Answer

1. What Measure-related backend structures already exist?

2. What fields does the measures table currently have?

3. Is there already a status concept for measures?

4. Is there already a team_id or target-group concept for measures?

5. Where should the MeasureParticipation model/service/controller logic live?

6. Should participation be implemented directly in an existing controller or through a dedicated service?

7. How should duplicate participation be handled?

8. How should measure participation points fit into the existing PointsService?

9. How should company aggregation reuse existing privacy threshold patterns?

10. What exact API response shape should be used for employee measure participation state?

11. What frontend files will likely need changes?

12. What tests already exist and should be extended?

## Constraints

- Do not implement feature code in this task.

- Do not change database schema in this task.

- Do not change frontend code in this task.

- Do not implement screening/profile/scoring logic.

- Do not implement QR codes.

- Do not implement wallet redemption.

- Do not expose individual employee participation data to company users.

- Do not trust frontend-provided company_id, team_id, or user_id.

- A user belongs to exactly one company.

- users.company_id is required.

- Use the existing Codex workflow.

- Keep the review focused and actionable.

- No destructive DB reset commands such as migrate:fresh, db:wipe, docker compose down -v, or similar unless explicitly approved.

## Expected Output

Create or update a handoff document under:

docs/ai-tasks/

Suggested output file:

docs/ai-tasks/2026-06-01-01-review-measure-participation-architecture-handoff.md

The handoff must include:

- Current architecture summary

- Relevant files inspected

- Existing Measure data model

- Existing API routes

- Existing frontend pages/services

- Recommended implementation split

- Proposed database changes for the next task

- Proposed endpoint changes for the next task

- Test strategy

- Privacy/access-control notes

- Risks/open questions

- Clear recommendation for Task 1

## Recommended Subtask Split to Validate

Codex should validate or refine this proposed split:

1. Task 1: MeasureParticipation Persistence

   - Add persistence layer only.

   - Add table/model/relationships/factory/tests if appropriate.

   - No employee action endpoint yet.

   - No Angular changes.

2. Task 2: Employee Participation API + Points

   - Add employee participation endpoint.

   - Add duplicate protection.

   - Add points reason.

   - Extend employee measures response with participation state.

   - Add backend tests.

3. Task 3: Company Participation Summary API

   - Add aggregated company summary endpoint.

   - Apply company scoping.

   - Apply privacy threshold for team breakdowns if applicable.

   - Never expose individual employee participation records.

4. Task 4: Angular Measure Participation UI

   - Add employee participation button/state.

   - Add company aggregate count display.

   - Keep frontend from sending company_id/team_id/user_id.

## Handoff Format

Use this exact structure:

# Handoff: Measure Participation Architecture Review

## Summary

## Files Inspected

## Current Measure Architecture

## Current API Routes

## Current Frontend Architecture

## Current Points Architecture

## Privacy and Access-Control Findings

## Recommended Implementation Split

## Proposed Task 1 Scope

## Proposed Data Model

## Proposed API Shape

## Test Strategy

## Risks and Open Questions

## Final Recommendation

## Implementation Plan

This planning pass must not implement production code, tests, migrations, OpenAPI, frontend changes, or a separate handoff file. The next implementation work should be split into small tasks after this plan is reviewed.

### Current Findings to Anchor the Plan

- Measures are routed under `GET/POST/PATCH /api/company/measures` and `GET /api/employee/measures`.
- The `measures` table currently has: `id`, `company_id`, `team_id`, `title`, `category`, `description`, `status`, `suggested_at`, `started_at`, `completed_at`, `created_by`, timestamps.
- Measure status already exists as strings with current transitions around `SUGGESTED`, `ACTIVE`, `COMPLETED`, and `DISMISSED`.
- Team targeting already exists through nullable `measures.team_id`; `null` means company-wide/all teams.
- Employee measure listing already filters to the authenticated employee company, active measures, and either global measures or the employee's `users.team_id`.
- Company measure listing already scopes by authenticated company and applies manager/team-layer restrictions.
- There is no existing `MeasureParticipation` model/table/service/controller route.
- Points are centralized in `PointsService`, with configurable actions in `point_settings`; a new participation action should be added there rather than hardcoded in a controller.
- Existing aggregation/privacy patterns are in `AnonymityService` and `SurveyResultsAggregationService`; company-facing measure participation should reuse their threshold/suppression style and never return participant identities.
- Angular currently has a company measures page, an employee measures page, and employee API service methods using `ApiClient`; no direct fetch pattern is needed.

### Recommended Subtask Split

1. Task 1: Measure Participation Persistence
   - Add `measure_participations` table with `id`, `measure_id`, `user_id`, `company_id`, optional `team_id`, `participated_at`, timestamps.
   - Add foreign keys to `measures`, `users`, `companies`, and optionally `teams`.
   - Add a unique index on `(measure_id, user_id)` for duplicate protection.
   - Add indexes for company aggregate queries, likely `(company_id, measure_id)` and `(company_id, team_id, measure_id)`.
   - Add `MeasureParticipation` model, factory, and relationships from `Measure`, `User`, `Company`, and optionally `Team`.
   - Add persistence-focused backend tests for uniqueness, relationships, company/team denormalization, and migration shape.
   - Do not add employee action endpoints or Angular changes in this task.

2. Task 2: Employee Participation API and Points
   - Add a dedicated `MeasureParticipationService` in Laravel for eligibility checks, duplicate handling, participation creation, and points awarding.
   - Add a focused employee controller action, preferably outside the large `EmployeeController` if local conventions allow a dedicated `MeasureParticipationController`.
   - Add endpoint `POST /api/employee/measures/{measure}/participate`.
   - Derive `company_id`, `team_id`, and `user_id` only from the authenticated user and selected measure; do not accept them from the request body.
   - Eligibility must require same company, `status = ACTIVE`, and target scope of global measure or employee team match.
   - Duplicate participation should return `409` with structured error code such as `MEASURE_ALREADY_PARTICIPATED`; the database unique index remains the final guard.
   - Add `measure_participation` to `PointsService::DEFAULT_POINTS` and `PointSettingsSeeder`; award points only after successful first participation.
   - Extend employee measure response with stable participation state, for example `participation: { isParticipating: boolean, participatedAt: string|null }`.
   - Update OpenAPI for the new route, error responses, points action if documented, and changed employee measure response.
   - Add backend feature tests for success, duplicate conflict, wrong company, wrong team, inactive/completed measure, and points awarded once.

3. Task 3: Company Participation Summary API
   - Add company-facing aggregate endpoint, for example `GET /api/company/measures/{measure}/participation-summary`.
   - Scope measure lookup to authenticated user's company and existing manager/team-layer visibility rules.
   - Return only aggregate fields such as eligible count, participant count, participation rate, threshold state, and suppression reason.
   - Do not return user IDs, names, emails, raw participation rows, or timestamps per employee.
   - Apply anonymity threshold for team-level or narrow slices using the existing threshold style; when suppressed, return `null` for sensitive counts/rates and a clear suppression code.
   - Add backend tests for company admin visibility, manager team scoping, foreign-company denial, suppressed small groups, and no individual fields in JSON.
   - Update OpenAPI for the endpoint and response schema.

4. Task 4: Angular Measure Participation UI
   - Add typed measure/participation interfaces in the employee feature service or a shared API model location consistent with current frontend patterns.
   - Add `participateInMeasure(measureId)` in `EmployeeService`.
   - Update employee measures page to show participation button/state and handle loading, success, duplicate, and forbidden/inactive states.
   - Update company measures page to display aggregate participation counts only after the company summary API exists.
   - Keep Angular from sending `company_id`, `team_id`, or `user_id`.
   - Run Angular build in this later task, not in the planning task.

### Proposed API Shape

- Employee list item should extend the existing measure resource with:
  - `participation.isParticipating`
  - `participation.participatedAt`
- Successful participation response should return:
  - `data.measure`
  - `data.participation`
  - optional `data.pointsAwarded`
- Duplicate participation should return:
  - `error.code = MEASURE_ALREADY_PARTICIPATED`
  - `error.message`
- Company summary should return only aggregate data:
  - `measureId`
  - `isAboveThreshold`
  - `eligibleCount`
  - `participantCount`
  - `participationRate`
  - `suppressionReason`

### Validation Plan for Future Tasks

- Backend persistence/API tasks: run `docker compose exec api php artisan test`, targeted feature tests first if needed, and `docker compose exec api php artisan route:list`.
- OpenAPI/API behavior tasks: verify `docs/api/openapi.yaml` matches all added routes, response fields, and error codes.
- Frontend task: run `docker compose exec web npm run build`.
- Infrastructure changes are not expected; if any Docker config changes occur, run `docker compose config`.
- Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, or other destructive database/Docker commands without explicit approval.

### Open Questions Before Patch Mode

- Confirm the exact point value for `measure_participation`; if no product decision exists, start with a conservative default and make it configurable through `point_settings`.
- Confirm whether participation means a lightweight opt-in only, or later completion/attendance verification; this MVP plan assumes opt-in participation only.
- Confirm whether company-wide participation totals may be shown below the anonymity threshold; safest default is to apply threshold suppression to any company-facing participation metric when the eligible or participant population is too small.
- Confirm whether manager-only users should see summaries for global measures, managed-team measures, or both; align with existing measure listing behavior before implementing Task 3.
