# Task: Plan Employee Portal Eligibility and Aggregate Exclusion for Company Roles

Date: 2026-06-11

## Context

Manual QA and product review found a role/eligibility issue:

A company admin currently cannot submit their own daily check-in. The request fails with `403`.

Planning also clarified the intended product model:

Company-side users such as `COMPANY_ADMIN`, `COMPANY_MANAGER`, and `COMPANY_OWNER` should be treated as full employee-portal users for their own self-service data and participant actions.

That means they should be able to use employee self-service features for themselves, including check-ins and participation flows, if `User::canUseEmployeePortal()` allows them.

However, users with company/team reporting access must not influence the company/team aggregates or threshold calculations for reports they can access.

This planning task must prepare a safe implementation plan before Claude performs the patch.

Do not modify production code in this task.

## Core Product Decision

Company roles and employee participation are not mutually exclusive.

A user can be:

- a company admin/manager/owner with management permissions
- and also a participant using the employee portal for their own check-ins, measures, profile, history, and other self-service functions

For v1:

Eligible employee-portal users include:

- `EMPLOYEE`
- `COMPANY_MANAGER`
- `COMPANY_ADMIN`
- `COMPANY_OWNER` if present in the codebase

Only when:

- `User::canUseEmployeePortal()` returns true
- the user belongs to a valid company
- internal ELYO platform users remain excluded according to the existing internal-company rule
- the employee route is truly self-service and only acts on `request->user()`

## Privacy Product Decision

Users with company/team reporting access may participate personally, but their personal inputs must be excluded from company/team aggregate reports and threshold calculations.

In short:

- Admin/Manager/Owner can submit own check-ins and participate in measures.
- Admin/Manager/Owner can receive their own points/streaks if the existing self-service flow awards them.
- Admin/Manager/Owner can see their own employee portal data.
- Admin/Manager/Owner must not count toward aggregates or thresholds for company/team reports they can access.

Reason:

If a manager or HR/admin is included in a threshold group and can also view the aggregate, they know their own input and can mathematically reduce anonymity for the remaining employees.

Example:

- Threshold: 5
- 4 employees + 1 manager respond
- Manager can view the result and knows their own response
- The manager can infer more about the other 4 responses than intended

Therefore, threshold checks must use only reportable participants.

## Goal of This Planning Task

Create a documented implementation plan for Claude.

The plan must cover:

1. Fixing employee portal route eligibility for company roles
2. Keeping employee routes self-service only
3. Excluding report-viewer roles from company/team aggregate calculations and thresholds
4. Preserving tenant/privacy boundaries
5. Updating tests and OpenAPI/documentation where needed

Do not patch code during this planning task.

## Part A: Employee Portal Eligibility Planning

### Inspect Backend

Inspect and document:

- `apps/api-laravel/routes/api.php`
- route groups under `/api/employee`
- role middleware on employee routes
- `PortalMiddleware`
- `RoleMiddleware`
- `User::canUsePortal(...)`
- `User::canUseEmployeePortal()` or equivalent
- Auth login/me portal response logic
- Employee controllers/services used by employee routes
- tests around auth and employee portal eligibility

### Expected Target Model

Employee routes should be accessible to users for whom employee portal eligibility is true.

Preferred direction:

- use `portal:employee` as the central eligibility gate for employee routes
- avoid duplicating employee-portal roles in multiple route groups if possible
- keep `auth:sanctum`

Acceptable direction:

- if existing conventions require role middleware, use a shared/centralized role list that mirrors employee portal eligibility
- avoid scattering duplicated role lists across routes

### Route Safety Review

Before proposing to open an employee route to company roles, inspect whether it is truly self-service.

A self-service employee route:

- acts only on `request->user()`
- does not accept arbitrary `userId`, `employeeId`, or company/team IDs to act on others
- returns only the authenticated user's own data
- uses authenticated user company/team identity for scoping
- does not expose company-wide raw data or other users' individual data

Review at least:

- `GET /api/employee/checkin/status`
- `POST /api/employee/checkin`
- `GET /api/employee/dashboard`
- `GET /api/employee/measures`
- `POST /api/employee/measures/{measure}/participate`
- `POST /api/employee/measure-checkins/{token}`
- `GET /api/employee/history`
- `GET /api/employee/profile`
- `PUT /api/employee/profile`
- `POST /api/employee/documents`
- employee survey routes, if present

