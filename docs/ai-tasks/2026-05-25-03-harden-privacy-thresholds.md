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

## Implementation Notes

Use elyo-privacy-review guidance because this touches wellbeing, surveys, company dashboards, reports, and anonymity thresholds.

- Under threshold, do not expose responseCount, respondentCount, eligibleEmployeeCount, eligibleCount, participationRate, current, checkedInCount, answerCount, bucket counts, bucket percentages, or tiny bucket labels.
- Use null for aggregate values where the response shape must remain stable.
- Add suppressionReason: ANONYMITY_THRESHOLD_NOT_MET.
- Trend points must not expose respondents. Remove or null respondents.
- Do not change general team management/member endpoints.
- Team memberCount may remain in team management, but must not leak as health-dashboard eligible metadata below threshold.
- Do not broadly redesign response shapes.
- Update OpenAPI only for actual response changes.
- Angular must not display exact suppressed/null counts as 0.

## Review Follow-up Required: Suppressed Distribution Answer Count Leak

A review found a must-fix privacy issue in survey question aggregation:

When a question distribution is suppressed because one or more non-zero buckets are below the anonymity threshold, the response still exposes answerCount. For YES/NO questions, this can reveal the exact split. Example: total answerCount = 4, threshold = 3, and suppressed buckets imply a 1/3 split.

Required changes:
1. In SurveyResultsAggregationService:
   - If a question result is suppressed because of small-bucket/distribution suppression, answerCount must be null or absent.
   - Apply this consistently for YES_NO, SCALE, and MULTIPLE_CHOICE question distributions.
   - Do not return bucket labels, bucket counts, percentages, or total answerCount when suppression would allow inference.
   - Keep raw text answers suppressed.

2. OpenAPI:
   - Update docs/api/openapi.yaml so answerCount is nullable or absent for suppressed question results.
   - Document the suppressed distribution semantics accurately.

3. Tests:
   - Update the current YES/NO test that asserts the leaking answerCount.
   - Add or update tests for YES_NO, SCALE, and MULTIPLE_CHOICE suppressed distributions.
   - Assert that answerCount is null or absent when isSuppressed is true due to distribution suppression.
   - Assert that unsuppressed above-threshold distributions still return answerCount and distribution values as expected.

Out of scope:
- Do not redesign survey response shapes broadly.
- Do not change dashboard/report privacy logic unless directly required by this fix.
- Do not change Angular unless API typing/build forces it.
- Do not normalize unrelated OpenAPI naming.
- Do not run destructive database commands.

Validation:
- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test
- git diff --check
- Angular build only if Angular files change.
