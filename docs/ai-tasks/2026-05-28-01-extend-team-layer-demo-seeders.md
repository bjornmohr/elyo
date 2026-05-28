# Task: Extend demo seeders for team-layer smoke testing

## Goal

Extend local/demo seed data so the optional company team-layer behavior can be smoke-tested end-to-end after a fresh local migration.

This task is seed/test-data only. It should not introduce new product behavior.

## Context

The team-layer feature now supports:

- companies.team_layer_enabled
- team-layer disabled companies
- team-layer enabled companies
- users.team_id nullable
- manager-only workflows blocked when team_layer_enabled=false
- company-level surveys/measures/dashboard/reporting still available when disabled
- employee survey visibility excludes team-targeted surveys when disabled
- GET /company/teams returns an empty list when disabled
- team write/detail/member endpoints reject with TEAM_LAYER_DISABLED when disabled

Current seeders provide a team-enabled `demo-gmbh`, but do not clearly cover:
- a small company without teams
- a disabled-team-layer company with historical team-scoped data
- manager-only disabled team-layer smoke scenarios

## Files in scope

- apps/api-laravel/database/seeders/DatabaseSeeder.php
- apps/api-laravel/database/seeders/DemoDataSeeder.php
- apps/api-laravel/database/seeders/PointSettingsSeeder.php only if needed, but no changes are expected

## Required changes

1. Keep existing demo company

Keep `demo-gmbh` as the normal team-enabled demo company:

- slug: `demo-gmbh`
- team_layer_enabled: true
- admin: `admin@demo.de`
- manager: `manager@demo.de`
- employees: `employee1@demo.de` etc.
- keep enough wellbeing entries to pass anonymity threshold
- keep at least one active survey
- keep at least one measure
- ensure the manager invite/demo invite uses `team_id` where the schema supports team-scoped invites

2. Add small company without team layer

Create a new company:

- slug: `small-demo-gmbh`
- name: `Small Demo GmbH`
- status: active
- team_layer_enabled: false
- anonymity_threshold: 3 or 5

Create users:

- `small.admin@demo.de`
- `small.manager@demo.de`
- `small.employee1@demo.de`
- `small.employee2@demo.de`
- password: `demo1234`

Rules:

- users must have valid company_id
- users should have team_id = null
- manager role may exist for smoke testing but must not get team-scoped data
- create company-wide wellbeing entries
- create at least one company-wide survey with no team targeting
- create at least one company-wide measure with team_id = null
- do not create teams for this company

3. Add disabled company with historical team data

Create a new company:

- slug: `legacy-teams-disabled-gmbh`
- name: `Legacy Teams Disabled GmbH`
- status: active
- team_layer_enabled: false
- anonymity_threshold: 3 or 5

Create:

- one historical team, e.g. `Legacy Team`
- admin: `legacy.admin@demo.de`
- manager: `legacy.manager@demo.de`
- employees: `legacy.employee1@demo.de`, `legacy.employee2@demo.de`, etc.
- users may have team_id assigned to the legacy team

Create historical/team-scoped data:

- at least one team-targeted survey attached to the legacy team
- at least one team-scoped measure with team_id set
- optional wellbeing entries sufficient for company-level dashboard/reporting

Expected smoke behavior after seed:

- admin can log in and use company-level flows
- team UI should be unavailable/hidden
- GET /company/teams returns empty list
- team-targeted surveys should not show for employees
- direct team-scoped operations should be rejected by backend
- company-wide surveys/measures remain usable

4. Preserve ELYO internal platform company

Keep `elyo-platform`:

- team_layer_enabled: false
- assigned to platform/support users
- do not use it as the smoke-test company

5. Seeder quality

- Seeders must be idempotent.
- Use `updateOrInsert` or deterministic cleanup for seeded demo records.
- Avoid duplicate survey questions/responses when seeding multiple times.
- Avoid deleting unrelated developer data outside the demo companies.
- Keep timestamps deterministic enough for dashboard/trend tests.
- Use the existing direct DB seeding style unless a small model-based helper is clearly cleaner.
- Do not modify migrations/schema in this task.
- Do not change application behavior.

## Optional helper refactor

If useful, add small private helper methods in DemoDataSeeder to reduce repetition:

- upsertCompany
- upsertTeam
- upsertCompanyWideSurvey
- seedWellbeingEntries

Keep helpers local to DemoDataSeeder. Do not create production services for seed-only logic.

## Out of scope

- No new product features.
- No Angular changes.
- No OpenAPI changes.
- No migrations.
- No privacy threshold changes.
- No manager hierarchy.
- No replacement of teams.manager_id.
- No destructive commands inside Codex.
- Do not run migrate:fresh from Codex unless explicitly instructed after review.

## Validation

For implementation validation, run:

- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test --filter=EmployeeTest
- docker compose exec api php artisan test
- git diff --check

After manual approval, the user may run a local fresh migration/seed manually:

- docker compose exec api php artisan migrate:fresh --seed

## Expected output

Report:

- companies seeded
- accounts/passwords created
- smoke scenarios covered
- commands run
- files changed
- risks/open questions

## Implementation Plan