### Expected Planning Decision

If all `/api/employee/*` routes are self-service:

- plan to replace `role:EMPLOYEE` with employee portal eligibility for the group
- use `portal:employee` if that is the correct central gate

If any route is not self-service:

- keep that route restricted
- document exactly why
- propose a narrow fix

### Frontend

Inspect:

- `apps/web-angular/src/app/app.routes.ts`
- `portalGuard('employee')`
- `AuthStore.allowedPortals()`
- employee check-in component/service
- employee measures component/service
- employee profile/history/dashboard/survey routes/components if present

Expected frontend direction:

- likely no route guard change if frontend already uses `portalGuard('employee')`
- do not create duplicate company-side endpoints/components for self-service actions
- keep API calls in employee services
- optional navigation/switcher from company portal to employee portal is out of scope unless tiny and clearly needed

## Part B: Aggregate and Threshold Exclusion Planning

### Privacy Rule

For company/team aggregate reports, exclude users who can view those reports from both:

- threshold participant counts
- aggregation calculations

This applies when a user has reporting/management access, for example:

- `COMPANY_OWNER`
- `COMPANY_ADMIN`
- `COMPANY_MANAGER`
- HR/admin roles if present in the codebase

For v1, use a conservative rule:

- exclude company/report-viewer roles from company/team aggregates globally
- do not attempt fine-grained "manager can view only Team A, so include them in Team B" logic unless it already exists cleanly

### Important Distinction

The exclusion applies only to reporting/aggregation.

It must not remove personal self-service behavior:

- personal check-in still works
- personal measure participation still works
- personal points/streaks still work if existing flow awards them
- personal employee portal history/profile still works

### Inspect Aggregation Surfaces

Inspect and document all current aggregation/threshold surfaces affected by this decision, including at least:

- survey result aggregation and suppression
- company dashboard wellbeing/check-in aggregation, if present
- measure participation summary
- any team-level summary endpoints
- any suppression/threshold helper/service
- OpenAPI docs for aggregate endpoints

Look for existing threshold logic:

- threshold constants/config
- eligibleCount
- participantCount
- suppressed flags
- team/company scoping
- aggregation services
- query filters

### Required Aggregate Semantics

For every affected aggregate:

- `eligibleCount` should count only reportable participants
- `participantCount` should count only reportable participants who submitted/participated
- threshold checks must use reportable participant counts
- aggregate values must be calculated only from reportable participants
- if `eligibleCount < threshold`, suppress
- if `participantCount < threshold`, suppress

Do not include report viewers merely to pass threshold.

### Suggested Centralization

Prefer a central helper/scope/service over scattered role checks.

Possible names, depending on code style:

- `User::isExcludedFromCompanyAggregates()`
- `User::canViewCompanyReports()`
- `User::isReportViewer()`
- query scope such as `whereReportableParticipant()`

The exact name should match existing conventions.

The implementation must be easy to reuse in:

- survey aggregates
- check-in/wellbeing aggregates
- measure participation summaries

Do not hardcode one-off exclusions in unrelated controllers if a central approach is practical.

### Company Manager Specific Rule for v1

For own participant actions:

- use the authenticated user's own `company_id` and own `team_id`
- do not use manager managed-team scope as participant scope

For reporting exclusion:

- exclude `COMPANY_MANAGER` from aggregates for v1
- do not attempt fine-grained per-managed-team inclusion/exclusion unless existing report permission logic already supports it cleanly

### Platform/Internal Users

Internal ELYO platform users should remain excluded from customer company aggregate semantics unless explicitly assigned as real participants in a non-internal company.

If ambiguous:

- document as open question
- do not broaden behavior

## Part C: Test Planning

### Backend Tests: Employee Portal Eligibility

Plan tests for:

- `EMPLOYEE` can access employee self-service routes as before
- `COMPANY_ADMIN` can submit own daily check-in
- `COMPANY_MANAGER` can submit own daily check-in
- `COMPANY_OWNER` can submit own daily check-in if role exists
- duplicate check-in behavior remains unchanged
- company-role user without company is rejected
- internal ELYO platform roles remain rejected when `canUseEmployeePortal()` is false
- company admin/manager can access their own employee profile/dashboard/history only if those routes are confirmed self-service
- employee survey/profile/document routes remain self-service only
- no route lets company admin/manager act for another user through employee endpoints

