# Task: Cleanup MeasureParticipation Factory Laziness

## Goal

Refine the MeasureParticipationFactory so it remains tenant-consistent by default without eagerly creating unused tenant data when tests provide explicit overrides.

This is a small cleanup task after the persistence hardening pass.

## Context

The current MeasureParticipationFactory now creates tenant-consistent default data, but it eagerly creates a company, creator, user, and measure inside definition().

Laravel applies explicit create([...]) overrides after definition(), so tests that pass measure_id, user_id, company_id, and team_id still create unused default records before those attributes are replaced.

This makes factory usage noisier and slower, and it can make future count/assertion tests misleading.

## Scope

Inspect and update only:

- apps/api-laravel/database/factories/MeasureParticipationFactory.php
- apps/api-laravel/tests/Feature/MeasureParticipationPersistenceTest.php if needed

## Requirements

1. Preserve tenant-consistent default factory behavior.

MeasureParticipation::factory()->create() must still create a valid participation where:

- participation.company_id matches participation.measure.company_id
- participation.user.company_id matches participation.company_id
- participation.team_id is null by default unless a specific team state is used

2. Avoid eager unused data creation when explicit overrides are provided.

For tests like:

MeasureParticipation::factory()->create([
    'measure_id' => $measure->id,
    'user_id' => $user->id,
    'company_id' => $company->id,
    'team_id' => null,
])

the factory should not create unrelated default companies, users, creators, or measures that are then overwritten.

3. Prefer lazy attribute closures, state callbacks, or a small explicit helper state.

Acceptable approaches:

- Use lazy closures that derive missing attributes only when needed.
- Use afterMaking/afterCreating only if it does not create unused data for explicit overrides.
- Add a named state for default tenant-consistent setup if that is clearer.
- Keep explicit test setup explicit where that avoids factory magic.

4. Do not over-engineer.

Do not introduce a broad factory abstraction layer.

Do not add generic tenant factory infrastructure.

This task is only about cleaning up MeasureParticipationFactory.

## Out of Scope

Do not change:

- migrations
- models, unless absolutely required by the factory cleanup
- routes
- controllers
- services
- requests
- resources
- policies
- OpenAPI
- Angular
- points logic
- seeders
- company reporting behavior
- QR or attendance verification
- screening/profile/scoring logic

Do not add database-level tenant consistency constraints in this task.

Do not run destructive commands:

- php artisan migrate:fresh
- php artisan db:wipe
- docker compose down -v
- any destructive database or Docker reset

## Tests

Update or add tests to prove:

1. Default factory usage still creates tenant-consistent data.
2. Explicit override usage does not create unrelated extra Company, User, or Measure records.
3. Existing duplicate-prevention and relationship tests still pass.

For the explicit override test, use record counts before and after factory creation if this fits the existing test style.

Example intent:

- create one company
- create one measure for that company
- create one user for that company
- record current counts
- create MeasureParticipation using explicit overrides
- assert only the participation count changed, not companies/users/measures

## Validation

Run:

- docker compose exec api php artisan test --filter=MeasureParticipation

If targeted tests pass and time permits:

- docker compose exec api php artisan test

## Expected Handoff

Return:

- Files changed
- Factory cleanup approach
- Tests added or updated
- Validation commands run
- Confirmation that default factory data remains tenant-consistent
- Confirmation that explicit override factory usage no longer creates unrelated tenant data
- Confirmation that no API, OpenAPI, Angular, points, migration, or reporting changes were made
