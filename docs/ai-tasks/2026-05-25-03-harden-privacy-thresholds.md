# Task: Harden privacy thresholds for dashboard and survey aggregation

## Goal

Prevent small-group re-identification by suppressing not only averages and distributions, but also participation/count metadata below the configured anonymity threshold.

## Context

Current Codex analysis found:

- Aggregation thresholds exist.
- Averages and distributions are suppressed below threshold.
- Some low-count metadata is still returned:
  - response counts
  - respondent counts
  - eligible counts
  - participation rate
  - current response count
- This can reveal participation patterns in small teams.

## Scope

Inspect and adjust:

- company dashboard aggregation
- wellbeing aggregation
- survey result aggregation
- report aggregation
- tests for threshold behavior
- API response shape where needed

## Required changes

1. Identify all aggregated company/team/survey responses.
2. Below the configured anonymity threshold, suppress or coarsen all sensitive metadata.
3. Return a generic suppressed response.
4. Preserve useful non-sensitive metadata only if it cannot enable re-identification.
5. Add focused tests.

## Hard constraints

- Do not expose individual health data.
- Do not trust frontend-supplied thresholds.
- Do not run `migrate:fresh`.
- Do not run `db:wipe`.
- Do not run `docker compose down -v`.
- Do not do a broad refactor.
- Do not change frontend unless required by API response changes.

## Expected output

After implementation, report:

- changed files
- response shape changes
- tests added or updated
- commands to run
- risks or open questions
