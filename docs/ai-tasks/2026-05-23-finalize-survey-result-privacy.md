# Task: Finalize Survey Result Privacy Before Commit

## Goal

Finalize the survey result privacy hardening before committing.

Close the remaining small metadata disclosure risks and clean up unrelated .gitignore noise.

## Context

The current diff already implements backend-side suppression for small survey-result buckets across SCALE, YES_NO and MULTIPLE_CHOICE.

Current review found no must-fix architecture or privacy leak, but several should-fix items:

1. .gitignore contains a literal EOF artifact.
2. .gitignore also adds /docs/ai-tasks/, which would hide useful task documentation.
3. SCALE still returns exact suppressedCount when scale buckets are suppressed.
4. There is no explicit test for question-level suppression when an optional/text question has fewer answers than the anonymity threshold.

Relevant files:

- .gitignore
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

- .gitignore cleanup
- SCALE suppressedCount behavior
- question-level suppression for low answer counts
- related tests
- Angular display handling if required
- OpenAPI schema if response shape changes

Do not change:

- auth
- routing
- database schema
- employee survey answering
- partner/admin features
- Docker setup
- broader controller architecture

## Requirements

1. .gitignore cleanup:
    - Remove the literal EOF line.
    - Remove /docs/ai-tasks/ from .gitignore.
    - Keep ignoring generated handoff/result artifacts such as:
        - docs/ai-handoff/current-*
        - docs/ai-results/latest.diff
        - docs/ai-results/latest.diffstat.txt
    - Do not ignore docs/ai-tasks/.

2. SCALE suppression metadata:
    - If any SCALE bucket is suppressed:
        - isSuppressed must be true
        - suppressedCount must be null
        - avgValue must be null
        - minValue must be null
        - maxValue must be null
    - Keep visible safe buckets only if this does not allow reconstruction.
    - Do not expose exact tiny hidden bucket sizes.

3. Question-level low-answer suppression:
    - If a question has answerCount > 0 and answerCount < company anonymity threshold:
        - mark the question as isSuppressed true
        - set answerCount to null
        - do not return distribution/options/counts/percentages/aggregates for that question
        - return suppressionReason: "QUESTION_THRESHOLD_NOT_MET"
    - If answerCount is 0, it may stay visible as 0 and no details are returned.
    - If answerCount >= threshold, keep existing type-specific suppression behavior.

4. TEXT questions:
    - Continue never returning raw text answers.
    - If answerCount is below threshold, apply question-level suppression.
    - If answerCount meets threshold, return answerCount only.

5. Tests:
    - Update SCALE suppression tests to assert suppressedCount is null.
    - Add a test for an optional or text question with answerCount below threshold:
        - global survey threshold is met
        - question-level answerCount is below threshold
        - response suppresses that question
        - exact answerCount is not exposed
        - no raw text or detail data is exposed
    - Keep existing YES_NO, MULTIPLE_CHOICE and SCALE privacy tests green.
    - Keep positive SCALE distribution test green.

6. Angular:
    - If question-level suppression is returned, show the existing neutral suppression message.
    - Do not display null answerCount as 0.
    - Do not show misleading empty charts or percentages.

7. OpenAPI:
    - Document that answerCount may be nullable for suppressed question-level results.
    - Document suppressionReason values:
        - "QUESTION_THRESHOLD_NOT_MET"
        - "DISTRIBUTION_SUPPRESSED"
    - Ensure suppressedCount is nullable.

## Constraints

- Do not expose individual employee health data.
- Do not expose tiny-group metadata through suppressedCount or answerCount.
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

- Full Laravel suite passes.
- Angular build passes.
- Docker Compose config remains valid.
- Diff whitespace check passes.

## Output Required

At the end, report:

1. Files changed
2. .gitignore cleanup
3. SCALE suppressedCount behavior changed
4. Question-level suppression behavior added
5. Tests added or updated
6. OpenAPI changes
7. Commands run and results
8. Any open questions