# Audit logging concept

## Purpose and boundary

This is the pilot implementation of the append-only audit set from
[ADR-001 §2.7](../adr-documents/ADR-001-Trennung-Identity-Mapping-Health-Reporting.md#27-rollen-audit-und-break-glass).
Security-sensitive mapping access is written synchronously to the separate
`elyo_audit` database. Runtime roles can insert but cannot update or delete
events. The audit table has no foreign keys to Identity, Mapping, or Health.

## Event schema

| Field | Type | Meaning |
|---|---|---|
| `id` | ULID, primary key | Server-generated event identifier |
| `event_type` | string | Stable event name, such as `mapping.resolveOwnSubject` |
| `purpose` | string | Mandatory `PurposeCode` value |
| `actor_context` | JSON | Non-personal runtime/workflow classification described below |
| `subject_ref` | nullable 64-character string | One-way Health subject reference; unused by current mapping events |
| `user_ref` | nullable 64-character string | One-way Identity user reference used by mapping events |
| `outcome` | string | `success`, `denied`, or `failed` |
| `correlation_id` | string | Inbound ULID/UUID `X-Correlation-ID`, or a server-generated ULID |
| `occurred_at` | timestamp with time zone | Time at which the synchronous event was written |

`actor_context` always contains:

```json
{
  "type": "employee-self-service",
  "runtime": "employee-health-api",
  "role": "employee"
}
```

`role` is a non-personal authorization classification such as `system`,
`employee`, `privacy-admin`, or `reporting-worker`.
`provisioning.backfill` additionally contains a `summary` object with
`scanned`, `missing`, `active`, `revoked`, `provisioned`, `failed`, and
`dry_run`. These are operational counts only; no identifiers or health values
are included.

## Reference and immutability invariants

- A database CHECK constraint prevents `subject_ref` and `user_ref` from both
  being set in one row.
- Mapping events set only `user_ref`. It is HMAC-SHA-256 over the Identity ID,
  domain-separated with the `audit-subject:` prefix through
  `MappingCryptography`; the plaintext ID is never stored.
- Future Health access events must set only `subject_ref`, derived with
  HMAC-SHA-256 and an audit-specific domain/key. They must never store a raw
  `health_subject_id`.
- Audit rows contain no health values, free text, email addresses, or document
  contents.
- No Eloquent audit model exists, so application code has no update/delete
  API. PostgreSQL runtime grants remain INSERT-only; the migration owner is
  reserved for schema and retention operations.

## Covered event set

Each public MappingService call emits exactly one event, including rejected and
failed calls:

| Event type | Calls | Actor context |
|---|---|---|
| `mapping.provisioningStateForUser` | `provisioningStateForUser` | registration workflow / identity API |
| `mapping.provisionOwnSubject` | `provisionOwnSubject` | registration workflow / identity API |
| `mapping.resolveOwnSubject` | `resolveOwnSubject` | employee self-service / employee health API |
| `mapping.revokeSubjectLink` | `revokeSubjectLink` | privacy admin |
| `mapping.resolveReportingCohort` | guarded pilot call, currently `denied` | reporting worker |
| `mapping.resolveForDataSubjectRequest` | guarded pilot call, currently `denied` | privacy admin |
| `provisioning.backfill` | one summary after each backfill or dry run | registration workflow / identity API |

Backfill granularity is deliberate: MappingService calls retain one
per-subject event because they are independently security-relevant. The command
adds one run summary; it does not query or log raw identifiers and does not add
per-row logger calls outside the MappingService operations already required.

Domain rejections (`invalid purpose`, missing or revoked mappings, and guarded
pilot operations) use `denied`. Technical exceptions use `failed`.

## Fail-closed behavior

`DatabaseAuditLogger` writes directly on the `audit` connection. MappingService
invokes it synchronously from `finally`, without a catch that can swallow an
audit exception. A failed insert therefore replaces any result or domain
exception and prevents a subject resolution from returning. For provision and
revoke mutations, the Mapping/Health transactions stay open until the audit
insert succeeds; an audit failure rolls the mutation back.

Mapping and Audit are separate databases, so provision/revoke data writes and
their audit row cannot commit atomically. The audit insert commits before the
domain transactions. A later domain commit failure can therefore leave a
successful audit event without its corresponding mutation, but a domain
mutation cannot commit after an audit failure. Reconciliation must treat the
domain databases as authoritative for final state.

## Retention and alerting

ADR-001 §2.7 requires two-year retention. The retention/deletion mechanism is
implemented with the retention work in task 13; it must run as a narrowly
scoped maintenance role, not a runtime role.

Alerting sketch:

- alert on audit insert failures immediately because mapping access fails
  closed;
- alert on spikes in `denied`/`failed` outcomes by event type and runtime;
- alert on unexpected event types, malformed actor context, or rows violating
  the expected single-reference application convention;
- monitor for gaps in expected provisioning backfill summaries.

Alert payloads must contain aggregate counts and correlation IDs only.

## Deliberately deferred

ADR-001 §2.7 also names break-glass use, permission changes, deletions, exports,
document retrieval, reporting jobs, and failed accesses outside Mapping.
Non-self Health reads/writes are also part of the target audit set. Current
code has no such Health access call sites; their events land with those
features so this task does not invent HTTP or domain behavior. Break-glass
approval metadata, audit-reader UI/API, automated alert transport, and the
two-year retention job are likewise deferred.
