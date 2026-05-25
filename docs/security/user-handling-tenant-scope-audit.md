# User Handling And Tenant Scope Audit

Date: 2026-05-25

## Executive Summary

The frontend stores only the Sanctum bearer token in `localStorage`; user, role, company, team, portal and allowed portal values are held in memory for UI routing and display. The Angular interceptor sends only `Authorization: Bearer ...`, not client-side role or tenant identifiers, so backend authorization remains the effective control point (`apps/web-angular/src/app/core/store/auth.store.ts:8`, `apps/web-angular/src/app/core/store/auth.store.ts:28`, `apps/web-angular/src/app/core/services/auth.interceptor.ts:5`).

Laravel protected routes are grouped behind Sanctum and role middleware (`apps/api-laravel/routes/api.php:45`, `apps/api-laravel/routes/api.php:50`, `apps/api-laravel/routes/api.php:64`, `apps/api-laravel/routes/api.php:94`). The role middleware derives authority from `$request->user()->roles`, not from request body/query fields (`apps/api-laravel/app/Http/Middleware/RoleMiddleware.php:16`).

The audit found and fixed four tenant-scope gaps:

- Employee survey `show` and `respond` accepted a survey id from another team in the same company.
- Company report `teamId` query accepted a foreign-company team id for non-manager company roles.
- Company managers could call the team members route for a non-managed team.
- Company manager user/invitation endpoints were company-wide instead of managed-team scoped; manager invitation creation is now denied until invitations can carry a team assignment.

No schema or migration changes were made. No destructive database reset commands were run.

## Frontend Authority Review

| Area | File/lines | Result |
|---|---:|---|
| Auth store persistence | `apps/web-angular/src/app/core/store/auth.store.ts:8`, `apps/web-angular/src/app/core/store/auth.store.ts:28` | Safe. Only `elyo_token` is persisted in `localStorage`. Role, company, team and active portal are in memory. |
| HTTP interceptor | `apps/web-angular/src/app/core/services/auth.interceptor.ts:5` | Safe. Sends only bearer token. |
| Login/me handling | `apps/web-angular/src/app/core/services/auth.service.ts:17`, `apps/web-angular/src/app/core/services/auth.service.ts:36` | Safe as UI state. Uses server response for navigation state; backend tests verify this state is not authoritative. |
| Portal detection | `apps/web-angular/src/app/core/services/auth.service.ts:67` | Safe. Hostname-derived portal affects UI routing only. |

## Endpoint Audit Table

