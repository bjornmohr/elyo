# Task: Plan Admin/Manager Own Check-in Eligibility Fix

Date: 2026-06-11

## Context

Manual QA found a role/eligibility bug:

A company-side admin user currently cannot submit their own daily check-in. The submit request fails with `403`.

This needs a planning pass before implementation because the issue may involve:

- Laravel route middleware
- portal eligibility logic
- role middleware
- user/company scoping
- frontend route guards
- check-in service usage
- related employee-style participation endpoints

Existing product context:

- A user belongs to exactly one company.
- Company admins/managers may also be real participants in their own company.
- Individual check-in/participation data must never be exposed to company reporting views.
- Company reporting must remain aggregate/thresholded only.
- Tenant scoping must not be weakened.
- Platform/system admin behavior must stay explicit and must not accidentally grant cross-company participation.

## Goal

Create an implementation plan to fix the `403` when eligible company-side users submit their own check-in.

Do not patch code in this task.

The plan must identify the root cause and propose the smallest safe implementation path.

## Scope of Planning

Inspect and document:

1. Existing daily check-in backend routes
2. Existing employee portal route middleware
3. Existing role middleware rules for check-in endpoints
4. Existing `PortalMiddleware`
5. Existing `User::canUsePortal(...)` or equivalent portal eligibility logic
6. Existing frontend route guards/navigation for employee check-in
7. Existing Angular service endpoint used by the check-in submit action
8. Existing tests around daily check-in/status
9. Whether measure participation endpoints have the same role/portal issue
10. Whether QR check-in redemption has the same role/portal issue

Do not modify files during this planning task.

## Product Decision for v1

For v1, company-side users who are also real members of a company should be able to submit their own participant actions.

Eligible roles:

- `EMPLOYEE`
- `COMPANY_MANAGER`
- `COMPANY_ADMIN`
- `COMPANY_OWNER` if this role exists in the codebase and is currently part of company portal semantics

Eligibility requirements:

- user must belong to a company
- user must act only for their own company
- no cross-company access
- no access to individual employee data through company reporting
- duplicate check-in behavior remains unchanged
- point/streak behavior remains unchanged unless already naturally triggered by the existing check-in flow

Platform/system admin:

- Do not automatically grant platform/system admins employee check-in ability unless existing seed/domain logic intentionally gives them an internal company and portal eligibility.
- If platform admin behavior is ambiguous, document it as an open question instead of expanding scope.

## Expected Behavior After Future Patch

Daily check-in:

- eligible employee can submit as before
- eligible company admin can submit own daily check-in
- eligible company manager can submit own daily check-in
- duplicate daily check-in still returns the existing duplicate behavior
- user without company or ineligible role is rejected
- response shape remains compatible with existing frontend

Related endpoints to evaluate:

- daily check-in status endpoint
- measure participation endpoint
- QR check-in token redemption endpoint
- employee dashboard/check-in frontend route

Do not automatically expand the implementation to all employee routes. The plan must distinguish:

- own participant actions
- employee self-service profile/dashboard data
- company management/reporting actions

## Privacy and Security Requirements

The future fix must not:

- expose individual employee check-in data to company admins/managers
- weaken company/team scoping
- allow company users to submit check-ins for other users
- allow cross-company check-ins
- change aggregate threshold/suppression behavior
- change survey privacy behavior
- add raw participation exports

The future fix may:

- allow a company admin/manager to use the same self-check-in endpoint for themselves
- allow points/streak behavior to occur if that is already part of the existing employee check-in flow

## Planning Output Required

Return a documented plan with these sections:

1. Current backend route findings
   - route names/paths
   - middleware stack
   - controller/actions involved
2. Current frontend findings
   - route/guard involved
   - component/service involved
   - exact endpoint called
3. Root cause of the observed `403`
   - backend middleware?
   - portal eligibility?
   - frontend guard?
   - wrong endpoint?
4. Proposed backend changes
   - exact files
   - role/portal eligibility strategy
   - why it preserves tenant/privacy boundaries
5. Proposed frontend changes
   - exact files
   - guard/service/UI behavior
6. Related endpoint decision
   - whether daily check-in status should change too
   - whether measure participation should change too
   - whether QR redemption should change too
