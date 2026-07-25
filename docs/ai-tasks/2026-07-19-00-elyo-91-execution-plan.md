# ELYO-91 Execution Plan — Health Data Model Hardening

> Master document for executing the prompt series `2026-07-19-01` … `2026-07-19-17` with Claude Code.
> Scope: Laravel backend, infrastructure, docs, and the two explicitly approved Angular adjustments (check-in scale/note, runtime routing). No other Angular work.

## Session decisions (2026-07-19, Björn)

| # | Decision |
|---|---|
| D1 | Domain separation as **separate PostgreSQL databases in the existing container**: `elyo_identity`, `elyo_subject_mapping`, `elyo_health`, `elyo_audit`; own PG role per runtime with minimal grants. (`elyo_reporting` prepared, not populated — no reporting worker yet.) |
| D2 | **Runtime split now**: `api-identity`, `api-employee`, `api-company` as compose services from one image via `ELYO_RUNTIME` profile; **nginx path routing** keeps a single base URL for Angular. No aggregator gateway (would violate ADR-001 §2.4). Reporting worker + privacy runtime prepared only. |
| D3 | `wellbeing_entries` moves **fully** into the health domain now (health_subject_id, scale 1–5, `note` removed) **including** the Angular check-in UI adjustment. ELYO-110 is documented as "implemented instead of assessed". |
| D4 | ELYO-105 includes the **employee lab-marker HTTP endpoints** per ELYO-102 §1 (list, history, POST manual, DELETE own). |
| D5 | Mapping operations: **3 now** (`provisionOwnSubject`, `resolveOwnSubject`, `revokeSubjectLink`), `resolveReportingCohort` / `resolveForDataSubjectRequest` as defined interface with not-implemented guard. |
| D6 | Static boundary enforcement via **Deptrac** (dev dependency) + runtime grants tests. |
| D7 | Company wellbeing aggregates lose their live source; endpoints return an explicit **reporting-pending** state until the reporting domain exists (live aggregation was never ADR-001 §2.5 conform). |
| D8 | Anamnesis profiles, health documents (DB side), and wearables move to the health domain **now** (prompt 08a) — consistency with the fresh schema rebuild. Storage hardening for documents (own bucket, signed URLs, virus scan — ADR-001 §2.9) is a follow-up ticket. Points and surveys stay on `user_id` (own epic: they break company aggregation features); flagged in the ELYO-110 evaluation doc. |
| D9 | **Postgres-only testing** (revised 2026-07-19): the sqlite lane is removed. All suites (default, `boundary`, `privacy`) run against Postgres test databases (`elyo_*_test`) with the real roles — one engine, production parity, grants testable. Docker is required to run tests (already the documented workflow). |
| D10 | Mapping table field-encrypted with dedicated key (`MAPPING_ENCRYPTION_KEY`), HMAC lookup column; KMS deferred per ADR-001. |

## Prompt series

