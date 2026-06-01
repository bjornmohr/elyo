# Task: Angular portal switcher based on backend allowedPortals

## Goal

Update Angular portal navigation so the frontend uses backend-provided `allowedPortals` and `activePortal` as the source of truth.

Users with multiple allowed portals should be able to switch between Employee/App and Company/Admin contexts from the user menu. Users with only Employee/App access should land there after login, even if they have manager/admin roles that are not company-portal eligible.

## Architectural rules

- Backend remains the source of truth for portal eligibility.
- Angular must not calculate portal access from raw roles.
- Angular must consume `allowedPortals` and `activePortal` from the auth response/AuthStore.
- Company portal access must not be shown if `allowedPortals` excludes `company`.
- Employee/App portal access must be shown if `allowedPortals` includes the existing employee/app portal identifier.
- Do not introduce a new portal identifier.
- Do not broaden manager permissions in Angular.
- Route guards are UX/navigation protection only; backend remains the authorization boundary.

## Scope

Angular-only unless a clear auth model type mismatch appears.

Allowed files likely:
- apps/web-angular/src/app/core/models/auth.models.ts
- apps/web-angular/src/app/core/store/auth.store.ts
- apps/web-angular/src/app/core/guards/auth.guards.ts
- apps/web-angular/src/app/app.routes.ts
- apps/web-angular/src/app/app.html
- existing shell/user-menu components if present

Do not change:
- Laravel/backend behavior
- OpenAPI unless TypeScript contract mismatch proves the schema is wrong
- seeders
- migrations
- Docker/config
- unrelated UI pages

## Required behavior

1. Portal identifiers

Before patching, inspect current auth responses and Angular code to determine the existing employee/app portal identifier.

- Do not invent a new portal name.
- Use the existing identifier consistently.
- If the backend uses `employee`, keep `employee`.
- If the backend uses `app`, keep `app`.

2. Login redirect

After successful login:
- Use backend `activePortal` if present and allowed.
- If `activePortal` is missing, fall back to the first usable portal from `allowedPortals`.
- If the resolved portal is company, navigate to the company portal route.
- If the resolved portal is employee/app, navigate to the employee/app route.
- If the resolved portal is admin/platform, navigate to admin/platform route.
- If no portal is allowed, show a clear error instead of staying silently on the login page or redirect-looping.

Do not route based on raw roles.

3. Route guards

- Company routes should be guarded by portal eligibility, not only role checks.
- Employee/app routes should be guarded by portal eligibility if such a guard exists or can be safely introduced.
- Do not keep a route guard that blocks employee/app access solely because a real company user lacks explicit `EMPLOYEE` role while backend `allowedPortals` includes employee/app.
- Avoid redirect loops: a denied portal route should redirect to another allowed portal or a clear no-access/login state.

4. Main navigation

- Show Company navigation/switch only if `allowedPortals` includes `company`.
- Show Employee/App navigation/switch only if `allowedPortals` includes the existing employee/app identifier.
- Show Admin/Platform navigation/switch only if `allowedPortals` includes the existing admin/platform identifier.
- Do not show portal links from raw roles.

5. User menu portal switch

Add or update the top-right user menu so users with multiple allowed portals can switch portals.

- Show only allowed portals.
- Mark the current portal as active or disabled.
- Suggested labels:
  - Employee/App portal: `Employee-Portal`
  - Company portal: `Company-Portal`
  - Admin/Platform portal: `Admin-Portal`
- Keep the UI minimal.
- Do not redesign the layout.

6. Manager disabled-team-layer behavior

For manager-only users whose backend allowedPortals excludes `company`:
- Do not show the Company portal.
- If employee/app is allowed, route them there.
- Do not require raw `EMPLOYEE` role in Angular if backend allowedPortals grants employee/app access.
- Do not make them company-wide admins.

## Tests / validation

Prefer build/type validation unless the project already has focused Angular tests for guards/auth.

Run:
- docker compose exec web npm run build
- git diff --check

If backend files are unexpectedly changed:
- docker compose exec api php artisan test

## Out of scope

- No backend portal eligibility changes.
- No company route middleware changes.
- No seed changes.
- No OpenAPI cleanup.
- No database/schema changes.
- No privacy threshold changes.
- No broad UI redesign.
- No destructive commands.

## Expected output

Report:
- changed Angular files
- portal identifier used
- login redirect behavior
- route guard behavior
- user menu portal switch behavior
- validation commands run
- risks/open questions

## Implementation Plan

1. Preserve the existing portal contract.
   - Use the current Angular `Portal` union from `apps/web-angular/src/app/core/models/auth.models.ts`: `admin`, `company`, `employee`, `partner`.
   - Treat `employee` as the existing Employee/App portal identifier.
   - Do not introduce `app` or any other new portal identifier unless a contract mismatch is proven before patching.

