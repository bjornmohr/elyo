# Task: Multi-database Postgres infrastructure and Laravel connections

## Goal

Provision `elyo_identity`, `elyo_subject_mapping`, `elyo_health`, `elyo_audit` (and empty `elyo_reporting`) with per-runtime PostgreSQL roles and minimal grants inside the existing postgres container, and expose them as named Laravel connections. No schema/table changes yet.

## Context

Relevant files:

- docker-compose.yml (single `postgres` service, one DB `elyo`)
- apps/api-laravel/config/database.php
- .env.example
- ADR-003 (D1), ADR-001 §2.1/2.4/2.9

Background:

- Today one DB/one superuser-ish role serves everything; boundary tests would be meaningless.
- Target roles (pilot): `elyo_identity_rt` (identity RW), `elyo_employee_rt` (identity read for auth + points RW, mapping via dedicated role NOT granted, health RW, audit INSERT), `elyo_company_rt` (identity RW limited, NO mapping, NO health, audit INSERT), `elyo_mapping_svc` (mapping RW only + audit INSERT), `elyo_migrator` (DDL on all domain DBs, never used by runtimes).
- The mapping DB is reachable exclusively via `elyo_mapping_svc`; app runtimes get a distinct Laravel connection `mapping` using those credentials so the OS/DB boundary — not PHP — enforces access.

## Scope

Change only:

- New: `infra/postgres/initdb/01-databases-and-roles.sql` (or `.sh`) mounted into postgres `/docker-entrypoint-initdb.d/`
- docker-compose.yml (mount + env for new credentials; keep single postgres service)
- .env.example (new DB_* variables per connection)
- apps/api-laravel/config/database.php (connections: `identity` [becomes default], `mapping`, `health`, `audit`)
- apps/api-laravel/.env.example if present

Do not change:

- Any migration, model, service, route
- apps/web-angular
- n8n/mailpit/redis services

## Requirements

1. initdb script creates the five databases, the five roles with passwords from env, and grants exactly per the matrix above; `REVOKE ALL ... FROM PUBLIC` on mapping and audit DBs.
2. Audit roles receive INSERT (and SELECT for `elyo_mapping_svc` only if needed for tests) — no UPDATE/DELETE anywhere on audit.
3. `database.php`: named connections `identity`, `mapping`, `health`, `audit`, all pgsql, credentials via distinct env vars; `DB_CONNECTION=identity` becomes the default; legacy `pgsql` connection kept as alias to identity for BC.
4. Existing app keeps working against `identity` (tables migrate there in prompt 03; for now the old `elyo` DB may simply be renamed/kept — document the chosen path).
5. Add a small shell script `infra/postgres/check-grants.sh` that asserts (via psql) that: identity runtime role cannot SELECT in `elyo_subject_mapping`; company role cannot connect to health; audit tables reject UPDATE/DELETE. Used by CI and prompt 06.
6. initdb additionally creates test databases `elyo_identity_test`, `elyo_subject_mapping_test`, `elyo_health_test`, `elyo_audit_test` with the **same roles and grants** as their production counterparts (prompt 03 switches phpunit to them; grant parity is what makes boundary tests meaningful in the test lane).
7. `docker compose config` stays valid; document a reset path (`docker compose down -v` re-runs initdb).

## Constraints

- Keep the patch minimal; no schema DDL beyond database/role creation.
- No new PHP packages.
- Do not hardcode secrets; defaults in .env.example only, clearly marked dev-only.
- Do not touch phpunit.xml — the test suite still runs on its current sqlite config until prompt 03 migrates it to the Postgres test databases; it must stay green here.

## Privacy and Security Requirements

- Mapping DB reachable only by `elyo_mapping_svc` and `elyo_migrator`.
- No role except `elyo_migrator` has DDL rights.
- Passwords distinct per role.

## Validation

Run:

    docker compose config
    docker compose down -v && docker compose up -d postgres
    docker compose exec postgres psql -U elyo -c '\l'
    bash infra/postgres/check-grants.sh
    docker compose exec api php artisan test

Expected result:

- Five databases exist; grants script passes all assertions; full test suite green.

## Output Required

1. Files changed
2. Final role/grant matrix as table
3. Commands run and results
4. Open questions

## Review Checklist

- Does the grant matrix match ADR-001's access matrix (company: no mapping/health)?
- Is the migrator role unused by any runtime service definition?
- Are all credentials env-driven?
- Is the legacy path (existing `elyo` DB) explicitly handled and documented?
