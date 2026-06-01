# Task: Backend portal eligibility for company users

## Goal

Make backend portal eligibility reflect the target architecture:

Every real company user should have Employee/App portal access.
Company/Admin/Manager portals are additional work contexts.
Internal ELYO platform users must not automatically receive Employee/App portal access just because they have a company_id.

## Architectural rules

- Every user belongs to exactly one company.
- ELYO internal platform users are assigned to the internal ELYO company.
- Real customer-company users always have Employee/App portal access.
- COMPANY_MANAGER is team-scoped and only receives Company portal access when company.team_layer_enabled=true.
- COMPANY_ADMIN and COMPANY_OWNER receive Company portal access regardless of team_layer_enabled.
- ELYO_ADMIN / ELYO_SUPPORT receive platform/admin portal access and should not automatically receive Employee/App portal access.
- Do not broaden manager-only users into company-wide access.
- Backend remains the source of truth for allowedPortals and activePortal.

## Scope

Backend-only.

Allowed files:
- apps/api-laravel/app/Models/User.php
- apps/api-laravel/app/Http/Controllers/Auth/AuthController.php only if portal response assembly requires it
- relevant backend tests, preferably AuthTest

Do not change:
- Angular
- seeders
- migrations
- OpenAPI unless login/me response contract is materially changed
- company controllers
- team-layer enforcement controllers
- Docker/config

## Required behavior

1. Real company users get Employee/App portal
   - A user assigned to a real customer company should receive employee/app portal access even if their explicit roles are COMPANY_MANAGER, COMPANY_ADMIN, or COMPANY_OWNER.
   - If the implementation currently requires the EMPLOYEE role for employee portal access, adjust portal eligibility carefully or document why explicit EMPLOYEE role is required.

2. Internal ELYO users are excluded
   - Users assigned to the internal ELYO platform company must not get Employee/App portal access only because they have company_id.
   - Use the existing internal company convention if present, e.g. slug `elyo-platform`.
   - Do not introduce a migration or new company type in this task.

3. Company portal remains additive
   - COMPANY_ADMIN and COMPANY_OWNER can use Company portal regardless of team_layer_enabled.
   - COMPANY_MANAGER can use Company portal only when company.team_layer_enabled=true.
   - COMPANY_MANAGER with team_layer_enabled=false must not receive Company portal.

4. Active portal fallback
   - Preserve existing portal order as much as possible.
   - If requested portal is allowed, use it.
   - If requested portal is not allowed, reject consistently.
   - If no portal is requested:
     - ELYO_ADMIN should default to admin/platform portal.
     - COMPANY_ADMIN / COMPANY_OWNER may default to company portal.
     - COMPANY_MANAGER with team_layer_enabled=true may default to company portal if current behavior does that.
     - COMPANY_MANAGER with team_layer_enabled=false should fall back to employee/app portal if it is allowed.
     - plain Employee should default to employee/app portal.

## Tests

Extend AuthTest with focused cases:

- manager-only user in real company with team_layer_enabled=false:
  - allowedPortals includes employee/app
  - allowedPortals excludes company
  - activePortal falls back to employee/app

- manager-only user in real company with team_layer_enabled=true:
  - allowedPortals includes employee/app
  - allowedPortals includes company

- company admin in real company with team_layer_enabled=false:
  - allowedPortals includes employee/app
  - allowedPortals includes company

- plain employee:
  - allowedPortals includes employee/app only

- ELYO admin/support assigned to internal ELYO company:
  - does not get employee/app portal just because of company_id
  - gets expected platform/admin portal

- requested company portal for manager-only disabled-team-layer user is rejected consistently.

## Out of scope

- No Angular portal switch.
- No seed changes.
- No database migration.
- No internal company type column.
- No manager hierarchy.
- No OpenAPI cleanup.
- No destructive database commands.

## Validation

Run:
- docker compose exec api php artisan test --filter=AuthTest
- docker compose exec api php artisan test
- git diff --check

## Expected output

Report:
- changed files
- portal eligibility behavior
- activePortal fallback behavior
- tests run
- risks/open questions

## Implementation Plan

1. Confirm the current portal eligibility surface before patching.
   - Re-read `apps/api-laravel/app/Models/User.php`, `apps/api-laravel/app/Http/Controllers/Auth/AuthController.php`, and `apps/api-laravel/tests/Feature/AuthTest.php`.
   - Keep the change inside the task's allowed backend files unless implementation reveals an unavoidable contract issue.
   - Do not change Angular, migrations, seeders, Docker/config, OpenAPI, company controllers, or team-layer enforcement controllers unless the future implementation intentionally expands scope after review.

