# Task: Mapping domain — health_subjects, subject_mappings, MappingService

## Goal

Create the protected mapping domain: `health_subjects` (health DB) and `subject_mappings` (mapping DB, field-encrypted) plus `App\Services\Privacy\MappingService` implementing the three pilot operations with mandatory purpose codes and an audit contract.

## Context

Relevant files:

- apps/api-laravel/database/migrations/{mapping,health}/ (from prompt 03)
- apps/api-laravel/config/database.php (connections `mapping`, `health`)
- ADR-001 §2.2/2.3 (subject model, 5 operations, status ACTIVE/REVOKED, tombstone)
- ADR-003 (D5, D10)
- Jira ELYO-104

Background:

- Every user gets one global, employer-independent `health_subject_id` (ULID). The user_id↔subject link exists ONLY in `subject_mappings`.
- Pilot implements `provisionOwnSubject`, `resolveOwnSubject`, `revokeSubjectLink`; `resolveReportingCohort` and `resolveForDataSubjectRequest` exist on the interface but throw a dedicated `OperationNotAvailableException`.
- Field encryption uses `MAPPING_ENCRYPTION_KEY` (not APP_KEY). Lookups use `MAPPING_HMAC_KEY`; orphan recovery uses the independent `MAPPING_SUBJECT_DERIVATION_KEY`. `user_id` itself is stored encrypted.

## Scope

Change only:

- New migrations in `database/migrations/health/` (`health_subjects`: ulid PK, status, timestamps) and `database/migrations/mapping/` (`subject_mappings`: id, `user_id_hmac` unique, `user_id_encrypted`, `health_subject_id` ulid, `status` ACTIVE|REVOKED, `revoked_at`, timestamps)
- New: `app/Services/Privacy/` (MappingService, contracts, PurposeCode enum, exceptions)
- New: `app/Models/Privacy/SubjectMapping.php`, `app/Models/Health/HealthSubject.php` (connection-pinned, `$guarded`, no relations to User)
- New: `app/Services/Privacy/AuditLoggerContract.php` + `NullAuditLogger` binding (real writer comes in prompt 07)
- config/services or dedicated `config/privacy.php` for keys
- tests/Feature/Privacy/, tests/Unit/Privacy/
- .env.example (three independent mapping-domain keys)

Do not change:

- Existing controllers/routes (integration happens in prompt 05)
- User model (no `healthSubject()` relation — forbidden by design)

## Requirements

1. `PurposeCode` enum (backed string): `PROVISIONING`, `HEALTH_SELF_READ`, `HEALTH_SELF_WRITE`, `REVOCATION`, `REPORTING`, `DSR` (extensible). Every MappingService method requires one; invalid purpose for an operation throws before any unavailable-operation guard.
2. `provisionOwnSubject(int $userId, PurposeCode $purpose): string` — all-or-nothing order: create HealthSubject first (health DB), then mapping row; idempotent (existing ACTIVE mapping returns existing subject id); repeatable after partial failure (orphan subject without mapping is adopted, not duplicated).
3. `resolveOwnSubject(int $userId, PurposeCode $purpose): string` — HMAC lookup; throws `MappingNotFoundException` (mapping absent) and `MappingRevokedException` (tombstone) as distinct types.
4. `revokeSubjectLink(int $userId, PurposeCode $purpose): void` — sets REVOKED + `revoked_at`, keeps the row as tombstone; final (no re-activation path).
5. Both remaining interface methods present, typed, throwing `OperationNotAvailableException` with reference to ADR-003 D5.
6. Every operation calls the AuditLoggerContract with: typed operation, purpose, runtime-correct typed actor context, subject reference — never user_id and health_subject_id in the same audit payload (pass a one-way subject reference; design detail documented in code).
7. Models: pinned `$connection`, no Eloquent relationship crossing domains, mass assignment locked down.
8. Tests: unit (HMAC determinism, encryption round-trip, purpose validation) + feature against the Postgres test databases (provision idempotency, orphan-adoption, resolve/revoke flows, distinct exceptions). Grant-level assertions stay in prompt 06's boundary suite.

## Constraints

- Keep the patch minimal; no HTTP layer.
- No new packages (use Laravel Crypt with custom key / hash_hmac).
- Do not weaken existing tests.

## Privacy and Security Requirements

- No plaintext user_id in the mapping table; no logging of key material.
- No code path returns the full mapping row to callers — only the resolved id.
- Exceptions must not embed user_id + health_subject_id together in messages.

## Validation

Run:

    docker compose exec api php artisan elyo:migrate-fresh --seed
    docker compose exec api php artisan test --filter=Privacy
    docker compose exec api php artisan test

Expected result:

- New Privacy tests green; full suite green; fresh migration works.

## Output Required

1. Files changed
2. MappingService public interface (signatures)
3. Encryption/HMAC design summary (3–5 sentences)
4. Commands run and results
5. Open questions

## Review Checklist

- Is provisioning order subject-first and provably idempotent?
- Is REVOKED truly final (no code path back to ACTIVE)?
- Do the models prevent cross-domain joins (no relations, pinned connections)?
- Are the two deferred operations guarded, not silently stubbed?
