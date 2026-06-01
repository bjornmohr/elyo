# Task: Add Measure Participation Persistence

## Goal

Add the persistence layer for employee participation in company health measures.

This task must only introduce the database table, Eloquent model, relationships, factory if useful, and persistence-focused backend tests.

Do not add employee participation endpoints, points awarding, company summary APIs, OpenAPI changes, or Angular changes in this task.

## Context

The architecture review found:

- Measures are routed under GET/POST/PATCH /api/company/measures and GET /api/employee/measures.
- The measures table currently has id, company_id, team_id, title, category, description, status, suggested_at, started_at, completed_at, created_by, and timestamps.
- Measure status already exists as strings around SUGGESTED, ACTIVE, COMPLETED, and DISMISSED.
- Team targeting already exists through nullable measures.team_id; null means company-wide/all teams.
- Employee measure listing already filters to the authenticated employee company, active measures, and either global measures or the employee's users.team_id.
- Company measure listing already scopes by authenticated company and applies manager/team-layer restrictions.
- There is no existing MeasureParticipation model/table/service/controller route.
- Points are centralized in PointsService and must not be touched in this task.
- Company-facing participation aggregation must not be implemented in this task.

## Scope

Implement only the persistence foundation for measure participation.

Add a new table:

measure_participations

Suggested columns:

- id
- measure_id
- user_id
- company_id
- team_id nullable
- participated_at timestamp nullable
- created_at
- updated_at

Required constraints:

- foreign key measure_id references measures.id
- foreign key user_id references users.id
- foreign key company_id references companies.id
- foreign key team_id references teams.id nullable
- unique index on measure_id and user_id

Suggested query indexes:

- company_id, measure_id
- company_id, team_id, measure_id

Add an Eloquent model:

- App\Models\MeasureParticipation

Add relationships where appropriate:

- Measure hasMany MeasureParticipation
- MeasureParticipation belongsTo Measure
- MeasureParticipation belongsTo User
- MeasureParticipation belongsTo Company
- MeasureParticipation belongsTo Team nullable
- User hasMany MeasureParticipation
- Company hasMany MeasureParticipation
- Team hasMany MeasureParticipation if this fits existing model conventions

Add a factory if existing test patterns use factories for comparable models.

## Important Design Rules

- Do not accept or process frontend-provided company_id, team_id, or user_id in this task.
- Do not create participation action endpoints in this task.
- Do not add business eligibility logic in this task.
- Do not add points logic in this task.
- Do not add company aggregation logic in this task.
- Do not add QR code or attendance verification logic.
- Do not add screening, scoring, profiling, or medical recommendation logic.
- Do not expose individual participation data to company users.
- Do not update Angular in this task.
- Do not update OpenAPI in this task unless the codebase has a strict rule requiring schemas for model-only changes. Prefer no OpenAPI change here.
- No destructive DB reset commands such as migrate:fresh, db:wipe, docker compose down -v, or similar unless explicitly approved.

## Tests

Add persistence-focused backend tests only.

Cover:

1. A measure participation can be created for a measure, user, company, and optional team.
2. A user cannot have duplicate participation rows for the same measure.
3. A measure can have many participations.
4. A user can have many measure participations.
5. Company and team denormalization is persisted correctly.
6. Nullable team_id works for company-wide measures.
7. Foreign key constraints are defined consistently with the existing migration style.

Use existing Laravel test conventions from the repository.

## Validation

Run targeted tests first if possible.

Suggested commands:

- docker compose exec api php artisan test --filter=MeasureParticipation
- docker compose exec api php artisan test

Also inspect migration status if needed without destructive commands.

Allowed non-destructive command:

- docker compose exec api php artisan migrate:status

Do not run:

- php artisan migrate:fresh
- php artisan db:wipe
- docker compose down -v
- any destructive database reset

## Expected Handoff

Return a concise handoff with:

- Files changed
- Migration added
- Model and relationships added
- Factory added or reason why not
- Tests added
- Tests run
- Confirmation that no endpoints, points logic, OpenAPI, or Angular changes were added
- Risks and open questions for Task 2

## Implementation Plan

