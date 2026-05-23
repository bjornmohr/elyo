# Task: Fix MULTIPLE_CHOICE Survey Result Inference Leak

## Goal

Fix the remaining privacy inference issue in company survey MULTIPLE_CHOICE results.

If any multiple-choice option bucket is below the company anonymity threshold, the endpoint must not expose a partial option distribution or exact suppressed count, because company users may know the full option list and infer which hidden option was selected.

## Context

A previous privacy hardening task added bucket suppression for survey result distributions.

Current review found a must-fix issue:

- MULTIPLE_CHOICE hides suppressed option labels.
- It still returns visible option counts/percentages, answerCount and suppressedCount.
- Since company users can know the full option list, a case with one omitted option and suppressedCount = 1 reveals that one employee chose the omitted option.
- This is not safe enough for health-related company reporting.

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

- MULTIPLE_CHOICE survey result privacy handling
- related feature tests
- frontend display logic for suppressed multiple-choice distributions
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

1. Backend MULTIPLE_CHOICE behavior:
    - If no option bucket is below the anonymity threshold, keep returning the normal option distribution.
    - If any option bucket is below the anonymity threshold:
        - do not return partial option distribution
        - do not return suppressed option labels
        - do not return exact suppressedCount
        - set options to an empty array
        - set isSuppressed to true
        - set suppressedCount to null
        - optionally return suppressionReason: "DISTRIBUTION_SUPPRESSED"

2. Keep total answerCount visible only if the global survey anonymity threshold is met.

3. Do not weaken existing SCALE privacy behavior:
    - suppressed SCALE buckets must not leak through avgValue, minValue or maxValue.

4. Do not weaken existing YES_NO privacy behavior:
    - if either true or false group is below threshold, exact split must remain hidden.

5. Tests:
    - Update the existing MULTIPLE_CHOICE suppression test.
    - The test must assert:
        - options is an empty array when any bucket is below threshold
        - isSuppressed is true
        - suppressedCount is null
        - hidden option labels are not present in the response
    - Add or keep a positive test where all multiple-choice buckets are above threshold and normal distribution is returned.
    - Keep SCALE and YES_NO privacy tests green.

6. Frontend:
    - If a MULTIPLE_CHOICE question isSuppressed, show a neutral suppression message.
    - Do not show partial options.
    - Do not display misleading 0% values.
    - Keep the UI simple and MVP-compatible.

7. OpenAPI:
    - Document that MULTIPLE_CHOICE options may be an empty array when distribution is suppressed.
    - Mark suppressedCount as nullable.
    - Document optional suppressionReason if implemented.
    - Update the 403 threshold response body to include:
        - error
        - minRequired
        - current
        - participation
        - isAboveThreshold

## Constraints

- Do not expose individual employee health data.
- Do not return raw text answers to company users.
- Do not expose small group membership through partial distributions.
- Do not introduce new packages.
- Keep the patch minimal.
- Do not weaken existing privacy tests.
- Do not change unrelated UI areas.

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
2. MULTIPLE_CHOICE privacy leak fixed
3. Tests added or updated
4. OpenAPI changes
5. Commands run and results
6. Any open questions