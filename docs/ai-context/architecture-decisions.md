# Architecture Decisions

## Current Target

ELYO_TARGET is the target implementation.

The legacy app may exist at ../ELYO and is read-only reference material.

## Stack

- Frontend: Angular
- Backend: Laravel API
- Database: PostgreSQL
- Cache/queues/sessions: Redis
- Local development: Docker Compose
- Mail testing: Mailpit
- Integrations: n8n, but not for core business rules

## Architectural Rules

- API-first.
- Angular talks to Laravel only through API endpoints.
- Laravel owns business logic.
- PostgreSQL remains the primary database.
- OpenAPI documents API contracts.
- Docker Compose must remain valid.
- Features should be migrated and hardened in vertical slices.

## Architecture Decision Records

Full ADRs live in `docs/adr-documents/`.

- **ADR-001** — Separation of identity, mapping, health, and reporting data (four DBs + audit, five runtimes, protected mapping domain with five purpose-bound operations). See `docs/adr-documents/ADR-001-Trennung-Identity-Mapping-Health-Reporting.md`.
- **ADR-002** — DSFA pre-check: scope, methodology, and blocker control for the privacy impact assessment. See `docs/adr-documents/ADR-002-DSFA-Vorpruefung-Scope-Methodik-Blocker-Steuerung.md`.
- **ADR-003** — Pilot deployment topology and implementation decisions D1–D10 (multi-DB in one Postgres container, runtime split with nginx path routing, 3-of-5 mapping ops, Postgres-only testing, reporting-pending company aggregates). See `docs/adr-documents/ADR-003-Deployment-Topologie-Pilot.md`.

## Avoid

- Microservices for the MVP.
- Business logic in n8n.
- Business logic hidden inside Angular components.
- Direct frontend access to database-like structures.
- Medical diagnosis or therapy wording.
