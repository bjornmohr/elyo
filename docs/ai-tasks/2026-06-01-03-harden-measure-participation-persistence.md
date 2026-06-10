# Task: Harden Measure Participation Persistence

## Goal

Fix the persistence-layer issues found after the initial MeasureParticipation implementation before building any employee participation API.

This task must harden factory defaults and persistence tests so that tests do not accidentally create tenant-inconsistent participation rows.

## Context

The previous persistence task added:

- measure_participations table
- MeasureParticipation model
- Relationships from Measure, User, Company, and Team
- MeasureParticipation factory
- Persistence-focused tests

Review verdict:

- Changes were narrowly scoped to persistence and relationships.
- No API routes, OpenAPI contract, Angular, points logic, or company summary behavior changed.
- Targeted tests pass.

Must-fix findings:

1. MeasureParticipationFactory currently creates unrelated Measure, User, and Company records by default.
   This can produce tenant-inconsistent rows where:
   - measure.company_id does not match participation.company_id
   - user.company_id does not match participation.company_id
   - team_id, if set, may not match the user/company/measure context

2. The migration only enforces independent foreign keys.
   It does not prevent cross-company combinations like:
   - measure_id from Company A
   - user_id from Company B
   - company_id from Company C

Decision:

- Do not solve full tenant consistency with complex database constraints in this task.
- Keep database constraints focused on foreign keys and duplicate prevention.
- Fix factory defaults so normal tests create valid business data.
- Add tests that make the intended persistence assumptions explicit.
- Service-level derivation and rejection of mismatched company/team/user/measure context will be implemented in the later Employee Participation API task.

## Scope

### 1. Inspect current implementation

Review:

- apps/api-laravel/database/factories/MeasureParticipationFactory.php
- apps/api-laravel/tests/Feature/MeasureParticipationPersistenceTest.php
- apps/api-laravel/app/Models/MeasureParticipation.php
- related Measure, User, Company, and Team factories
- related model relationships

### 2. Fix MeasureParticipationFactory defaults

Update the factory so that default created records are tenant-consistent.

Expected behavior for default factory usage:

- participation.company_id matches measure.company_id
- participation.user_id belongs to a user from the same company
- participation.team_id is nullable by default unless explicitly requested
- participated_at remains set according to the existing implementation style

Prefer factory callbacks/states over hiding inconsistent assumptions.

Possible approach:

- Create a Company first.
- Create a Measure for that Company.
- Create a User for that same Company.
- Set participation.company_id from that Company.
- Keep team_id null by default.

If existing factory conventions make a different approach cleaner, follow the repository style.

### 3. Add explicit factory states if useful

Add states only if they improve test clarity.

Possible states:

- forCompanyWideMeasure()
- forTeamMeasure()
- forMeasure(Measure $measure)
- forUser(User $user)

Do not over-engineer. This is still a tiny persistence hardening task, not a factory framework, despite humanity's endless urge to abstract three lines into a lifestyle.

### 4. Add or update tests

Add tests that prove:

1. MeasureParticipation::factory()->create() creates tenant-consistent data by default.
2. The participation company_id matches the measure company_id.
3. The participation user belongs to the same company_id.
4. Default team_id is nullable and works for company-wide measures.
5. A team-specific factory state, if added, creates a team from the same company and a user assigned to that team/company.
6. Duplicate prevention still works for measure_id + user_id.
7. Existing relationship tests still pass.

Do not add API tests in this task.

### 5. Document the service-level guardrail for Task 2

Add a short note in the test or handoff, not production docs unless there is an existing handoff convention:

- DB constraints protect foreign keys and duplicate participation.
- Tenant/team consistency must be enforced in the future MeasureParticipationService.
- Future API must derive user_id, company_id, and team_id from the authenticated user and selected measure.
- The request body must never provide user_id, company_id, or team_id.

## Out of Scope

Do not add or change:

- routes
- controllers
- request classes
- API resources
- service classes
- policies
- points logic
- point settings seeder
- OpenAPI
- Angular
- company participation summary
- QR or attendance verification
- screening, scoring, profile, or medical recommendation logic
- n8n logic

