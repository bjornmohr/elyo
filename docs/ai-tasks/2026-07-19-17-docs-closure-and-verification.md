# Task: Documentation closure, blocker evidence and final verification

## Goal

Close the epic's documentation obligations: ELYO-110 evaluation doc (implemented path), ELYO-109 decision doc, DSFA blocker-evidence update, Jira comment drafts for all tickets, and a full validation run of everything built in prompts 01–16.

## Context

Relevant files:

- docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md (blocker table R1–R3, §7)
- ADR-002 §2.7 (blocker release mechanics: technical proof + DSB acknowledgment)
- docs/decisions/, docs/ai-results/ (if used), all prompt output reports
- Jira ELYO-91, 104–111

Background:

- Blocker release needs (a) boundary/privacy test proof for R1–R3 (health_subject_id + mapping domain + role separation) and (b) DSB acknowledgment — (b) is human-side; this task assembles the evidence for it.
- ELYO-110's deliverable was an assessment; per session decision D3 the migration was implemented instead — the doc records analysis, decision, and outcome.

## Scope

Change only:

- New: `docs/further-docs/elyo-110-wellbeing-health-subject-migration.md` (assessment → decision D3 → implemented state incl. prompt 08a scope; impacts on aggregation/streaks; follow-ups: points/surveys per ADR-001 §2.6, document storage hardening §2.9, as proposed new issues with draft descriptions)
- New: `docs/further-docs/elyo-109-checkin-note-decision.md` (note removed per ELYO-102 B4/3.3; reintroduction path gated on ELYO-109 privacy decision, additive with minimization concept)
- docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md: update the R1–R3 rows' "Umsetzungsstatus" with concrete evidence pointers (test suite names, prompt/commit refs) — do NOT change classifications; blockers formally remain until DSB acknowledgment (note this)
- New: `docs/further-docs/2026-07-19-elyo-91-jira-comments.md` — one ready-to-paste comment per ticket (ELYO-91, 104–111): what was built, evidence (tests/files), deviations, open points
- docs/ai-handoff/current-status.md refresh

Do not change:

- Application code (report defects as findings instead)
- ADR-001/002

## Requirements

1. Full validation battery executed and results captured verbatim in the Jira comments doc:
   - `docker compose config`
   - `docker compose run --rm migrate` (fresh + seed)
   - full test suite, `boundary` suite, `privacy` suite, `composer deptrac`
   - `bash infra/smoke-runtime-split.sh`
   - `docker compose exec web npm run build`
   - OpenAPI parity check: every route from `php artisan route:list` (full profile) exists in `docs/api/openapi.yaml` and vice versa; every schema change of the series (1–5 scale, note removal, lab endpoints, reporting_pending blocks, ULID ids) is reflected — mismatches are findings, not fixups
2. Epic acceptance criteria from ELYO-91 mapped to evidence in the ELYO-91 comment (table: criterion → proof).
3. Deviations register: every intentional deviation from ticket text across the series (e.g. ELYO-110 implemented not assessed, endpoints included in ELYO-105 scope, 3-of-5 mapping ops, company aggregates pending) with a one-line justification + ADR-003 reference.
4. Follow-up issue drafts (title + 3-sentence description) for: remaining mapping ops with reporting epic, document storage hardening (ADR-001 §2.9), points/surveys domain decision, retention periods legal decision, ELYO-144 privacy suite completion.
5. If any validation step fails: stop, document, and produce a fix-forward micro-task file instead of patching silently.

## Constraints

- Docs only; keep each Jira comment under ~30 lines, evidence-dense.

## Privacy and Security Requirements

- No secrets, subject ids, or personal data in any doc.

## Validation

Run: the full battery from Requirement 1.

Expected result:

- All green; documentation set complete and internally consistent.

## Output Required

1. Files changed
2. Validation battery results table
3. Deviations register
4. Follow-up issue list
5. Anything blocking DSB handover

## Review Checklist

- Does R1–R3 evidence point to reproducible commands, not prose claims?
- Are blockers still marked as formally open pending DSB acknowledgment?
- Could each Jira comment stand alone for a reviewer without repo access?

## Implementation Plan

### Scope and pre-implementation gates

