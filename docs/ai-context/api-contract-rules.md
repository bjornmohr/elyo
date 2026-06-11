# API Contract Rules

## General

The API contract lives in:

    docs/api/openapi.yaml

Update OpenAPI when:
- a route is added
- a route is removed
- request body changes
- response body changes
- error behavior changes
- auth/role behavior changes

## Error Format

Use a consistent error format:

    {
      "error": {
        "code": "VALIDATION_ERROR",
        "message": "Validation failed.",
        "details": {}
      }
    }

## Form Validation

- Required fields must be validated in both the frontend and backend.
- Cross-field logical rules must be enforced in the backend and mirrored in the frontend when practical.
- Backend validation remains the source of truth.
- Frontend forms must display backend validation errors.
- Form submissions must not fail silently.

## Portal Eligibility and Aggregate Exclusion

- Company roles (COMPANY_OWNER, COMPANY_ADMIN, COMPANY_MANAGER) may also be employee-portal participants; `/api/employee/*` routes gate on `portal:employee` (`User::canUseEmployeePortal()`), not on a raw EMPLOYEE role list.
- Employee self-service routes act only on the authenticated user and must never expose another user's raw data.
- Report viewers (any user holding a company role, including multi-role EMPLOYEE + company-role users) are excluded from company/team aggregate values, eligible counts, and anonymity threshold calculations. Use `User::scopeReportableForCompanyAggregates()`; do not hand-roll role filters in aggregation queries.
- Exclusion applies to reporting only: personal check-ins, participation, points, and streaks keep working for report viewers.
- Backend remains the source of truth for eligibility and exclusion.

## API Design

- Use Laravel Resources for response shape.
- Use Form Requests for validation.
- Use middleware, policies or gates for role checks.
- Do not leak internal exception details.
- Use stable response keys.
