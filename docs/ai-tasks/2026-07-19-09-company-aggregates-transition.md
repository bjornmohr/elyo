# Task: Company wellbeing aggregates → explicit reporting-pending state

## Goal

Remove the company runtime's live read path on wellbeing data (now health-domain) and return a contract-documented `reporting_pending` state for the affected dashboard/report blocks, until the reporting domain (separate epic) delivers quarterly snapshots.

## Context

Relevant files:

- app/Services/AnonymityService.php (aggregated over `wellbeing_entries.company_id` — source gone since prompt 08)
- app/Http/Controllers/Company/CompanyController.php (dashboard), ReportController.php
- docs/api/openapi.yaml
- ADR-001 §2.5 (reporting only via suppressed quarterly snapshots — live aggregation was never conform), ADR-003 (D7)

Background:

- Health knows no company; `resolveReportingCohort` is deliberately deferred (D5). Any interim "quick aggregate" would recreate the violation this epic removes. The correct interim state is: data unavailable, clearly marked, UI-safe.

## Scope

Change only:

- AnonymityService: remove wellbeing-sourced methods or convert to explicit unavailable results (keep survey aggregation intact — surveys are identity-side and untouched)
- Company dashboard/report endpoints: affected blocks return `{ "status": "reporting_pending" }`-style payload (exact shape below), other blocks unchanged
- docs/api/openapi.yaml (mark affected fields nullable/pending with description referencing the reporting epic)
- Company feature tests

Do not change:

- Survey results aggregation, measure participation summaries (own sources, not wellbeing)
- apps/web-angular (existing UI must tolerate the shape — verify, see Requirements 3)
- Suppression thresholds/logic used by surveys

## Requirements

1. Affected wellbeing blocks respond with `status: "reporting_pending"`, `data: null`, and NO partial numbers; HTTP 200 (block-level state, not endpoint failure).
2. Zero remaining code paths from company/admin controllers to health connection or health models — verify via Deptrac (must already fail otherwise) plus explicit grep in the report.
3. Check the Angular company dashboard consumption: if the current UI would hard-crash on the new shape, keep field names present-but-null exactly where needed and document each; visual polish is NOT in scope.
4. Tests: company dashboard returns pending block for a company with (formerly) plenty of check-in data; no company endpoint contains mood/energy/stress/score values anywhere in its JSON (pattern assertion — reusable for prompt 16).
5. Add a short note in `docs/ai-context/current-known-issues.md`: company wellbeing insights pending reporting domain, pointer to reporting epic.

## Constraints

- Keep the patch minimal; resist building any interim aggregation.
- Do not remove AnonymityService suppression utilities still used by surveys.

## Privacy and Security Requirements

- No individual or aggregated health values in any company/admin response.
- Do not expose why-details (no counts of withheld data).

## Validation

Run:

    docker compose exec api php artisan test
    docker compose exec api composer deptrac
    docker compose exec web npm run build

Expected result:

- Suite + Deptrac green; Angular builds; company dashboard renders (manually verified against the pending state).

## Output Required

1. Files changed
2. Exact pending payload shape + list of null-kept legacy fields
3. Grep evidence: no health reads from company namespace
4. Commands run and results
5. Open questions

## Review Checklist

- Are surveys/measures aggregates untouched?
- Is the pending state contract-documented (OpenAPI) and test-covered?
- Could any code path still reach health from the company runtime?