1. Inspect the existing `DemoDataSeeder` flow before patching.
   - Confirm current demo company, platform company, roles, team creation, wellbeing entries, surveys, measures, and invite token fields.
   - Confirm whether `invite_tokens.team_id` and survey-team pivot records are available through existing migrations before using them.

2. Keep `demo-gmbh` as the team-enabled baseline.
   - Preserve `team_layer_enabled: true`, existing demo accounts, enough employee wellbeing entries for the anonymity threshold, at least one active survey, and at least one measure.
   - Update the demo invite token to include the demo team id where the current schema supports it.
   - Avoid changing app behavior, routes, OpenAPI, migrations, or frontend code.

3. Add local helper methods inside `DemoDataSeeder` only if they keep the patch smaller and clearer.
   - Candidate helpers: `upsertCompany`, `upsertTeam`, `upsertCompanyWideSurvey`, `attachSurveyToTeam`, `seedWellbeingEntries`, and small account/group seed helpers.
   - Keep helpers private and seed-only; do not add production services.
   - Use deterministic lookup keys such as company slug, user email, team name within company, and survey title within company.

4. Seed `small-demo-gmbh` as the no-team-layer company.
   - Create/update the company with `team_layer_enabled: false`, active status, and anonymity threshold 3 or 5.
   - Create `small.admin@demo.de`, `small.manager@demo.de`, `small.employee1@demo.de`, and `small.employee2@demo.de` with password `demo1234`.
   - Ensure all users have the small company id and `team_id = null`.
   - Do not create teams for this company.
   - Seed company-wide wellbeing entries, one active company-wide survey with no team targeting, and one company-wide measure with `team_id = null`.

5. Seed `legacy-teams-disabled-gmbh` as the disabled company with historical team data.
   - Create/update the company with `team_layer_enabled: false`, active status, and anonymity threshold 3 or 5.
   - Create/update one historical team named `Legacy Team`.
   - Create `legacy.admin@demo.de`, `legacy.manager@demo.de`, and enough `legacy.employeeN@demo.de` accounts for threshold-safe company-level smoke testing.
   - Assign legacy users to the historical team where useful for smoke scenarios.
   - Seed at least one team-targeted survey by inserting the survey and its `survey_team` pivot row.
   - Seed at least one team-scoped measure with `team_id` set to the legacy team.
   - Seed optional company-level wellbeing entries sufficient for dashboard/reporting checks.

6. Make seeding idempotent and scoped.
   - Use `updateOrInsert`, deterministic deletes scoped to the seeded demo company ids, or clear-and-reinsert only for child records owned by the seeded demo surveys/companies.
   - Do not delete unrelated developer data outside `demo-gmbh`, `small-demo-gmbh`, `legacy-teams-disabled-gmbh`, and `elyo-platform`.
   - Avoid duplicate survey questions, responses, answers, measures, teams, roles, and invite tokens when the seeder runs more than once.
   - Keep timestamps deterministic enough for dashboard and trend smoke testing.

7. Preserve privacy and portal boundaries.
   - Keep company users limited to seeded aggregate smoke data.
   - Do not seed raw free-text survey answers intended for company visibility.
   - Keep disabled-team-layer smoke data arranged so company-wide flows remain usable while team-targeted employee survey visibility should be excluded by existing backend behavior.

8. Leave `DatabaseSeeder` and `PointSettingsSeeder` unchanged unless inspection during implementation shows a necessary seed-order issue.
   - Expected implementation target is `apps/api-laravel/database/seeders/DemoDataSeeder.php`.
   - No migrations, tests, frontend, OpenAPI, Docker, or config files should be changed.

9. Validation for the later implementation pass only.
   - Do not run tests, builds, Docker commands, destructive database commands, or `migrate:fresh` during this planning pass.
   - During implementation validation, run the task-requested commands: `docker compose exec api php artisan test --filter=CompanyTest`, `docker compose exec api php artisan test --filter=EmployeeTest`, `docker compose exec api php artisan test`, and `git diff --check`.
   - Leave `docker compose exec api php artisan migrate:fresh --seed` for manual user approval.

## Final Scope Clarification

Before patching, inspect the current migrations/schema references and confirm:

- invite_tokens.team_id exists before writing team_id into demo invite tokens.
- the exact survey-team pivot table and columns exist before inserting team-targeted survey records.
- the exact measure team_id column exists before seeding team-scoped measures.

Seeder implementation rules:

- Keep DatabaseSeeder.php and PointSettingsSeeder.php unchanged unless a real seed-order issue is found.
- Prefer changing only DemoDataSeeder.php.
- Keep the existing demo-gmbh scenario as the team-enabled baseline.
- Add new scenarios without weakening or removing existing demo accounts.
- Use helper methods only if they reduce duplication and keep the patch smaller.
- Do not add production services.
- Do not change migrations, OpenAPI, frontend, routes, controllers, models, or tests in this task.
- For seeded surveys, avoid duplicate questions/responses/answers across repeated seed runs by clearing and recreating only child records owned by the seeded survey.
- Do not delete unrelated developer data outside the seeded demo companies:
  - demo-gmbh
  - small-demo-gmbh
  - legacy-teams-disabled-gmbh
  - elyo-platform
