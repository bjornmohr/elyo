# Task: Audit logging — append-only audit DB writer for mapping and health access

## Goal

Replace the NullAuditLogger with a real writer into `elyo_audit` (INSERT-only), covering every mapping operation and non-self health access, plus a short audit concept doc.

## Context

Relevant files:

- app/Services/Privacy/AuditLoggerContract.php + NullAuditLogger (prompt 04)
- database/migrations/audit/ (empty, prompt 03)
- ADR-001 §2.7 (audit set, append-only, 2-year retention, never user_id + health_subject_id together)
- Jira ELYO-107

Background:

- Audited event set (pilot subset relevant to this epic): mapping operations (provision/resolve/revoke incl. denied/failed attempts), health-domain writes/reads outside employee self-access, provisioning backfill runs. Remaining ADR set (break-glass, exports, reporting jobs) lands with those features.
- Entries carry: event type, purpose code, actor context (runtime, role — not personal data where avoidable), subject reference (one-way), outcome, timestamp, request correlation id. Never user_id and health_subject_id in the same row.

## Scope

Change only:

- New migration in `database/migrations/audit/` (`audit_events` table; no FKs to other domains)
- New: `app/Services/Privacy/DatabaseAuditLogger.php` + container binding replacing NullAuditLogger
- MappingService call sites only if the contract needs additional context fields
- New doc: `docs/further-docs/audit-logging-concept.md` (documentation convention per AGENTS.md)
- tests/Feature/Privacy/ (audit assertions), tests/Boundary/ (INSERT-only grant already covered — extend if needed)

Do not change:

- Grants (INSERT-only already provisioned in prompt 02)
- Any HTTP responses

## Requirements

1. `audit_events`: ulid PK, `event_type`, `purpose`, `actor_context` (json, schema documented), `subject_ref` (nullable), `user_ref` (nullable), `outcome`, `correlation_id`, `occurred_at`; CHECK or application invariant: not both `subject_ref` and `user_ref` set. Choose refs as HMAC-based one-way values; document.
2. Writer inserts synchronously on the `audit` connection; on audit-write failure the mapping operation FAILS (fail-closed — an unauditable resolution must not happen); test this.
3. Every MappingService operation (success and failure paths) produces exactly one audit event; no N+1 logging in loops (backfill command batches a summary event per run + per-subject events — justify the chosen granularity in the doc).
4. Concept doc: covered events, field schema, invariants, 2-year retention pointer, alerting sketch (idea only, per ticket), what is deliberately deferred.
5. Tests: resolve creates event; failed resolve (missing mapping) creates denied event; both-refs invariant enforced; audit write failure blocks the operation.

## Constraints

- No new packages; no queue (synchronous, fail-closed).
- Keep the patch minimal.

## Privacy and Security Requirements

- No health values, no plaintext ids in audit rows.
- Audit rows immutable: no model update/delete methods; connection role has no UPDATE/DELETE (already enforced).

## Validation

Run:

    docker compose exec api php artisan elyo:migrate-fresh --seed
    docker compose exec api php artisan test --filter=Audit
    docker compose exec api php artisan test --testsuite=boundary
    docker compose exec api php artisan test

Expected result:

- All suites green; concept doc complete.

## Output Required

1. Files changed
2. Audit event schema table
3. Fail-closed design note
4. Commands run and results
5. Open questions

## Review Checklist

- Is fail-closed really enforced (no try/catch swallowing)?
- Can any row contain both user_ref and subject_ref?
- Is the event set traceable to ADR-001 §2.7 with deferred items named?
