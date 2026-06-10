# Task: QR Check-in v1

Date: 2026-06-10

## Goal

Implement the first narrow QR check-in flow for existing company measures.

Company users should be able to create or retrieve a QR/check-in link for an existing eligible measure. Employees should be able to redeem that token while authenticated. Successful redemption creates the existing `measure_participations` row with QR verification metadata.

This task builds on the existing Measures slice:

- Company Measures
- Employee Measures listing
- Employee self-report participation
- Measure domain metadata
- Participation verification metadata
- Company participation summary
- OpenAPI contract cleanup
- Aggregate-only privacy protections

## Core Behavior

QR Check-in v1 should support:

1. Company user generates or retrieves a QR check-in token/link for an existing measure.
2. Employee opens/scans the check-in link while authenticated.
3. Backend validates token and employee access.
4. Backend creates participation for the authenticated employee.
5. Participation stores:
   - `verification_type = QR_CHECKIN`
   - `verified_at = now`
   - `verified_by_user_id = null`
6. Existing duplicate participation protection remains active.
7. Points are awarded once through the existing `measure_participation` reason.
8. Company participation summary remains aggregate-only and threshold-protected.

## Explicitly Out of Scope

Do not implement:

- Admin confirmation
- Partner confirmation
- Public anonymous check-in
- Event calendar
- Measures Hub restructuring
- Persona/recommendation logic
- Questionnaire/check-in changes
- AI/video generation logic
- Point-award behavior changes
- `points_override` behavior
- Individual participation lists for company users
- Exposure of participant names/emails/user IDs to company users

## Security and Privacy Constraints

- Employee identity must always be derived from authentication.
- Do not accept user_id, company_id, team_id, participated_at, verification_type, or points values from the QR redemption request body.
- Do not expose individual participation rows to company users.
- Do not expose employee names, emails, user IDs, verification metadata, or individual timestamps in company summary.
- Token values must not be stored in plaintext.
- Store only a token hash.
- QR/check-in token must be sufficiently random and unguessable.
- Token redemption must check company scope, team scope, measure status, and duplicate participation.
- Do not allow employees from other companies to redeem a token.
- Do not allow employees outside the measure team scope to redeem a team-scoped measure token.
- Do not run destructive database commands.

## Proposed Data Model

Add a new table, for example:

`measure_checkin_tokens`

Fields:

- `id`
- `measure_id`
- `company_id`
- `token_hash`
- `created_by_user_id`
- `valid_from` nullable timestamp
- `valid_until` nullable timestamp
- `revoked_at` nullable timestamp
- `last_used_at` nullable timestamp
- `created_at`
- `updated_at`

Indexes / constraints:

- unique index on `token_hash`
- index on `measure_id`
- index on `company_id`
- index on `valid_until` if useful for lookup/cleanup

Do not store raw token values.

## Backend Requirements

### 1. Migration and Model

Create an additive migration for `measure_checkin_tokens`.

Create a Laravel model if project style expects it:

- `MeasureCheckinToken`

Relationships:

- belongsTo `Measure`
- belongsTo `Company`
- belongsTo creator `User`

Casts:

- `valid_from`
- `valid_until`
- `revoked_at`
- `last_used_at`

### 2. Token Generation

Create or extend a service, for example:

- `MeasureCheckinTokenService`

Behavior:

- Generate a cryptographically secure random token.
- Store only a hash of the token.
- Return the raw token only at creation/retrieval time if needed for QR link generation.
- Prefer one active reusable token per measure for v1 unless existing product assumptions require rotation.
- If an active non-expired token already exists for the measure, either:
  - return a newly generated raw token only if raw token can be safely reconstructed, which it cannot if only hashed, or
  - revoke/replace existing token and return the new raw token, or
  - store a separate non-secret display link identifier and hash a secret part.

Choose the simplest secure approach and explain it in the handoff.

Important:
Because raw tokens are not recoverable from hashes, a "get existing link" endpoint cannot return an old raw token unless the system stores reversible secret material, which it should not. Prefer "generate/rotate token" semantics over "retrieve old token" if necessary.

### 3. Company Endpoint

Add a company endpoint for token generation/rotation.

Suggested route:

- `POST /api/company/measures/{measure}/checkin-token`

Behavior:

- Authenticated company user only.
- Must pass existing company portal and role/scope checks.
- Measure must belong to the authenticated user’s company.
- Manager/team scoping must be respected.
- Measure should be active or otherwise eligible according to existing measure status rules.
- Measure should have `verification_requirement = SELF_REPORT` or a QR-ready requirement only if such requirement is introduced in this task.
- Since current company create/update only supports `SELF_REPORT`, do not require companies to create QR-required measures yet unless this task explicitly enables it.
- Generate/rotate token and return a check-in URL or token payload for QR rendering by the frontend.

