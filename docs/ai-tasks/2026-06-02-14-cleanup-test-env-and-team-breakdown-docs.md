# Task: Cleanup Test Environment Staging and teamBreakdown Contract Docs

## Goal

Fix the review findings before committing the Laravel test-environment isolation and teamBreakdown documentation clarification.

This is a cleanup task. It must not change product behavior.

## Findings to Fix

1. PHPUnit test-environment change is split between staged and unstaged changes.
   - `tests/bootstrap.php` and the environment assertion test are staged.
   - `apps/api-laravel/phpunit.xml` is unstaged.
   - A staged-only commit would add dead bootstrap code without the PHPUnit configuration pointing to it.

2. `teamBreakdown` contract is inconsistent across docs and OpenAPI.
   - New clarification accepts `teamBreakdown: null`.
   - QA checklist still expects `teamBreakdown` to be absent.
   - QA handoff still labels `teamBreakdown: null` as a low privacy/product-contract issue.
   - OpenAPI text says the endpoint always returns `teamBreakdown` as null, while the schema permits a nullable array.

3. Nice-to-have:
   - `apps/api-laravel/tests/bootstrap.php` silently deletes `bootstrap/cache/config.php` using suppressed unlink.
   - This is understandable, but should be documented directly in the file or fail loudly if removal fails.

## Scope

Allowed files:

- `apps/api-laravel/phpunit.xml`
- `apps/api-laravel/tests/bootstrap.php`
- `docs/ai-tasks/2026-06-01-11-manual-qa-measure-participation-flow.md`
- `docs/ai-tasks/2026-06-01-11-manual-qa-measure-participation-flow-handoff.md`
- `docs/ai-tasks/2026-06-02-13-clarify-team-breakdown-contract.md`
- `docs/api/openapi.yaml` only if needed to make the `teamBreakdown` contract wording consistent

Do not change product behavior.

## Required Fixes

### 1. Stage PHPUnit config with the test bootstrap

Ensure `apps/api-laravel/phpunit.xml` is included together with:

- `apps/api-laravel/tests/bootstrap.php`
- `apps/api-laravel/tests/Feature/MeasureParticipationTestEnvironmentIsolationTest.php`

Do not leave the PHPUnit bootstrap wiring unstaged.

### 2. Align QA checklist wording

Update the manual QA checklist so it no longer treats the mere presence of `teamBreakdown: null` as a privacy failure.

Correct rule:

- `teamBreakdown` may be present as `null`.
- `teamBreakdown` must not contain team-level counts, rates, participant data, identifiable response data, or individual health data.
- Non-null `teamBreakdown` data requires a separate privacy-reviewed feature.

### 3. Align QA handoff wording

Update the manual QA handoff so `teamBreakdown: null` is no longer described as a low privacy/product-contract issue.

Instead, document it as:

- accepted current contract
- no privacy impact while null
- non-null team breakdown remains out of scope

### 4. Align OpenAPI wording if needed

If OpenAPI currently describes `teamBreakdown` inconsistently, update only the description/schema wording.

Preferred contract:

- `teamBreakdown` is a nullable field.
- Current backend returns it as `null`.
- Non-null array data is reserved for a future privacy-reviewed team breakdown feature.
- Do not add actual non-null behavior.

Do not add individual participant fields.

### 5. Improve tests/bootstrap.php comment or failure behavior

If `tests/bootstrap.php` deletes `bootstrap/cache/config.php`, add an explicit comment explaining why.

Prefer avoiding suppressed failures.

Acceptable behavior:

- If cache file exists, attempt to delete it.
- If deletion fails, throw a RuntimeException with a clear message.
- Keep this test-only.

## Out of Scope

Do not change:

- Laravel application services/controllers/routes/resources
- Angular
- migrations
- seeders
- Docker
- n8n
- Measure Participation product behavior
- Company summary behavior
- tests beyond the bootstrap comment/failure behavior

Do not run destructive commands.

## Validation

Run:

- git diff --check
- docker compose exec api php artisan test --filter=MeasureParticipation
- docker compose exec api php artisan test --filter=MeasureParticipationSummary

If time permits:

- docker compose exec api php artisan test

Also verify staging:

- git diff --cached --name-only
- git status --short

## Expected Handoff

Return:

- Files changed
- Confirmation that phpunit.xml is staged with tests/bootstrap.php
- Confirmation that teamBreakdown docs/checklist/OpenAPI wording is consistent
- Confirmation that `teamBreakdown: null` is accepted current behavior
- Confirmation that non-null teamBreakdown remains out of scope
- Commands run and results

## Implementation Plan

1. Inspect current repository state without changing files.
   - Check `git status --short` and `git diff --cached --name-only` to confirm the staged/unstaged split described in the task.
   - Inspect only the allowed files listed in this task.

2. Fix PHPUnit test bootstrap staging consistency.
   - Confirm `apps/api-laravel/phpunit.xml` wires the test bootstrap expected by the staged `apps/api-laravel/tests/bootstrap.php`.
   - Stage `apps/api-laravel/phpunit.xml` together with the bootstrap and environment assertion test so a commit cannot include dead bootstrap code.
   - Do not change application runtime configuration.

3. Improve `apps/api-laravel/tests/bootstrap.php` failure behavior.
   - If it deletes `bootstrap/cache/config.php`, replace suppressed deletion with an explicit existence check and failure path.
   - Throw a clear `RuntimeException` if the test-only cache file cannot be removed.
   - Add a short comment explaining that this protects PHPUnit from stale cached Laravel configuration.

4. Align `teamBreakdown` documentation wording.
   - Update the manual QA checklist so `teamBreakdown: null` is accepted and not treated as a privacy failure.
   - Update the manual QA handoff so `teamBreakdown: null` is documented as the accepted current contract with no privacy impact while null.
   - Keep non-null team breakdown explicitly out of scope and subject to future privacy review.

5. Align OpenAPI wording only if needed.
   - Inspect `docs/api/openapi.yaml` for inconsistent `teamBreakdown` descriptions.
   - If inconsistent, update only description/schema wording to state that `teamBreakdown` is nullable, currently returned as `null`, and non-null array data is reserved for a future privacy-reviewed feature.
   - Do not add new response fields or non-null backend behavior.

6. Validate the cleanup after patching.
   - Run `git diff --check`.
   - Run `docker compose exec api php artisan test --filter=MeasureParticipation`.
   - Run `docker compose exec api php artisan test --filter=MeasureParticipationSummary`.
   - If time permits, run `docker compose exec api php artisan test`.
   - Verify staging with `git diff --cached --name-only` and `git status --short`.

7. Final review before handoff.
   - Confirm architecture and portal boundaries are unchanged.
   - Confirm no company, HR, or manager view gains individual health data, raw free-text answers, identifiable survey responses, or team-level values below a reviewed anonymity design.
   - Confirm OpenAPI is changed only if needed for contract wording consistency.
   - Report files changed, behavior changed, commands run, test/build results, open questions, and intentional deviations.
