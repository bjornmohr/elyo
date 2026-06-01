# Task: Add Employee Measure Participation API and Points

## Goal

Implement the employee-facing API flow for participating in active company health measures.

Employees must be able to participate once in an eligible measure. The backend must create a MeasureParticipation row, award points once, and extend the employee measures response with participation state.

This task must enforce tenant and team scoping server-side. The frontend must not provide user_id, company_id, or team_id.

## Context

Previous tasks added and hardened the persistence layer:

- measure_participations table
- MeasureParticipation model
- relationships
- tenant-consistent factory behavior
- persistence-focused tests

Current architecture findings:

- Measures are routed under:
  - GET /api/company/measures
  - POST /api/company/measures
  - PATCH /api/company/measures/{measure}
  - GET /api/employee/measures
- The measures table currently has:
  - id
  - company_id
  - team_id
  - title
  - category
  - description
  - status
  - suggested_at
  - started_at
  - completed_at
  - created_by
  - timestamps
- Measure statuses include:
  - SUGGESTED
  - ACTIVE
  - COMPLETED
  - DISMISSED
- Team targeting already exists through nullable measures.team_id:
  - null means company-wide/all teams
  - non-null means team-specific
- Employee measure listing already filters to:
  - authenticated employee company
  - ACTIVE measures
  - global measures or measures matching the employee users.team_id
- Points are centralized in PointsService with configurable actions in point_settings.
- There is currently no employee participation route or service.

## Scope

Implement only:

1. MeasureParticipationService or equivalent focused service
2. Employee participation endpoint
3. Employee measure response participation state
4. Points action for measure participation
5. Backend tests
6. OpenAPI updates for the new/changed API contract

Do not implement:

- Angular UI
- company participation summary
- QR codes
- attendance verification
- wallet redemption
- screening/profile/scoring logic
- medical recommendations
- n8n logic

## Backend Requirements

### 1. Add participation service

Create a dedicated service if this matches project conventions.

Suggested name:

- App\Services\MeasureParticipationService

Responsibilities:

- Load/check measure eligibility for the authenticated employee.
- Enforce same-company scope.
- Enforce measure status ACTIVE.
- Enforce team targeting:
  - company-wide measure: allowed for employees in the same company
  - team-specific measure: allowed only if authenticated user's team_id matches measure.team_id
- Prevent duplicate participation.
- Create MeasureParticipation row.
- Set:
  - measure_id from selected measure
  - user_id from authenticated user
  - company_id from authenticated user or scoped measure after validating consistency
  - team_id from authenticated user team_id or measure team_id according to existing domain convention
  - participated_at to now()
- Award points exactly once after successful first participation.
- Handle database unique constraint as final duplicate guard.

Important:

- Do not accept user_id, company_id, team_id, or participated_at from the request body.
- The request body should be empty for this MVP unless existing conventions require otherwise.

### 2. Add employee endpoint

Add:

POST /api/employee/measures/{measure}/participate

Route requirements:

- authenticated employee only
- behind existing employee route middleware / portal constraints
- must not be accessible by company/admin users unless they also satisfy employee portal access rules

Expected behavior:

Success:

- create participation
- award points
- return 201 or 200 according to existing API conventions
- response should include participation state and optionally points awarded/current total if easy and consistent

Duplicate:

- return 409
- stable error code:
  - MEASURE_ALREADY_PARTICIPATED

Wrong company / not visible:

- return 404 or 403 according to existing project conventions
- prefer not leaking existence of foreign-company measures

Wrong team:

- return 404 or 403 according to existing project conventions
- prefer not leaking existence of team-restricted measures

Inactive/completed/dismissed/suggested measure:

- return 409 or 422 according to existing project conventions
- stable error code recommended:
  - MEASURE_NOT_ACTIVE

### 3. Extend employee measures response

Extend GET /api/employee/measures so each measure includes participation state for the authenticated employee.

Suggested shape:

participation:
  isParticipating: boolean
  participatedAt: string|null

Use the existing casing convention of API resources. If current API uses snake_case, follow it. If current API uses camelCase, follow it.

Do not make the frontend infer participation state from a separate endpoint.

Implementation notes:

- Avoid N+1 queries where easy.
- Use eager loading or exists/select logic according to existing Laravel conventions.
- Only include the current employee's participation state, never other users.