Response should include non-sensitive metadata:

- `token` or `checkinUrl` only if raw token is newly generated
- `validFrom`
- `validUntil`
- `revokedAt`
- `measureId`

Do not return token hash.

### 4. Employee Redemption Endpoint

Add an employee endpoint for token redemption.

Suggested route:

- `POST /api/employee/measure-checkins/{token}`

Behavior:

- Authenticated employee only.
- Resolve token by hashing the provided token.
- Reject if token does not exist.
- Reject if revoked.
- Reject if not yet valid.
- Reject if expired.
- Reject if measure not active.
- Reject if measure company does not match employee company.
- Reject if measure is team-scoped and employee is not in that team.
- Reject duplicate participation with existing 409 behavior/code style.
- Create participation with:
  - `verification_type = QR_CHECKIN`
  - `participated_at = now`
  - `verified_at = now`
  - `verified_by_user_id = null`
- Award points once through existing behavior.
- Update token `last_used_at` on successful redemption.

Do not accept body identity fields.

### 5. Participation Service

Prefer reusing/extending `MeasureParticipationService` rather than duplicating participation logic.

Possible approach:

- existing self-report method remains unchanged
- add a dedicated QR method that accepts authenticated `User` and resolved `Measure`
- both paths share internal duplicate/creation/points behavior where practical
- verification type is explicit per path

Do not change self-report behavior.

### 6. Verification Constants

Extend minimal backend constants carefully.

Current runtime has:

- `SELF_REPORTED`

QR task may add:

- `QR_CHECKIN`

Do not add admin/partner runtime values unless needed. They remain future work.

### 7. OpenAPI

Update `docs/api/openapi.yaml` for:

- company token generation endpoint
- employee token redemption endpoint
- relevant request/response/error schemas
- employee participation response enum now including `SELF_REPORTED` and `QR_CHECKIN` if QR redemption can return employee measure participation state with QR value

Do not document admin/partner endpoints.

Do not document company access to individual participation rows.

### 8. Frontend

Keep frontend minimal.

Company UI:

- Add a small action on a measure to generate/rotate a QR check-in link.
- Display the returned link clearly.
- Do not build a full QR rendering library unless already available.
- If no QR library exists and adding one is too much, display a copyable check-in URL for v1 and document that actual QR rendering is a future UI improvement.
- Do not expose token hash.

Employee UI:

- Add minimal handling only if the app routes can support opening `/employee/measure-checkins/:token` or similar.
- If routing work is too large, backend endpoint and OpenAPI may be enough for v1, but document frontend gap.
- Do not add admin/partner UI.
- Do not restructure Measures Hub.

## Tests

Backend tests required:

- company can generate token for own eligible measure
- manager/team scoping is respected for token generation
- cross-company company user cannot generate token
- token hash is stored, raw token is not stored
- employee can redeem valid token
- redemption creates participation with `verification_type = QR_CHECKIN`
- `verified_at` is set
- `verified_by_user_id` is null
- points awarded once
- duplicate redemption returns 409 and does not award points twice
- cross-company employee cannot redeem token
- wrong-team employee cannot redeem team-scoped token
- inactive/revoked/expired/not-yet-valid tokens are rejected
- company summary remains aggregate-only and does not expose QR verification metadata

Frontend tests only if frontend changes are made.

OpenAPI should be updated and reviewed for contract consistency.

## Validation

Run non-destructive validation only:

- relevant Laravel feature tests for QR/token/measure participation
- existing CompanyTest / EmployeeTest / MeasureParticipationSummaryTest if touched
- Angular build/tests if frontend changes
- `git diff --check`
- `git status --short`
- OpenAPI validation command if one exists

Do not run:

- `migrate:fresh`
- `db:wipe`
- `docker compose down -v`
- destructive git reset/checkout commands

## Expected Handoff

Final handoff must include:

- summary
- files changed
- DB migration details
- token security approach
- company endpoint behavior
- employee redemption behavior
- self-report behavior preserved
- privacy/scoping notes
- tests run
- validation results
- open questions / intentionally deferred work
- recommended next task

## Implementation Plan

### Scope Decision

Implement QR Check-in v1 as a backend-first vertical slice with a minimal company UI for generating/copying a check-in URL only if the API slice is stable within the patch. Use rotate-on-request semantics for company token generation because raw tokens cannot be recovered from stored hashes. Do not implement retrieval of old raw tokens, QR image rendering, anonymous check-in, admin/partner confirmation, point policy changes, or individual participation lists.

