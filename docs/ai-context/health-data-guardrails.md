# Health Data Guardrails

## Non-negotiable Rules

- No diagnosis wording.
- No therapy promises.
- No individual employee health data in company views.
- No raw free-text health answers in company views.
- No identifiable survey responses in company views.
- No individual document access for company users.

## Safe Language

Prefer:
- orientation
- self-reflection
- resources
- burden indicators
- general measures
- aggregated trends

Avoid:
- diagnosis
- treatment
- cure
- medically certain claims
- individual risk classification for HR

## Health Data Canonical Rules

- Check-in scale is 1–5 (canonical). No other scale range.
- The free-text `note` field on check-ins is removed (per ELYO-102 B4). Do not reintroduce raw free-text on check-ins.
- Lab values are never reportable. Company/reporting views must never expose individual lab values or lab-value aggregates (allowlist principle, ADR-001 §2.5).

## Survey Results

Survey results shown to company users must be aggregated.

Apply:
- global anonymity threshold
- bucket-level suppression for small groups
- no raw text output
- no misleading charts when data is suppressed
