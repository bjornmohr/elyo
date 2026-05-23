# Task: TODO Short title

## Goal

TODO Describe what should work after this task. Keep it concrete and testable.

## Context

Relevant files:

- TODO path

- TODO path

Relevant docs:

- AGENTS.md

- docs/ai-context/TODO-file.md

- docs/api/openapi.yaml

Background:

- TODO Important current behavior

- TODO Important previous decision

- TODO Known risk or bug

## Scope

Change only:

- TODO path or folder

- TODO path or folder

Do not change:

- TODO path or folder

- TODO path or folder

## Requirements

1. TODO Requirement

2. TODO Requirement

3. TODO Requirement

## Constraints

- Keep the patch minimal.

- Do not change unrelated areas.

- Do not invent missing legacy behavior.

- Mark unknowns explicitly.

- Do not introduce new packages unless explicitly required.

- Do not weaken existing tests.

- Preserve existing API response shapes unless this task explicitly says otherwise.

## Privacy and Security Requirements

- Do not expose individual employee health data.

- Do not expose raw free-text health answers to company users.

- Do not expose small-group metadata.

- Do not hardcode secrets.

- Do not leak internal exception details.

- Preserve company, team and user scoping.

## Validation

Run these commands:

    docker compose exec api php artisan test

    docker compose exec web npm run build

    docker compose config

    git diff --check

Expected result:

- Full Laravel test suite passes.

- Angular build passes.

- Docker Compose config remains valid.

- Diff whitespace check passes.

## Output Required

At the end, report:

1. Files changed

2. Behavior changed

3. Tests added or updated

4. Commands run and results

5. Open questions

6. Intentional deviations, if any

## Review Checklist

Before considering the task done, check:

- Does the change preserve the Angular/Laravel/PostgreSQL architecture?

- Does the change keep business logic in Laravel?

- Does the change preserve portal boundaries?

- Does the change preserve company, team and user scoping?

- Does the change avoid exposing individual health data?

- Are relevant tests included or updated?

- Does Angular still build?

- Does OpenAPI need an update?

- Is the diff small enough to review safely?