### 1. Backend Data Model

Add an additive migration for `measure_checkin_tokens` with:

- `id`
- `measure_id` foreign key with cascade delete
- `company_id` foreign key with cascade delete
- `token_hash` unique string
- `created_by_user_id` nullable or constrained to users with null-on-delete depending on existing migration style
- nullable timestamps `valid_from`, `valid_until`, `revoked_at`, `last_used_at`
- normal timestamps

Add `App\Models\MeasureCheckinToken` with fillable fields, timestamp casts, and `measure`, `company`, and `creator` relationships. Add a `checkinTokens()` relationship to `Measure` only if needed by the service/tests.

### 2. Token Service

Create `MeasureCheckinTokenService` to own token creation, hashing, lookup, and validation. Use `Str::random()` or `random_bytes()`-backed Laravel helpers with enough entropy, store only `hash('sha256', $rawToken)`, and return the raw token only from the rotate/create call.

For company generation:

- verify the measure is eligible before token creation
- revoke existing active non-expired tokens for the measure by setting `revoked_at`
- create and return a new raw token plus token metadata
- build `checkinUrl` from frontend app URL/config if an existing reliable frontend base URL exists; otherwise return the raw token and let the frontend compose a relative route

For employee redemption:

- hash the route token and resolve `MeasureCheckinToken` with its `measure`
- reject missing, revoked, not-yet-valid, expired, inactive-measure, cross-company, and wrong-team cases
- update `last_used_at` only after successful participation creation

### 3. Company API

Add `POST /api/company/measures/{measure}/checkin-token` inside the existing authenticated company portal route group.

Implement a narrow controller action, either on `Company\MeasureController` or a dedicated `Company\MeasureCheckinTokenController` if that keeps the controller cleaner. Reuse the current company measure scoping rules:

- company admins/owners can generate for their company measures
- manager-only users must have team layer enabled and may generate only for their managed team measure
- manager-only users must not generate for company-wide measures unless existing measure rules explicitly allow that; current update logic requires exact managed-team ownership, so keep that stricter behavior for QR v1
- block foreign-company measures
- block inactive measures with a stable 409-style error

Return only non-sensitive fields plus the newly generated raw token or check-in URL:

- `measureId`
- `token`
- `checkinUrl`
- `validFrom`
- `validUntil`
- `revokedAt`

Never return `token_hash`.

### 4. Employee API

Add `POST /api/employee/measure-checkins/{token}` inside the existing employee route group.

Add an employee controller action or dedicated `Employee\MeasureCheckinController`. The request body should be ignored; authenticated user identity, company, team, timestamps, verification type, and points must all be derived server-side.

On success, return `201` with the existing employee `MeasureResource` shape for the redeemed measure, loaded with only the authenticated employee's participation. Keep duplicate participation conflicts aligned with the current `MEASURE_ALREADY_PARTICIPATED` 409 response.

### 5. Participation Service

Extend `MeasureParticipationService` without changing self-report behavior:

- keep `participate(User $user, int|string $measureId)` as the self-report path
- add a QR-specific method accepting the authenticated `User` and resolved `Measure`
- extract shared participation creation into a private method that receives verification type and timestamp behavior
- add `MeasureParticipation::VERIFICATION_TYPE_QR_CHECKIN = 'QR_CHECKIN'`
- preserve one participation per `(measure_id, user_id)` and existing points award behavior

For QR participation, create rows with:

- `verification_type = QR_CHECKIN`
- `participated_at = now`
- `verified_at = now`
- `verified_by_user_id = null`

### 6. OpenAPI Contract

Update `docs/api/openapi.yaml` in the implementation patch for:

- `POST /company/measures/{measure}/checkin-token`
- `POST /employee/measure-checkins/{token}`
- token generation response schema
- employee redemption response and error cases
- employee participation `verificationType` enum including `SELF_REPORTED` and `QR_CHECKIN`

Do not document any company individual participation rows or admin/partner verification endpoints.

### 7. Frontend Plan

If frontend is included in this v1 patch, keep it minimal:

- add an API method through the existing Angular API client/service pattern, not direct `fetch`
- add a compact action in `company-measures.component` to generate/rotate the check-in link
- display the returned link as copyable text
- do not add a QR rendering dependency unless already present

Employee route handling is optional for v1. If added, create a small authenticated employee route that reads the token from the URL, posts to the redemption endpoint, and shows success/conflict/error states. If this expands scope too much, leave employee frontend routing as an explicit deferred item and keep backend/OpenAPI complete.

