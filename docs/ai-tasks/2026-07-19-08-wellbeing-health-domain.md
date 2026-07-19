# Task: Rebuild wellbeing/check-in in the health domain (1–5 scale, no note)

## Goal

Move check-in persistence to the health domain: new `wellbeing_entries` on `health_subject_id` with canonical 1–5 scale and without `note`; employee endpoints resolve via MappingService; streak/points keep working. Backend side of ELYO-102 §3 (B3/B4/B5).

## Context

Relevant files:

- app/Services/WellbeingService.php, app/Http/Controllers/Employee/EmployeeController.php
- app/Services/PointsService.php (streak reads wellbeing by user_id)
- app/Models/WellbeingEntry.php
- database/migrations/identity/ (old wellbeing table), database/migrations/health/
- docs/decisions/elyo-102-api-contract-entscheidungen.md §3 (3.1–3.4), §4.2 (B3–B5)
- docs/api/openapi.yaml
- Jira ELYO-109/ELYO-110, ADR-003 (D3)

Background:

- Decisions: mood/energy/stress required int 1–5; score computed on 1–5 (mean of mood, inverted stress, energy → `(mood + (6 - stress) + energy) / 3`); `note` removed from request and response; one check-in per subject per day (`409 CHECKIN_ALREADY_DONE` per docs/ai-context/api-contract-rules.md); `GET /employee/checkin/status` keeps `completed` + `entry` (nullable); optional `location`/`sleep` are ELYO-133 — NOT in this task.
- No data migration: schema is rebuilt fresh (pre-production), old identity-side table is dropped.
- Company aggregation (AnonymityService) breaks by design here; it is handled in prompt 09 — in THIS task make it compile/tests pass by pointing it to an explicit empty/unavailable source with a `// ELYO-91 prompt 09` marker, without inventing new company behavior.

## Scope

Change only:

- New migration in `database/migrations/health/` (wellbeing_entries: ulid PK, `health_subject_id` FK to health_subjects, mood/stress/energy smallint 1–5 CHECK, score, period_key, unique(subject, period_key), timestamps)
- Drop old wellbeing table from identity baseline (edit baseline — allowed pre-review of prompt 03? No: add a follow-up migration removing it OR regenerate identity baseline; state which and why)
- app/Models/Health/WellbeingEntry.php (new, connection-pinned; delete old model)
- WellbeingService (subject-based), EmployeeController (checkin, checkinStatus, history, dashboard), PointsService streak source
- DemoDataSeeder (seed 1–5 entries via subjects)
- docs/api/openapi.yaml (checkin request/response, WellbeingEntry schema 1–5, note removed)
- tests (EmployeeTest and related)

Do not change:

- Points amounts/rules, survey/measure features
- Company endpoints beyond the marked stub (prompt 09)
- apps/web-angular (prompt 10)

## Requirements

1. `POST /employee/checkin` validates mood/energy/stress as required integers 1–5. Per ELYO-102 B4 the field `note` is no longer accepted: a request containing `note` fails with 422 (`prohibited` rule) — deliberate hard rejection instead of silent dropping, so the old UI cannot silently lose user input; Angular is adjusted in prompt 10 within the same release train.
2. Flow: auth user → `resolveOwnSubject(HEALTH_SELF_WRITE)` → uniqueness per (subject, period_key) → create; missing mapping triggers idempotent repair via `provisionOwnSubject` then proceeds (log + audit), per ADR repeatability.
3. History/dashboard/status read via `resolveOwnSubject(HEALTH_SELF_READ)`; response shapes unchanged except 1–5 values and removed `note`; entry ids are the new opaque ULIDs.
4. Streak: PointsService gets its consecutive-days source from a health-domain service call (subject-scoped) instead of querying WellbeingEntry by user_id; points stay identity-side.
5. Score formula and rounding documented in OpenAPI description; `score` on 1–5 scale.
6. Tests: happy path, 409 duplicate, 422 out-of-range (0/6), 422 with note, streak continuity, no `user_id` column anywhere in health schema (schema assertion), checkin creates audit-relevant mapping resolution (assert audit event exists).

## Constraints

- Keep the patch reviewable; if >~600 diff lines, split seeder/test adjustments into a clearly marked second commit.
- No `location`/`sleep` fields (ELYO-133).
- Do not weaken existing tests — adapt them to 1–5 deliberately.

## Privacy and Security Requirements

- Health table: no user_id, no company_id.
- No endpoint returns health_subject_id to the client.
- Company/admin roles get no new read path (403 unchanged).

## Validation

Run:

    docker compose exec api php artisan elyo:migrate-fresh --seed
    docker compose exec api php artisan test
    docker compose exec api php artisan test --testsuite=boundary

Expected result:

- Full suite green (company aggregate tests may be adjusted only as far as the prompt-09 stub requires — list every such change).

## Output Required

1. Files changed
2. Contract diff summary (request/response before → after)
3. How the prompt-09 stub is marked
4. Commands run and results
5. Open questions

## Review Checklist

- Is the 1–5 validation + note rejection exactly ELYO-102 B3/B4?
- Does the streak still work end-to-end (test evidence)?
- Any residual `WellbeingEntry::where('user_id'...)` anywhere? (grep)
- OpenAPI consistent with implementation?