| Method/path | Controller/action | Allowed roles | User id source | Company id source | Scoping enforced | Request tenant ids accepted | Rating | Reason |
|---|---|---|---|---|---|---|---|---|
| `GET /api/auth/me` | `AuthController@me` | Any authenticated | `$request->user()` | `$request->user()->company_id` | N/A | No | safe | Returns identity fields only; later backend checks use token identity (`AuthController.php:82`). |
| `POST /api/auth/logout` | `AuthController@logout` | Any authenticated | `$request->user()` | N/A | Token-scoped | No | safe | Deletes current token only (`AuthController.php:75`). |
| `GET /api/admin/companies` | `AdminCompanyController@index` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()` via middleware | Platform-wide by role | Admin role middleware | No | safe | Platform-admin endpoint by design (`routes/api.php:50`). |
| `POST /api/admin/companies` | `AdminCompanyController@store` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()->id` | New company payload | Admin role middleware | Company creation payload only | safe | Creates a new tenant; creator is server identity (`AdminCompanyController.php:22`). |
| `GET /api/admin/companies/{company}` | `AdminCompanyController@show` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()` via middleware | Route model | Admin role middleware | Route id | safe | Platform-admin endpoint by design (`AdminCompanyController.php:40`). |
| `PUT /api/admin/companies/{company}` | `AdminCompanyController@update` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()` via middleware | Route model | Admin role middleware | Route id | safe | Platform-admin endpoint by design (`AdminCompanyController.php:47`). |
| `POST /api/admin/companies/{company}/invite-company-admin` | `AdminCompanyController@inviteCompanyAdmin` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()->id` | Route model | Admin role middleware | Route id | safe | Invite company comes from route model and role is fixed to company admin (`AdminCompanyController.php:61`). |
| `GET /api/admin/partners` | `AdminPartnerController@index` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()` via middleware | N/A | Admin role middleware | No | safe | Platform-admin endpoint by design. |
| `PATCH /api/admin/partners/{id}` | `AdminPartnerController@update` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()` via middleware | N/A | Admin role middleware | Route id | safe | Platform-admin endpoint by design. |
| `GET /api/admin/points-config` | `AdminPointsController@index` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()` via middleware | N/A | Admin role middleware | No | safe | Platform-admin endpoint by design. |
| `PUT /api/admin/points-config` | `AdminPointsController@update` | `ELYO_ADMIN`, `ELYO_SUPPORT` | `$request->user()` via middleware | N/A | Admin role middleware | No tenant ids | safe | Platform-admin endpoint by design. |
| `GET /api/company/dashboard` | `CompanyController@dashboard` | Owner, admin, manager | `$request->user()` | `$user->company` | Company and manager team scope | No | safe | Uses authenticated company; manager scope derives managed team (`CompanyController.php:23`). |
| `GET /api/company/users` | `CompanyInvitationController@users` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager managed-team filter | No | safe | Fixed: manager list limited to `team_id` in managed teams (`CompanyInvitationController.php:14`). |
| `GET /api/company/invitations` | `CompanyInvitationController@invitations` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager own employee invites | No | safe | Fixed: manager sees only own employee invites (`CompanyInvitationController.php:39`). |
| `POST /api/company/invitations` | `CompanyInvitationController@storeInvitation` | Owner, admin, manager | `$request->user()->id` | `$user->company_id` | Company; manager denied | Role payload accepted for admin/owner only | safe | Fixed: company comes from auth; managers cannot create unassigned invites (`CompanyInvitationController.php:63`). |
| `DELETE /api/company/invitations/{invite}` | `CompanyInvitationController@destroyInvitation` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager own employee invites | Route id | safe | Fixed: foreign company and unauthorized manager revokes return 403 (`CompanyInvitationController.php:111`). |
| `GET /api/company/teams` | `TeamController@index` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager managed teams | No | safe | Manager query filters by `manager_id` and company (`TeamController.php:14`). |
| `POST /api/company/teams` | `TeamController@store` | Owner, admin, manager route; request authorizes owner/admin | `$request->user()` | `$request->user()->company_id` | Company | `managerId` validated in company | safe | Company id is server-derived; request denies managers (`TeamController.php:34`). |
| `GET /api/company/teams/{id}` | `TeamController@show` | Owner, admin, manager | `$request->user()` | `$request->user()->company_id` | Company; manager managed team | Route id | safe | Foreign company returns 404; non-managed team returns 403 (`TeamController.php:49`). |
| `PUT /api/company/teams/{id}` | `TeamController@update` | Owner, admin, manager route; request authorizes owner/admin | `$request->user()` | `$request->user()->company_id` | Company | `managerId` validated in company | safe | Company id is route query scope; manager blocked by request (`TeamController.php:63`). |
| `DELETE /api/company/teams/{id}` | `TeamController@destroy` | Owner, admin, manager route; action owner/admin only | `$request->user()` | `$request->user()->company_id` | Company; owner/admin only | Route id | safe | Manager explicitly forbidden (`TeamController.php:80`). |
| `GET /api/company/teams/{teamId}/members` | `TeamController@members` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager managed team | Route id | safe | Fixed: manager non-managed team returns 403 (`TeamController.php:96`). |
| `GET /api/company/surveys` | `CompanySurveyController@index` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager accessible surveys | No | safe | Company query plus manager scope (`CompanySurveyController.php:25`). |
| `POST /api/company/surveys` | `CompanySurveyController@store` | Owner, admin, manager | `$request->user()->id` | `$request->user()->company_id` | Company; manager writable teams | `teamIds` validated and normalized | safe | Team ids are validated against company and manager-owned teams (`CompanySurveyController.php:48`, `CompanySurveyController.php:247`). |
| `GET /api/company/surveys/{id}` | `CompanySurveyController@show` | Owner, admin, manager | `$request->user()` | `$request->user()->company_id` | Company; manager accessible surveys | Route id | safe | Company route id is scoped; manager access checked (`CompanySurveyController.php:80`). |
| `PATCH /api/company/surveys/{id}` | `CompanySurveyController@update` | Owner, admin, manager | `$request->user()` | `$request->user()->company_id` | Company; manager edit rules | `teamIds` validated and normalized | safe | Route id scoped to company; manager can edit only allowed draft surveys (`CompanySurveyController.php:93`). |
| `POST /api/company/surveys/{id}/activate` | `CompanySurveyController@activate` | Owner, admin, manager | `$request->user()` | `$request->user()->company_id` | Company; manager edit rules | Route id | safe | Company route id scoped; manager edit check (`CompanySurveyController.php:132`). |
| `DELETE /api/company/surveys/{id}` | `CompanySurveyController@destroy` | Owner, admin, manager | `$request->user()` | `$request->user()->company_id` | Company; manager edit rules | Route id | safe | Company route id scoped; manager edit check (`CompanySurveyController.php:151`). |
| `GET /api/company/surveys/{id}/results` | `CompanySurveyController@results` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager result team scope; threshold | Route id | safe | Company route id scoped; aggregation enforces threshold and manager team scope (`CompanySurveyController.php:165`). |
| `GET /api/company/measures` | `MeasureController@index` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager team/global measures | No | safe | Company query plus manager team filter (`MeasureController.php:16`). |
| `POST /api/company/measures` | `MeasureController@store` | Owner, admin, manager | `$request->user()->id` | `$user->company_id` | Company; manager team override | `teamId` validated in company | safe | Company id and creator are auth-derived; managers forced to managed team (`MeasureController.php:44`). |
| `PATCH /api/company/measures/{id}` | `MeasureController@update` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager managed team | Route id | safe | Company route id scoped; manager non-managed measures forbidden (`MeasureController.php:75`). |
| `GET /api/company/reports` | `ReportController@index` | Owner, admin, manager | `$request->user()` | `$user->company_id` | Company; manager managed team; threshold | `teamId` checked in company | safe | Fixed: foreign `teamId` returns 403; manager ignores query and uses managed team (`ReportController.php:21`). |
| `GET /api/employee/dashboard` | `EmployeeController@dashboard` | Employee | `$request->user()` | `$user->company_id` | Own entries only | No | safe | Uses authenticated user relation and company filter (`EmployeeController.php:26`). |
| `GET /api/employee/checkin/status` | `EmployeeController@checkinStatus` | Employee | `$request->user()` | `$request->user()->company_id` | Own daily period | No | safe | Uses auth user and period key (`EmployeeController.php:47`). |
| `POST /api/employee/checkin` | `EmployeeController@checkin` | Employee | `$request->user()` | `$user->company_id` | Own daily period; duplicate guard | Ignores forged ids | safe | Uses auth user; duplicate check prevents point/streak update (`EmployeeController.php:61`). |
| `GET /api/employee/history` | `EmployeeController@history` | Employee | `$request->user()` | `$request->user()->company_id` | Own entries only | No | safe | Uses auth user relation and company filter (`EmployeeController.php:104`). |
| `GET /api/employee/profile` | `EmployeeController@getProfile` | Employee | `$request->user()` | N/A | Own profile only | No | safe | Loads own anamnesis/documents only (`EmployeeController.php:118`). |
| `PUT /api/employee/profile` | `EmployeeController@updateProfile` | Employee | `$request->user()` | N/A | Own profile only | Ignores forged ids | safe | Updates auth user and `updateOrCreate(['user_id' => $user->id])` (`EmployeeController.php:145`). |
| `POST /api/employee/documents` | `EmployeeController@uploadDocument` | Employee | `$request->user()->id` | N/A | Own document only | No | safe | Stores under auth user id and creates own document (`EmployeeController.php:182`). |
| `GET /api/employee/measures` | `EmployeeController@measures` | Employee | `$request->user()` | `$user->company_id` | Company plus own team/global | No | safe | Query scopes by authenticated company and team (`EmployeeController.php:215`). |
| `GET /api/employee/surveys` | `Employee\SurveyController@index` | Employee | `$request->user()` | `$user->company_id` | Company plus own team/global | No | safe | Company and team scope enforced (`Employee/SurveyController.php:18`). |
| `GET /api/employee/surveys/{id}` | `Employee\SurveyController@show` | Employee | `$request->user()` | `$user->company_id` | Company plus own team/global | Route id | safe | Fixed: route id now also checks accessible survey team scope (`Employee/SurveyController.php:38`). |
| `GET /api/employee/surveys/{id}/result` | `Employee\SurveyController@result` | Employee | `$request->user()` | `$user->company_id` | Company plus own response | Route id | safe | Returns only authenticated user's own response (`Employee/SurveyController.php:62`). |
| `POST /api/employee/surveys/{id}/respond` | `Employee\SurveyController@respond` | Employee | `$request->user()->id` | `$user->company_id` | Company plus own team/global; duplicate response guard | Ignores forged ids | safe | Fixed: route id now checks accessible survey team scope; creates response with auth user/company (`Employee/SurveyController.php:110`). |