### 4. Points integration

Add configurable action:

measure_participation

Update:

- PointsService default action map
- PointSettingsSeeder or equivalent seed/config file if point actions are seeded

Default point value:

- If existing point values suggest a clear scale, choose a conservative value consistent with that scale.
- If unclear, use 20 as a conservative MVP default and keep it configurable through point_settings.

Rules:

- Award points only after successful first participation.
- Do not award points on duplicate participation.
- Do not award points when measure is inactive, wrong company, or wrong team.
- Do not hardcode controller-level point mutation if PointsService already centralizes awarding.

### 5. Tests

Add or update backend feature tests covering:

1. Employee can participate in an ACTIVE company-wide measure in own company.
2. Employee can participate in an ACTIVE team-specific measure for own team.
3. Employee cannot participate in a measure from another company.
4. Employee cannot participate in a team-specific measure for another team.
5. Employee cannot participate in SUGGESTED, COMPLETED, or DISMISSED measures.
6. Duplicate participation returns 409 with MEASURE_ALREADY_PARTICIPATED.
7. Duplicate participation does not award points twice.
8. Successful first participation awards measure_participation points once.
9. Participation row derives user_id, company_id, and team_id server-side.
10. Request body user_id, company_id, team_id, participated_at are ignored if sent.
11. GET /api/employee/measures includes participation.isParticipating / participation.participatedAt for the authenticated user.
12. GET /api/employee/measures does not expose other users' participation data.
13. Company/admin users cannot use the employee participation endpoint unless they are valid employee portal users according to existing middleware rules.

Use existing authentication/test helper conventions.

### 6. OpenAPI

Update docs/api/openapi.yaml or the existing OpenAPI source if present.

Document:

- POST /api/employee/measures/{measure}/participate
- success response
- 409 duplicate response with MEASURE_ALREADY_PARTICIPATED
- inactive response with MEASURE_NOT_ACTIVE if implemented
- updated employee measure response participation object
- no request body or empty request body, depending on project convention

Do not document company summary API in this task.

## Privacy and Access Control Rules

Hard rules:

- Frontend must never decide company_id, team_id, or user_id.
- Participation creation must derive identity from the authenticated user.
- Company users must not receive individual employee participation records.
- Employee response must only include the authenticated employee's own participation state.
- Wrong-company or wrong-team measures should not leak useful existence details.
- No screening, medical, profile, or diagnosis logic belongs in this task.

## Out of Scope

Do not add or change:

- Angular UI
- company participation summary endpoint
- company aggregate reporting
- QR code logic
- attendance verification
- wallet redemption
- benefit payout
- screening
- profiling
- medical recommendations
- n8n workflows
- destructive migration/reset scripts

Do not run destructive commands:

- php artisan migrate:fresh
- php artisan db:wipe
- docker compose down -v
- any destructive database or Docker reset

## Validation

Run targeted backend tests first.

Suggested targeted commands:

- docker compose exec api php artisan test --filter=MeasureParticipation
- docker compose exec api php artisan test --filter=Employee

If targeted tests pass and time permits:

- docker compose exec api php artisan test

Inspect routes:

- docker compose exec api php artisan route:list | grep measures

If OpenAPI tooling exists, run the existing validation command. If no validation command exists, document that OpenAPI was updated manually.

Do not run frontend build in this task.

## Expected Handoff

Return:

- Files changed
- Service/controller/route changes
- Employee measure response changes
- Points action/default value added
- Seeder/config changes
- OpenAPI changes
- Tests added/updated
- Validation commands run
- Confirmation that no Angular, company summary, QR, wallet, screening, or medical logic was added
- Privacy/access-control notes
- Open questions for Task 3

## Implementation Plan

### Current Conventions Observed

- Employee routes live in `apps/api-laravel/routes/api.php` under `auth:sanctum` and `role:EMPLOYEE`.
- `GET /api/employee/measures` is implemented in `App\Http\Controllers\Employee\EmployeeController::measures`.
- Employee measure listing currently reuses `App\Http\Resources\Company\MeasureResource`, which returns camelCase response keys.
- Points are centralized in `App\Services\PointsService::DEFAULT_POINTS`, `awardPoints`, and `PointSettingsSeeder`.
- `MeasureParticipation` persistence already exists with `measure_id`, `user_id`, `company_id`, `team_id`, and `participated_at`.

