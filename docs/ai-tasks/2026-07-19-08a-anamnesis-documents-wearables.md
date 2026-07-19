# Task: Move anamnesis, health-document metadata and wearables to the health domain

## Goal

Complete the health-domain consistency of the fresh schema: `anamnesis_profiles`, health-document metadata, and wearable connection/sync data move to `elyo_health` on `health_subject_id`, following the wellbeing pattern from prompt 08. Storage hardening for document files (ADR-001 §2.9) is explicitly deferred.

## Context

Relevant files:

- app/Models/AnamnesisProfile.php, HealthDocument.php, UserDocument.php, WearableConnection.php, WearableSync.php
- app/Http/Controllers/Employee/EmployeeController.php (getProfile/updateProfile/uploadDocument), app/Services/WearableService.php, StorageService.php
- database/migrations/identity/ (baseline from prompt 03), database/migrations/health/
- Prompt 08 result (pattern: subject resolution in HTTP layer, connection-pinned health models)
- ADR-001 §2.6 (all health/behavior data on health_subject_id), §2.9; ADR-003 (D8 revised)

Background:

- These tables are health data on `user_id` — leaving them in identity would force permanent exceptions in the privacy suite (prompt 16) and violate the AGENTS.md health-domain rules from day one.
- Investigate first and report: which of these models are actually wired to live endpoints vs. dormant demo remnants (e.g. wearables may have no active routes). Dormant tables still move — but their services get the minimal treatment (compile + tests), no feature build-out.
- `UserDocument` needs a classification decision: if it stores non-health documents (e.g. general attachments), it stays in identity — decide from actual usage and document; if health-related, it moves.

## Scope

Change only:

- New migrations in `database/migrations/health/` (anamnesis_profiles, health_documents, wearable_connections, wearable_syncs — ulid PKs, health_subject_id, no user_id/company_id)
- Removal of the corresponding tables from the identity baseline (same mechanism as prompt 08 chose — follow it)
- Models moved to `app/Models/Health/` (connection-pinned, no cross-domain relations; old models deleted; `User` model relations removed)
- EmployeeController profile/document paths + WearableService: subject resolution via MappingService (HEALTH_SELF_READ/WRITE)
- DemoDataSeeder (seed via subjects)
- docs/api/openapi.yaml only if response shapes change (they should NOT — ids may become ULIDs; document)
- tests

Do not change:

- File storage mechanics (paths, disk, StorageService internals) — DB reference side only; add a `// ADR-001 §2.9 storage hardening follow-up` marker
- Company/admin routes (none of this data is reachable there today; verify and assert in tests)
- apps/web-angular (response shapes stay stable; ULID ids are opaque strings to the client — verify the Angular typing tolerates string ids, report if not)

## Requirements

1. All four tables (or three, per the UserDocument decision) rebuilt in health with health_subject_id; no user_id column; delete-behavior per retention concept placeholder (no cascade from users — subject-scoped deletion, prompt 13 wires it).
2. Employee profile get/update and document upload work unchanged from the client perspective (same routes, same shapes, ULID ids allowed).
3. Document storage paths: keep current mechanics but assert no path contains user names/emails (test); if they currently do, switch new uploads to UUID-based names now (cheap, §2.9-aligned) and note it.
4. `User` model has zero remaining relations to health-domain models (reflection/grep evidence in report).
5. Tests: profile round-trip, upload + retrieval, cross-subject isolation (A cannot reach B's documents/profile → 404), wearable service still passes its existing tests subject-scoped.
6. Deptrac layers extended for the moved models; zero violations.

## Constraints

- Follow prompt 08's established patterns exactly (resolution placement, exception handling, audit path) — no parallel idioms.
- No feature build-out for dormant wearable code.
- Keep the patch reviewable; split seeder/test bulk into a marked second commit if large.

## Privacy and Security Requirements

- No health_subject_id in any response.
- Document responses only for the owning subject; foreign ids → 404.
- No storage path leaks identity data.

## Validation

Run:

    docker compose exec api php artisan elyo:migrate-fresh --seed
    docker compose exec api php artisan test
    docker compose exec api composer deptrac
    docker compose exec api php artisan test --testsuite=boundary

Expected result:

- Full suite + Deptrac + boundary green; employee profile/document flows verified manually.

## Output Required

1. Files changed
2. Live-vs-dormant findings per model; UserDocument classification decision
3. Storage path assessment result
4. Commands run and results
5. Open questions

## Review Checklist

- Zero user_id/company_id in the moved schemas?
- Client-visible behavior unchanged (routes, shapes)?
- openapi.yaml updated for every shape/ID-format change (or explicitly confirmed unchanged)?
- Storage hardening cleanly deferred with marker, not half-built?
- Do User-model relations to health data no longer exist?
