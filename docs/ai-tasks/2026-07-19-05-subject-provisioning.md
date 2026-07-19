# Task: Synchronous subject provisioning on registration and seed backfill

## Goal

Wire `provisionOwnSubject` into user creation (invite-accept flow), provision all seeded users, and add a backfill command — so every active user deterministically has a health subject before any health feature ships.

## Context

Relevant files:

- apps/api-laravel/app/Http/Controllers/Auth/InviteController.php (accept → user creation)
- apps/api-laravel/app/Services/Invitations/
- apps/api-laravel/database/seeders/DemoDataSeeder.php
- app/Services/Privacy/MappingService.php (prompt 04)
- ADR-001 §2.2 (synchronous, all-or-nothing), Jira ELYO-104

Background:

- ADR-001: provisioning happens synchronously at successful self-registration; order subject → mapping; repeatability compensates partial failure (no cross-DB transaction exists).
- Users created by admin invite flows also need subjects; company/admin actors must never receive the subject id in any response.

## Scope

Change only:

- Invite accept / user creation path (controller + invitation service)
- DemoDataSeeder (provision after user creation)
- New: `app/Console/Commands/ProvisionMissingHealthSubjects.php` (`elyo:provision-subjects`)
- tests/Feature/ (invite flow + command)

Do not change:

- MappingService internals
- Any response shape of existing endpoints (subject id never leaves the backend)

## Requirements

1. On user creation in the accept flow: call `provisionOwnSubject` with `PurposeCode::PROVISIONING` after the user row is committed; on provisioning failure the registration still succeeds but is flagged (log + `elyo:provision-subjects` repairs) — document this choice inline referencing ADR-001's repeatability principle.
2. Seeder provisions subjects for all employee users (and any user who could ever hold health data — decide: simplest is all users; document).
3. `elyo:provision-subjects`: idempotent sweep over users without ACTIVE mapping; `--dry-run` flag; outputs counts only (no ids).
4. No API response, log line, or exception message exposes a health_subject_id together with identifying data.
5. Tests: accept flow provisions; command backfills a user created without subject; idempotent re-run creates nothing.

## Constraints

- Keep the patch minimal.
- No new packages; no queue dependency (synchronous per ADR).
- Preserve existing invite flow behavior and responses.

## Privacy and Security Requirements

- Subject ids never in HTTP responses, seeder output, or command output.
- Command output aggregates counts only.

## Validation

Run:

    docker compose exec api php artisan elyo:migrate-fresh --seed
    docker compose exec api php artisan elyo:provision-subjects --dry-run
    docker compose exec api php artisan test

Expected result:

- Seed leaves zero unprovisioned users; dry-run reports 0 missing; full suite green.

## Output Required

1. Files changed
2. Decision notes (failure handling, which users get subjects)
3. Commands run and results
4. Open questions

## Review Checklist

- Is provisioning failure handling explicit and repair path tested?
- Does any output leak subject ids? (grep the diff)
- Is the seeder deterministic across `elyo:migrate-fresh` runs?
