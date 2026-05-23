# Task: Harden Company Survey Results Privacy

## Goal

Strengthen the company survey results endpoint so aggregated survey results cannot reveal very small answer groups.

This task is the first real workflow test for the Codex agent setup.

## Context

The current implementation already supports:

- GET /api/company/surveys/{id}/results
- company scoping
- manager team scoping
- global anonymity threshold
- SCALE aggregation
- YES_NO aggregation
- MULTIPLE_CHOICE aggregation
- no raw TEXT answer output

Relevant backend files:

- apps/api-laravel/app/Http/Controllers/Company/CompanySurveyController.php
- apps/api-laravel/app/Http/Resources/Company/SurveyResultsResource.php
- apps/api-laravel/tests/Feature/CompanyTest.php

Relevant frontend file:

- apps/web-angular/src/app/features/company/pages/surveys/company-surveys.component.ts

Relevant context docs:

- AGENTS.md
- docs/ai-context/health-data-guardrails.md
- docs/ai-context/auth-and-roles.md
- docs/ai-context/api-contract-rules.md

## Scope

Change only what is required for survey result privacy hardening.

Allowed backend areas:

- Company survey result endpoint
- Survey result resource/serialization
- Related feature tests
- OpenAPI only if response shape changes

Allowed frontend areas:

- Company survey results display
- Type definitions inside the same feature file if currently colocated

Do not change:

- authentication flow
- unrelated company dashboard code
- employee survey answering flow
- partner/admin features
- Docker setup
- database schema unless a test proves it is necessary

## Requirements

1. Keep the existing global anonymity threshold check.

2. Add bucket-level privacy protection for answer distributions.

3. SCALE results:
    - Do not expose individual scale buckets with count below the company anonymity threshold.
    - Do not expose raw small bucket values if that would reveal a small group.
    - Prefer returning a neutral suppression marker instead of misleading zero values.

4. YES_NO results:
    - If either trueCount or falseCount is greater than 0 but below the anonymity threshold, do not expose the exact true/false split.
    - Return a clear suppression indicator, for example `isSuppressed: true`.
    - Keep total answerCount visible only if the global survey threshold is met.

5. MULTIPLE_CHOICE results:
    - Do not expose options with count below the anonymity threshold as individual options.
    - Use a neutral suppressed bucket or a `suppressedCount`.
    - Do not expose the labels of suppressed options.

6. TEXT results:
    - Continue not returning raw text answers.
    - Keep only answerCount, provided the global survey threshold is met.

7. Frontend:
    - Show a neutral message when result details are suppressed.
    - Do not show misleading 0% values when data is suppressed.
    - Keep the UI simple and MVP-compatible.

8. Tests:
    - Add or update tests for MULTIPLE_CHOICE bucket suppression.
    - Add or update tests for YES_NO minority suppression.
    - Add or update tests for SCALE bucket suppression.
    - Existing manager team scoping tests must remain green.
    - Existing global anonymity threshold behavior must remain green.

## Constraints

- Do not expose individual employee health data.
- Do not return raw text answers to company users.
- Do not change route names.
- Do not introduce new packages.
- Keep the patch minimal.
- Do not weaken existing tests.
- Do not change unrelated UI areas.
- Do not invent legacy behavior.
- Mark uncertainty explicitly.

## Validation

Run:

    docker compose exec api php artisan test
    docker compose exec web npm run build

Expected:

- All Laravel tests pass.
- Angular build passes.

## Output Required

At the end, report:

1. Files changed
2. Privacy behavior implemented
3. Tests added or changed
4. Commands run and results
5. Any open questions