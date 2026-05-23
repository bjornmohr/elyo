# Task: Fix SCALE Survey Result Aggregate Privacy Leak

## Goal

Fix the remaining privacy leak in company survey SCALE results where suppressed scale buckets can still be reconstructed through aggregate values such as avgValue.

## Context

A previous task added bucket-level suppression for company survey results.

Current review found a must-fix issue:

- For SCALE questions, small distribution buckets are suppressed.
- minValue and maxValue are set to null when a bucket is suppressed.
- avgValue is still returned.
- This allows callers to reconstruct the hidden bucket value from visible distribution buckets, answerCount and avgValue.

Example:

- visible bucket: value 8, count 3
- answerCount: 4
- avgValue: 6.5
- inferred hidden value: 2

Relevant files:

- apps/api-laravel/app/Http/Controllers/Company/CompanySurveyController.php
- apps/api-laravel/tests/Feature/CompanyTest.php
- apps/web-angular/src/app/features/company/pages/surveys/company-surveys.component.ts
- docs/api/openapi.yaml

Relevant docs:

- AGENTS.md
- docs/ai-context/health-data-guardrails.md
- docs/ai-context/api-contract-rules.md

## Scope

Change only:

- SCALE result privacy handling
- related SCALE privacy test assertions
- frontend display logic for suppressed or null aggregate values
- OpenAPI survey results response documentation

Do not change:

- auth
- routing
- database schema
- employee survey answering
- company dashboard outside survey results
- partner/admin features
- Docker setup

## Requirements

1. Backend:
    - When any SCALE distribution bucket is suppressed, return:
        - avgValue: null
        - minValue: null
        - maxValue: null
    - Keep isSuppressed: true.
    - Keep suppressedCount.
    - Keep visible non-small distribution buckets.
    - Do not expose enough aggregate data to reconstruct suppressed bucket values.

2. Tests:
    - Update the existing SCALE suppression test to assert:
        - data.questions.0.avgValue is null
        - data.questions.0.minValue is null
        - data.questions.0.maxValue is null
    - Keep the assertion that the suppressed distribution bucket value is not returned.
    - Existing YES_NO and MULTIPLE_CHOICE suppression tests must remain green.

3. Frontend:
    - Do not display a numeric average when avgValue is null.
    - If a SCALE question is suppressed, show the existing neutral suppression message.
    - Avoid displaying misleading empty or 0 values.

4. OpenAPI:
    - Fix the company survey results response schema so it does not become misleading.
    - Include the fields actually returned by the endpoint:
        - scope
        - participation
        - minRequired
        - avgValue
        - minValue
        - maxValue
        - scaleMinLabel
        - scaleMaxLabel
        - isSuppressed
        - suppressedCount
        - distribution
        - trueCount
        - falseCount
        - truePercentage
        - falsePercentage
        - options
    - Mark avgValue, minValue and maxValue as nullable.
    - Mark trueCount, falseCount, truePercentage and falsePercentage as nullable.
    - Keep the schema pragmatic. Do not over-engineer oneOf unless it stays readable and accurate.

## Constraints

- Do not expose individual employee health data.
- Do not return raw text answers to company users.
- Do not introduce new packages.
- Keep the patch minimal.
- Do not weaken existing privacy tests.
- Do not change unrelated UI areas.

## Validation

Run:

    docker compose exec api php artisan test
    docker compose exec web npm run build
    docker compose config

Expected:

- All Laravel tests pass.
- Angular build passes.
- Docker Compose config remains valid.

## Output Required

At the end, report:

1. Files changed
2. Privacy leak fixed
3. Tests added or updated
4. OpenAPI changes
5. Commands run and results
6. Any open questions