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
