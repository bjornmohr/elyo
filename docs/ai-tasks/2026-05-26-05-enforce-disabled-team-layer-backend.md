# Task: Enforce disabled company team layer in backend

## Goal

Enforce `companies.team_layer_enabled = false` on backend team-scoped operations.

The previous slice introduced the company team-layer setting and wired it through auth/company responses, invitation behavior, OpenAPI, and minimal Angular navigation/invitation UI. This task closes the backend gap: hiding teams in Angular is not an authorization boundary.

## Architectural decisions

- Every user belongs to exactly one company.
- `users.team_id` remains nullable.
- `users.team_id` represents the user's own organizational team affiliation.
- Team management scope is separate from `users.team_id`.
- `COMPANY_MANAGER` is a team-scoped role and is only meaningful when team layer is enabled.
- Company admins manage company-wide.
- Company managers manage only assigned/managed teams.
- Employees without a team are valid.
- Company-level health/reporting must continue to work when team layer is disabled.
- Team-level operations must be rejected or safely limited when team layer is disabled.
- Privacy thresholds must not be weakened.
- Angular hiding is not security; Laravel must enforce the rule.

## Scope

Backend-only enforcement for disabled team layer.

This task should prevent direct API access to team-scoped behavior when `team_layer_enabled = false`.

## Required backend behavior

1. Team management endpoints

When the authenticated user's company has `team_layer_enabled = false`:

- Creating teams should be rejected.
- Updating teams should be rejected.
- Deleting teams should be rejected.
- Viewing team members should be rejected.
- Listing teams may either:
  - return an empty list for frontend compatibility, or
  - return 403.

Choose the behavior that best fits existing API conventions and document/test it. Prefer consistency over cleverness.

2. Team-scoped dashboard/reporting

When `team_layer_enabled = false`:

- Company-level dashboard/reporting must still work.
- Team-scoped dashboard/reporting parameters must be rejected or ignored safely.
- Do not return team-level aggregates.
- Do not expose team-level eligible/member/participation metadata.
- Do not weaken anonymity/privacy thresholds.

3. Survey team targeting

When `team_layer_enabled = false`:

- Creating or updating surveys with team targeting must be rejected.
- Company-wide surveys must still work.
- Survey results must not expose team-scoped aggregates when the team layer is disabled.

4. Measure/team targeting if present

When `team_layer_enabled = false`:

- Creating/updating measures with team targeting should be rejected if the backend supports team-scoped measures.
- Company-wide measures must still work.
- Do not introduce new measure features.

5. Manager behavior

When `team_layer_enabled = false`:

- Manager workflows that depend on managed teams must be rejected server-side.
- Managers must not become company-wide admins.
- Existing company admin flows must continue to work company-wide.

## Implementation guidance

- Prefer a small reusable backend guard/service/helper over duplicating `if (! company->team_layer_enabled)` everywhere.
- Keep Laravel as source of truth.
- Keep controllers thin where practical.
- Preserve existing response/error conventions.
- Use stable error codes, for example:
  - TEAM_LAYER_DISABLED
  - FORBIDDEN
  - VALIDATION_ERROR
  depending on existing conventions.
- Do not change frontend in this task unless strictly required by OpenAPI/build fallout.
- Do not change database schema unless absolutely necessary; the setting already exists.

## OpenAPI

Update `docs/api/openapi.yaml` for changed backend behavior:

- Team management disabled behavior.
- Team-scoped dashboard/reporting restriction if request/response behavior changes.
- Survey team targeting rejection if documented.
- Measure team targeting rejection if documented.

Do not perform broad OpenAPI cleanup.

## Tests

Add or update focused backend tests:

- Company with team layer disabled cannot create teams.
- Company with team layer disabled cannot update/delete teams.
- Company with team layer disabled cannot view team members.
- Team list behavior when disabled matches the chosen contract.
- Company-level dashboard still works when team layer is disabled.
- Team-scoped dashboard/reporting is rejected or safely ignored when disabled.
- Survey with team targeting is rejected when disabled.
- Company-wide survey still works when disabled.
- Manager team-scoped workflows are rejected when disabled.
- Existing enabled-company team behavior remains intact.
- Privacy threshold tests remain green.

