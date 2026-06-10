# Task: Fix QR Token Tenant State Leak

Date: 2026-06-10

## Goal

Fix the remaining QR Check-in v1 tenant-boundary issue before merge.

The current QR redemption flow resolves token lifecycle state before enforcing employee company/team scope. This can leak token state across tenant/team boundaries:

- a foreign-company token may return revoked/expired/not-yet-valid conflicts
- a wrong-team token may return revoked/expired/not-yet-valid conflicts

Instead, foreign-company and wrong-team tokens must be indistinguishable from missing tokens.

## Current Problem

Current flow:

1. `EmployeeController` calls `MeasureCheckinTokenService::resolveActiveToken(...)`
2. `resolveActiveToken(...)` checks token lifecycle state
3. only later `MeasureParticipationService` checks employee visibility/company/team scope

This means an out-of-scope employee can learn whether a token exists and whether it is revoked, expired, or not yet valid.

That violates the tenant boundary and the OpenAPI contract, which documents wrong company/team as `404`.

## Required Behavior

For QR redemption:

`POST /api/employee/measure-checkins/{token}`

Return `404 CHECKIN_TOKEN_NOT_FOUND` for all of these cases:

- token hash does not exist
- token exists but belongs to another company
- token exists but belongs to a team-scoped measure and the employee is not in that team

Only after company/team scope is confirmed may the API return lifecycle-specific errors:

- `409 CHECKIN_TOKEN_REVOKED`
- `409 CHECKIN_TOKEN_EXPIRED`
- `409 CHECKIN_TOKEN_NOT_YET_VALID`
- `409 MEASURE_NOT_ACTIVE`
- `409 MEASURE_ALREADY_PARTICIPATED`
- `409 MEASURE_DOES_NOT_ALLOW_QR_CHECKIN`

Do not expose token state across tenant or team boundaries.

## Scope

Implement only the tenant-state leak fix and focused tests.

Do not implement:

- new QR product behavior
- single-use QR tokens
- admin confirmation
- partner confirmation
- anonymous/public check-in
- event calendar
- measure hub changes
- points policy changes
- recommendations/personas
- unrelated frontend refactors
- destructive migrations

## Backend Requirements

### 1. Refactor token resolution order

Update the QR redemption flow so scope is enforced before lifecycle details are revealed.

Preferred approach:

- split token lookup from lifecycle validation

Example responsibilities:

- `findTokenByRawToken(...)`
  - hashes raw token
  - loads token with measure
  - returns token or null
  - does not reveal revoked/expired/not-yet-valid state

- employee redemption flow:
  1. find token by hash
  2. if missing, return `404 CHECKIN_TOKEN_NOT_FOUND`
  3. check token measure company/team against authenticated employee
  4. if company/team mismatch, return `404 CHECKIN_TOKEN_NOT_FOUND`
  5. only now validate revoked/expired/not-yet-valid
  6. validate measure active status
  7. validate `verification_requirement = QR_CODE`
  8. create QR participation
  9. mark token used transactionally or according to the current explicit decision

Keep all identity values server-derived.

Do not accept user/company/team/timestamp/verification fields from request bodies.

### 2. Error contract

Use:

- `404 CHECKIN_TOKEN_NOT_FOUND` for missing/out-of-scope tokens
- existing `409` lifecycle errors only for in-scope tokens

Do not return lifecycle-specific error codes for foreign-company or wrong-team tokens.

### 3. Preserve current behavior

Keep existing intended QR behavior:

- QR_CODE can redeem through QR
- SELF_REPORT cannot redeem through QR
- QR_CODE cannot self-report
- duplicate QR redemption returns duplicate conflict
- points are awarded once
- token hashes only, no plaintext storage
- active-token rotation invariant remains unchanged
- company reporting remains aggregate-only

### 4. Tests

Add focused backend tests for cross-scope lifecycle masking:

#### Foreign company masking

Create a QR token for a measure in company A.

Authenticate employee from company B.

Assert redemption returns:

- status `404`
- error code `CHECKIN_TOKEN_NOT_FOUND`

Run this for at least:

- active valid foreign token
- revoked foreign token
- expired foreign token
- not-yet-valid foreign token

#### Wrong team masking

Create a team-scoped QR measure/token for team A.

Authenticate employee from same company but team B.

Assert redemption returns:

- status `404`
- error code `CHECKIN_TOKEN_NOT_FOUND`

Run this for at least:

- active valid wrong-team token
- revoked wrong-team token
- expired wrong-team token
- not-yet-valid wrong-team token

#### In-scope lifecycle still specific

Authenticate an eligible employee.

Assert in-scope tokens still return:

- revoked token: `409 CHECKIN_TOKEN_REVOKED`
- expired token: `409 CHECKIN_TOKEN_EXPIRED`
- not-yet-valid token: `409 CHECKIN_TOKEN_NOT_YET_VALID`

#### Unknown token

Assert unknown token returns:

- `404 CHECKIN_TOKEN_NOT_FOUND`

### 5. OpenAPI

Confirm `docs/api/openapi.yaml` matches the behavior:

- unknown/wrong-company/wrong-team token: `404 CHECKIN_TOKEN_NOT_FOUND`
- revoked/expired/not-yet-valid token: `409`, but only for in-scope token context

Update descriptions if needed to make this explicit.

Do not document tenant-state leakage.

### 6. Frontend

Only update frontend if error-code assumptions need adjustment.

The employee UI can continue showing a generic invalid-link message for:

- `CHECKIN_TOKEN_NOT_FOUND`

Do not add separate messages for foreign-company or wrong-team cases.

### 7. Active-token uniqueness note

Do not expand this task into a broader PostgreSQL-vs-SQLite test infrastructure refactor.

The active-token uniqueness guarantee is already implemented for PostgreSQL through the partial unique index. The SQLite test gap is a should-fix/nice-to-have unless current CI uses PostgreSQL.

Document this residual risk in the handoff.

## Validation

Run non-destructive validation only:

- focused Laravel QR/token redemption tests
- relevant EmployeeTest / CompanyTest if touched
- Angular tests/build only if frontend files change
- `git diff --check`
- `git diff --cached --check` if staging is used
- `git status --short`

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

Report:

- summary
- files changed
- new token resolution order
- tenant/team masking behavior
- lifecycle error behavior after scope validation
- tests added
- validation commands and results
- residual SQLite/PostgreSQL active-token uniqueness test gap
- remaining risks/open questions