### Backend Tests: Participation Flows

Plan tests for:

- company admin/manager can list their own visible measures
- company admin/manager can self-report participate in their own visible measure
- company admin/manager can redeem QR token for own visible measure
- company admin/manager cannot participate in cross-company measures
- company manager uses own `user.team_id`, not managed-team scope, as participant scope

### Backend Tests: Aggregate Exclusion

Plan tests for each affected aggregate surface:

Survey aggregation:

- threshold not passed when only employees + manager/admin reach raw count but reportable employee count is below threshold
- manager/admin responses excluded from aggregate values
- eligibleCount/participantCount reflect reportable participants only
- suppression remains active when reportable participant count is below threshold

Measure participation summary:

- manager/admin participation does not help pass threshold
- manager/admin participation excluded from rate numerator/denominator if the endpoint is visible to company roles
- report remains suppressed if only inclusion of manager/admin would pass threshold

Wellbeing/check-in aggregates, if present:

- manager/admin check-ins excluded from company/team aggregate values
- threshold uses reportable participants only

General:

- personal points/streaks still awarded for manager/admin self-check-in if existing behavior awards them
- no individual data exposed

### Angular Tests

Plan tests for:

- employee check-in service endpoint paths/payloads
- employee portal route access relies on allowed portals, not raw EMPLOYEE role
- eligible company admin/manager can use check-in UI if frontend route already allows portal access
- 403 errors still show visible messages
- no direct API calls introduced in components

Only require Angular changes if implementation touches frontend code.

## Part D: OpenAPI and Documentation Planning

Plan OpenAPI updates if implementation changes endpoint eligibility docs.

Document:

- employee endpoints are available to authenticated users with employee portal eligibility
- company admin/manager/owner may use employee self-service endpoints for themselves
- aggregate endpoints exclude users with reporting access from threshold and aggregate calculations
- 403 remains for users without employee portal eligibility

Update or add a concise AI context note if useful:

- company roles may also be employee-portal participants
- report viewers are excluded from company/team aggregate threshold and aggregation calculations
- backend remains source of truth
- employee self-service routes must never expose other users' raw data

Preferred existing location:

- `docs/ai-context/api-contract-rules.md`
- or another existing privacy/aggregation guideline file

Do not create a large new documentation system.

## Part E: Explicit Out of Scope

Do not implement in this planning task.

Future implementation should not include:

- migrations unless planning proves absolutely necessary
- destructive database commands
- broad dashboard redesign
- new company-side duplicate check-in endpoints
- new raw data exports
- changes to points amounts
- changes to streak rules
- changes to survey question/answer model
- changes to QR token generation/hashing/redemption semantics
- fine-grained per-manager report visibility exclusion unless already supported cleanly
- employee access to company management endpoints
- company admins acting on behalf of another employee through employee routes

Forbidden commands:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout

## Part F: Planning Output Required

Return a documented plan with these sections:

1. Current employee portal backend route findings
2. Current employee portal frontend findings
3. Root cause of admin/manager `403`
4. Employee route eligibility proposal
5. Route-by-route self-service safety classification
6. Aggregate/threshold surfaces found
7. Proposed aggregate exclusion strategy
8. Proposed backend changes
9. Proposed frontend changes
10. OpenAPI/docs changes
11. Backend test plan
12. Angular test plan
13. Risks and open questions
14. Explicit out-of-scope list
15. Recommended implementation order for Claude

## Future Validation Commands

The planner should refine these, but likely validation after implementation includes:

- targeted Laravel employee/check-in tests
- targeted Laravel survey/measure/wellbeing aggregation tests
- relevant Angular specs if frontend is touched
- `docker compose exec web npm run build`
- `git diff --check`
- `git status --short`

Do not run tests during the planning task unless the normal plan script does so read-only.

## Implementation Plan

### 1. Current employee portal backend route findings