| Prompt | File | Jira | Deliverable | Review focus |
|---|---|---|---|---|
| 01 | `…-01-adr-003-and-context-docs.md` | ELYO-91 | ADR-003 (deployment topology D1–D10), AGENTS.md + ai-context updates | Decisions correctly captured; no code |
| 02 | `…-02-multi-db-infrastructure.md` | ELYO-104 | initdb script (DBs + roles + grants), compose, .env.example, Laravel connections | Grants matrix matches ADR-001 access matrix |
| 03 | `…-03-migration-restructure.md` | ELYO-104 | Per-connection migration directories, identity schema rebuilt, fresh+seed tooling | No schema drift vs. current behavior; wellbeing untouched yet |
| 04 | `…-04-mapping-domain-service.md` | ELYO-104 | health_subjects + mapping tables, MappingService (3 ops, purpose codes, encryption, tombstone), audit contract (log stub) | Service interface, encryption/HMAC, REVOKED semantics |
| 05 | `…-05-subject-provisioning.md` | ELYO-104 | Synchronous provisioning after invite-accept identity commit, seeder provisioning, backfill command, failure paths | Subject → mapping order, generic failure flagging, idempotent repair |
| 06 | `…-06-boundary-enforcement.md` | ELYO-106 | Grants boundary tests (PG lane), Deptrac rules + CI | Standard connection provably cannot read mapping |
| 07 | `…-07-audit-logging.md` | ELYO-107 | Audit DB writer (INSERT-only), events for mapping ops, concept doc | Never user_id + health_subject_id in one entry |
| 08 | `…-08-wellbeing-health-domain.md` | ELYO-110/109 | wellbeing rebuilt in health domain (ULID, 1–5, no note/company_id), employee endpoints reworked via mapping | Contract per ELYO-102 §3; streak/points still work |
| 08a | `…-08a-anamnesis-documents-wearables.md` | ELYO-91 | Anamnesis, health-document metadata, wearables moved to health domain on health_subject_id | No user_id remnants; storage hardening explicitly deferred |
| 09 | `…-09-company-aggregates-transition.md` | ELYO-91 | Company dashboard/report wellbeing blocks → reporting-pending state | No health read path left for company runtime |
| 10 | `…-10-angular-checkin-adjustment.md` | ELYO-91 | Angular check-in 1–5, note removed, displays adjusted | Only approved Angular scope touched |
| 11 | `…-11-lab-marker-model.md` | ELYO-105 | Marker catalog + readings schema (history-capable), services, seeds, tests | health_subject_id only; no user_id column |
| 12 | `…-12-lab-marker-endpoints.md` | ELYO-105 | Employee endpoints per ELYO-102 §1 + OpenAPI + authz tests | 1.5 authorization; company/admin 403 |
| 13 | `…-13-retention-and-deletion.md` | ELYO-108 | Retention matrix, user-deletion flow (health wipe, mapping tombstone last), job skeleton | Deletion order; matrix completeness |
| 14 | `…-14-runtime-profiles.md` | ELYO-106 | `ELYO_RUNTIME` profiles: route subsets + connection allowlist, fail-safe default | Company profile has no mapping/health code path |
| 15 | `…-15-compose-runtime-split.md` | ELYO-106 | Compose services per runtime, per-runtime credentials, nginx path routing | One base URL for Angular; roles per container |
| 16 | `…-16-privacy-regression-suite.md` | ELYO-111 | `privacy` testsuite: leak patterns, join denial, grants, CI job | Fails on new leaking endpoint (pattern doc) |
| 17 | `…-17-docs-closure-and-verification.md` | all | ELYO-110 evaluation doc, ELYO-109 decision doc, DSFA pointer update, Jira comment drafts, full validation | Blocker evidence chain complete |

## Dependency graph

```
01 → 02 → 03 → 04 → 05 → 06 → 07 → 08 → 08a → 09 → 10
                                  08 → 11 → 12
                     06 ────────────────────→ 14 → 15
                     07/12/13 ──────────────→ 16 → 17
08/08a → 13
```
Strictly sequential execution in file order is always safe.

## Execution protocol (per prompt)

1. **Branch:** `git checkout -b elyo-91/<NN>-<slug>` from the previous reviewed state (or trunk after merge).
2. **Run:** Start a fresh Claude Code session in repo root: `claude "Execute the task in docs/ai-tasks/2026-07-19-<NN>-<slug>.md exactly as specified. Read AGENTS.md first."` A fresh session per prompt keeps context clean and the diff attributable.
3. **Self-validation:** the prompt's Validation section must pass before review (`php artisan test`, suite-specific commands, `docker compose config`).
4. **Review gate (you):** review the diff against the prompt's Review Checklist; every prompt is sized for a single review pass. Check the "Output Required" report for open questions / deviations first. **Standing check at every gate:** if the diff touches routes, validation, shapes, errors, or ID formats, `docs/api/openapi.yaml` must be updated in the same diff — otherwise the task is incomplete.
5. **Merge or iterate:** on findings, iterate **in the same session** ("fix finding X, do not touch anything else"). Merge only when green + reviewed.
6. **Jira:** move the mapped ticket, paste the report as comment (prompt 17 produces consolidated comment drafts).

## Failure / drift rules

- If a prompt cannot be completed as specified, the session must stop and report — no improvising outside Scope.
- Schema changes after prompt 03 always via **new** migration files within the domain directory; never edit reviewed migrations (pre-production, but review traceability matters).
- If a later prompt reveals a defect in an earlier one: fix-forward with a dedicated micro-task file, same template.
- `migrate:fresh` + reseed (all connections) is the reset path — no production data exists.

## Definition of done (epic acceptance mapping)

| ELYO-91 acceptance criterion | Evidence |
|---|---|
| Health features reference health_subject_id only | Prompts 08/11/12 + suite 16 |
| Mapping not trivially joinable | Prompts 02/04/06/14/15 + grants tests |
| Demo lab_markers schema excluded from production | Prompt 01 (docs) + 11 (statik check, no demo refs) |
| Audit/retention/free-text rules documented + implemented | Prompts 07/13/08 + docs in 01/17 |
| Production blockers resolved or explicitly open | Prompt 17 (blocker evidence table for DSFA §R1–R3) |
