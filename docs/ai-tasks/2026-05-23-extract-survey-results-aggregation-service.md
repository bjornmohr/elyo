# Task: Extract Survey Results Aggregation Service

## Goal

Move company survey result aggregation and privacy suppression logic out of CompanySurveyController into a dedicated backend service.

The behavior must remain unchanged.

## Context

The previous commit hardened company survey result privacy.

The endpoint now includes:
- global anonymity threshold
- question-level suppression
- SCALE bucket suppression
- YES_NO bucket suppression
- MULTIPLE_CHOICE distribution suppression
- no raw TEXT answers
- Angular suppression display
- OpenAPI documentation
- feature tests for privacy behavior

The controller now contains too much privacy-critical aggregation logic. This task is a refactor only.

Relevant files:

- apps/api-laravel/app/Http/Controllers/Company/CompanySurveyController.php
- apps/api-laravel/tests/Feature/CompanyTest.php
- docs/api/openapi.yaml

Likely new file:

- apps/api-laravel/app/Services/SurveyResultsAggregationService.php

Relevant docs:

- AGENTS.md
- docs/ai-context/health-data-guardrails.md
- docs/ai-context/api-contract-rules.md

## Scope

Change only:

- Extract survey result aggregation/privacy logic into a service
- Keep controller behavior identical
- Keep tests passing
- Add unit tests only if useful and small

Do not change:

- API response shape
- OpenAPI schema
- Angular frontend
- auth
- routing
- database schema
- survey answering logic
- partner/admin features
- Docker setup

## Requirements

1. Create a service class for company survey result aggregation.

2. Move privacy-critical logic from CompanySurveyController into the service:
    - participation calculation
    - global threshold result
    - question-level suppression
    - SCALE aggregation and suppression
    - YES_NO aggregation and suppression
    - MULTIPLE_CHOICE aggregation and suppression
    - TEXT answer count handling

3. Keep CompanySurveyController thin:
    - authorization/scope checks may stay in the controller
    - request/response orchestration may stay in the controller
    - aggregation logic should move to the service

4. Do not change existing API response keys or values.

5. Do not change frontend behavior.

6. Do not weaken privacy behavior.

7. Existing tests must pass unchanged unless minor namespace/import adjustments are required.

8. If adding service-level tests is simple, add focused tests for:
    - question-level suppression
    - SCALE suppression
    - MULTIPLE_CHOICE suppression
      Otherwise rely on existing feature tests.

## Constraints

- Refactor only.
- No behavior changes.
- No new packages.
- Do not expose individual employee health data.
- Do not return raw text answers.
- Keep the patch minimal.
- Mark any uncertainty explicitly.

## Validation

Run:

    docker compose exec api php artisan test
    docker compose exec web npm run build
    docker compose config
    git diff --check

Expected:

- Full Laravel suite passes.
- Angular build passes.
- Docker Compose config remains valid.
- Diff whitespace check passes.

## Output Required

At the end, report:

1. Files changed
2. What logic moved into the service
3. Confirmation that API response shape did not change
4. Tests added or changed
5. Commands run and results
6. Any open questions