- `/api/employee/*` routes are inside the authenticated Sanctum group but currently use `role:EMPLOYEE` only in `apps/api-laravel/routes/api.php`.
- `PortalMiddleware` already centralizes portal eligibility through `User::canUsePortal($portal)` and returns `PORTAL_FORBIDDEN` for rejected portal access.
- `User::canUseEmployeePortal()` already allows `EMPLOYEE`, `COMPANY_OWNER`, `COMPANY_ADMIN`, and `COMPANY_MANAGER`, while excluding users in the internal `elyo-platform` company unless they are plain employees.
- Auth login and `/auth/me` already expose `allowedPortals`, and existing tests prove company admins/managers may receive `employee` in `allowedPortals`.
- Existing employee route tests cover plain employees and one manager+employee role case, but do not cover pure company-role users accessing employee routes.

### 2. Current employee portal frontend findings

- Angular routes already guard `/employee` with `portalGuard('employee')`, not a raw role guard.
- `portalGuard('employee')` uses `AuthStore.allowedPortals()` and restores `/auth/me` when needed.
- The header and portal switcher already show employee navigation when `allowedPortals` contains `employee`.
- Employee API calls remain centralized in `EmployeeService`; components do not need duplicate company-side endpoints for self-service actions.
- No existing Angular guard spec was found at `apps/web-angular/src/app/core/guards/auth.guards.spec.ts`; any frontend coverage should follow existing test conventions or be limited to affected services/components.

### 3. Root cause of admin/manager `403`

- Backend auth allows company roles to use the employee portal, but the employee route group still requires the literal `EMPLOYEE` role.
- A pure `COMPANY_ADMIN` or `COMPANY_MANAGER` can log in with employee portal eligibility and pass the Angular portal guard, then receives a backend `403` from `RoleMiddleware` on `/api/employee/checkin`.
- A company role with an added `EMPLOYEE` role may pass today, which hides the bug in some test data.

### 4. Employee route eligibility proposal

- Replace the employee route group middleware from `role:EMPLOYEE` to `portal:employee`, keeping the existing outer `auth:sanctum` group.
- Do not duplicate the company role list in routes; use `User::canUseEmployeePortal()` as the single backend eligibility source.
- Keep employee endpoints self-service only and continue deriving user, company, team, participation time, and document ownership from `request->user()`.
- Keep company management endpoints under `/api/company/*`; do not add company-side check-in or participation duplicates.

### 5. Route-by-route self-service safety classification

- `GET /api/employee/checkin/status`: self-service. Uses authenticated user wellbeing entries and the current period.
- `POST /api/employee/checkin`: self-service. Uses authenticated user, requires `company_id`, writes only that user's wellbeing entry, and awards that user's points/streaks.
- `GET /api/employee/dashboard`: self-service. Reads only authenticated user's wellbeing entries, points, and streak.
- `GET /api/employee/measures`: self-service. Lists active measures in authenticated user's company and own `team_id`, with only that user's participation state.
- `POST /api/employee/measures/{measure}/participate`: self-service. Uses `MeasureParticipationService` to scope by authenticated user's company/team and writes participation for the authenticated user only.
- `POST /api/employee/measure-checkins/{token}`: self-service. Resolves token, verifies company/team scope against authenticated user, and writes participation for authenticated user only.
- `GET /api/employee/history`: self-service. Reads authenticated user's wellbeing entries in their own company.
- `GET /api/employee/profile`: self-service. Reads authenticated user's profile and documents.
- `PUT /api/employee/profile`: self-service. Updates authenticated user's name/anamnesis profile and may award that user's points.
- `POST /api/employee/documents`: self-service. Stores under authenticated user's document path and creates a document for that user.
- Employee survey routes: self-service. List/show/respond/result use authenticated user's company/team visibility and return only that user's survey result for `/result`.

### 6. Aggregate/threshold surfaces found

