# Task: Compose runtime split with nginx path routing

## Goal

Run `api-identity`, `api-employee`, `api-company` as separate compose services from the same image with their own ELYO_RUNTIME profile and own DB credentials; nginx routes by path so Angular keeps a single base URL. Angular unchanged except a verification.

## Context

Relevant files:

- docker-compose.yml, infra/ (nginx config location), apps/api-laravel/Dockerfile
- Runtime profiles (prompt 14), roles/credentials (prompt 02)
- ADR-001 §2.4 (one image, start profiles, no runtime-to-runtime communication, migrator role never in runtime containers), ADR-003 (D2)

Background:

- Path map: `/api/auth/*`, `/api/admin/*`, `/api/partner/*` → api-identity; `/api/employee/*` → api-employee; `/api/company/*` → api-company; `/api/health` → any (route to identity). Angular's base URL stays exactly as today.
- Each service gets ONLY its role's credentials (env): identity → elyo_identity_rt; employee → elyo_employee_rt + elyo_mapping_svc (mapping connection) + health + audit; company → elyo_company_rt + audit. Migration runs as a separate one-shot service/profile with elyo_migrator — never inside runtime containers.
- Reporting worker + privacy runtime: define compose profiles (`--profile future`) with no command or a sleep placeholder, documented as prepared-only.

## Scope

Change only:

- docker-compose.yml (three api services from one image, `migrate` one-shot service, future profiles; remove/repurpose the single `api` service — keep a dev convenience alias if helpful)
- nginx config (path routing per map above)
- .env.example (per-service credential vars)
- Makefile / scripts (up/migrate/test flows updated)
- README dev-setup section
- apps/web-angular: NO code change; verify environment base URL still matches (report only)

Do not change:

- Laravel code (prompt 14 finished it)
- Postgres init (roles exist)

## Requirements

1. `docker compose up` brings up all three runtimes + nginx; Angular login → employee dashboard → check-in works end-to-end through path routing.
2. `docker compose exec api-company env | grep DB_` shows no mapping/health credentials (assert in a smoke script `infra/smoke-runtime-split.sh`: env checks + curl per path prefix → correct runtime, cross-path 404s).
3. Migration flow: `docker compose run --rm migrate` executes `elyo:migrate-fresh --seed` with migrator credentials; runtime containers have no migrator env vars.
4. Update AGENTS.md validation commands (`docker compose exec api ...` → correct service names; tests run in which container — decide: a dev/tooling service with `full` profile for the test suite, document it).
5. Session/auth continuity across runtimes verified: Sanctum token issued by identity is accepted by employee/company runtimes (shared identity DB read) — smoke-tested.

## Constraints

- One image for all runtimes (build once).
- Keep resource footprint reasonable (shared volumes where safe: vendor build in image, not host-mount duplication chaos) — document choices.
- No aggregator/gateway container.

## Privacy and Security Requirements

- Credential sets strictly per matrix; no service holds all credentials (ADR-001 §2.10).
- nginx passes no internal headers exposing runtime topology beyond necessity.

## Validation

Run:

    docker compose config
    docker compose up -d && docker compose run --rm migrate
    bash infra/smoke-runtime-split.sh
    docker compose exec api-tooling php artisan test        # per chosen tooling service name
    docker compose exec web npm run build

Expected result:

- Smoke script green (routing + env isolation); suite green; Angular flows work manually.

## Output Required

1. Files changed
2. Service/credential matrix
3. Smoke script output
4. Commands run and results
5. Open questions

## Review Checklist

- Does any runtime container hold migrator or foreign-domain credentials?
- Does Angular work with zero changes?
- Is the test/tooling path clearly documented for daily development?
