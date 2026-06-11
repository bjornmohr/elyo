# Task: Fix QR Review Residuals

Date: 2026-06-10

## Goal

Fix the remaining non-tracking QR Check-in v1 review findings before merge.

The previously reported must-fix tracking issues are already handled:

- CompanyMeasuresService is now tracked.
- measure-checkin.component.spec.ts is now tracked.

This task must address the remaining review findings without changing the core QR feature behavior.

## Scope

Implement only the remaining QR review residuals:

1. Remove dead resolveActiveToken code.
2. Document CHECKIN_TOKEN_NOT_FOUND in OpenAPI for QR redemption 404 responses.
3. Add QR redemption test for inactive measures returning 409 MEASURE_NOT_ACTIVE.
4. Add company participation summary test proving QR participations remain aggregate-only.
5. Add orphaned-token guard in QR token lookup/redemption flow.
6. Normalize inconsistent 404 error shape if reachable.
7. Optionally reduce noisy tests around verificationRequirement without losing QR_CODE coverage.

Do not implement:

- new QR product behavior
- single-use tokens
- admin confirmation
- partner confirmation
- anonymous/public check-in
- event calendar
- measure hub changes
- points policy changes
- recommendations/personas
- unrelated frontend refactors
- destructive migrations or destructive validation commands

## Current Review Context

Architecture and privacy are acceptable:

- Business logic stays in Laravel services/controllers.
- Company users get no individual participation rows or verification metadata.
- Token hashes only, no plaintext token storage.
- Identity is derived from auth, never request body.
- Employee portal scope is enforced before lifecycle details are revealed.
- Manager team scoping for token generation is enforced.
- Health data guardrails are unchanged.

The remaining issues are cleanup, contract documentation, and missing focused tests.

## 1. Remove dead resolveActiveToken

### Problem

After the tenant-state leak refactor, no caller invokes MeasureCheckinTokenService::resolveActiveToken(...).

The flow now calls lookup and lifecycle validation separately.

Keeping the old method is misleading because future code may use it and accidentally reintroduce the tenant-state leak.

### Required behavior

Remove resolveActiveToken(...) if it is truly unused.

Before removing:

- search the repository for all usages
- confirm no controller/test/service uses it

After removing:

- ensure tests and PHP static references still pass
- do not replace it with another shortcut that validates lifecycle before scope

## 2. OpenAPI 404 schema for employee QR redemption

### Problem

POST /employee/measure-checkins/{token} returns structured 404 errors with error.code = CHECKIN_TOKEN_NOT_FOUND.

But the OpenAPI 404 response currently has only a plain description.

### Required behavior

Update docs/api/openapi.yaml for POST /employee/measure-checkins/{token}.

Document the 404 response schema and error code:

- CHECKIN_TOKEN_NOT_FOUND

This 404 must cover:

- unknown token
- foreign-company token
- wrong-team token
- orphaned token if explicitly guarded

Use an existing compatible error schema if the repo has one.

If no suitable schema exists, add a small dedicated schema such as MeasureCheckinTokenNotFoundError.

Do not document revoked/expired/not-yet-valid as 404. Those remain 409 only after scope is confirmed.

## 3. Add inactive-measure QR redemption test

### Problem

OpenAPI and runtime contract list 409 MEASURE_NOT_ACTIVE for QR redemption, but there is no focused QR-path test for it.

### Required behavior

Add a backend feature test proving:

- QR token belongs to an in-scope measure
- authenticated employee is eligible by company/team
- measure is not active
- redemption returns HTTP 409
- redemption returns error code MEASURE_NOT_ACTIVE
- no participation is created
- no points are awarded

Place the test near existing employee QR redemption tests.

Do not rely only on self-report inactive-measure tests.

## 4. Add company participation summary test for QR participations

### Problem

The task spec requires proving that company summary remains aggregate-only when QR participations exist.

### Required behavior

Add a backend feature test proving:

- a QR_CODE measure has one or more QR participations
- company participation summary endpoint still returns only aggregate/threshold-safe fields
- response does not expose verificationType
- response does not expose verifiedAt
- response does not expose verifiedBy
- response does not expose user_id
- response does not expose email
- response does not expose individual participation rows
- response does not expose individual timestamps
- threshold behavior remains intact

Use existing MeasureParticipationSummaryTest style if available.

Do not add company individual participation lists.

## 5. Add orphaned-token guard

### Problem

findTokenByRawToken eager-loads measure, but later code assumes checkinToken->measure is non-null.

The migration uses cascade delete, so orphaned tokens should not exist. But if one does exist, the redemption path can throw a PHP error instead of returning a safe 404.

### Required behavior

Add an explicit guard after token lookup:

- if token does not exist: return 404 CHECKIN_TOKEN_NOT_FOUND
- if token exists but has no measure: return 404 CHECKIN_TOKEN_NOT_FOUND

Do this before lifecycle checks.

Do not expose orphaned-token state through a separate error code.

Add a focused test only if practical without awkwardly violating DB constraints. If creating an orphaned token is cumbersome because of foreign keys/cascade behavior, document that the guard was added defensively and is not directly tested.

## 6. Normalize reachable 404 shape

### Problem

There is an inconsistent NotFoundHttpException catch branch that may return only message = Not found, while the QR token path otherwise returns the structured CHECKIN_TOKEN_NOT_FOUND error shape.

### Required behavior

If this catch branch is reachable in the QR redemption flow, normalize it to the structured QR token 404 shape.

Preferred:

- avoid relying on NotFoundHttpException for QR token visibility failures
- return or throw the project-style structured error consistently

If the catch branch is not reachable after the new flow, remove it or document why it remains.

Do not change unrelated 404 behavior outside the QR redemption endpoint.

## 7. Reduce noisy verificationRequirement test changes if practical

### Problem

Some existing company measure tests were changed from SELF_REPORT to QR_CODE to prove QR_CODE acceptance. This makes the original SELF_REPORT scenario less explicit and makes blame noisier.

### Required behavior

If practical and low-risk:

- restore existing broad domain-field create/update tests to use SELF_REPORT
- add separate focused tests for QR_CODE create/update acceptance

Keep coverage for both:

- SELF_REPORT
- QR_CODE

Do not spend time on this if it causes broad unrelated churn.

## 8. Active-token uniqueness residual

The prior review noted that SQLite does not meaningfully enforce the partial active-token uniqueness invariant in the same way PostgreSQL does.

For this task:

- do not expand into a CI/database infrastructure task
- do not redesign token rotation
- document the residual in the handoff if still true

This project targets PostgreSQL, and the PostgreSQL partial unique index remains the intended enforcement.

## Validation

Run non-destructive validation only:

- focused Laravel QR/token redemption tests
- relevant EmployeeTest
- relevant CompanyTest if company verification tests are touched
- relevant MeasureParticipationSummaryTest
- Angular tests/build only if frontend files unexpectedly change
- git diff --check
- git diff --cached --check if staging is used
- git status --short

Do not run:

- migrate:fresh
- db:wipe
- docker compose down -v
- destructive git reset/checkout commands
- unrelated full-suite destructive commands

## Expected Handoff

Report:

- summary
- files changed
- dead code removal result
- OpenAPI 404 schema update
- inactive-measure QR redemption test result
- QR participation summary privacy test result
- orphaned-token guard decision/testability
- 404 shape normalization
- whether noisy verificationRequirement tests were adjusted
- validation commands and results
- remaining risks/open questions
