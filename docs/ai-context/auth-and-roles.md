# Auth and Roles

## Current Decision

A user belongs to exactly one company.

A user belongs to at most one team through users.team_id.

A user must never be assigned to multiple companies.

## Portal Context

- app.* means employee portal
- company.* means company or HR portal
- partner.* is lower MVP priority

## Roles

- EMPLOYEE
- COMPANY_MANAGER
- COMPANY_ADMIN
- ELYO_ADMIN
- PARTNER

## Rules

- EMPLOYEE can access only their own data.
- COMPANY_MANAGER can access only assigned team scope.
- COMPANY_ADMIN can access company-level aggregated data.
- ELYO_ADMIN can access platform administration.
- PARTNER must be isolated from company and employee data unless explicitly designed otherwise.

## Guardrails

Company and manager users must never see individual employee health data.

Company dashboards only show aggregated data above anonymity threshold.
