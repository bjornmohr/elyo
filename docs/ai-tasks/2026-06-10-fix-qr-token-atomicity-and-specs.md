# Task: Fix QR Token Atomicity and Specs

Date: 2026-06-10

## Goal

Fix the remaining QR Check-in v1 merge blockers around token rotation atomicity, redemption side effects, and frontend test contract alignment.

The QR flow now preserves the intended domain contract:

- `SELF_REPORT` measures use the self-report participation endpoint.
- `QR_CODE` measures use the QR redemption endpoint.
- Company token generation is only allowed for `QR_CODE` measures.
- Company reporting remains aggregate-only.
- Token hashes are stored, raw tokens are returned only on rotation.

The remaining issue is that concurrent token rotations can leave multiple active tokens for the same measure, even though the API contract says rotation revokes existing active tokens. Also, QR redemption currently creates participation/points before updating `last_used_at`, and frontend specs use `422` where the API contract uses `409`.

## Scope

Implement only:

1. Make QR token rotation atomic enough to guarantee only one active token per measure.
2. Make redemption side effects consistent around `last_used_at`.
3. Align frontend check-in specs with the backend/OpenAPI `409` conflict contract.
4. Add missing focused tests for unknown/revoked/expired/not-yet-valid token redemption and token rotation uniqueness.

Do not implement:

- single-use QR tokens
- admin confirmation
- partner confirmation
- anonymous/public check-in
- event calendar
- measure hub changes
- points policy changes
- recommendations/personas
- unrelated frontend refactors
- destructive migrations or destructive validation commands

## Product Decision

QR tokens are intentionally reusable by multiple eligible employees for v1.

This supports the intended onsite/workshop model:

- one QR code can be displayed for a measure
- many eligible employees may redeem it
- each employee can participate only once because of the existing `(measure_id, user_id)` uniqueness rule

Do not change QR tokens to single-use tokens.

## 1. Token rotation atomicity

### Problem

Current rotation roughly does:

1. revoke currently active tokens
2. create a new token

Without row locking or a database constraint, two concurrent rotations can both create active tokens for the same measure.

This violates the contract that rotation replaces existing active tokens.

### Required behavior

After any successful rotation, there must be at most one active token for a measure.

"Active token" means a token for the measure where:

- `revoked_at IS NULL`

For v1, validity windows may be nullable and do not change the active-token uniqueness requirement.

### Preferred implementation

Use a database-level guarantee if compatible with the current database.

Since the project uses PostgreSQL, prefer a partial unique index:

- unique on `measure_id`
- only where `revoked_at IS NULL`

Example intent:

`unique active measure check-in token per measure where revoked_at is null`

Name it explicitly, for example:

`measure_checkin_tokens_one_active_per_measure`

If the migration is still part of the unmerged QR slice, update that migration directly.

If the migration has already been applied/merged in a shared environment, add a new additive migration instead.

Given this QR slice is currently unmerged, prefer updating the existing new migration.

### Service behavior

Keep rotation in a transaction.

Inside rotation:

1. validate measure is `QR_CODE`
2. validate measure is active
3. revoke existing active tokens for the measure
4. create the new token row

If a race still hits the unique constraint, handle it using the project’s normal exception behavior or retry once if that is already common style. Do not build complex retry infrastructure.

Do not expose token hash.

## 2. Redemption side effects and `last_used_at`

### Problem

QR redemption creates participation and awards points before `last_used_at` is updated. If `markUsed()` fails, the endpoint may return an error after participation/points were already committed.

### Required behavior

Make this explicit and safe.

Preferred:

- wrap token resolve/participation creation/points/mark-used in one database transaction

If wrapping everything in one transaction conflicts with existing service boundaries or creates unnecessary risk:

- make `last_used_at` best-effort
- ensure a `last_used_at` failure does not make the endpoint fail after participation was created
- document this decision in the handoff

Prefer transaction if practical.

Do not change participation duplicate behavior.

Do not change points amount/reason behavior.

## 3. Frontend spec status codes

### Problem

Employee check-in component specs currently use `422` for conflict-like check-in failures.

Backend/OpenAPI use `409`.

### Required behavior

Update the specs to use `409` for check-in conflict cases that are contractually conflicts, including:

- duplicate participation
- inactive measure
- revoked token
- expired token
- not-yet-valid token
- QR/self-report requirement conflicts if represented in the component specs

Do not change component runtime behavior unless the tests reveal an actual bug.

## 4. Missing backend tests

Add or update focused backend tests for:

### Token rotation uniqueness

- rotating a token twice for the same `QR_CODE` measure leaves only one active token
- previous token is revoked
- new token is active
- raw token is returned only for the new rotation
- token hash remains stored, raw token not stored

If practical, add a near-concurrent or transaction-level test for the unique active-token guarantee.

At minimum, assert the database has a unique active-token constraint/index or assert the migration contains the partial unique index.

### Token lifecycle redemption

Add tests for:

- unknown token returns `404`
- revoked token returns `409 CHECKIN_TOKEN_REVOKED`
- expired token returns `409 CHECKIN_TOKEN_EXPIRED`
- not-yet-valid token returns `409 CHECKIN_TOKEN_NOT_YET_VALID`

Keep existing tests passing for:

- `QR_CODE` can redeem through QR
- `SELF_REPORT` cannot redeem through QR
- `QR_CODE` cannot self-report
- duplicate QR redemption returns duplicate conflict
- points are awarded once
- company summary remains aggregate-only

## 5. OpenAPI

Only update OpenAPI if needed.

Confirm the documented status codes match runtime/tests:

- unknown token: `404`
- revoked/expired/not-yet-valid token: `409`
- duplicate participation: `409`
- inactive measure: `409`

Do not document single-use behavior.

Do not document admin/partner/public check-in behavior.

## 6. Validation

Run non-destructive validation only:

- focused Laravel QR/token tests
- relevant CompanyTest / EmployeeTest / MeasureParticipationSummaryTest if touched
- focused Angular specs for employee check-in component if available
- `docker compose exec web npm run build` if frontend specs/types changed and project expects build validation
- `git diff --check`
- `git diff --cached --check` if staging is used
- `git status --short`

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands
- unrelated full-suite destructive commands

## Expected Handoff

Report:

- summary
- files changed
- active-token uniqueness strategy
- whether existing QR migration was updated or a new migration was added
- redemption transaction/best-effort decision for `last_used_at`
- status-code spec alignment
- backend tests added
- frontend specs updated
- validation commands and results
- remaining risks/open questions