1. Treat this as a documentation-only closure task. Do not change application
   code, tests, Docker/configuration, migrations, OpenAPI, frontend, backend, or
   ADR-001/ADR-002. Runtime startup is allowed only as transient validation
   state.
2. Preserve unrelated work already present in the worktree. Record the starting
   commit and inspect prompt/task files 01–16, their handoffs/results, relevant
   git history, and the implemented files/tests before making evidence claims.
3. Resolve these repository-state discrepancies before the documentation patch:
   - This task names new files under `docs/further-docs/`, while `AGENTS.md`
     requires new non-ADR documentation under `docs/further_docs/`. Do not
     silently choose or create duplicate files; obtain an explicit path decision
     and keep all three new documents in the approved directory.
   - `docs/ai-handoff/current-status.md` does not currently exist. Treat the
     scoped “refresh” as creation at that exact path unless an existing canonical
     handoff file is identified.
   - The DSFA risk table currently has no `Umsetzungsstatus` column. Add a narrow
     status column only if needed to satisfy the task, populate concrete evidence
     for R1–R3, leave other rows semantically unchanged, and do not alter either
     risk-classification column.
   - Requirement 5 conditionally requires a new fix-forward micro-task, although
     it is not in the normal “Change only” list. Treat that file as the sole
     conditional output after a failed validation; do not start the closure
     documents in that case.
4. Apply the documentation-only test-first exception from `AGENTS.md`: no new
   production or test code is planned. The required validation battery remains
   the acceptance gate and evidence source.

### Evidence inventory

1. Build a private working matrix for prompts 01–16 containing Jira ticket,
   implemented behavior, decisive file paths, focused test classes/suites,
   reviewed commit reference, intentional deviation, and remaining open point.
2. Verify every entry against repository state and git history. Do not copy
   planned behavior from task files as proof of implementation, and do not
   invent missing commit references.
3. For DSFA R1–R3, identify reproducible proof for:
   - health tables and models keyed by `health_subject_id`, with no health-domain
     `user_id`;
   - mapping access only through `MappingService`, using mandatory purpose codes;
   - PostgreSQL/runtime role separation and denied cross-domain access.
4. Map ELYO-91 acceptance criteria to concrete files, tests, commands, and
   commits. Separately collect the ADR-003-backed deviation set, including D3,
   D4, D5, D7, and D8.
5. Keep all evidence free of credentials, encryption material, personal data,
   raw health values, `user_id`, and `health_subject_id` values.

### Validation gate before documentation

1. Satisfy the known runtime-smoke precondition first: start the existing
   Compose services required by the split runtimes and nginx, including their
   declared dependencies, then confirm their running/healthy state with
   `docker compose ps`. Do not change Compose or weaken the smoke script.
2. Run the complete battery from the beginning, without reusing the earlier
   partial run:

   ```bash
   docker compose config
   docker compose run --rm migrate
   docker compose exec api-tooling php artisan test
   docker compose exec api-tooling php artisan test --testsuite=boundary
   docker compose exec api-tooling php artisan test --testsuite=privacy
   docker compose exec api-tooling composer deptrac
   bash infra/smoke-runtime-split.sh
   docker compose exec web npm run build
   docker compose exec api-tooling php artisan route:list
   ```

3. Run the reproducible operation-level route/OpenAPI audit against
   `route:list --json`: include only Laravel `api/*` operations, exclude
   `HEAD`/`OPTIONS`, strip the `/api` prefix, normalize parameter names, and
   compare method/path pairs with OpenAPI in both directions. Require zero
   missing and zero stale operations.
4. Audit the implemented contract against OpenAPI for the series-specific
   schema changes: check-in scale 1–5, removed note field, employee lab-marker
   endpoints, company `reporting_pending` blocks, and ULID identifiers.
   Mismatches are findings, never fixups in this task.
5. For every command, retain the exact command, exit status, and decisive
   terminal summary line verbatim. Omit incidental logs that could contain
   secrets or identifiers.
6. On the first failure, stop the battery and do not author closure documents.
   Create one scoped fix-forward task under `docs/ai-tasks/` containing the
   failing command, sanitized verbatim evidence, diagnosis boundary,
   reproduction steps, and rerun requirements. After a later fix, restart the
   entire battery from step 1.

