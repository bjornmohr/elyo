# Task: ADR-003 deployment topology and context docs update

## Goal

Document the ELYO-91 implementation decisions (session 2026-07-19) as ADR-003 and update the agent context docs so every following prompt inherits correct constraints. Docs only — no code.

## Context

Relevant files:

- ADR-001-Trennung-Identity-Mapping-Health-Reporting.md
- docs/ai-tasks/2026-07-19-00-elyo-91-execution-plan.md (decisions D1–D10)
- AGENTS.md
- docs/ai-context/architecture-decisions.md
- docs/ai-context/health-data-guardrails.md

Background:

- ADR-001 fixes the target architecture (4 DBs + audit, 5 runtimes, mapping contract with 5 operations, no runtime-to-runtime communication).
- The execution plan's decision table D1–D10 concretizes deployment for the pilot: multi-DB in one Postgres container, runtime split with nginx path routing, 3-of-5 mapping ops, Deptrac, Postgres-only testing, full health-domain move (wellbeing, anamnesis, documents, wearables), reporting-pending state for company aggregates.

## Scope

Change only:

- New folders: `docs/adr-documents/` and `docs/further-docs/`
- New file: `docs/adr-documents/ADR-003-Deployment-Topologie-Pilot.md` (format like ADR-001)
- Move (git mv, content unchanged): `ADR-001-Trennung-Identity-Mapping-Health-Reporting.md` and `ADR-002-DSFA-Vorpruefung-Scope-Methodik-Blocker-Steuerung.md` from repo root into `docs/adr-documents/`; update all repo-internal references to their paths
- AGENTS.md (add health-domain rules + documentation conventions + OpenAPI rule; keep existing rules intact)
- docs/ai-context/architecture-decisions.md
- docs/ai-context/health-data-guardrails.md

Do not change:

- Any code, migrations, compose files
- ADR-001 / ADR-002 content (moved, not edited)

## Requirements

1. ADR-003 captures D1–D10 verbatim in decision form, each with a short rationale and explicit reference to the ADR-001 section it concretizes or deviates from (notably: reporting DB prepared-only, 3-of-5 mapping ops, company aggregates reporting-pending).
2. AGENTS.md gains a "Health domain rules" block: no `user_id` columns in health-domain tables, mapping access only via `App\Services\Privacy\MappingService`, purpose code mandatory, no health models importable outside the health/privacy namespaces, new health data always on `health_subject_id`.
3. AGENTS.md gains a "Documentation conventions" block: ADR documents go exclusively into `docs/adr-documents/`; every other new documentation file goes into `docs/further-docs/` (existing files edited in place; `docs/ai-tasks/`, `docs/ai-context/`, `docs/api/` keep their established roles).
4. AGENTS.md gains an "OpenAPI contract rule" block: `docs/api/openapi.yaml` is the binding contract — any change to routes, request/response shapes, validation rules, error responses, or ID formats MUST update openapi.yaml in the same patch; a patch with API behavior change and untouched openapi.yaml is incomplete and fails review.
5. architecture-decisions.md gets a short entry per ADR (001–003) with one-line summary and file pointer (new `docs/adr-documents/` paths).
6. health-data-guardrails.md gains: check-in scale is 1–5 (canonical), free-text `note` removed per ELYO-102 B4, lab values never reportable (allowlist principle ADR-001 §2.5).
7. German language for the ADR (consistent with ADR-001/002); AGENTS.md and ai-context stay English.
8. Reference sweep: `grep -rn "ADR-001\|ADR-002"` over docs/ and repo root; every path-based reference points to the new location.

## Constraints

- Keep the patch minimal; docs only.
- Do not restate entire ADR-001 content — reference it.
- Mark anything you cannot derive from the listed sources explicitly as OPEN instead of inventing it.

## Privacy and Security Requirements

- No secrets, keys, or credentials in docs.
- Wording per health-data-guardrails.md (no diagnosis language).

## Validation

Run:

    git diff --stat

Expected result:

- New folders exist; ADR-001/002 moved with references updated; ADR-003 created; AGENTS.md + two ai-context files edited; nothing else touched.

## Output Required

1. Files changed
2. Any OPEN markers set and why
3. Deviations, if any

## Review Checklist

- Do D1–D10 appear completely and correctly in ADR-003?
- Are deviations from ADR-001 (reporting prepared-only, 3-of-5 ops) explicitly flagged as pilot concretizations?
- Do AGENTS.md rules match what prompts 02–16 will build?
- Are the documentation conventions and the OpenAPI contract rule unambiguous for future tasks?
- Are all ADR references repo-wide pointing to docs/adr-documents/?
