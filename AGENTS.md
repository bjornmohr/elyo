# ELYO Agent Instructions

## Project Context

This repository is ELYO_TARGET.

The legacy reference codebase may exist at ../ELYO and must be treated as read-only.

Target architecture:
- Angular frontend in apps/web-angular
- Laravel API in apps/api-laravel
- PostgreSQL as primary database
- Redis for cache, sessions and queues
- Docker Compose for local development
- Mailpit for local mail testing
- n8n for integrations only, not business logic

## Non-negotiable Rules

- Do not modify ../ELYO.
- Do not introduce microservices.
- Do not switch PostgreSQL to MySQL or MariaDB.
- Do not create medical diagnosis or therapy claims.
- Do not expose individual employee health data to company, HR or manager users.
- Keep business logic in Laravel, not in Angular and not in n8n.
- Use OpenAPI as the contract between frontend and backend.
- Prefer small, reviewable patches.
- Do not change unrelated areas.
- Mark unknowns explicitly.
- Do not invent legacy behavior.

## Backend Rules

Laravel lives in:

    apps/api-laravel

Use:
- Controllers for HTTP entry points
- Form Requests for validation
- Resources for API responses
- Services for domain logic
- Policies and middleware for authorization
- Feature tests for API behavior

Important validation commands:

    docker compose exec api php artisan test
    docker compose exec api php artisan route:list

If migrations change:

    docker compose exec api php artisan migrate:fresh

## Frontend Rules

Angular lives in:

    apps/web-angular

Use:
- Feature folders
- Angular services for API calls
- Guards for role access
- Environment config for API base URL
- No direct fetch calls inside components

Important validation command:

    docker compose exec web npm run build

## Frontend Style Guideline

Alle UI-Arbeit am Angular-Frontend **muss** der Typografie- & Lesbarkeits-Guideline
folgen: `docs/ai-context/frontend-typography-guideline.md`.

Kernregeln (nicht verhandelbar):

- **Kein tragender Text unter 12 px.** `text-[10px]` und `text-[11px]` sind verboten.
- Fließtext und Werte ≥ 14 px (`text-sm`), Seitentitel `text-2xl`.
- Sekundärtext nicht heller als `text-slate-500`; Text auf farbigem Grund ≥ 13 px, Deckkraft ≥ 90 %.
- KPI-/Metrik-Zahlen groß (`text-3xl`), Kacheln mit ≥ `p-5` Padding, max. 3–4 Spalten.
- Interaktive Flächen ≥ 44 × 44 px.
- Fonts unverändert: Body `DM Sans`, Headings `Fraunces`.

Vor Abschluss jeder Frontend-Aufgabe prüfen:
`grep -rn "text-\[1[01]px\]" apps/web-angular/src` muss **0 Treffer** liefern.

## Docker Rules

The local stack must remain valid.

Before or after infrastructure changes, run:

    docker compose config

Do not use localhost for inter-container communication.

Use service names such as:
- postgres
- redis
- mailpit
- api
- web

## Health Data and Company Reporting Rules

Company users may only see aggregated data.

Never expose:
- individual employee health records
- raw free-text survey answers
- individual wellbeing entries
- identifiable survey responses
- personal medical documents

Respect anonymity thresholds when aggregating data.

For survey results:
- Global anonymity threshold must be met before showing results.
- Small answer buckets must not reveal tiny groups.
- Text answers must not be shown raw to company users.

## Output Expectations

For every task, report:

1. Files changed
2. Behavior changed
3. Commands run
4. Test/build result
5. Open questions
6. Intentional deviations, if any

## Review Expectations

Before considering a task done, check:

- Does it preserve architecture?
- Does it preserve portal boundaries?
- Does it preserve company/team/user scoping?
- Does it avoid leaking health data?
- Are tests included for changed backend behavior?
- Does Angular still build?
- Is OpenAPI updated if API behavior changed?

## Codex Workflow

For programming tasks, follow the workflow described in:

    docs/ai-context/codex-workflow.md

Default process:

1. Create a task file in docs/ai-tasks/.
2. Run plan mode before patch mode.
3. Keep patches small.
4. Run validation commands.
5. Create handoff files.
6. Review diff before commit.
7. Do not mix unrelated cleanup into feature/refactor commits.
