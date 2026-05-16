# Auth, Portal & Data Model Migration Plan

## 1. Current Legacy Auth Assumptions (../ELYO React Prototype)

- Uses NextAuth with credentials provider (email + password).
- Single `role` field on session user: `EMPLOYEE` or company role.
- Role-based redirect after login: EMPLOYEE → `/employee/dashboard`, else → `/company/dashboard`.
- No portal/subdomain concept in the prototype — routing is purely role-based.
- Public company registration exists at `/auth/register` (companyName, name, email, password).
- Invite flow: `/auth/invite/[token]` — verifies token via GET, accepts via POST with name + password.
- Partner has separate auth (`/partner/login`, `/partner/register`) with its own model.
- Admin routes exist for partner management only (`/admin/partners`).
- No admin company CRUD in the legacy prototype.
- German UI language throughout.

## 2. Current Angular Auth Implementation

- **Models**: Single `Role` enum (EMPLOYEE, COMPANY_MANAGER, COMPANY_ADMIN, ELYO_ADMIN, PARTNER). Single `role` on `User` interface.
- **AuthStore**: Signal-based, stores user + token in localStorage. `hasRole()` checks single role.
- **AuthService**: login/logout/getMe via ApiClient. Login expects `{user, token}` response (mismatch with current API which returns `{access_token, token_type}`).
- **Guards**: `authGuard` checks `isAuthenticated()`, `roleGuard` checks `hasRole()`.
- **Login component**: Reactive forms, visually matches React prototype well (teal gradient, Fraunces font, split-panel layout via AuthLayoutComponent).
- **Routes**: auth/login, auth/register, auth/invite/:token, employee/*, company (single route), partner (placeholder), admin (placeholder).
- **No portal context**: No activePortal, no subdomain detection, no portal shells.
- **No Angular Material**: Uses Tailwind CSS only.

## 3. Current Laravel Auth Implementation

- **User model**: String UUID PK, single `role` column (Role enum), `password_hash` field (not `password`), `is_active` boolean, belongs to Company.
- **Role enum**: COMPANY_ADMIN, COMPANY_MANAGER, EMPLOYEE, ELYO_ADMIN (missing: ELYO_SUPPORT, COMPANY_OWNER, PARTNER).
- **AuthController**: Public `register()` creates company + COMPANY_ADMIN user. `login()` returns Sanctum token. `me()` returns user data.
- **InviteController**: `verify()` looks up raw token. `accept()` creates user with role/company from invite.
- **RoleMiddleware**: Checks single `role->value` against allowed roles.
- **InviteToken model**: Stores raw token (not hashed), `used_at` instead of `status`/`accepted_at`, no `invited_by_user_id`, no `token_hash`.
- **Company model**: String UUID PK, many extra fields (logo_url, primary_color, industry, etc.), no `status`, no `created_by_elyo_admin_id`.
- **No user_roles table**: Single role per user.
- **No admin company CRUD routes**.
- **No company user/invitation management routes**.
- **Partner**: Separate auth system with own model and `auth:partner` guard.

## 4. Current Migration State

| Migration | Tables | Status |
|-----------|--------|--------|
| 000001_create_core_tables | companies, users, teams, wellbeing_entries | **Replace** — string IDs, missing columns, wrong password field |
| 000002_create_survey_tables | surveys, survey_questions, survey_responses, survey_answers | **Keep with modifications** — align IDs to bigint |
| 000003_create_profile_and_document_tables | anamnesis_profiles, health_documents, user_documents | **Keep with modifications** |
| 000004_create_points_tables | user_points, point_transactions | **Keep with modifications** |
| 000005_create_remaining_tables | invite_tokens, partners, measures, wearable_*, push_subscriptions, notification_preferences | **Replace** — invite_tokens needs redesign |
| 2026_04_27_* | personal_access_tokens, cache, jobs, queue, sessions, failed_jobs | **Keep** — Laravel infrastructure |

## 5. Migration Decisions

### Remove/Replace
- **000001**: Replace. Switch to bigint auto-increment IDs. Add `password` (not `password_hash`), `status` enum, remove single `role` column, add `company_id` FK properly. Remove `is_active`, `avatar_url`, `team_id` from users (team_id stays as FK but added after teams table).
- **000005**: Replace invite_tokens table. Add `token_hash` (not raw `token`), `status` enum, `invited_by_user_id`, `accepted_at`. Make `company_id` nullable for platform invites.

### Add New
- **user_roles** table: `id`, `user_id`, `role`, `created_at`, `updated_at`.

### Keep (with ID type alignment)
- Survey tables, profile/document tables, points tables — adjust FK types to match new bigint users/companies IDs.
- Laravel infrastructure tables (sessions, cache, jobs, etc.).

## 6. Final Data Model

### users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK auto | |
| name | string | |
| email | string unique | |
| password | string | Hashed via bcrypt |
| company_id | bigint nullable FK → companies | Null only for ELYO_ADMIN/ELYO_SUPPORT |
| status | string default 'active' | active, inactive, suspended |
| last_login_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### companies
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK auto | |
| name | string | |
| slug | string unique | |
| status | string default 'active' | active, inactive, suspended |
| anonymity_threshold | integer default 5 | |
| created_by_elyo_admin_id | bigint nullable FK → users | |
| created_at | timestamp | |
| updated_at | timestamp | |

### user_roles
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK auto | |
| user_id | bigint FK → users | |
| role | string | ELYO_ADMIN, ELYO_SUPPORT, COMPANY_OWNER, COMPANY_ADMIN, COMPANY_MANAGER, EMPLOYEE, PARTNER |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique constraint on (user_id, role).

### invite_tokens
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK auto | |
| company_id | bigint nullable FK → companies | Null for platform/admin invites |
| email | string | |
| role | string | |
| token_hash | string unique | SHA-256 hash of raw token |
| status | string default 'pending' | pending, accepted, expired, revoked |
| invited_by_user_id | bigint nullable FK → users | |
| expires_at | timestamp | |
| accepted_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### teams
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK auto | |
| name | string | |
| description | text nullable | |
| color | string nullable | |
| company_id | bigint FK → companies | |
| manager_id | bigint nullable FK → users | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Additional domain tables (kept/aligned)
- wellbeing_entries
- surveys, survey_questions, survey_responses, survey_answers
- anamnesis_profiles, health_documents, user_documents
- user_points, point_transactions
- measures
- partners
- wearable_connections, wearable_syncs
- push_subscriptions, notification_preferences

All FKs to users/companies updated to bigint.

## 7. Required API Routes

### Auth
- `POST /api/auth/login` — email, password, requested_portal
- `POST /api/auth/logout`
- `GET /api/auth/me` — user, roles, company, allowedPortals
- `GET /api/auth/invite/verify` — token query param
- `POST /api/auth/invite/accept` — token, name, password, password_confirmation

### Admin (ELYO_ADMIN only)
- `GET /api/admin/companies`
- `POST /api/admin/companies`
- `GET /api/admin/companies/{company}`
- `PUT /api/admin/companies/{company}`
- `POST /api/admin/companies/{company}/invite-company-admin`
- `GET /api/admin/users` (if needed)

### Company (COMPANY_OWNER, COMPANY_ADMIN, COMPANY_MANAGER)
- `GET /api/company/dashboard`
- `GET /api/company/users`
- `GET /api/company/invitations`
- `POST /api/company/invitations`
- `DELETE /api/company/invitations/{invite}`
- `GET /api/company/teams` (existing)
- `POST /api/company/teams` (existing)
- `GET /api/company/surveys` (existing)
- `GET /api/company/measures` (existing)
- `GET /api/company/reports` (existing)

### Employee (EMPLOYEE)
- `GET /api/employee/dashboard`
- `POST /api/employee/checkin`
- `GET /api/employee/history`
- `GET /api/employee/profile`
- `PUT /api/employee/profile`
- `GET /api/employee/surveys`

### Partner (if supported — currently has separate auth)
- Existing partner routes remain for now.

## 8. Required Angular Routes

- `/auth/login`
- `/auth/register`
- `/auth/invite/:token`
- `/employee/dashboard`
- `/employee/checkin`
- `/employee/history`
- `/employee/profile`
- `/employee/surveys`
- `/company/dashboard`
- `/company/users`
- `/company/invitations`
- `/company/teams`
- `/company/surveys`
- `/company/measures`
- `/company/reports`
- `/admin/companies`
- `/admin/companies/create`
- `/admin/users`
- `/partner/dashboard` (if supported)

## 9. Required Portal Layouts/Shells

| Shell | Portal | Roles |
|-------|--------|-------|
| PublicAuthShell | — | Unauthenticated (login, register, invite) |
| EmployeeShell | employee | EMPLOYEE |
| CompanyShell | company | COMPANY_OWNER, COMPANY_ADMIN, COMPANY_MANAGER |
| AdminShell | admin | ELYO_ADMIN, ELYO_SUPPORT |
| PartnerShell | partner | PARTNER (if supported) |

Each shell has its own sidebar navigation. Portal is determined by hostname/subdomain.

## 10. Visual Parity Risks

1. **Auth layout**: Angular already matches React prototype well (split-panel, teal gradient, Fraunces font). Minor alignment needed.
2. **Company dashboard**: React has TrendChartClient with charts — Angular has no chart library. Will need empty/placeholder states.
3. **Employee dashboard**: React has DashboardClient — need to match layout without inventing data.
4. **Navigation sidebars**: React uses custom sidebars per portal group — Angular needs matching shells.
5. **Angular Material integration**: Must theme Material components to match ELYO teal/warm palette. Default purple theme must not appear.
6. **Tables**: React uses custom styled tables — Material mat-table needs ELYO styling.
7. **Cards/surfaces**: React uses shadow-card, warm backgrounds — Material mat-card needs custom theme.
8. **German language**: All UI text must remain in German.
9. **Fonts**: Fraunces (display) + DM Sans (body) must be loaded in Angular.

## 11. Open Questions

1. **Partner portal**: Legacy has separate partner auth system. Should this be unified into the main users table with PARTNER role, or kept separate for MVP? **Recommendation**: Keep separate for now, document for future unification.
2. **COMPANY_OWNER vs COMPANY_ADMIN**: Legacy only has COMPANY_ADMIN. Is COMPANY_OWNER needed for MVP or can it be added later? **Recommendation**: Add to enum/model but treat as equivalent to COMPANY_ADMIN for MVP permissions.
3. **ELYO_SUPPORT**: Not in legacy. Add to enum but no special handling needed for MVP beyond admin portal access.
4. **Chart library**: Company dashboard in React has trend charts. Should we add a chart library (e.g., ngx-charts) or use empty states? **Recommendation**: Empty states for MVP, document chart requirement.
5. **Wearable tables**: Keep in migrations but not actively used in MVP.
6. **team_id on users**: Keep as FK for employee-team assignment, but not part of core auth model.
7. **Public register endpoint**: Must be removed/disabled per requirements. Show invitation-only message instead.