Do not introduce complex DB-level tenant consistency constraints unless there is already a simple existing project convention for this exact pattern.

Do not run destructive commands:

- php artisan migrate:fresh
- php artisan db:wipe
- docker compose down -v
- any destructive database or Docker reset

## Validation

Run targeted tests first:

- docker compose exec api php artisan test --filter=MeasureParticipation

If targeted tests pass and time permits, run the full backend suite:

- docker compose exec api php artisan test

Optional non-destructive inspection:

- docker compose exec api php artisan migrate:status

## Expected Handoff

Return:

- Files changed
- Factory changes
- Test changes
- Validation commands run
- Confirmation that no API, OpenAPI, Angular, points, or company summary behavior changed
- Confirmation that factory default data is tenant-consistent
- Explicit note that service-level tenant/team mismatch rejection remains required for Task 2

## Implementation Plan

1. Confirm current persistence shape before patching.
   - Re-read `MeasureParticipationFactory`, `MeasureParticipationPersistenceTest`, `MeasureParticipation`, and the related `Measure`, `User`, `Company`, and `Team` factories.
   - Verify there are no existing project-specific factory states for tenant scoping that should be reused.
   - Keep the implementation limited to the factory and persistence test file unless inspection reveals an existing relationship test in the same persistence area that must be adjusted.

2. Make the default `MeasureParticipationFactory` tenant-consistent.
   - Replace the unrelated default `Measure::factory()`, `User::factory()`, and `Company::factory()` assignments with a single shared company context.
   - Create the default measure for that company.
   - Create the default user for that same company with `team_id` set to `null`.
   - Set `company_id` from the same company and keep default `team_id` as `null`.
   - Keep `participated_at` aligned with the current implementation style.
   - Prefer an `afterMaking` or state/callback approach only if plain factory attribute closures cannot reliably share the same created company across attributes.

3. Add only useful factory states.
   - Add a team-scoped state only if it keeps tests clearer than repeating explicit attributes.
   - If added, the state should create a team, manager, user, and measure within one company and set participation `company_id`, `team_id`, `user_id`, and `measure_id` consistently.
   - Avoid a broader factory abstraction layer or states that are not exercised by this task's tests.

4. Strengthen persistence tests.
   - Add a test proving `MeasureParticipation::factory()->create()` creates a participation where:
     - `participation.company_id` matches `participation.measure.company_id`
     - `participation.user.company_id` matches `participation.company_id`
     - `participation.team_id` is `null` by default
   - Keep or update the existing nullable team/company-wide test so it covers the intended default behavior without duplicating the same assertion set.
   - If a team-scoped factory state is added, add one test proving the measure, user, team, and participation all share the same company and that the user is assigned to the team.
   - Preserve the duplicate-prevention test for `measure_id` + `user_id`.
   - Preserve existing relationship coverage for measure, user, company, and team relationships.
   - Add a short inline test comment documenting that cross-company and team mismatch rejection remains service-level work for the later Employee Participation API task.

5. Keep scope boundaries intact.
   - Do not change routes, controllers, requests, resources, services, policies, points behavior, OpenAPI, Angular, Docker, migrations, seeders, or company reporting behavior.
   - Do not add complex database constraints for cross-table tenant consistency in this task.
   - Do not use legacy `../ELYO` as anything other than read-only reference.

6. Validation for the later patch-mode task.
   - Planned targeted command: `docker compose exec api php artisan test --filter=MeasureParticipation`.
   - Planned broader backend command if targeted tests pass and time permits: `docker compose exec api php artisan test`.
   - Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, frontend builds, or any destructive Docker/database command for this task.

Additional clarification:

- Preserve existing User factory role/company conventions.

- Only override company_id and team_id as needed to make MeasureParticipationFactory tenant-consistent.

- Keep default team_id null only if this matches the existing users.team_id nullable model.

- Do not change migrations, routes, controllers, services, resources, OpenAPI, Angular, points logic, seeders, or company reporting.

- Do not introduce database-level tenant consistency constraints in this task.

- Run only non-destructive validation commands.