2. Move employee/app portal eligibility into `User`.
   - Add a focused helper such as `isInternalElyoCompany()` that detects the existing internal company convention by company slug `elyo-platform`.
   - Add a focused helper such as `canUseEmployeePortal()` that returns true for explicit `EMPLOYEE` users and for real customer-company users with customer company roles (`COMPANY_MANAGER`, `COMPANY_ADMIN`, `COMPANY_OWNER`), while returning false for platform users assigned to the internal ELYO company.
   - Preserve the existing `isEmployee()` role helper if other code depends on strict `EMPLOYEE` role membership; do not broaden its meaning unless a follow-up inspection proves it is portal-only.
   - Update `canUsePortal('employee')` and `allowedPortals()` to use the new employee portal helper.

3. Preserve company and admin portal behavior.
   - Keep `canUseCompanyPortal()` unchanged in principle: `COMPANY_OWNER` and `COMPANY_ADMIN` get company portal; `COMPANY_MANAGER` gets company portal only when `company.team_layer_enabled=true`.
   - Keep admin/platform portal eligibility tied to `Role::adminPortalRoles()`.
   - Keep requested-portal rejection in `AuthController` consistent with the existing `PORTAL_FORBIDDEN` response.

4. Preserve and verify active portal fallback order.
   - Keep `allowedPortals()` order as `admin`, `company`, `employee`, `partner` unless a failing test proves a different existing order is required.
   - This means ELYO admin/support default to `admin`, company admins/owners default to `company`, managers with team layer enabled default to `company`, managers with team layer disabled default to `employee`, and plain employees default to `employee`.
   - Do not add request-side fallback logic to `AuthController` unless `allowedPortals()` cannot express the required order cleanly.

5. Update `AuthTest` cases around portal eligibility.
   - Replace the existing company-only employee portal rejection expectation with the new target behavior for real company users.
   - Update the manager-only disabled-team-layer test to assert `allowedPortals` includes `employee`, excludes `company`, and login falls back to `activePortal=employee`.
   - Update the manager-only enabled-team-layer test to assert both `company` and `employee` are present, with the current default active portal preserved as `company`.
   - Update the company admin disabled-team-layer test to assert both `company` and `employee` are present, with active portal preserved as `company`.
   - Keep the plain employee test asserting employee-only portal access.
   - Add or update a platform user test using an internal company with slug `elyo-platform` to prove ELYO admin/support do not receive employee portal from `company_id` alone and still receive `admin`.
   - Keep the requested company portal rejection test for a manager in a disabled team-layer company.

6. Validation for the future patch-mode run.
   - Run `docker compose exec api php artisan test --filter=AuthTest`.
   - Run `docker compose exec api php artisan test`.
   - Run `git diff --check`.
   - Do not run migration, Docker, frontend build, or OpenAPI validation commands for this task unless scope changes require them.

7. Review checklist before handoff.
   - Confirm backend remains the source of truth for `allowedPortals` and `activePortal`.
   - Confirm company/manager portal access is not broadened beyond the team-layer rule.
   - Confirm internal ELYO platform users are not treated as employees solely because they have `company_id`.
   - Confirm no individual health data paths or company reporting endpoints are touched.
   - Confirm OpenAPI is unchanged because the response shape and error format remain stable; if behavior change documentation is deemed material, flag it as an open question instead of silently editing OpenAPI in this task.

## Final Scope Clarification

Before patching, inspect the current portal identifiers used by the backend and frontend contracts.

- Do not introduce a new portal identifier.
- Use the existing employee/app portal identifier consistently in `canUsePortal()`, `allowedPortals()`, `activePortal`, and tests.
- If the existing identifier is `employee`, keep `employee`.
- If the existing identifier is `app`, keep `app`.
- Do not rename portals in this task.

Internal ELYO company handling:
- Detect the current internal platform company convention only in a small helper, e.g. `isInternalElyoCompany()`.
- For this MVP task, using slug `elyo-platform` is acceptable if that is the existing convention.
- Do not add a migration or new company type field.

Employee role semantics:
- Do not broaden `isEmployee()` unless it is proven to be portal-only.
- Prefer a separate helper for employee/app portal eligibility.
- Employee/app portal eligibility and strict EMPLOYEE role membership are not necessarily the same concept.

OpenAPI:
- Leave OpenAPI unchanged unless the response shape or portal identifiers change.
- If only the contents of allowedPortals change according to the existing schema, do not edit OpenAPI.

Validation:
- docker compose exec api php artisan test --filter=AuthTest
- docker compose exec api php artisan test
- git diff --check
