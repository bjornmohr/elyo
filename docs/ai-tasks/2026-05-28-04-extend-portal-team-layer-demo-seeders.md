# Task: Extend demo seeders for portal and team-layer smoke testing

## Goal

Extend local/demo seed data so the current portal architecture and optional team-layer behavior can be smoke-tested end-to-end after a fresh local migration.

This task is seed/demo-data only. It must not introduce new product behavior.

## Current architecture

The current target architecture is:

- Every user belongs to exactly one company.
- Real customer-company users receive Employee/App portal access from backend portal eligibility.
- Internal ELYO platform users are assigned to the internal ELYO company but must not automatically receive Employee/App portal access.
- Company portal access is additive.
- `COMPANY_ADMIN` and `COMPANY_OWNER` receive Company portal regardless of `company.team_layer_enabled`.
- `COMPANY_MANAGER` receives Company portal only when `company.team_layer_enabled=true`.
- Manager-only users with `team_layer_enabled=false` must not access `/api/company/*`.
- Employee/App routes remain usable for real company users according to backend portal eligibility.
- `users.team_id` remains nullable and represents the user's own organizational team affiliation.
- Team-scoped workflows are disabled when `company.team_layer_enabled=false`.
- Company-level dashboard, reports, surveys, and measures remain available for company admins when team layer is disabled.
- Employee survey list/show/respond must not expose team-targeted surveys when team layer is disabled.

## Scope

Expected implementation target:

- `apps/api-laravel/database/seeders/DemoDataSeeder.php`

Only change these files if inspection proves a necessary seed-order or constant issue:

- `apps/api-laravel/database/seeders/DatabaseSeeder.php`
- `apps/api-laravel/database/seeders/PointSettingsSeeder.php`

Do not change:

- migrations
- models
- controllers
- middleware
- routes
- Angular
- OpenAPI
- Docker/config
- tests
- production services
- legacy `../ELYO`

## Required inspection before patching

Inspect existing seeders and migrations/schema references before modifying seed data.

Confirm:

- current demo company setup
- internal ELYO platform company setup
- seeded roles
- current team creation flow
- current wellbeing entry seeding
- current survey/question/response/answer seeding
- current measure seeding
- current invite token fields
- whether `invite_tokens.team_id` exists before writing it
- exact survey-team pivot table and columns before inserting team-targeted survey records
- whether `measures.team_id` exists before writing it

Do not guess table names. Use existing migrations/model relationships.

## Existing baseline to preserve

Keep `demo-gmbh` as the team-enabled baseline.

Preserve:

- `demo-gmbh`
- `team_layer_enabled=true`
- existing demo accounts:
  - `admin@demo.de`
  - `manager@demo.de`
  - `employee1@demo.de` etc.
- enough employee wellbeing entries for the anonymity threshold
- at least one active survey
- at least one measure
- existing partner demo data unless unrelated cleanup is required

Update only where useful:

- if `invite_tokens.team_id` exists, update the existing demo invite token to include the demo team id.

## New smoke company 1: small-demo-gmbh

Create or update a company:

- slug: `small-demo-gmbh`
- name: `Small Demo GmbH`
- status: active
- `team_layer_enabled=false`
- `anonymity_threshold=3` unless current conventions strongly prefer another value

Create or update accounts with password `demo1234`:

- `small.admin@demo.de`
- `small.manager@demo.de`
- `small.employee1@demo.de`
- `small.employee2@demo.de`
- `small.employee3@demo.de` if needed for threshold-safe checks

Role expectations:

- `small.admin@demo.de`: `COMPANY_ADMIN`
- `small.manager@demo.de`: `COMPANY_MANAGER`
- employees: `EMPLOYEE`

Important:

- all users must have `company_id` for Small Demo GmbH
- all users should have `team_id=null`
- do not create teams for this company
- manager has Employee/App portal through backend portal eligibility even if no explicit `EMPLOYEE` role is assigned; do not add `EMPLOYEE` role unless the current architecture explicitly requires it
- if tests/current backend still require explicit `EMPLOYEE` role for employee API access, document that as an open question instead of silently changing the architecture in the seeder

Seed data:

- company-wide wellbeing entries sufficient for company-level dashboard/report smoke checks
- one active company-wide survey with no team targeting
- survey questions without raw free-text demo answers intended for company visibility
- optional survey responses/answers sufficient for threshold-safe company-level results
- one company-wide measure with `team_id=null`

Expected smoke behavior:

- admin can use company-level dashboard/reports/surveys/measures
- manager cannot access company portal/company routes when team layer is disabled
- manager can use Employee/App portal if backend allowedPortals grants it
- employees can use check-in and company-wide surveys
- no teams exist

## New smoke company 2: legacy-teams-disabled-gmbh