Prefer existing feature tests such as `CompanyTest`, `AuthTest`, survey/report tests. Do not broadly rewrite test suites.

## Out of scope

- Do not change Angular UI gating in this task.
- Do not replace `teams.manager_id` with a join table.
- Do not implement manager hierarchy.
- Do not implement historical team assignment.
- Do not redesign dashboard/reporting/survey/measures.
- Do not normalize API casing.
- Do not change privacy threshold rules except where needed to prevent disabled-team-layer leakage.
- Do not run `migrate:fresh`.
- Do not run `db:wipe`.
- Do not run `docker compose down -v`.
- Do not run destructive database commands.

## Validation

Run sequentially:

- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test --filter=AuthTest
- docker compose exec api php artisan test
- git diff --check

Angular build is not required unless Angular files or generated frontend contract files change.

## Expected output

After implementation, report:

- changed files
- backend behavior changes
- OpenAPI changes
- tests run
- chosen behavior for disabled team list endpoint
- risks/open questions

## Implementation Plan

### Constraints for patch mode

- Modify only backend/API contract/test files needed for this task.
- Do not change Angular UI, database schema, migrations, Docker config, or unrelated documentation.
- Keep Laravel as the authorization source of truth; Angular hiding remains only presentation.
- Preserve company-level reporting and privacy thresholds when team layer is disabled.
- Do not allow `COMPANY_MANAGER` users to expand into company-wide access when team layer is disabled.
- Prefer focused feature tests over broad suite rewrites.

### Discovery

1. Inspect current backend team-layer data model and relationships:
   - `Company` model and `team_layer_enabled` casting/defaults.
   - `User` team/company relationships.
   - `Team` model relationships, including manager ownership if present.
2. Locate team-management routes, controllers, policies, requests, resources, and existing tests.
3. Locate dashboard/reporting endpoints that accept team filters or return team-level metadata.
4. Locate survey create/update/result flows that support team targeting or team aggregate output.
5. Locate measure create/update flows if team targeting is currently implemented.
6. Review existing API error response conventions before choosing exact status codes/error codes.

### Contract decisions to make before patching

1. Choose disabled team-list behavior after checking existing conventions:
   - Prefer `403` if team management endpoints consistently reject unauthorized access.
   - Prefer empty list only if existing UI/API conventions treat list endpoints as compatibility-safe filtered collections.
2. Use one stable disabled-team-layer error code if compatible with existing API responses, likely `TEAM_LAYER_DISABLED`.
3. Treat any explicit team-scoped dashboard/reporting parameter as rejected unless existing endpoint conventions already ignore unauthorized filters safely and tests cover that no team metadata is returned.

### Backend implementation

1. Add a small reusable backend guard in the existing Laravel style, such as a service/helper/policy method, that determines whether team-scoped company behavior is allowed for the authenticated user's company.
2. Apply the guard to team-management write/read operations:
   - create, update, delete teams
   - view team members
   - list teams using the chosen contract
3. Apply the guard to dashboard/reporting team filters:
   - company-level reporting remains available
   - team-scoped requests are rejected or safely ignored according to the chosen contract
   - responses do not include team-level aggregates or team eligible/member/participation metadata when disabled
4. Apply the guard to survey targeting and results:
   - reject create/update requests with team targeting when disabled
   - allow company-wide surveys when disabled
   - suppress or omit team aggregate result output when disabled
5. Apply the guard to measure targeting only if current backend code already supports team-scoped measures:
   - reject team targeting when disabled
   - leave company-wide measures unchanged
6. Verify manager-dependent flows:
   - reject managed-team workflows when team layer is disabled
   - keep company admin company-wide flows intact
   - ensure managers do not receive implicit company-wide access

### Tests