### Documentation patch after a fully green gate

1. Create the ELYO-110 document at the approved path:
   - distinguish the original assessment request from session decision D3 and
     the implemented result;
   - cover prompt 08 and 08a scope, `health_subject_id` migration, 1–5 check-in
     scale, note removal, and impact on aggregation, streaks, and points;
   - state that company aggregates remain `reporting_pending`;
   - record points/surveys, document-storage hardening, and other deferred work
     as follow-up decisions, not implemented behavior.
2. Create the ELYO-109 decision document at the approved path:
   - record removal of `note` under ELYO-102 B4/§3.3;
   - explain data-minimization and privacy rationale without legal or medical
     claims;
   - define an additive reintroduction path gated by a future ELYO-109 privacy
     decision, explicit purpose/minimization rules, contract changes, and tests.
3. Update only the DSFA R1–R3 implementation-status cells with concrete,
   reproducible evidence: command, suite/test name, relevant file, and reviewed
   prompt/commit. Preserve all classifications and explicitly retain the formal
   blocker pending DSB acknowledgment under ADR-002 §2.7.
4. Create the Jira comment draft document:
   - provide one ready-to-paste, evidence-dense comment for ELYO-91 and
     ELYO-104–111, each no longer than about 30 lines;
   - make each comment understandable without repository access while still
     naming decisive files, tests, and commit references;
   - include an ELYO-91 criterion-to-proof table;
   - include the sanitized validation table with exact commands, exit statuses,
     and verbatim summary lines;
   - include the complete deviations register with one-line rationale and
     ADR-003 decision reference;
   - include title plus three-sentence drafts for remaining mapping operations
     with the reporting epic, ADR-001 §2.9 document-storage hardening,
     points/surveys domain decision, legal retention periods, and ELYO-144
     privacy-suite completion;
   - state open points and that DSB acknowledgment remains human-side.
5. Create or refresh `docs/ai-handoff/current-status.md` with the reviewed
   commit, completed prompt range, documentation outputs, green validation
   summary, deviations/follow-ups, and the remaining DSB handover blocker.
   Keep it concise and do not duplicate sensitive raw logs.

### Final consistency review and handoff

1. Check links, ticket numbers, ADR section references, prompt/commit
   references, terminology, and agreement among the ELYO-109/110 documents,
   DSFA rows, Jira drafts, and current-status handoff.
2. Confirm Jira comment line counts, the five required follow-up drafts, every
   ELYO-91 acceptance criterion, and every intentional deviation.
3. Review the path-limited diff and run `git diff --check`. Confirm no file
   outside the approved documentation set changed as part of this task.
4. Report:
   - files changed;
   - documentation behavior changed;
   - commands run and validation result table;
   - deviations register;
   - follow-up issue list;
   - open questions and anything still blocking DSB handover;
   - intentional deviations, including any approved path exception.

## Implementation Result

### Closure artifacts

- Created
  `docs/further_docs/elyo-110-wellbeing-health-subject-migration.md`.
- Created `docs/further_docs/elyo-109-checkin-note-decision.md`.
- Added reproducible R1–R3 implementation evidence to
  `docs/privacy/dsfa-vorpruefung-laborwerte-checkin.md` without changing the
  original risk classifications.
- Created
  `docs/further_docs/2026-07-19-elyo-91-jira-comments.md` with nine
  ready-to-paste ticket comments, the validation battery, ELYO-91
  criterion-to-proof mapping, deviations register, and five three-sentence
  follow-up drafts.
- Created the repository-required local handoff at
  `docs/ai-handoff/current-status.md`. This `current-*` artifact is
  intentionally gitignored by the repository but exists in the workspace.

The task requested `docs/further-docs/`; current `AGENTS.md` requires all new
non-ADR documentation in `docs/further_docs/`. The authoritative underscore
path was used, and no duplicate documentation tree was created.

### Validation result

