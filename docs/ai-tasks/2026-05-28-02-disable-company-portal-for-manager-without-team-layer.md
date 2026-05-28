# Task: Disable company portal access for manager-only users when team layer is disabled

## Goal

A COMPANY_MANAGER is a team-scoped role. When a company has team_layer_enabled=false, manager-only users must not receive company portal access. They should only use the employee/app portal if they also have EMPLOYEE access.

## Architectural decision

- COMPANY_MANAGER is not a company-wide admin role.
- COMPANY_MANAGER is only meaningful when company.team_layer_enabled=true.
- If team_layer_enabled=false, manager-only users must not see or access company dashboard, reports, surveys, measures, teams, invitations, or company user management.
- COMPANY_ADMIN and COMPANY_OWNER remain company-wide roles and keep company portal access regardless of team_layer_enabled.
- Angular may hide/redirect, but Laravel/Auth remains source of truth.

## Required backend changes

1. Auth portal calculation
   - Update auth login/me portal calculation so manager-only users do not receive the company portal when their company has team_layer_enabled=false.
   - allowedPortals should exclude company for manager-only disabled-team-layer users.
   - activePortal should fall back to app/employee when available.

2. Company route protection
   - Ensure manager-only users with team_layer_enabled=false cannot access company portal endpoints.
   - Prefer central middleware/portal guard logic over per-controller duplication.
   - Do not block COMPANY_ADMIN/COMPANY_OWNER.
   - Do not broaden managers into company-wide access.

3. Error behavior
   - Use existing API error envelope and stable code where practical.
   - TEAM_LAYER_DISABLED is acceptable if current team-layer guard already uses it.
   - FORBIDDEN is acceptable if this is treated as portal access denial.
   - Be consistent and document if OpenAPI behavior changes.

## Required Angular changes

1. Portal visibility
   - Company portal switch/navigation must not be shown for manager-only disabled-team-layer users.
   - If direct company URL access occurs, route guard should redirect to employee/app portal or show no-access state.

2. Do not duplicate business logic beyond consuming allowedPortals/auth state where possible.

## Tests

Add focused tests:

- manager-only user with team_layer_enabled=false logs in and allowedPortals does not include company.
- manager-only user with team_layer_enabled=false has activePortal app/employee when possible.
- manager-only user with team_layer_enabled=true still gets company portal.
- company admin with team_layer_enabled=false still gets company portal.
- manager-only disabled-team-layer user cannot access /company/dashboard.
- employee functionality still works for that user if EMPLOYEE role exists.

## OpenAPI

Update docs/api/openapi.yaml only if login/me response examples or company endpoint error behavior change.

## Out of scope

- Do not remove roles from users.
- Do not migrate data.
- Do not change team assignment model.
- Do not add manager hierarchy.
- Do not change privacy thresholds.
- Do not change seeder smoke scenarios in this task.
- Do not run destructive database commands.

## Validation

Run:

- docker compose exec api php artisan test --filter=AuthTest
- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test
- docker compose exec web npm run build
- git diff --check

## Implementation Plan

1. Backend portal eligibility
   - Update `apps/api-laravel/app/Models/User.php` so `canUsePortal('company')` and `allowedPortals()` treat a manager-only user as company-portal eligible only when their company has `team_layer_enabled=true`.
   - Keep `COMPANY_OWNER` and `COMPANY_ADMIN` company-portal eligible regardless of `team_layer_enabled`.
   - Preserve existing portal order so `activePortal` fallback remains stable and manager+employee users can fall back to `employee`.

2. Backend company route protection
   - Add central company portal protection for `/api/company/*`, preferably by extending existing middleware/guard wiring instead of duplicating checks in every controller.
   - Reuse `TeamLayerGuard::isManagerOnly()` and existing `TEAM_LAYER_DISABLED` response behavior where practical, or use the existing forbidden portal error consistently if the route-level guard is framed as portal access denial.
   - Apply the guard only to manager-only users whose company has `team_layer_enabled=false`; do not restrict company admins or owners.

3. Backend tests
   - Extend `apps/api-laravel/tests/Feature/AuthTest.php` for login and `/auth/me` portal calculations:
     - manager-only plus disabled team layer excludes `company`.
     - manager-only plus disabled team layer plus `EMPLOYEE` falls back to `employee`.
     - manager-only plus enabled team layer includes `company`.
     - company admin plus disabled team layer still includes `company`.
     - requested `company` login for a disabled-team-layer manager-only user is rejected consistently.
   - Extend `apps/api-laravel/tests/Feature/CompanyTest.php` for `/api/company/dashboard` denial to manager-only users when `team_layer_enabled=false`, while confirming admins remain allowed.

4. Angular portal gating
   - Update route access for the company portal in `apps/web-angular/src/app/app.routes.ts` to use `portalGuard('company')` in addition to authentication, so direct `/company/*` access follows backend-provided `allowedPortals`.
   - Update `apps/web-angular/src/app/app.html` navigation to show the Company link from `allowedPortals` instead of role-only checks.
   - Keep business rules out of Angular; consume `allowedPortals` and existing auth state only.

5. OpenAPI review
   - Review `docs/api/openapi.yaml` after backend behavior is patched.
   - Update it only if the chosen implementation changes documented login/me examples, company endpoint authorization behavior, or error codes.

6. Validation to run in patch mode
   - `docker compose exec api php artisan test --filter=AuthTest`
   - `docker compose exec api php artisan test --filter=CompanyTest`
   - `docker compose exec api php artisan test`
   - `docker compose exec web npm run build`
   - `git diff --check`

7. Risk checks before handoff
   - Confirm manager-only users are never broadened into company-wide access.
   - Confirm portal denial does not expose health data or company aggregates to disabled-team-layer managers.
   - Confirm employee routes still work for users who also have `EMPLOYEE`.
   - Confirm no migrations, seeders, Docker config, n8n logic, or legacy `../ELYO` files are touched.

## Approved Review Notes

Clarify the intended error semantics:

- COMPANY_MANAGER is a team-scoped company role.
- When company.team_layer_enabled=false, a manager-only user is not company-portal eligible.
- For /api/company/* access by a manager-only disabled-team-layer user, prefer the existing portal/forbidden access-denied behavior over TEAM_LAYER_DISABLED if such a portal guard already exists.
- Keep TEAM_LAYER_DISABLED for users who are company-portal eligible but attempt disabled team-layer-specific operations.
- Tests should primarily assert that manager-only disabled-team-layer users cannot access company endpoints, and should only assert a specific error code if the existing middleware already guarantees one.
- Do not broaden manager-only users into company-wide access.