## Findings

### Fixed: employee survey route id could bypass team targeting

Before the fix, employees could open or answer an active same-company survey assigned to a different team if they knew the survey id. The list endpoint had correct team filtering, but `show` and `respond` did not repeat it. The fix adds the same global-or-own-team scope to both actions (`apps/api-laravel/app/Http/Controllers/Employee/SurveyController.php:38`, `apps/api-laravel/app/Http/Controllers/Employee/SurveyController.php:110`).

### Fixed: report `teamId` could reference another company

Company admins/owners could pass a foreign `teamId` query parameter to `/api/company/reports`. The aggregation still received the authenticated company id, but accepting an out-of-company team id was an API contract and scoping bug. The fix rejects non-manager report requests when `teamId` is not owned by the authenticated company (`apps/api-laravel/app/Http/Controllers/Company/ReportController.php:34`).

### Fixed: manager team members endpoint allowed non-managed team route ids

`GET /api/company/teams/{teamId}/members` checked company ownership but did not apply the manager's managed-team restriction. The fix now returns 403 for manager-only users requesting a same-company team they do not manage (`apps/api-laravel/app/Http/Controllers/Company/TeamController.php:96`).

### Fixed: manager user and invitation views were company-wide

Company managers could list all company users and all company invitations, and could revoke company-wide pending invites. Manager invitation creation also had no way to assign the invite to the managed team because `invite_tokens` has no `team_id`. The fix scopes manager user lists to managed teams, scopes invitation lists/revokes to the manager's own employee invites, and denies manager invitation creation until team-scoped invitations exist (`apps/api-laravel/app/Http/Controllers/Company/CompanyInvitationController.php:14`, `apps/api-laravel/app/Http/Controllers/Company/CompanyInvitationController.php:39`, `apps/api-laravel/app/Http/Controllers/Company/CompanyInvitationController.php:63`, `apps/api-laravel/app/Http/Controllers/Company/CompanyInvitationController.php:111`).

