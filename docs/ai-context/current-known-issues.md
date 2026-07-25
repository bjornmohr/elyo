# Current Known Issues

## Active Focus

- Survey results overview was added recently.
- Current user observation: code behaves as programmed.
- Next likely focus: privacy hardening, tests and documentation alignment.

## Pending Capabilities

- Company wellbeing insights (dashboard `company`/`trend`/`teams[].metrics` blocks, `/company/reports`) are pending
  the reporting domain. The source moved into the health domain on `health_subject_id` (ADR-003 D3) and live
  aggregation from the company runtime was never ADR-001 §2.5 conform, so every affected block returns
  `{ "status": "reporting_pending", "data": null }` with HTTP 200. The dashboard `company` block additionally keeps
  `isAboveThreshold: null` and `responseCount: null` for the current Angular consumer. Blocks stay empty until the
  reporting epic (ADR-003 D7, `elyo_reporting`) delivers suppressed quarterly snapshots. Numbers return with that
  epic, not before.

## Open Checks

- Verify bucket-level suppression in survey results.
- Verify unique constraint for one survey response per user per survey.
- Verify manager team scoping.
- Verify OpenAPI matches implemented endpoints.
- Verify Angular survey results UI handles suppressed data correctly.