1. Inspect the existing Laravel persistence conventions before patching.
   - Confirm the current `measures` migration style, especially explicit unsigned foreign keys, `foreign(...)->references(...)->on(...)`, delete behavior, and index naming conventions.
   - Confirm relationship method naming and typed return conventions in `Measure`, `User`, `Company`, and `Team`.
   - Confirm factory and feature-test conventions for persistence-only model coverage.

2. Add a focused migration for `measure_participations`.
   - Create a new timestamped migration in `apps/api-laravel/database/migrations/`.
   - Define `id`, `measure_id`, `user_id`, `company_id`, nullable `team_id`, nullable or current `participated_at`, and timestamps.
   - Use foreign keys to `measures`, `users`, `companies`, and nullable `teams` in the same style as existing migrations.
   - Add `unique(['measure_id', 'user_id'])`.
   - Add query indexes for `['company_id', 'measure_id']` and `['company_id', 'team_id', 'measure_id']`.
   - Choose delete behavior deliberately:
     - `measure_id` can cascade because participation rows have no meaning after the measure is deleted.
     - `user_id` can cascade if this matches existing user-owned persistence tables.
     - `company_id` can cascade if this matches company-owned persistence tables.
     - `team_id` should use `nullOnDelete`/`onDelete('set null')` to preserve company-wide/history rows when a team is removed.

3. Add the `MeasureParticipation` model.
   - Create `apps/api-laravel/app/Models/MeasureParticipation.php`.
   - Use `HasFactory`.
   - Add fillable fields for `measure_id`, `user_id`, `company_id`, `team_id`, and `participated_at`.
   - Cast `participated_at` to `datetime`.
   - Add typed `belongsTo` relationships for `measure`, `user`, `company`, and nullable `team`.

4. Add inverse relationships on existing models.
   - Add `participations(): HasMany` to `Measure`.
   - Add `measureParticipations(): HasMany` to `User`.
   - Add `measureParticipations(): HasMany` to `Company`.
   - Add `measureParticipations(): HasMany` to `Team` if it fits the existing `Team` relationship style.
   - Import `HasMany` where needed without changing unrelated relationships.

5. Add a factory if it follows existing test conventions.
   - Create `apps/api-laravel/database/factories/MeasureParticipationFactory.php`.
   - Default to related `Measure`, `User`, and `Company` factories, and keep `team_id` nullable by default unless a state is useful.
   - Prefer explicit test setup for company/team consistency so the factory does not hide denormalization assumptions.

6. Add persistence-focused backend tests only.
   - Add a focused feature test such as `apps/api-laravel/tests/Feature/MeasureParticipationPersistenceTest.php`.
   - Cover creation with a measure, user, company, and team.
   - Cover duplicate prevention for the same `measure_id` and `user_id` with a database-level `QueryException`.
   - Cover `Measure::participations()` returning many rows.
   - Cover `User::measureParticipations()` returning many rows.
   - Cover persisted `company_id` and `team_id` denormalization.
   - Cover nullable `team_id` for company-wide measures.
   - Cover foreign key constraints consistently with existing migration tests by attempting an invalid insert and expecting a database exception.

7. Keep scope boundaries explicit.
   - Do not add controllers, routes, requests, resources, services, policies, employee action endpoints, company aggregation APIs, points logic, QR/attendance verification, OpenAPI changes, Angular changes, or n8n logic.
   - Do not accept frontend-provided `company_id`, `team_id`, or `user_id`; this task has no request-handling surface.
   - Do not expose individual participation data to company users.

8. Validate in patch mode with non-destructive commands only.
   - Run `docker compose exec api php artisan test --filter=MeasureParticipation` first.
   - Run `docker compose exec api php artisan test` if the targeted test passes and time permits.
   - Optionally run `docker compose exec api php artisan migrate:status` if migration state needs inspection.
   - Do not run `php artisan migrate:fresh`, `php artisan db:wipe`, `docker compose down -v`, or other destructive database/Docker commands.

9. Review the diff and handoff.
   - Confirm only backend persistence files and tests changed during implementation.
   - Confirm no API behavior or OpenAPI contract changed.
   - Confirm portal boundaries and health-data guardrails are preserved.
   - Handoff should list files changed, migration/model/relationship/factory/test additions, commands run, validation result, and open questions for a later participation-action/API task.