1. Add focused feature tests for disabled team-layer team management:
   - create team rejected
   - update/delete team rejected
   - view team members rejected
   - list teams matches the chosen contract
2. Add or extend dashboard/reporting tests:
   - company-level dashboard/reporting still works when disabled
   - team-scoped filters are rejected or safely ignored
   - no team-level aggregates or team participation metadata leak when disabled
3. Add or extend survey tests:
   - team-targeted survey create/update rejected when disabled
   - company-wide survey create/update still works when disabled
   - survey results do not expose team-scoped aggregates when disabled
4. Add measure tests only if team-scoped measures currently exist.
5. Add manager-flow tests that prove disabled team layer does not grant company-wide manager access.
6. Keep at least one enabled-company regression test for existing team behavior.

### OpenAPI updates for patch mode

Update `docs/api/openapi.yaml` only for actual backend behavior changes:

- disabled team-management behavior, including the chosen list endpoint contract
- disabled team-scoped dashboard/reporting behavior if request or response behavior changes
- survey team-targeting rejection
- measure team-targeting rejection only if implemented in backend

Do not perform broad OpenAPI cleanup.

### Validation for patch mode

Run only after implementation, sequentially:

1. `docker compose exec api php artisan test --filter=CompanyTest`
2. `docker compose exec api php artisan test --filter=AuthTest`
3. `docker compose exec api php artisan test`
4. `git diff --check`

Do not run Angular build unless Angular files or generated frontend contract files are changed.

### Review checklist

- Laravel enforces disabled team layer for direct API calls.
- Company-level reporting still works with `team_layer_enabled = false`.
- Team-level operations do not leak team aggregates, participation metadata, member lists, or health-related individual data.
- Existing privacy thresholds remain unchanged or stricter.
- Manager scope remains team-bound and does not become company-wide when team layer is disabled.
- OpenAPI matches the implemented disabled-team-layer behavior.
- The patch is limited to this task's backend/API contract/test scope.

## Approved Review Notes

Implement the backend enforcement task with these concrete contract decisions:

1. Disabled team management behavior:
   - GET /company/teams should remain safe for frontend compatibility and return an empty list using the existing list response shape.
   - Team create/update/delete operations must be rejected with a stable error code, preferably TEAM_LAYER_DISABLED.
   - Team members endpoint must be rejected with TEAM_LAYER_DISABLED.
   - Do not expose team member data when team_layer_enabled is false.

2. Disabled team-scoped request behavior:
   - Explicit team-scoped dashboard/report/survey/measure requests must be rejected server-side when team_layer_enabled is false.
   - Prefer 403 with error.code = TEAM_LAYER_DISABLED for disabled team-layer access.
   - Do not silently ignore explicit teamId/teamIds filters, because that can mislead clients into thinking team-scoped data was returned.
   - Company-level dashboard/report/survey/measure behavior must continue to work.

3. Manager behavior:
   - COMPANY_MANAGER users must not receive company-wide fallback access when team_layer_enabled is false.
   - Manager team-scoped workflows must be rejected with TEAM_LAYER_DISABLED.

4. Scope guard:
   - Do not change Angular in this task.
   - Do not change schema/migrations.
   - Do not change privacy thresholds except to prevent disabled-team-layer team-scope leakage.
   - Do not broaden role permissions.

## Review Follow-up Required

Apply a narrow follow-up patch based on review findings.

Required changes:
1. Fix disabled team list behavior.
   - GET /api/company/teams must not return 403 when team_layer_enabled is false.
   - It must return 200 with the existing list response shape, but an empty list.
   - Do not expose real teams when team_layer_enabled is false.
   - Keep team create/update/delete/member endpoints rejected with TEAM_LAYER_DISABLED.
   - Update tests accordingly: disabled list returns 200 and empty list.