- `docker compose config`: passed.
- Fresh migrate/seed: passed for Identity, Mapping, Health, and Audit.
- Full Laravel suite: `593 passed (7618 assertions)`.
- Boundary suite: `23 passed (111 assertions)`.
- Privacy suite: `71 passed (371 assertions)`.
- Deptrac: `Violations 0, Warnings 0, Errors 0`.
- Runtime split smoke: passed.
- Angular production build: passed.
- Laravel/OpenAPI operation parity: `77/77`, missing `0`, stale `0`.
- OpenAPI semantic schema audit: passed.
- Final `git diff --check HEAD`: initially failed, corrected — see the
  post-closure review correction below.

### Post-closure review correction

A review of this branch on 2026-07-26 re-ran the battery independently and
found that the recorded `git diff --check HEAD` result did not hold. Both new
closure documents ended with a trailing blank line:

```
docs/further_docs/elyo-109-checkin-note-decision.md:46: new blank line at EOF.
docs/further_docs/elyo-110-wellbeing-health-subject-migration.md:65: new blank line at EOF.
```

The check exited `2`, not `0`. The surplus newline was removed from both files
and `git diff --check HEAD` now exits `0` with no output. No document content,
classification, evidence pointer, or validation number changed.

The same review re-executed and confirmed, against the corrected worktree:
full suite `593 passed (7618 assertions)`, boundary `23 passed (111
assertions)`, privacy `71 passed (371 assertions)`, deptrac
`Violations 0, Warnings 0, Errors 0`, runtime split smoke
`runtime split smoke test passed`, Angular production build complete, route
list `Showing [82] routes`, operation parity `77/77` with `0` missing and `0`
stale, and the semantic OpenAPI audit. It additionally verified that the
`full` runtime is a strict superset of the three split runtimes (`union=77`,
`not_in_full=0`), that every cited test class and commit reference resolves,
and that `elyo_employee_rt` holds exactly the two intended column `UPDATE`
privileges and no table-level write privilege in both `elyo_identity` and
`elyo_identity_test`.

`docker compose run --rm migrate` was not repeated during that review because
it destroys local database state; its result remains as originally recorded.

### Validation fix-forwards and scope deviation

Task 17 correctly stopped twice before closure documentation:

1. The runtime smoke exposed a 500 when `api-employee` attempted Sanctum's
   normal token-use timestamp update. The test-first fix-forward
   `2026-07-26-fix-employee-runtime-sanctum-token-grant.md` added a
   column-limited PostgreSQL grant plus positive and negative role tests.
2. The operation audit found `77/71/13/7` Laravel/OpenAPI parity. The
   contract-only fix-forward `2026-07-26-fix-openapi-route-parity.md` added 13
   existing operations, removed 7 stale operations, and reached `77/77/0/0`.

Those changes are intentional exceptions to Task 17's normal docs-only scope,
authorized by Requirement 5's fix-forward flow and required before the closure
could resume. No unrelated application behavior, frontend, runtime topology,
credential set, ADR-001, or ADR-002 was changed.

### Remaining blocker

The technical R1–R3 condition in ADR-002 §2.7 is satisfied. Productive
real-health-data persistence remains formally blocked until the
Datenschutz-Verantwortliche acknowledges the DSFA pre-assessment without veto
and records date, participants, and any time-bound conditions.

## Tests & Validation

- Test-first applied: yes for the runtime regression; operation-parity red/green
  applied for the contract repair; documentation-only closure used the allowed
  test-first exception.
- Tests added/updated:
  - `PostgresRoleBoundaryTest` covers the only permitted employee-runtime token
    writes and denied protected Identity writes.
- ACs covered by tests:
  - DSFA R1: `HealthSchemaBoundaryTest`.
  - DSFA R2: `SourceBoundaryTest`,
    `SubjectMappingStorageBoundaryTest`, and
    `MappingNonJoinabilityPrivacyTest`.
  - DSFA R3: `PostgresRoleBoundaryTest`, `LabAccessPrivacyTest`, and runtime
    smoke.
- Validation commands executed:
  - Full battery and both explicit OpenAPI audits recorded in
    `docs/further_docs/2026-07-19-elyo-91-jira-comments.md`.
- Known gaps / intentionally not tested:
  - DSB acknowledgment is a human process.
  - Future Reporting Worker/privacy behavior remains ELYO-144 work.