## Tests Added

Added `apps/api-laravel/tests/Feature/TenantScopeTest.php`.

Coverage includes:

- Employee-forged `user_id`, `userId`, `company_id`, `companyId`, and `role` are ignored on check-in (`TenantScopeTest.php:23`).
- Employee-forged ids are ignored on profile update (`TenantScopeTest.php:51`).
- Employees cannot view or respond to another team's survey (`TenantScopeTest.php:70`).
- Company admins cannot create measures or reports with foreign-company `teamId` (`TenantScopeTest.php:102`).
- Company admins cannot access foreign-company team, measure, or survey route ids (`TenantScopeTest.php:122`).
- Managers cannot access non-managed teams or create out-of-scope resources (`TenantScopeTest.php:138`).
- Managers see only users in managed teams (`TenantScopeTest.php:168`).
- Managers see/revoke only their own employee invites (`TenantScopeTest.php:196`).
- Forged `role` or `activePortal` does not bypass backend role middleware (`TenantScopeTest.php:232`).
- `/api/auth/me` identity values are not accepted back as authority (`TenantScopeTest.php:247`).
- Company admins cannot use forged company query parameters when listing users (`TenantScopeTest.php:262`).

## Commands Run

```bash
git status --short
find apps/web-angular/src -type f
find apps/api-laravel/app apps/api-laravel/routes apps/api-laravel/tests/Feature -type f
rg "company_id|companyId|user_id|userId|team_id|teamId|role|managedTeamId|auth\\(\\)->user\\(\\)|\\$request->user\\(\\)|\\$request->input|validated\\(|localStorage|sessionStorage" apps/web-angular apps/api-laravel
docker compose exec api php artisan route:list
docker compose exec api php artisan test --filter=Tenant
docker compose exec api php artisan test --filter=Auth
docker compose exec api php artisan test
```

Test results:

- `php artisan route:list`: passed, 59 routes.
- `php artisan test --filter=Tenant`: passed, 11 tests and 34 assertions.
- `php artisan test --filter=Auth`: passed, 24 tests and 56 assertions.
- `php artisan test`: passed, 85 tests and 297 assertions.

## Remaining Risks

- Manager invitation creation is now denied because the current invitation schema has no team assignment. If product requirements need managers to invite employees directly, add a team-scoped invite contract and persistence first.
- `/api/auth/me` intentionally returns `companyId`, `teamId`, `roles`, and `allowedPortals` for UI state. These fields must remain display/navigation data only; backend tests now cover forged role/company attempts.
- Employee survey result access returns only the authenticated user's own submitted response. It does not re-check current team assignment after submission. This is treated as own-data access, but product should confirm whether historical own survey results should remain visible after team changes.
- Platform admin routes are intentionally tenant-wide for `ELYO_ADMIN` and `ELYO_SUPPORT`.
- Partner routes use a separate guard and were reviewed for tenant-authority concerns only at the route boundary; `POST /api/partner/documents` remains a placeholder.

## Final Verdict

Approved after fixes. The current protected API derives user and company authority from server-side authenticated identity, validates or ignores frontend-controlled tenant/user fields, and has regression coverage for forged user, company, team, role, and portal attempts.
