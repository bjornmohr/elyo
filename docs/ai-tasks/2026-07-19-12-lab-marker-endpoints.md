# Task: Employee lab-marker HTTP endpoints (ELYO-102 §1)

## Goal

Expose the lab model via the contract-decided employee endpoints: list (latest per marker), per-marker history, manual POST, DELETE own reading — with the §1.5 authorization model and OpenAPI update.

## Context

Relevant files:

- Health lab service + models (prompt 11)
- app/Services/Privacy/MappingService.php
- routes/api.php, app/Http/Controllers/Employee/
- docs/decisions/elyo-102-api-contract-entscheidungen.md §1 (1.1–1.5), §5 (A1–A4)
- docs/ai-context/api-contract-rules.md (error format)
- docs/api/openapi.yaml

Background:

- Endpoints: `GET /employee/lab-markers` (latest reading per marker + catalog metadata), `GET /employee/lab-markers/{markerKey}/history` (paginated, chronological; empty list not 404 if markerKey exists; unknown markerKey → 404), `POST /employee/lab-markers` (markerKey, value, measuredAt; source fixed `manual` in MVP), `DELETE /employee/lab-markers/{id}` (own readings only; foreign id → 404).
- Authorization 1.5: Sanctum, role EMPLOYEE only, own data via `resolveOwnSubject` (purpose HEALTH_SELF_READ/WRITE) — company/admin/partner → 403. Negative guarantee: no company/admin/reporting endpoint exposes lab values or aggregates.

## Scope

Change only:

- routes/api.php (employee group), new `Employee/LabMarkerController`, FormRequests, API Resources
- docs/api/openapi.yaml (new paths + schemas; status enum stable keys; source enum with reserved values)
- tests/Feature/Employee/ (full endpoint coverage)

Do not change:

- Health schema/services beyond what response shaping strictly needs
- apps/web-angular (lab UI is epic ELYO-93 — none exists on main)
- Company/admin routes

## Requirements

1. Response fields per 1.1: id (ULID), markerKey, name, unit, value, measuredAt, status, low, high, group, source. Status computed via the single shared derivation (prompt 11).
2. History endpoint: contract-defined pagination (page/perPage or cursor — pick, document in OpenAPI), chronological ascending, fields value/measuredAt/source/status.
3. POST validates markerKey against active catalog, value numeric, measuredAt date not in future; source not client-settable in MVP (server sets `manual`); returns created reading (201).
4. DELETE: resolves subject, deletes only readings of that subject; cross-subject id → 404 (not 403); audit via mapping resolution path.
5. Error responses follow api-contract-rules.md; role matrix tests: employee 200-family; company/admin/partner each 403 on all four routes; unauthenticated 401.
6. OpenAPI: schemas referenced per ELYO-102 IDs in descriptions (e.g. "per ELYO-102 1.1"); breaking-change section untouched (these are additive).

## Constraints

- Keep the patch minimal; controller thin, logic in the prompt-11 service.
- No aggregate endpoint of any kind.
- Plausibility ranges per marker remain out (ELYO-114) — validation is generic.

## Privacy and Security Requirements

- No health_subject_id in any response.
- No lab data reachable from company/admin/reporting routes (assert in tests).
- Foreign readings indistinguishable from non-existent (404).

## Validation

Run:

    docker compose exec api php artisan test --filter=LabMarker
    docker compose exec api php artisan test
    docker compose exec api php artisan route:list | grep lab-markers

Expected result:

- Four routes registered under employee middleware; all tests green.

## Output Required

1. Files changed
2. Route table (method, path, middleware)
3. Pagination decision
4. Commands run and results
5. Open questions

## Review Checklist

- Role matrix fully tested (employee/company/admin/partner/unauth)?
- 404-vs-403 semantics per 1.5 (foreign id → 404)?
- OpenAPI complete and referencing ELYO-102 decision IDs?