7. Test plan
   - backend tests
   - Angular tests
8. OpenAPI impact
   - whether docs need role/eligibility clarification
9. Risks and open questions
10. Explicit out-of-scope list

## Implementation Constraints for Future Patch

When patching later:

- Prefer changing portal eligibility or middleware centrally only if it is semantically correct.
- Avoid duplicating endpoints for company admin/manager self-actions.
- Avoid broadening every employee route blindly.
- Keep backend as source of truth.
- Keep Angular API calls in services.
- Add tests for role access and privacy/scoping behavior.
- Do not run destructive commands.

Forbidden commands:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout

## Validation Commands for Future Patch

Likely commands after implementation:

- relevant Laravel feature tests for check-in / wellbeing / employee endpoints
- relevant Angular specs for check-in route/component/service
- `docker compose exec web npm run build`
- `git diff --check`
- `git status --short`

The planner should refine these after inspecting the codebase.

## Implementation Plan

### 1. Current backend route findings

- Daily check-in routes live in `apps/api-laravel/routes/api.php` under the protected `auth:sanctum` group and the `/api/employee` prefix.
- The current employee route group uses only `role:EMPLOYEE`; it does not use `portal:employee`.
- Relevant paths/actions:
  - `GET /api/employee/checkin/status` -> `App\Http\Controllers\Employee\EmployeeController::checkinStatus`
  - `POST /api/employee/checkin` -> `EmployeeController::checkin`
  - `GET /api/employee/dashboard` -> `EmployeeController::dashboard`
  - `GET /api/employee/measures` -> `EmployeeController::measures`
  - `POST /api/employee/measures/{measure}/participate` -> `EmployeeController::participateInMeasure`
  - `POST /api/employee/measure-checkins/{token}` -> `EmployeeController::redeemMeasureCheckin`
- `RoleMiddleware` returns `403` with `error.code = FORBIDDEN` when the authenticated user does not have one of the route roles.
- `PortalMiddleware` returns `403` with `error.code = PORTAL_FORBIDDEN` when `User::canUsePortal($portal)` is false.
- `User::canUseEmployeePortal()` already allows:
  - `EMPLOYEE`
  - `COMPANY_OWNER`
  - `COMPANY_ADMIN`
  - `COMPANY_MANAGER`
  - except internal ELYO platform-company users, via `isInternalElyoCompany()`
- `AuthTest` already documents that a real-company `COMPANY_ADMIN` can log in to the employee portal and that a manager without team-layer company access still gets only the employee portal.

### 2. Current frontend findings

- Angular employee portal routes live in `apps/web-angular/src/app/app.routes.ts` under `/employee`.
- The `/employee` route uses `authGuard` plus `portalGuard('employee')`; there is no frontend role guard on `/employee/checkin`.
- `portalGuard('employee')` checks `AuthStore.allowedPortals()`, which is populated from `/api/auth/login` and `/api/auth/me`.
- The check-in UI is `apps/web-angular/src/app/features/employee/pages/checkin/checkin.component.ts`.
- The check-in service calls are in `apps/web-angular/src/app/features/employee/services/employee.service.ts`:
  - `getCheckinStatus()` calls `GET /employee/checkin/status`
  - `submitCheckin(...)` calls `POST /employee/checkin` with `mood`, `stress`, `energy`, and optional `note`
- QR measure check-in UI is `apps/web-angular/src/app/features/employee/pages/measure-checkin/measure-checkin.component.ts`; it calls `EmployeeService.redeemMeasureCheckin(token)` -> `POST /employee/measure-checkins/{token}`.
- Measure self-report participation is `EmployeeMeasuresComponent` -> `EmployeeService.participateInMeasure(measure.id)` -> `POST /employee/measures/{measure}/participate`.
- No dedicated check-in component spec currently exists. `EmployeeService` has a spec for measure participation, but not for daily check-in status/submit endpoints.

### 3. Root cause of the observed `403`

