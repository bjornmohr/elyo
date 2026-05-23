# Task: Tighten YES_NO Suppression Metadata and Restore SCALE Positive Coverage

## Goal

Close the remaining privacy metadata issue for suppressed YES_NO survey results and restore positive-path test coverage for non-suppressed SCALE results.

## Context

The current survey results privacy hardening passes tests and build, but review found two should-fix issues:

1. YES_NO suppression still returns exact suppressedCount.
   This hides the yes/no orientation, but still reveals the exact tiny minority bucket size.

2. SCALE positive-path coverage was weakened.
   The existing test should verify avgValue, minValue, maxValue and distribution percentages when all scale buckets meet the anonymity threshold and have different values.

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

- YES_NO suppressed result metadata
- related YES_NO test assertions
- SCALE positive-path test coverage
- OpenAPI schema if suppressedCount nullability needs to be clarified
- Angular only if it explicitly renders suppressedCount, which it should not

Do not change:

- auth
- routing
- database schema
- employee survey answering
- company dashboard outside survey results
- partner/admin features
- Docker setup
- broader controller architecture

## Requirements

1. YES_NO backend behavior:
    - If either YES or NO bucket is below the company anonymity threshold:
        - set isSuppressed to true
        - set suppressedCount to null
        - set trueCount to null
        - set falseCount to null
        - set truePercentage to null
        - set falsePercentage to null
    - If both YES and NO buckets meet the threshold:
        - return normal true/false counts and percentages
        - set isSuppressed to false
        - set suppressedCount to 0 or null, whichever is consistent with the existing API style

2. Tests for YES_NO:
    - Update the existing suppressed YES_NO test to assert:
        - isSuppressed is true
        - suppressedCount is null
        - trueCount is null
        - falseCount is null
        - truePercentage is null
        - falsePercentage is null

3. SCALE positive-path test:
    - Add or update a test where all scale buckets meet the anonymity threshold.
    - Use at least two different scale values.
    - Verify:
        - isSuppressed is false
        - avgValue is correct
        - minValue is correct
        - maxValue is correct
        - distribution includes all expected scale buckets
        - distribution percentages are correct
    - Do not weaken the existing suppressed SCALE test.

4. OpenAPI:
    - Ensure suppressedCount is documented as nullable.
    - Ensure YES_NO count and percentage fields are documented as nullable.
    - Do not over-engineer the schema.

5. Frontend:
    - No UI change is needed unless the component displays suppressedCount.
    - The UI should continue showing a neutral suppression message for suppressed YES_NO questions.

## Constraints

- Do not expose individual employee health data.
- Do not expose tiny-group metadata through suppressedCount.
- Do not return raw text answers to company users.
- Do not introduce new packages.
- Keep the patch minimal.
- Do not refactor the controller into services in this task.
- Do not weaken existing privacy tests.

## Validation

Run:

    docker compose exec api php artisan test
    docker compose exec web npm run build
    docker compose config
    git diff --check

Expected:

- All Laravel tests pass.
- Angular build passes.
- Docker Compose config remains valid.
- Diff whitespace check passes.

## Output Required

At the end, report:

1. Files changed
2. YES_NO metadata behavior changed
3. SCALE positive-path coverage added
4. OpenAPI changes
5. Commands run and results
6. Any open questions