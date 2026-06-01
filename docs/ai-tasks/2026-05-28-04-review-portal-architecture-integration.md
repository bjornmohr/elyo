# Task: Review portal architecture integration

## Goal

Review the current branch state after the backend portal eligibility, company route guard, and Angular portal switcher work.

This is a review-only task. Do not modify files.

## Context

The target architecture is:

- Every user belongs to exactly one company.
- Real customer-company users should have Employee/App portal access.
- Internal ELYO platform users are assigned to the internal ELYO company but must not automatically receive Employee/App portal access.
- Company/Admin/Manager portals are additional work contexts.
- `COMPANY_MANAGER` is team-scoped.
- `COMPANY_MANAGER` receives Company portal only when `company.team_layer_enabled=true`.
- `COMPANY_ADMIN` and `COMPANY_OWNER` receive Company portal regardless of `team_layer_enabled`.
- Manager-only users with `team_layer_enabled=false` must not access `/api/company/*`.
- Angular must consume backend-provided `allowedPortals` and `activePortal`.
- Angular must not calculate portal visibility or routing from raw roles.
- Backend remains the source of truth.

## Review scope

Review the current diff/branch only.

Do not modify files.

Inspect only files relevant to portal architecture and integration, especially:

Backend:
- `apps/api-laravel/app/Models/User.php`
- `apps/api-laravel/app/Http/Middleware/PortalMiddleware.php`
- `apps/api-laravel/app/Http/Controllers/Auth/AuthController.php`
- `apps/api-laravel/routes/api.php`
- `apps/api-laravel/tests/Feature/AuthTest.php`
- `apps/api-laravel/tests/Feature/CompanyTest.php`

Angular:
- `apps/web-angular/src/app/core/models/auth.models.ts`
- `apps/web-angular/src/app/core/store/auth.store.ts`
- `apps/web-angular/src/app/core/services/auth.service.ts`
- `apps/web-angular/src/app/core/guards/auth.guards.ts`
- `apps/web-angular/src/app/app.routes.ts`
- `apps/web-angular/src/app/app.html`
- any shell/user-menu component changed by the branch

OpenAPI:
- `docs/api/openapi.yaml`, but only `/auth/login`, `/auth/me`, and portal-related 403 response schemas if changed.

Do not inspect seeders, migrations, Docker config, n8n, legacy `../ELYO`, or unrelated controllers unless the diff directly touches them.

## Required checks

1. Backend portal eligibility

Confirm:

- Real customer-company users can receive Employee/App portal access.
- Internal ELYO platform users do not receive Employee/App portal access merely because they have `company_id`.
- `COMPANY_ADMIN` and `COMPANY_OWNER` receive Company portal regardless of `team_layer_enabled`.
- `COMPANY_MANAGER` receives Company portal only when `team_layer_enabled=true`.
- Manager-only users with `team_layer_enabled=false` do not receive Company portal.
- `allowedPortals` and `activePortal` are computed consistently in login and `/auth/me`.

2. Backend route protection

Confirm:

- `/api/company/*` routes are centrally protected by `portal:company` or equivalent.
- Manager-only users with `team_layer_enabled=false` receive `403 PORTAL_FORBIDDEN` for representative company endpoints.
- Plain employees cannot access company endpoints.
- Company admins with disabled team layer can still access company endpoints.
- Manager+employee users with disabled team layer can still access employee endpoints.

3. Angular portal behavior

Confirm:

- Login redirect uses `activePortal` / `allowedPortals`, not raw roles.
- `returnUrl` is ignored if incompatible with `allowedPortals`.
- Employee/App routes are guarded by portal eligibility, not strict `Role.EMPLOYEE` only.
- Company/Admin/Partner portal links are shown only when `allowedPortals` contains that portal.
- Portal switcher shows only allowed portals.
- Current portal is marked or disabled.
- Manager-only user with `allowedPortals=['employee']` does not redirect-loop and lands in the Employee/App portal.

4. API contract

Confirm:

- `/auth/login` and `/auth/me` document `activePortal` and `allowedPortals` if OpenAPI documents those response schemas.
- Portal-related 403 responses use the actual runtime error shape.
- No broad unrelated OpenAPI cleanup was introduced.

5. Privacy and health-data safety

Confirm:

- The portal changes do not expose individual wellbeing entries, raw survey text answers, documents, or identifiable health data to company users.
- The portal changes do not weaken anonymity thresholds.
- Blocking manager-only disabled-team-layer users from company routes reduces or preserves privacy.

6. Tests

Confirm that tests cover at least:

- manager-only + disabled team layer: no Company portal
- manager-only + enabled team layer: Company portal allowed
- company admin + disabled team layer: Company portal allowed
- real company user gets Employee/App portal
- internal ELYO user does not get Employee/App portal from company assignment
- requested Company portal denied for disabled-team-layer manager
- manager+employee disabled-team-layer user can access Employee/App API
- manager-only disabled-team-layer user cannot access company dashboard/surveys
- plain employee cannot access company dashboard

## Output format

Return only:

- Verdict
- Must-fix issues with exact file/line evidence
- Should-fix issues with exact file/line evidence
- Architecture assessment
- Privacy assessment
- API contract assessment
- Test coverage assessment
- Unnecessary changes
- Open questions

Keep the review concise. Max 8 findings total.

Do not report formatting-only churn unless it hides a real behavior or contract risk.
Do not suggest broad refactors unless needed to prevent a merge blocker.