- Survey results: `SurveyResultsAggregationService` counts eligible users, scoped responses, and question answers through users with `EMPLOYEE` role.
- Company dashboard wellbeing aggregates: `AnonymityService::getAggregatedMetrics()` filters wellbeing entries by active users with `EMPLOYEE` role.
- Company report trends: `AnonymityService::getTrendData()` uses the same role filter and threshold checks on respondent counts.
- Company dashboard continuity: `AnonymityService::getContinuityData()` uses `eligibleEmployeeCount()`, but `checkedInThisPeriod` and continuous-user queries need the same reportable-user filter to avoid counting report viewers.
- Measure participation summary: `MeasureParticipationSummaryService` counts eligible users and participants through users with `EMPLOYEE` role.
- Survey list `responses_count` in `CompanySurveyController::index/show/store/update/activate` is not the official thresholded result, but it is a company-visible count and should be reviewed; if retained, it should not count report viewers.

### 7. Proposed aggregate exclusion strategy

- Introduce one reusable reportable-participant predicate instead of repeating role checks.
- Recommended shape: add `User::isExcludedFromCompanyAggregates(): bool` plus a query scope such as `scopeReportableForCompanyAggregates(Builder $query, ?array $teamIds = null)`.
- The reportable predicate for v1 should include active users in the company and exclude any user with `COMPANY_OWNER`, `COMPANY_ADMIN`, or `COMPANY_MANAGER`.
- Keep requiring participant-like users to have `EMPLOYEE` role for aggregate eligibility unless product decides otherwise; company roles without `EMPLOYEE` can participate personally after this fix but should not become reportable.
- Apply the shared query/scope in survey result aggregation, measure participation summary, and wellbeing aggregate/trend/continuity queries.
- Threshold checks must use reportable participant counts only. Do not include report-viewer roles to pass global or bucket-level thresholds.
- For manager users, continue current v1 report scoping to managed team for viewing, but exclude managers from all company/team aggregate calculations regardless of team.

### 8. Proposed backend changes

- In `apps/api-laravel/routes/api.php`, change the employee group middleware to `portal:employee`.
- Add centralized reportable-user helpers to `User` or a small service consistent with existing code style.
- Update `SurveyResultsAggregationService` so `eligibleUsersQuery()`, `scopedResponsesQuery()`, and `scopedResponseConstraints()` all use the reportable-user filter.
- Update `MeasureParticipationSummaryService::eligibleEmployeesQuery()` so eligible and participant counts use the reportable-user filter.
- Update `AnonymityService` so metrics, trend, continuity, and `eligibleEmployeeCount()` use the same reportable-user filter for both numerator and denominator.
- Review company-visible survey `withCount('responses')`; either convert it to reportable response counts or document that it is a non-thresholded administrative count and remove it from sensitive UI if needed.
- Do not change self-service write behavior: check-ins, survey responses, measure participation, QR redemption, points, and streaks continue for eligible company roles acting as themselves.

### 9. Proposed frontend changes

- Likely no runtime frontend change is required because `/employee` already uses `portalGuard('employee')` and navigation already follows `allowedPortals`.
- Add or adjust Angular tests only if the implementation touches frontend code or if a suitable existing auth/route test location exists.
- Keep employee self-service API calls in `EmployeeService`; do not add direct component fetches or duplicate company services.
- Preserve visible error handling for backend `403` responses.

### 10. OpenAPI/docs changes

- Update employee endpoint descriptions in `docs/api/openapi.yaml` to say authenticated users with employee portal eligibility may use these routes for themselves.
- Add/confirm `403` behavior for users without employee portal eligibility on employee endpoints.
- Update aggregate endpoint descriptions for `/company/dashboard`, `/company/reports`, `/company/surveys/{id}/results`, and `/company/measures/{id}/participation-summary` to state that report-viewer roles are excluded from threshold and aggregate calculations.
- Add a concise context note in `docs/ai-context/health-data-guardrails.md` or `docs/ai-context/api-contract-rules.md`: company roles may be employee-portal participants, but report viewers are not reportable participants for company/team aggregates.
- Do not create a new documentation system.

### 11. Backend test plan