### 8. Tests

Add focused Laravel feature coverage, likely in a new `MeasureCheckinTokenTest` or adjacent measure participation tests:

- company admin can rotate/generate a token for an active own-company measure
- token hash is stored and raw token is not stored
- token generation revokes/replaces previous active token
- manager scoping is enforced for team measures
- foreign-company generation is blocked
- inactive measure generation is rejected
- employee can redeem a valid token
- QR redemption creates `QR_CHECKIN`, `verified_at`, `verified_by_user_id = null`, and one point transaction
- duplicate redemption returns 409 and does not award points twice
- cross-company employee and wrong-team employee redemption are blocked
- revoked, expired, and not-yet-valid tokens are rejected
- company participation summary remains aggregate-only and does not expose verification metadata

Add frontend tests only if frontend files change.

### 9. Validation Commands for Patch Mode

Run only non-destructive checks after implementation:

- targeted Laravel feature tests for QR check-in and touched measure participation behavior
- existing measure participation and summary tests if those code paths changed
- `docker compose exec api php artisan route:list` if routes changed
- `docker compose exec web npm run build` if frontend changed
- OpenAPI validation command if the repository has one
- `git diff --check`
- `git status --short`

Do not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, destructive git commands, or destructive Docker/database commands.

### 10. Open Questions / Explicit Decisions

- Decision: use rotate-on-request semantics; no endpoint should claim to retrieve a previously generated raw token.
- Decision: store only SHA-256 token hashes; no plaintext or reversible token storage.
- Decision: keep points behavior unchanged and use the existing `measure_participation` reason once per successful participation.
- Unknown: whether a reliable frontend public base URL already exists for composing absolute `checkinUrl`; if not, return a token plus a relative employee check-in path.
- Unknown: whether employee URL handling should be included in this first patch or deferred after backend/OpenAPI are complete.

## Final Clarifications Before Implementation

### QR requirement contract

With QR Check-in v1, `verificationRequirement = QR_CODE` becomes an implemented company measure requirement.

Update company measure create/patch validation, OpenAPI, and minimal UI options so company users may choose:

- `SELF_REPORT`
- `QR_CODE`

Do not enable or document:

- `ADMIN_CONFIRMATION`
- `PARTNER_CONFIRMATION`
- `NONE`

### Self-report must respect QR requirement

If a measure has `verification_requirement = QR_CODE`, the existing employee self-report endpoint must not complete it.

For:

`POST /api/employee/measures/{measure}/participate`

when the measure requires QR:

- return a stable error response
- prefer `409 Conflict`
- use a clear error code such as `MEASURE_REQUIRES_QR_CHECKIN`
- do not create participation
- do not award points

### QR redemption must require QR_CODE measures

QR redemption should only succeed for measures with:

- `verification_requirement = QR_CODE`

If the measure is `SELF_REPORT`, reject QR redemption with a stable conflict/validation response.

This keeps the contract honest:
- SELF_REPORT measures use the existing participate button.
- QR_CODE measures use the QR check-in endpoint.

### Employee UI behavior

Employee Measures UI must not display the normal self-report `Teilnehmen` action for `QR_CODE` measures.

For QR-required measures, display neutral information such as:

- "QR-Check-in erforderlich"
- "Teilnahme vor Ort per QR-Code"

Do not implement QR scanning UI beyond the backend redemption route unless it remains small and explicit in the handoff.

### Token lifecycle

Use rotate-on-request semantics.

When a company user generates a new token for a measure:
- revoke existing active tokens for that measure
- create a new token hash
- return the raw token/check-in URL only once in the response

Do not store plaintext token material.

### Token validity defaults

If no product-specific validity window exists, create tokens without expiration for v1 unless this conflicts with existing security conventions.

If adding expiration is easy and product-safe, choose a simple default and document it clearly. Do not add complex scheduling logic.

### OpenAPI

Update OpenAPI so:
- company create/patch request-side `verificationRequirement` allows exactly `SELF_REPORT` and `QR_CODE`
- employee participation response `verificationType` allows `SELF_REPORTED` and `QR_CHECKIN`
- QR endpoints and their error responses are documented
- admin/partner/none behavior remains undocumented/unavailable

### Tests

Add tests proving:
- QR_CODE measure cannot be completed through self-report endpoint
- SELF_REPORT measure cannot be redeemed through QR endpoint
- QR_CODE measure can be completed through QR redemption
- points are still awarded once
- duplicate QR redemption returns existing duplicate conflict