2. Fix OpenAPI for the actual disabled-team-layer contract.
   - Document TEAM_LAYER_DISABLED reusable error response/schema if not already present.
   - Document 403 TEAM_LAYER_DISABLED for team write/detail/member endpoints where backend returns it.
   - Do not document TEAM_LAYER_DISABLED for GET /company/teams list if it returns 200 empty list.
   - Document disabled team-layer behavior for surveys, measures, and reports where backend now returns TEAM_LAYER_DISABLED.
   - Document 422 cases only where backend actually returns 422 for invalid team targeting.
   - Do not perform broad OpenAPI cleanup.

3. Clean task file whitespace.
   - Remove trailing whitespace in this task file.
   - Do not add unrelated task chatter.

4. Cleanup local noise.
   - Remove .DS_Store if present.
   - Do not change .gitignore in this patch.

5. Migration scope check.
   - Do not add or modify migrations in this backend-enforcement task.
   - If the team_layer_enabled migration is already part of a previous committed slice in this same branch, leave it alone.
   - If it is newly staged/uncommitted as part of this task, report that as a scope issue instead of modifying it.

Out of scope:
- Do not change Angular.
- Do not change privacy thresholds.
- Do not change company team-layer setting schema.
- Do not broaden role permissions.
- Do not run destructive database commands.

Validation:
- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test --filter=TenantScopeTest
- docker compose exec api php artisan test
- git diff --check

## Final Review Follow-up Required

Apply a narrow follow-up patch based on review findings.

Required changes:

1. Fix trailing whitespace.
   - Remove trailing whitespace in this task file.
   - Ensure git diff --check and git diff --cached --check pass.

2. Add disabled team-management endpoint tests.
   - GET /api/company/teams when team_layer_enabled=false must return 200 with the existing list response shape and an empty list.
   - POST /api/company/teams must return 403 with error.code TEAM_LAYER_DISABLED.
   - PATCH/PUT /api/company/teams/{teamId} must return 403 with error.code TEAM_LAYER_DISABLED, depending on the actual route method.
   - DELETE /api/company/teams/{teamId} must return 403 with error.code TEAM_LAYER_DISABLED.
   - GET /api/company/teams/{teamId} must return 403 with error.code TEAM_LAYER_DISABLED if a show route exists.
   - GET /api/company/teams/{teamId}/members must return 403 with error.code TEAM_LAYER_DISABLED.
   - Do not expose team data or member data when team_layer_enabled=false.

3. Align OpenAPI error schemas.
   - Ensure TEAM_LAYER_DISABLED is documented with the actual runtime shape:
     { error: { code, message } }
   - Use a reusable TeamLayerDisabled error schema/response where practical.
   - Update survey results 403 docs so they do not only document the anonymity-threshold shape when TEAM_LAYER_DISABLED is also possible.
   - Update team members docs to use the reusable TEAM_LAYER_DISABLED response instead of text-only descriptions.
   - Update teams/surveys/measures/reports docs only where backend behavior actually returns TEAM_LAYER_DISABLED.
   - Do not perform broad OpenAPI cleanup.

4. Employee survey disabled-team-layer behavior.
   - Inspect employee survey listing/show/answer endpoints.
   - If a survey is team-targeted and the company has team_layer_enabled=false, it must not remain available as an active team-scoped employee workflow.
   - Company-wide surveys must remain visible and answerable.
   - Prefer filtering team-targeted surveys out of employee list responses when disabled.
   - Direct show/answer access to a team-targeted survey when disabled should be rejected safely or treated as not found, using existing conventions.
   - Add focused tests for this behavior.

5. Local noise cleanup.
   - Remove .DS_Store and docs/.DS_Store if present.
   - Do not change .gitignore in this patch.

6. Migration scope.
   - Do not add or modify migrations in this backend-enforcement task.
   - If the team_layer_enabled migration is still staged/uncommitted from the previous slice, report it as a staging/scope issue instead of modifying it.

Out of scope:
- Do not change Angular.
- Do not change schema/migrations.
- Do not change privacy thresholds.
- Do not broaden role permissions.
- Do not redesign survey behavior beyond disabled team-scoped availability.

Validation:
- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test --filter=TenantScopeTest
- docker compose exec api php artisan test
- git diff --check
- git diff --cached --check
