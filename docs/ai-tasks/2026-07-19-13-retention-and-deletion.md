# Task: Retention matrix and deletion flow for health data

## Goal

Deliver ELYO-108: a retention matrix per health data category, an implemented user-deletion flow honoring the domain order (health wipe → mapping tombstone last), and a scheduled-job skeleton for retention enforcement.

## Context

Relevant files:

- app/Services/Privacy/MappingService.php (revokeSubjectLink)
- Health models: WellbeingEntry, LabMarker(Reading) (prompts 08/11)
- ADR-001 §2.8 (30-day deletion, backups 90-day rotation, aggregates remain), §2.2 (read-only basis status)
- Jira ELYO-108; docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md

Background:

- Pilot account model: active or deleted, no intermediate state. Demo cascadeOnDelete is explicitly NOT the template.
- Deletion order (document + implement): identity personal data and health data physically deleted, mapping revoked/tombstoned LAST (ADR-001 §2.8: "Mapping zuletzt"); tombstone keeps the deletion provable without re-identification.
- Retention periods themselves are proposals for legal review — mark them PROPOSED, the mechanism is the deliverable.

## Scope

Change only:

- New doc: `docs/further-docs/retention-matrix.md` (category, legal basis pointer, proposed period, deletion trigger, mechanism, status PROPOSED/DECIDED)
- New: `app/Services/Privacy/AccountDeletionService.php` (or extend an existing user-deletion path if one exists — investigate first and report)
- New: `app/Console/Commands/EnforceRetention.php` (`elyo:enforce-retention`, skeleton: dry-run inventory of over-retention data; actual deletion behind `--execute`)
- Scheduler registration (commented or gated until periods are DECIDED)
- tests/Feature/Privacy/ (deletion flow)

Do not change:

- Mapping status model (ACTIVE/REVOKED only)
- Backup tooling (out of scope; referenced in matrix)

## Requirements

1. Matrix rows at minimum: wellbeing entries, lab readings, lab catalog (non-personal), anamnesis profiles, health documents (metadata + files), wearable connections/syncs (all health-domain since prompt 08a), audit events (2y per ADR), subject mappings (tombstone), points/streaks (identity, behavioral — flag for future health-domain move).
2. `AccountDeletionService::deleteUser`: resolves subject (purpose REVOCATION path), deletes health rows for the subject, deletes/anonymizes identity personal data per existing app semantics, revokes mapping LAST; each step idempotent and resumable; audited (summary events).
3. Failure mid-flow leaves a resumable state; re-run completes (test).
4. `elyo:enforce-retention` dry-run lists counts per category vs. matrix periods; no destructive default.
5. Tests: full deletion leaves zero health rows for the subject, mapping REVOKED, audit trail present, re-run no-op; health rows of other subjects untouched.

## Constraints

- No new packages; keep the patch minimal.
- 30-day grace period timing may be modeled as an immediate-execution service + documented scheduling note (the grace workflow UI is out of scope).

## Privacy and Security Requirements

- Deletion outputs counts only, never ids.
- No resurrection path for revoked mappings.

## Validation

Run:

    docker compose exec api php artisan test --filter=Deletion
    docker compose exec api php artisan elyo:enforce-retention   # dry-run
    docker compose exec api php artisan test

Expected result:

- Deletion tests green; dry-run reports coherent inventory; full suite green.

## Output Required

1. Files changed
2. Retention matrix summary
3. Deletion order + resumability design note
4. Commands run and results
5. Open questions (esp. periods needing legal decision)

## Review Checklist

- Is mapping revocation demonstrably the LAST step?
- Is every health category covered by a matrix row?
- Is the retention job non-destructive by default?