- Add employee eligibility tests for pure `COMPANY_ADMIN`, `COMPANY_MANAGER`, and `COMPANY_OWNER` if present, covering `POST /api/employee/checkin`.
- Keep existing plain `EMPLOYEE` check-in tests passing.
- Add duplicate check-in coverage for a company-role user to verify behavior remains unchanged.
- Add tests that company-role users without `company_id` are rejected by self-service write endpoints that require a company.
- Add tests that internal ELYO platform users remain rejected when `canUseEmployeePortal()` is false.
- Add self-service read/write coverage for profile, dashboard/history, employee measures, QR measure check-in, and employee surveys where practical.
- Add tests that company admin/manager cannot act for another user through employee endpoints by sending ignored user/company/team fields.
- Add participation tests proving company admin/manager can list visible measures, self-report participate, redeem QR tokens, cannot participate cross-company, and use own `user.team_id` rather than managed-team scope.
- Add aggregate exclusion tests for survey results, measure participation summary, wellbeing dashboard metrics, report trends, and continuity where existing fixtures support it.
- In aggregate tests, include users with both `EMPLOYEE` and a report-viewer role to prove the exclusion covers multi-role users.
- Verify personal points/streaks still award for manager/admin self-service flows where existing behavior awards them.

### 12. Angular test plan

- If frontend code changes, add tests that employee portal access depends on `allowedPortals` and not raw `EMPLOYEE` role.
- Keep or add `EmployeeService` tests confirming self-service calls use `/employee/*` paths and do not send user/company/team identifiers.
- Add component tests only if UI behavior changes; otherwise backend tests are the primary coverage for this bug.
- No Angular build is required in this planning task; run it after implementation if frontend files change.

### 13. Risks and open questions

- Open question: whether users with only company roles and no `EMPLOYEE` role should ever be reportable in company aggregates. The v1 plan says no.
- Open question: whether `CompanySurveyController` list/detail response counts should be privacy-filtered immediately or handled in a separate small cleanup if they are not displayed as final results.
- Risk: changing the route middleware opens all employee routes to broader employee-portal users; route-by-route review found them self-service, but tests should lock this down.
- Risk: aggregate filters are currently duplicated across services; partial updates could leave one company-facing surface counting report viewers.
- Risk: `AnonymityService::getContinuityData()` has multiple queries that must all use the same reportable-user constraint, not just the denominator.

### 14. Explicit out-of-scope list

- No migrations unless implementation uncovers an unavoidable need.
- No destructive database commands.
- No Docker teardown or volume deletion.
- No points amount or streak rule changes.
- No survey question/answer model changes.
- No QR token generation, hashing, or lifecycle changes beyond eligibility for redemption.
- No company-side duplicate self-service endpoints.
- No raw data export.
- No broad dashboard redesign.
- No fine-grained per-manager inclusion/exclusion logic for v1.
- No employee access to company management endpoints.
- No acting on behalf of another employee through employee routes.

### 15. Recommended implementation order for Claude

1. Add focused failing backend tests for pure company admin/manager employee route access and report-viewer aggregate exclusion.
2. Change the employee route group to `portal:employee`.
3. Add the centralized reportable-participant helper/scope.
4. Apply the helper/scope to survey aggregation and measure participation summaries.
5. Apply the helper/scope to wellbeing dashboard metrics, trends, and continuity queries.
6. Review and adjust company-visible survey response counts if they leak report-viewer participation.
7. Update OpenAPI and the concise AI context note.
8. Add or update Angular tests only if frontend code changes.
9. Run targeted Laravel tests for employee eligibility, survey aggregation, measure summary, and wellbeing aggregates.
10. Run `git diff --check`, relevant Angular specs/build only if frontend changed, then review the diff for portal boundaries, tenant scoping, and health-data leakage.

Plan approved with these constraints:

1. First add regression tests proving that pure COMPANY_ADMIN and COMPANY_MANAGER users with employee portal eligibility currently fail on POST /api/employee/checkin and pass after the fix.

2. Keep all /api/employee routes inside the existing auth:sanctum protection. Replace role-only gating with portal:employee; do not weaken authentication.

3. Treat "employee portal participant" and "reportable participant" as separate concepts:
    - company roles may use employee self-service routes for themselves
    - report-viewer roles must be excluded from company/team aggregate thresholds and aggregate calculations
    - multi-role users such as EMPLOYEE + COMPANY_MANAGER are also excluded from aggregates

4. Add explicit tests that internal ELYO platform users remain blocked from employee routes when canUseEmployeePortal() returns false.

5. Verify every /api/employee route is self-service only before opening the whole group via portal:employee. If any route accepts or exposes another user's data, stop and document it instead of opening it.