- Primary root cause: backend route middleware.
- Company admins/managers are eligible for the employee portal according to `User::canUseEmployeePortal()` and auth responses, but `/api/employee/*` routes currently require `role:EMPLOYEE`.
- A `COMPANY_ADMIN`, `COMPANY_MANAGER`, or `COMPANY_OWNER` without an additional `EMPLOYEE` role is rejected by `RoleMiddleware` before `EmployeeController::checkin()` runs.
- The frontend guard is probably not the cause because it uses `allowedPortals`, not raw role checks. If the user can navigate to `/employee/checkin`, the later `403` comes from the API request.
- The endpoint is not wrong for this v1 behavior. The existing endpoint is a self-action endpoint that derives identity from `request->user()`, which is the right model for company-side users submitting their own participant action.

### 4. Proposed backend changes

- Edit `apps/api-laravel/routes/api.php`.
- Do not broaden the entire `/api/employee` group in one step.
- Split the current employee routes into two groups:
  - A narrow self-participant group using middleware `['role:EMPLOYEE,COMPANY_OWNER,COMPANY_ADMIN,COMPANY_MANAGER', 'portal:employee']`.
  - The existing employee-only self-service group using `role:EMPLOYEE` for routes that expose broader employee profile/dashboard/history/survey/document behavior unless separately reviewed.
- Put these routes in the self-participant group:
  - `GET /employee/checkin/status`
  - `POST /employee/checkin`
  - `GET /employee/measures`
  - `POST /employee/measures/{measure}/participate`
  - `POST /employee/measure-checkins/{token}`
- Keep these routes employee-only for this patch:
  - `GET /employee/dashboard`
  - `GET /employee/history`
  - `GET /employee/profile`
  - `PUT /employee/profile`
  - `POST /employee/documents`
  - employee survey routes
- Rationale:
  - Daily check-in status must change with submit; otherwise the UI can load as unavailable or stale before submission.
  - Measure list/participation and QR redemption are also own participant actions and currently fail for the same route-middleware reason.
  - Profile, documents, dashboard, history, and surveys are broader employee self-service surfaces and should not be opened by this check-in fix without a separate product/privacy review.
- Keep `EmployeeController::checkin()` as the tenant boundary for daily check-in creation:
  - It rejects users without `company_id`.
  - `WellbeingService::submitCheckin()` writes `user_id` and `company_id` from the authenticated user.
  - The database unique key `wellbeing_entries(user_id, period_key)` preserves duplicate behavior.
- Keep `MeasureParticipationService` as the tenant/team boundary for measures:
  - It resolves visible measures by authenticated `user.company_id` and `user.team_id`.
  - It creates participation rows from the authenticated user only.
  - QR redemption masks wrong-company and wrong-team tokens as not found before participation.
- Do not grant platform/system admins access by role. The `portal:employee` middleware keeps internal ELYO platform users out unless existing domain data intentionally makes them eligible outside the internal platform company rule.

### 5. Proposed frontend changes

- No route guard change is expected for the core fix because `/employee` already uses `portalGuard('employee')`.
- Add or update tests only if the future patch touches frontend code.
- Recommended frontend test additions:
  - Add `apps/web-angular/src/app/features/employee/services/employee.service.spec.ts` coverage for `getCheckinStatus()` and `submitCheckin()` endpoint paths/payload shape.
  - If a check-in component spec is added, verify that `403` from submit displays the generic failure and does not expose backend details.
- Do not duplicate check-in endpoints under `/company`.
- Do not move API calls into components; keep all check-in API calls in `EmployeeService`.
- Optional navigation follow-up, if product wants a visible entry point from the company shell: add a deliberate link/switcher to the employee portal instead of calling employee endpoints from company components. This is not required for fixing the API `403`.

### 6. Related endpoint decision

- Daily check-in status: change in the same patch. It is part of the same UI flow and must use the same eligibility model as submit.
- Measure participation: change in the same patch for self-report and QR-related own participant actions, because the route group has the same role-only bug and the service already derives/scopes identity safely.
- QR check-in redemption: change in the same patch, because the QR link route is an own participant action and already has company/team masking.
- Employee measures list: change in the same patch, because QR and self-report participation require users to see their own eligible measures and participation state.
- Employee dashboard/history/profile/documents/surveys: keep out of scope for this patch. These are self-service data views beyond the reported check-in/participation issue.
- Company reporting endpoints: no change.

