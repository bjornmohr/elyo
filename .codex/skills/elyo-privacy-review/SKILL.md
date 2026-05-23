---
name: elyo-privacy-review
description: Review ELYO health-data and company-reporting features for privacy, anonymity thresholds, aggregation safety and non-diagnostic wording.
---

# ELYO Privacy Review Skill

Use this skill for any code or UI that touches:

- wellbeing data
- surveys
- survey results
- health documents
- anamnesis profiles
- company dashboards
- reports
- recommendations
- measures hub

## Non-negotiable Rules

Company, HR and manager users must never see individual employee health data.

Do not expose:

- raw health records
- raw free-text answers
- identifiable survey responses
- personal documents
- individual wellbeing entries
- small groups below anonymity threshold

## Survey Results Rules

Check:

- global anonymity threshold
- bucket-level suppression
- no raw text answers
- no tiny subgroup exposure
- no misleading percentages when data is suppressed
- safe handling of YES_NO minority groups
- safe handling of MULTIPLE_CHOICE small buckets
- safe handling of SCALE distributions

## Wording Rules

Prefer:

- indication
- orientation
- trend
- aggregated view
- general measure
- resource
- self-reflection

Avoid:

- diagnosis
- treatment
- therapy promise
- cure
- individual risk claim
- medical certainty

## Output Format

Return:

1. Privacy verdict
2. Must-fix leaks
3. Ambiguous risks
4. Wording issues
5. Missing tests
6. Suggested mitigation
