# Task: Enforce company portal eligibility on company routes

## Goal

Ensure `/api/company/*` routes are centrally protected by backend company portal eligibility.

Frontend `allowedPortals` is UX/navigation only. Laravel must enforce company portal access before company controllers expose company dashboard, survey, report, measure, team, invitation, or user-management data.

## Architectural rules

- Backend owns portal eligibility.
- Real company users may have Employee/App portal access.
- Company portal access is additive.
- `COMPANY_OWNER` and `COMPANY_ADMIN` can access the Company portal regardless of `company.team_layer_enabled`.
- `COMPANY_MANAGER` can access the Company portal only when `company.team_layer_enabled=true`.
- `COMPANY_MANAGER` with `team_layer_enabled=false` must keep Employee/App access where allowed but must not access `/api/company/*`.
- Plain employees must not access `/api/company/*`.
- Do not broaden managers into company-wide access.

## Scope

Backend-only.

Allowed files:
- `apps/api-laravel/routes/api.php` only if route middleware wiring is incomplete.
- `apps/api-laravel/app/Http/Middleware/PortalMiddleware.php` only if company portal eligibility is not centrally enforced.
- `apps/api-laravel/app/Models/User.php` only if `canUsePortal('company')` behavior is incorrect.
- Backend tests, preferably `apps/api-laravel/tests/Feature/CompanyTest.php` and `apps/api-laravel/tests/Feature/AuthTest.php`.

Do not change:
- Angular
- OpenAPI
- seeders
- migrations
- Docker/config
- company controllers unless a route bypass makes that unavoidable
- privacy threshold logic

## Required inspection

1. Inspect `apps/api-laravel/routes/api.php`.
   - Verify every `/api/company/*` route is inside the authenticated company group.
   - Verify the group applies role middleware for company roles and central `portal:company` middleware.
   - If any company route is outside the protected group, move only that route into the guarded group or apply equivalent middleware locally.

2. Inspect `PortalMiddleware`.
   - Verify it denies users when `$user->canUsePortal('company')` is false.
   - Verify it returns the existing `PORTAL_FORBIDDEN` 403 envelope.
   - Verify it runs after authentication and does not duplicate controller logic unnecessarily.

3. Inspect `User::canUsePortal('company')`.
   - Verify `COMPANY_OWNER` and `COMPANY_ADMIN` pass regardless of `team_layer_enabled`.
   - Verify `COMPANY_MANAGER` passes only when `team_layer_enabled=true`.
   - Verify `COMPANY_MANAGER` with `team_layer_enabled=false` fails Company portal but keeps Employee/App portal according to the previous task.

## Required tests

Add or keep focused feature tests:

1. Manager-only disabled team layer is denied from company dashboard.
   - User: role `COMPANY_MANAGER`
   - Company: `team_layer_enabled=false`
   - Request: `GET /api/company/dashboard`
   - Expect: `403`, `error.code = PORTAL_FORBIDDEN`

2. Manager-only disabled team layer is denied from company surveys.
   - Request: `GET /api/company/surveys`
   - Expect: `403`, `error.code = PORTAL_FORBIDDEN`

3. Manager-only enabled team layer can access a representative allowed company endpoint.
   - Company: `team_layer_enabled=true`
   - Request: a company endpoint that managers are allowed to access
   - Expect: successful response or non-portal-forbidden behavior.
   - Do not broaden manager access to admin-only endpoints.

4. Company admin disabled team layer can access company dashboard.
   - Role: `COMPANY_ADMIN`
   - Company: `team_layer_enabled=false`
   - Request: `GET /api/company/dashboard`
   - Expect: `200`

5. Plain employee cannot access representative company endpoints.
   - Role: `EMPLOYEE`
   - Request: `GET /api/company/dashboard`
   - Expect: `403`

6. Manager plus employee disabled team layer can still access employee API.
   - Roles: `COMPANY_MANAGER`, `EMPLOYEE`
   - Company: `team_layer_enabled=false`
   - Request: `GET /api/employee/dashboard` or closest existing employee dashboard endpoint.
   - Expect: `200`

## Error behavior

- Use the existing portal-forbidden response envelope.
- Do not add new error taxonomy.
- Do not convert this into `TEAM_LAYER_DISABLED`; this is portal access denial.

## Out of scope

- No Angular changes.
- No OpenAPI changes.
- No seed changes.
- No migrations.
- No privacy threshold changes.
- No controller-level TeamLayerGuard cleanup.
- No destructive database commands.

## Validation

Run:

- `docker compose exec api php artisan test --filter=CompanyTest`
- `docker compose exec api php artisan test --filter=AuthTest`
- `docker compose exec api php artisan test`
- `git diff --check`

## Expected output

Report:

- middleware/guard changes
- route behavior changes
- tests added or updated
- validation commands/results
- risks/open questions