Create or update a company:

- slug: `legacy-teams-disabled-gmbh`
- name: `Legacy Teams Disabled GmbH`
- status: active
- `team_layer_enabled=false`
- `anonymity_threshold=3` unless current conventions strongly prefer another value

Create or update one historical team:

- name: `Legacy Team`
- belongs to Legacy Teams Disabled GmbH
- manager: `legacy.manager@demo.de` if current team schema supports manager assignment

Create or update accounts with password `demo1234`:

- `legacy.admin@demo.de`
- `legacy.manager@demo.de`
- enough employees for threshold-safe company-level smoke checks:
  - `legacy.employee1@demo.de`
  - `legacy.employee2@demo.de`
  - `legacy.employee3@demo.de`
  - add more only if needed

Role expectations:

- `legacy.admin@demo.de`: `COMPANY_ADMIN`
- `legacy.manager@demo.de`: `COMPANY_MANAGER`
- employees: `EMPLOYEE`

Team assignment:

- assign legacy users to the historical team where useful for smoke scenarios
- keep this data intentionally historical, even though the company team layer is disabled

Seed data:

- company-level wellbeing entries sufficient for dashboard/report smoke checks
- at least one company-wide active survey with no team targeting
- at least one team-targeted active survey attached to `Legacy Team` through the correct survey-team pivot
- at least one company-wide measure with `team_id=null`
- at least one team-scoped measure with `team_id` set to `Legacy Team`

Expected smoke behavior:

- admin can use company-level dashboard/reports/surveys/measures
- team management UI/API should be unavailable or guarded by existing behavior
- employee survey list/show/respond should not expose the team-targeted survey when team layer is disabled
- team-scoped reports/surveys/measures should be blocked by existing backend behavior
- company-wide flows remain usable

## Internal platform company

Preserve the internal platform company:

- slug: `elyo-platform`
- `team_layer_enabled=false`
- assigned to platform/admin/support users as currently implemented

Do not use the internal platform company as a customer smoke company.

Do not accidentally create Employee/App smoke users under `elyo-platform`.

## Idempotency requirements

Seeder must be safe to run repeatedly.

Use deterministic lookup keys:

- company slug
- user email
- team name within company
- survey title within company
- measure title within company
- invite email + company id

Avoid duplicates:

- roles
- users
- teams
- survey questions
- survey responses
- survey answers
- survey-team pivot records
- measures
- invite tokens

Allowed strategies:

- `updateOrInsert`
- deterministic deletes scoped only to seeded demo companies/surveys
- clear and reinsert child records only for seeded surveys/measures owned by:
  - `demo-gmbh`
  - `small-demo-gmbh`
  - `legacy-teams-disabled-gmbh`

Do not delete unrelated developer data outside:

- `demo-gmbh`
- `small-demo-gmbh`
- `legacy-teams-disabled-gmbh`
- `elyo-platform`

## Privacy requirements

- Do not seed raw free-text survey answers intended for company visibility.
- Do not weaken anonymity thresholds.
- Seed enough aggregate data to test threshold-safe company-level dashboards/results.
- Do not expose individual health data through seed-only shortcuts.
- Keep team-targeted legacy data arranged so existing backend behavior filters or blocks it when team layer is disabled.

## Helper methods

You may add private helper methods inside `DemoDataSeeder` if they make the patch smaller and clearer.

Candidate helpers:

- `upsertCompany`
- `upsertUser`
- `assignRoles`
- `upsertTeam`
- `seedWellbeingEntries`
- `upsertCompanyWideSurvey`
- `upsertTeamTargetedSurvey`
- `attachSurveyToTeam`
- `seedSurveyResponses`
- `upsertMeasure`

Rules:

- helpers must remain private and seed-only
- do not create production services
- do not over-abstract a simple seeder into a framework, because apparently humanity has suffered enough

## Out of scope

- No product behavior changes.
- No backend auth/portal logic changes.
- No Angular changes.
- No OpenAPI changes.
- No migrations.
- No tests.
- No Docker/config changes.
- No destructive database commands from Codex.
- Do not run `migrate:fresh`.
- Do not run `db:wipe`.
- Do not run `docker compose down -v`.

## Validation during implementation

Run:

- `docker compose exec api php artisan test --filter=CompanyTest`
- `docker compose exec api php artisan test --filter=EmployeeTest`
- `docker compose exec api php artisan test`
- `git diff --check`

Do not run:

- `docker compose exec api php artisan migrate:fresh --seed`

The user will run the fresh migration/seed manually after review.

## Expected output

Report:

- files changed
- companies seeded
- accounts/passwords seeded
- team-layer smoke scenarios covered
- invite token team_id handling
- survey-team pivot handling
- validation commands/results
- risks/open questions
