# Task: Fix company integrity and enforce mandatory company assignment

## Goal

Enforce the ELYO product rule:

- Every user must belong to exactly one company.
- `users.company_id` must not be nullable.
- Even ELYO platform/admin users must belong to an internal ELYO company.
- Users should not operate without a company because the company context is part of the business and access model.
- The frontend must never be able to fake or override `company_id`.

## Architectural decision

This project is still in an early MVP phase and there is no relevant existing production data.

Therefore, it is allowed to adjust the existing core migration instead of adding a follow-up migration, if that produces a cleaner baseline schema.

Decision:

- `users.company_id` must be `NOT NULL`.
- The foreign key must not use `onDelete('set null')`.
- Company deletion must not orphan users.
- Prefer restrictive/no-action delete behavior for companies referenced by users.
- The ELYO platform admin must receive a real `company_id`.
- Seeders must create an internal ELYO platform company and assign it to the platform admin.

## Context

Current Codex analysis found:

- `users.company_id` is nullable.
- The migration uses `onDelete('set null')`.
- Employee check-in blocks missing company, but role middleware does not consistently enforce company presence for company/employee roles.
- Backend scoping is mostly server-side and should stay that way.
- Existing seed data may create platform/admin users without a company.

## Scope

Inspect and adjust:

- core users/company migration
- company/user seeders
- user factory if needed
- user model helpers if useful
- role/company middleware if needed
- auth/login/me behavior if needed
- relevant tests

## Required changes

1. Update the schema baseline.
   - Make `users.company_id` non-nullable.
   - Remove `nullable()` from the column.
   - Remove `nullOnDelete()` / `onDelete('set null')`.
   - Use restrictive/no-action delete behavior for the users-to-companies foreign key.
   - Do not introduce multi-company users.

2. Update seed data.
   - Create an internal ELYO company for platform/admin users.
   - Assign the ELYO platform/admin user this company_id.
   - Ensure all seeded users have a valid company_id.
   - Ensure test/demo employee/company users still belong to their correct companies.

3. Update factories/tests if necessary.
   - User factories must create or require a valid company by default.
   - Tests that intentionally need invalid companyless users may explicitly create them only if needed to test validation/middleware behavior.
   - Existing tests should not accidentally create invalid users.

4. Harden runtime behavior.
   - Company/employee scoped routes must continue to derive company context from the authenticated user.
   - Do not trust frontend-supplied `company_id`.
   - Middleware may still reject invalid users without company_id, but the DB should make this state impossible in normal operation.

5. Add or update focused tests.
   - Seeded users have company_id.
   - Platform admin has company_id.
   - Employee/company users cannot be created without company_id through normal factories/seeders.
   - Forged `company_id` payload does not override authenticated user company context if a suitable existing endpoint/test exists without broad refactor.
   - Company deletion cannot orphan users or is restricted.

## Out of scope

- Do not change invite acceptance flows unless required by the non-null company_id schema.
- Do not implement team fixes.
- Do not update OpenAPI in this patch.
- Do not touch frontend.
- Do not implement QR/event participation.
- Do not implement wallet changes.
- Do not do broad refactors.

## Hard constraints

- Do not run `migrate:fresh` automatically.
- Do not run `db:wipe`.
- Do not run `docker compose down -v`.
- Because there is no relevant data yet, changing the existing baseline migration is allowed.
- Any destructive local reset command must still be explicitly requested by the user, not run by Codex on its own.

## Expected output

After implementation, report:

- changed files
- migration changes
- seeder/factory changes
- tests added or updated
- validation commands run or recommended
- risks or open questions
