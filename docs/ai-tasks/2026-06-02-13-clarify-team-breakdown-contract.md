# Task: Clarify Measure Participation Summary teamBreakdown Contract

## Goal

Clarify the intended API/QA contract for `teamBreakdown` in company measure participation summaries.

Manual QA found that the response includes:

- `teamBreakdown: null`

This does not leak data, but the QA checklist expected `teamBreakdown` to not be visible at all.

The goal of this task is to align documentation/checklist expectations with the implemented stable API contract.

## Decision

Keep `teamBreakdown` in the response as a nullable future field.

Current behavior:

- `teamBreakdown` is always `null`
- no team-level data is returned
- no individual participation data is returned

This is acceptable.

The QA/privacy rule should be:

- `teamBreakdown` may be present as `null`
- `teamBreakdown` must not contain team-level counts, rates, participant data, or any individual data until a dedicated privacy-reviewed team breakdown feature is implemented

## Scope

Documentation/checklist clarification only.

Inspect and update only relevant docs/task notes/checklists, such as:

- manual QA handoff or QA checklist files under `docs/ai-tasks/`
- current known issues / follow-up docs if present
- API contract notes if there is a project-local checklist

Do not change backend behavior unless inspection reveals the OpenAPI currently contradicts the implemented contract.

Do not remove `teamBreakdown` from the API response in this task.

## Out of Scope

Do not change:

- Laravel services/controllers/resources
- OpenAPI unless it currently contradicts `teamBreakdown: nullable`
- Angular
- tests
- migrations
- seeders
- Docker
- n8n
- Measure Participation product behavior
- company summary aggregation logic

Do not implement team breakdown.

## Expected Handoff

Return:

- Files changed
- Contract wording updated
- Confirmation that `teamBreakdown: null` is accepted as the current stable response
- Confirmation that non-null teamBreakdown data remains out of scope
- Confirmation that no product behavior changed

## Implementation Plan

1. Inspect only task-local documentation/checklist material under `docs/ai-tasks/` that references measure participation summary QA expectations or `teamBreakdown`.
2. Identify wording that treats `teamBreakdown` presence as a privacy failure when the value is `null`.
3. Update the relevant documentation/checklist wording so the accepted current contract is explicit:
   - `teamBreakdown` may be present as `null`.
   - `teamBreakdown` must not contain team-level counts, rates, participant data, identifiable response data, or individual health data.
   - Non-null team breakdown data requires a separate privacy-reviewed feature.
4. Do not change Laravel, Angular, migrations, seeders, Docker, n8n, tests, or product behavior.
5. Do not update OpenAPI in this task unless inspection shows it explicitly contradicts the nullable `teamBreakdown` contract; if it does, stop and report that as an out-of-scope finding instead of changing OpenAPI.
6. Do not run tests, builds, Docker commands, migrations, or destructive commands.
7. Review the final diff to confirm only documentation/checklist wording was changed and no unrelated task content was rewritten.