2. Centralize portal resolution in Angular auth code.
   - Add or update a small helper in `AuthService` or `AuthStore` that resolves the effective portal from `activePortal` plus `allowedPortals`.
   - Resolution order: backend `activePortal` if it is present and included in `allowedPortals`; otherwise the first entry in `allowedPortals`; otherwise `null`.
   - Keep `getDefaultRoute(portal)` as the route mapping for `admin`, `company`, `employee`, and `partner`.
   - Ensure login and `/auth/me` restoration use this helper so they do not navigate from raw roles.

3. Fix login redirect behavior.
   - In `LoginComponent`, redirect after login using the resolved effective portal, not blindly using `res.activePortal`.
   - When a safe `returnUrl` exists, only honor it if it does not point to `/auth/login` and is compatible with the user’s allowed portals; otherwise redirect to the resolved portal default route.
   - If no portal can be resolved from `allowedPortals`, show a clear no-access error and avoid silent login-page loops.

4. Update route guards to use portal eligibility.
   - Keep `authGuard` responsible for authentication and user restoration.
   - Use `portalGuard('employee')` for employee routes instead of requiring `Role.EMPLOYEE`, so manager/admin users can reach Employee/App only when the backend grants the `employee` portal.
   - Keep `portalGuard('company')` on company routes; retain any role guard only if it does not broaden access beyond backend eligibility, with backend authorization still authoritative.
   - Add `portalGuard('admin')` to admin routes and `portalGuard('partner')` to partner routes so all portal routes consistently rely on `allowedPortals`.
   - Make denied portal routes redirect to another allowed portal default route, or to a clear login/no-access state if no portals are allowed.

5. Update top navigation and user menu behavior.
   - Replace role-derived portal links in `app.html` with `allowedPortals()` checks:
     - `employee` shows Employee/App navigation.
     - `company` shows Company navigation.
     - `admin` shows Admin navigation.
     - `partner` shows Partner navigation if retained in the current UI.
   - Add a minimal portal switch area in the authenticated user menu/header when more than one portal is allowed.
   - Show only allowed portals, label them `Employee-Portal`, `Company-Portal`, `Admin-Portal`, and `Partner-Portal` if partner is present.
   - Mark or disable the currently active portal.
   - On switch, set `activePortal` in `AuthStore` and navigate to `AuthService.getDefaultRoute(portal)`.

6. Keep shell/sidebar behavior narrow.
   - Do not redesign the existing shell components.
   - Only touch shell components if the portal switch must be available inside active portal layouts and cannot be cleanly handled from `app.html`.
   - Do not expose company navigation to manager-only users when `allowedPortals` excludes `company`.

7. Validation to run during patch mode, not during this planning step.
   - `docker compose exec web npm run build`
   - `git diff --check`
   - Run backend tests only if backend files are unexpectedly changed, which this task should avoid.

8. Review checks before handoff.
   - Confirm no backend, OpenAPI, migration, Docker, or unrelated documentation files changed.
   - Confirm Angular no longer calculates portal navigation from raw roles.
   - Confirm employee access is based on `allowedPortals.includes('employee')`.
   - Confirm company/admin/partner portal links are hidden unless the matching portal is allowed.
   - Confirm no health data display or company reporting behavior changed.

Open questions:
- Whether the existing `returnUrl` redirect should be strictly portal-compatible or always ignored when it conflicts with `allowedPortals`; the safer implementation is to reject conflicting return URLs and use the resolved portal default route.
- Whether partner portal switching should be included in the visible user menu; the current type and routes include `partner`, so the plan keeps it if `allowedPortals` contains `partner`.

## Approved Review Notes

Use the plan with these clarifications:

1. Return URL handling:
   - A returnUrl may only be honored if it points to a route belonging to one of the user's backend-provided allowedPortals.
   - If returnUrl points to a forbidden portal route, ignore it and navigate to the resolved portal default route.
   - Never redirect a user back to /auth/login after successful login unless there is no allowed portal and an explicit no-access error is shown.

2. Partner portal:
   - Do not introduce or refactor partner auth/session behavior.
   - Only show Partner-Portal in the switcher if the existing AuthStore/auth response already includes `partner` in allowedPortals and the route mapping already exists.
   - Do not connect partner cookie/session login to this task.

3. Route guards:
   - Employee/App routes must be gated by portal eligibility, not raw Role.EMPLOYEE.
   - Company/Admin/Partner routes should also rely on portal eligibility where applicable.
   - Do not remove backend authorization assumptions; frontend guards are UX/navigation only.

4. UI scope:
   - Keep the portal switch minimal.
   - Prefer adding it to the existing top-right user/account menu if present.
   - Do not redesign shell layouts.