### Planned Patch

1. Add `App\Services\MeasureParticipationService`.
   - Accept the authenticated `User` and a measure identifier/model from the employee endpoint.
   - Query only measures visible to that employee: same `company_id`, `ACTIVE`, and either global `team_id = null` or matching `users.team_id`.
   - Return 404 for measures outside company/team visibility to avoid leaking existence.
   - Return 409 with `MEASURE_NOT_ACTIVE` for same-company/same-team visible measures that are not `ACTIVE`, if the project conventions allow distinguishing inactive state without leaking cross-tenant data.
   - Return 409 with `MEASURE_ALREADY_PARTICIPATED` when the authenticated user already has a participation for that measure.
   - Create the participation inside a database transaction and derive all identity fields server-side.
   - Set `team_id` to the authenticated employee's `team_id` so the participation records the employee context at participation time.
   - Catch the database unique constraint as the final duplicate guard and map it to the same duplicate response.
   - Award `measure_participation` points only after the first successful insert.

2. Add an employee participation endpoint.
   - Add `POST /api/employee/measures/{measure}/participate` inside the existing `role:EMPLOYEE` route group.
   - Add a controller method, likely on `EmployeeController` unless a small dedicated employee measure controller better matches the final patch.
   - Ignore request body fields such as `user_id`, `company_id`, `team_id`, and `participated_at`.
   - Return `201` with the updated measure resource and participation state.
   - Keep company/admin access blocked by the existing employee role middleware.

3. Extend the employee measure response.
   - Prefer adding an employee-specific measure resource, for example `App\Http\Resources\Employee\MeasureResource`, to avoid adding employee-only participation fields to company measure responses.
   - Preserve existing camelCase response style.
   - Return:
     - `participation.isParticipating`
     - `participation.participatedAt`
   - Load only the authenticated employee's matching participation, using constrained eager loading or equivalent query logic to avoid N+1 queries and avoid exposing other users' participation data.

4. Add the points action.
   - Add `measure_participation => 20` to `PointsService::DEFAULT_POINTS`.
   - Let `PointSettingsSeeder` pick it up through the existing default map loop.
   - Do not add controller-level point mutation outside the participation service.

5. Add backend tests.
   - Add focused feature coverage in `EmployeeTest` or a new `MeasureParticipationTest`, following existing Sanctum helper conventions.
   - Cover successful company-wide and team-specific participation.
   - Cover wrong company, wrong team, inactive statuses, duplicate participation, duplicate points prevention, server-side identity derivation, ignored request body fields, employee measure participation state, no leakage of other users' participation state, and non-employee access denial.
   - Assert point transactions and `user_points.total` for first participation and no second award on duplicates.

6. Update OpenAPI.
   - Update `docs/api/openapi.yaml` for `POST /api/employee/measures/{measure}/participate`.
   - Document the success response, duplicate `409` with `MEASURE_ALREADY_PARTICIPATED`, inactive `409` with `MEASURE_NOT_ACTIVE` if implemented, and the new employee measure `participation` object.
   - Document that no request body is required.

### Validation Plan For Patch Mode

- Run `docker compose exec api php artisan test --filter=MeasureParticipation`.
- Run `docker compose exec api php artisan test --filter=Employee`.
- If targeted tests pass, run `docker compose exec api php artisan test`.
- Run `docker compose exec api php artisan route:list | grep measures`.
- Run OpenAPI validation only if an existing project command is present; otherwise document manual OpenAPI update.
- Do not run frontend build for this task.

### Privacy And Architecture Checks

- Keep all business logic in Laravel service/controller/resource code.
- Do not modify Angular, n8n, company reporting, QR, wallet, screening, profile scoring, or medical recommendation logic.
- Do not expose individual participation data to company routes.
- Keep employee participation state scoped to the authenticated employee only.
- Do not accept tenant or identity fields from the frontend.

### Open Questions

- Confirm whether inactive but otherwise visible employee measures should return `409 MEASURE_NOT_ACTIVE` or a non-leaking `404`; the plan prefers `409` only when the measure is same-company and team-visible.
- Confirm whether recording participation `team_id` should always use the employee's current `team_id` rather than the measure `team_id` for company-wide measures; the plan uses the employee context for audit consistency.
