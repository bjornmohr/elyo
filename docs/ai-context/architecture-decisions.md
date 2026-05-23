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

## Avoid

- Microservices for the MVP.
- Business logic in n8n.
- Business logic hidden inside Angular components.
- Direct frontend access to database-like structures.
- Medical diagnosis or therapy wording.
