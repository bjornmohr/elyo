# Task: Lab marker data model in the health domain

## Goal

Build the production lab-value model: marker catalog (metadata) + history-capable readings on `health_subject_id`, domain service, seeds, tests. No HTTP endpoints yet (prompt 12).

## Context

Relevant files:

- database/migrations/health/, app/Models/Health/, app/Services/ (health domain service location from prompt 08)
- docs/decisions/elyo-102-api-contract-entscheidungen.md §1 (fields, status keys, groups, source enum)
- docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md §3.1
- Jira ELYO-105; ADR-001 §2.6; ADR-003 (D4)

Background:

- Demo branch `demo/employee-lab-values-dashboard` (lab_markers on user_id, one value per marker, no timestamp, hardcoded LabMarkerRegistry) is scope reference ONLY — read for the field list, copy nothing.
- Contract-decided fields per marker reading: `markerKey`, `value`, `unit`, `measuredAt` (required), `status` (`below_range|in_range|above_range`), `low`/`high` nullable, `group` (`blutbild|immun|mikro|sonstige`), `source` (enum: `manual`; reserved `document_import`, `bgm_import`), opaque ULID id.
- Status is computed from value vs. range — decide storage vs. derivation and document (recommendation: derive, don't store, unless range versioning demands otherwise).

## Scope

Change only:

- New migrations in `database/migrations/health/`: `lab_markers` (catalog: marker_key unique, name, unit, low/high nullable, group, active flag, timestamps) and `lab_marker_readings` (ulid PK, health_subject_id FK, marker_key FK to catalog, value decimal, measured_at date, source enum, timestamps; index (subject, marker_key, measured_at))
- New: `app/Models/Health/LabMarker.php`, `LabMarkerReading.php` (connection-pinned, no cross-domain relations)
- New: health-domain service (create reading, list latest per marker, history per marker, delete own reading) operating on subject ids (mapping resolution stays in the HTTP layer, prompt 12)
- Catalog seeder (marker set derived from the demo reference field list — names/units/ranges as neutral orientation values, marked as content candidates pending ELYO-94 review)
- tests/Unit + tests/Feature for the service

Do not change:

- routes/api.php, controllers, OpenAPI (prompt 12)
- Any demo-branch files (not present on this branch anyway)

## Requirements

1. Readings are append-oriented: multiple readings per (subject, marker) with distinct measured_at; no unique(subject, marker) constraint.
2. Delete behavior: readings deletable by id (own-data correction per ELYO-102 §1.4); no cascade from identity users (retention concept, prompt 13, governs subject-level deletion).
3. Value validation at service level: numeric bounds sane-checked (positive, precision), per-marker plausibility ranges explicitly OUT (ELYO-114) — mark with TODO reference.
4. "Latest per marker" query is deterministic (measured_at desc, then created_at desc) and covered by a test with same-day duplicates.
5. Static assertion test: `lab_marker_readings` schema contains no user_id column; grep-test in CI or feature test asserting no production code references `App\Models\LabMarker` (the demo class name) — per ELYO-105 validation.
6. Seeds: catalog complete; demo employee gets a small synthetic reading history via subject (flagged synthetic).

## Constraints

- No new packages; keep the patch minimal.
- Status computation logic in one place (shared with prompt 12 responses).

## Privacy and Security Requirements

- health_subject_id only; no identity data anywhere in the health schema.
- Seeder output contains no subject ids.

## Validation

Run:

    docker compose exec api php artisan elyo:migrate-fresh --seed
    docker compose exec api php artisan test --filter=LabMarker
    docker compose exec api php artisan test

Expected result:

- New tests green; full suite green; fresh seed contains catalog + synthetic readings.

## Output Required

1. Files changed
2. Schema summary + status derivation decision
3. Commands run and results
4. Open questions

## Review Checklist

- History-capable (multiple timestamped values) proven by test?
- No user_id / no cascade-from-users anywhere in the new schema?
- Catalog content marked as pending fachliche Freigabe (ELYO-94)?