### 7. Test plan

- Backend tests in `apps/api-laravel/tests/Feature/EmployeeTest.php`:
  - Add a `COMPANY_ADMIN` with a real `company_id`; assert `POST /api/employee/checkin` succeeds, creates one `wellbeing_entries` row with that admin's `user_id` and company, and awards existing daily check-in points/streak behavior naturally.
  - Add a `COMPANY_MANAGER` with a real `company_id`; assert `POST /api/employee/checkin` succeeds.
  - Add `COMPANY_OWNER` coverage if the role exists in codebase; it does exist as `App\Enums\Role::COMPANY_OWNER`.
  - Assert duplicate admin/manager check-in still returns `409` with `CHECKIN_ALREADY_DONE`.
  - Assert a company-role user without `company_id` is rejected by `EmployeeController::checkin()` and does not create a wellbeing row.
  - Assert `ELYO_ADMIN` / `ELYO_SUPPORT` users remain rejected from `POST /api/employee/checkin` unless they already have explicit real-company employee-portal eligibility. For current internal platform company behavior, expect rejection.
  - Assert `GET /api/employee/checkin/status` works for company admin/manager and returns only their own status/entry.
  - Assert company admin/manager can use `GET /api/employee/measures`, `POST /api/employee/measures/{measure}/participate`, and `POST /api/employee/measure-checkins/{token}` only for measures visible in their own company/team scope.
  - Update the existing `test_company_user_cannot_use_employee_measure_participation_endpoint` because it conflicts with the v1 product decision. Replace it with positive own-participation coverage plus negative cross-company/team assertions.
- Backend auth tests in `apps/api-laravel/tests/Feature/AuthTest.php`:
  - Keep existing portal eligibility tests.
  - Optionally add a direct assertion that a company admin/manager with employee portal eligibility can reach the narrow self-participant employee routes without having the `EMPLOYEE` role.
- Angular tests:
  - Extend `EmployeeService` tests for daily check-in status and submit path/payload.
  - Existing `EmployeeMeasureCheckinComponent` and employee measures tests should remain valid because endpoint paths do not change.
- Do not run tests in this planning task. Future patch validation can run focused Laravel feature tests and relevant Angular specs before broader build/test commands.

### 8. OpenAPI impact

- Update `docs/api/openapi.yaml` in the future patch because auth/role behavior changes for documented employee endpoints.
- Add short descriptions to affected employee endpoints clarifying they are authenticated own-participant endpoints available to eligible employee-portal users, including company owner/admin/manager users in their own company when `portal:employee` eligibility allows it.
- Do not change request or response schemas unless implementation changes them. The planned fix is authorization/eligibility-only.
- Document `403` behavior for ineligible users if the affected endpoint sections do not already include it.

### 9. Risks and open questions

- Risk: broadening the whole `/api/employee` route group would unintentionally expose profile, document, history, survey, or dashboard surfaces to company roles. Mitigation: split route groups and open only own participant actions.
- Risk: company managers without a `team_id` may see only company-wide measures; this follows existing measure visibility logic. If managers should participate in their managed team without `users.team_id`, that is a separate product decision.
- Risk: admin/manager check-in notes remain individual health data. They are stored as the submitting user's own wellbeing entry and must remain absent from company reporting views.
- Open question: whether company admins/managers should get a first-class UI affordance from the company portal to switch to employee check-in, or whether direct employee portal access is enough for v1.
- Open question: whether survey participation should eventually be treated as an own participant action for company-role users. This is explicitly not included in this patch.
- Open question: platform admin behavior should remain unchanged unless product explicitly assigns platform admins to a real non-internal company as participants.

### 10. Explicit out-of-scope list

- No production code changes in this planning task.
- No frontend/backend implementation in this planning task.
- No migrations.
- No database destructive commands.
- No Docker commands.
- No tests/builds during this planning task.
- No new company-side duplicate check-in endpoint.
- No broad opening of all `/api/employee/*` routes.
- No change to aggregate company reporting, anonymity thresholds, survey privacy, or raw health data exposure.
- No change to points amounts, streak rules, duplicate behavior, request/response shape, or check-in storage model unless an existing focused test proves the current behavior is already different.
