# Task: ELYO MVP gap analysis against current codebase

You are analyzing the current ELYO codebase against the product documents in ELYO-neu.

Context:
- Backend: Laravel API
- Frontend: Angular
- Database: PostgreSQL
- Product rule: each user belongs to exactly one company.
- Product rule: users should not operate without a company in the current business model because the company pays for access.
- Product rule: frontend must never be able to fake a user or company_id and access another company's data.
- Hard rule: do NOT propose or run destructive database reset commands such as migrate:fresh, db:wipe, docker compose down -v unless the task explicitly requires schema rebuild and permission is given.

Documents to compare against:
- ELYO-neu/ELYO_MVP.docx
- ELYO-neu/ELYO_Technische Integration.docx
- ELYO-neu/ELYO_Dashboard.docx
- ELYO-neu/ELYO_Innovation.docx
- ELYO-neu/ELYO_Screening.docx
- ELYO-neu/ELYO_Personas.docx
- ELYO-neu/ELYO_Vertiefungsbogen.docx

Please inspect the codebase and produce a concise but complete technical report with:

1. Current implemented features
   - Auth/login
   - roles/portals
   - company scoping
   - team scoping
   - employee check-in
   - survey/screening
   - points/wallet
   - dashboard/aggregation
   - QR/event features
   - exports/imports
   - OpenAPI coverage
   - tests

2. Security and privacy verification
   - Can the frontend pass or fake company_id/team_id/user_id?
   - Are all backend queries scoped from the authenticated user/server-side context?
   - Are employee endpoints properly protected?
   - Are company/admin endpoints properly protected?
   - Are individual health/check-in/survey answers ever exposed to HR/company users?
   - Are aggregation thresholds enforced?
   - Are small teams protected from re-identification?

3. Database model review
   - users.company_id
   - users.team_id
   - teams/company relation
   - check-ins uniqueness and company/team scoping
   - survey answers and aggregation model
   - points/events model
   - whether current indexes support expected queries

4. MVP gap list
   Compare current code against a realistic MVP 1:
   - Employee onboarding/screening
   - daily check-in
   - profile assignment
   - points/wallet basics
   - BGM measures
   - QR check-in
   - company dashboard
   - aggregated reports
   - CSV export
   - privacy thresholds
   - OpenAPI + tests

5. Output format
   Use this exact structure:
   - Verdict
   - Implemented
   - Partially implemented
   - Missing
   - Security/privacy risks
   - Data model risks
   - Recommended next 10 implementation steps
   - Files inspected
   - Tests that should exist
   - Manual test checklist

Do not change files. Analysis only.
