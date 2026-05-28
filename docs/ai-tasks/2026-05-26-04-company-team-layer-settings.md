# Task: Add optional company team layer setting

## Goal

Make the team layer optional per company.

Small companies may not have meaningful teams, and many teams may be below the privacy threshold. ELYO must support companies where employees have no team and all health/reporting functionality works at company level.

## Architectural decisions

- Every user belongs to exactly one company.
- `users.team_id` remains nullable.
- `users.team_id` represents the user's own organizational team affiliation.
- A company can decide whether the team layer is enabled.
- Team management scope is separate from `users.team_id`.
- `COMPANY_MANAGER` is a team-scoped role and is only meaningful when team layer is enabled.
- Company admins/owners manage company-wide.
- Company managers manage only their assigned/managed teams.
- Employees without a team are valid.
- Health check-ins and surveys must work for employees, managers, and admins as company users where appropriate.
- Team-level reporting remains subject to privacy thresholds.
- Company-level reporting must work even when the team layer is disabled.

## Current context

Recent tasks established:

- `users.company_id` is mandatory.
- Platform/admin users receive an internal ELYO company.
- Team assignment through invitations exists.
- `teamId` in invitations represents the invited user's own team affiliation.
- Manager invite authority is team-scoped.
- Invite domain logic has been moved into services.
- Privacy threshold hardening was implemented for dashboard/survey/report aggregates.

## Scope

Add a company-level team layer setting and wire it through backend and Angular UI with minimal behavior changes.

Suggested field:

- `companies.team_layer_enabled` boolean default false

Exact naming may differ if existing conventions suggest a better name.

## Required backend changes

1. Database/model
   - Add a company-level boolean setting for whether the team layer is enabled.
   - Default should be false for small companies.
   - Update Company model fillable/casts/resources as needed.
   - Update factories/seeders to set explicit values where useful.

2. Company/admin responses
   - Expose the setting in relevant company detail/profile/settings responses used by Angular.
   - Ensure the frontend can know whether team features should be shown.

3. Team behavior
   - Employees may always have `team_id = null`.
   - Company admins may invite employees with or without teamId.
   - If team layer is disabled, team selection should not be required for admin-created employee invites.
   - If team layer is disabled, team management endpoints should either be hidden by frontend or rejected/limited by backend where appropriate.
   - Do not break existing team endpoints for companies that have team layer enabled.

4. Manager behavior
   - `COMPANY_MANAGER` remains team-scoped.
   - Managers may only invite employees into managed teams.
   - If a manager has no managed teams, they cannot create employee invites.
   - If team layer is disabled, manager workflows should not be exposed or should be rejected safely.
   - Do not make managers company-wide admins.

5. Health/reporting behavior
   - Company-level dashboard/reporting must work regardless of team layer.
   - Team-level dashboard/reporting should only be available when team layer is enabled and privacy thresholds are met.
   - Do not weaken privacy thresholds.
   - Do not expose individual health, survey, document, or free-text data.

## Required frontend changes

1. Company UI
   - Show/hide or disable team management UI based on team layer setting.
   - Keep the UI minimal.
   - No broad redesign.

2. Invitation UI
   - Admin/owner:
     - Show optional team affiliation select only when team layer is enabled and role supports team affiliation.
     - If team layer is disabled, do not send teamId.
   - Manager:
     - Role remains restricted to EMPLOYEE.
     - If exactly one managed team exists, auto-use that teamId.
     - If multiple managed teams exist, show a select limited to managed teams.
     - If no managed teams exist or team layer is disabled, prevent invitation submission and show a clear message.

3. Dashboard/reporting UI
   - Do not show team-level reporting controls when team layer is disabled.
   - Company-level reporting remains visible.

## OpenAPI

Update `docs/api/openapi.yaml` for any changed request/response contracts:

- company/team-layer setting field
- company settings/profile response
- team/invitation behavior if response/request shape changes

Do not perform broad OpenAPI cleanup.

## Tests

Add or update focused tests:

- company defaults to team layer disabled
- company with team layer disabled can operate with employees without teams
- company admin can invite employee without team when team layer disabled
- teamId is not sent/required when team layer disabled
- company with team layer enabled can invite employee with team
- manager invite behavior remains restricted to managed teams
- team-level reporting/controls are not exposed or are rejected when team layer disabled
- company-level dashboard/reporting still works when team layer disabled
- privacy thresholds remain enforced

Use existing feature tests where possible. Do not broadly rewrite test suites.

## Out of scope

- Do not replace `teams.manager_id` with a join table.
- Do not implement manager hierarchy.
- Do not implement historical team assignment.
- Do not redesign dashboard/reporting.
- Do not change privacy threshold rules except where necessary to respect disabled team layer.
- Do not normalize API casing.
- Do not implement new HR integrations.
- Do not run `migrate:fresh`.
- Do not run `db:wipe`.
- Do not run `docker compose down -v`.
- Do not run destructive database commands.

## Validation

Run sequentially:

- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test --filter=AuthTest
- docker compose exec api php artisan test
- docker compose exec web npm run build
- git diff --check
## Expected output

After implementation, report:

- changed files
- migration/model changes
- backend behavior changes
- frontend behavior changes
- OpenAPI changes
- tests/build commands run
- risks/open questions

## Implementation Plan

1. Keep this patch to the approved first slice
   - Implement only the company-level team-layer setting and the core invitation/navigation wiring.
   - Do not add broad team-layer gating for reports, surveys, measures, dashboard controls, or general team endpoints in this patch.
   - Keep `users.team_id` nullable and only as the user's own team affiliation.
   - Keep `COMPANY_MANAGER` team-scoped and never convert managers into company-wide admins.
   - Preserve existing privacy thresholds and avoid touching health-data aggregation behavior except where the approved invitation/navigation slice directly requires it.

2. Add the company setting in Laravel
   - Add a migration under `apps/api-laravel/database/migrations/` for `companies.team_layer_enabled` as a boolean defaulting to `false`.
   - Update `apps/api-laravel/app/Models/Company.php` fillable/casts for the boolean.
   - Update `apps/api-laravel/database/factories/CompanyFactory.php` so generated companies default explicitly to disabled.
   - Adjust seed/demo data only where existing demo team or manager invitation flows need the setting enabled to preserve current behavior.

3. Expose `teamLayerEnabled` through current API contracts
   - Add `teamLayerEnabled` to the auth/current-user company payloads used by Angular navigation and invitation screens.
   - Add the field to the relevant admin/company response and request handling only where the current admin company screens already create, update, or display company settings.
   - Use existing Laravel Resources, controller response conventions, and camelCase frontend-facing JSON patterns.
   - Avoid adding a new settings endpoint unless existing code has no suitable company/admin response to carry the value.

4. Update invitation backend behavior
   - For company admins/owners, allow employee invitations without `teamId` when the company has `team_layer_enabled = false`.
   - Reject non-null `teamId` for company invitations when `team_layer_enabled = false`, using the existing validation/domain error format.
   - Reject manager invitation workflows when `team_layer_enabled = false`, because managers only have team-scoped authority.
   - Preserve existing enabled-company behavior: admins/owners can invite with supported team assignment, and managers remain limited to `EMPLOYEE` invitations into managed teams.
   - Do not touch unrelated report, survey, measure, dashboard, or broad team-management behavior in this slice.

5. Update minimal Angular state and invitation/navigation UI
   - Extend the relevant auth/company TypeScript models with `teamLayerEnabled`.
   - Read the value from the existing auth/current-company state rather than introducing a new client-side business-rule source.
   - Hide the Teams navigation entry when the current company has team layer disabled.
   - In the invitation UI, hide the team select when disabled and omit `teamId` from submitted payloads.
   - Keep manager invitation submission blocked when team layer is disabled or when no managed team is available, using a clear existing-style message.
   - Avoid broad redesign and do not gate dashboard/report/survey/measure controls in this patch.

6. Update OpenAPI narrowly
   - Add `teamLayerEnabled` to only the auth/company/admin schemas and examples whose request or response shape changes.
   - Document the invitation behavior for disabled team layer: `teamId` must be omitted/null and manager invitation workflows are rejected.
   - Do not perform broad OpenAPI cleanup or document follow-up team-scoped reporting restrictions before they are implemented.

7. Add focused tests
   - Cover that companies default to team layer disabled.
   - Cover auth/company responses exposing `teamLayerEnabled`.
   - Cover admin employee invitation without `teamId` when disabled.
   - Cover rejection of invitation payloads with `teamId` when disabled.
   - Cover enabled companies still supporting teamId invitations.
   - Cover manager invitation flow rejection when team layer is disabled.
   - Prefer extending existing feature tests such as `CompanyTest` and `AuthTest`; do not broadly rewrite test suites.

8. Patch-phase validation plan
   - Do not run tests or builds during this plan-only step.
   - During the implementation phase, run the task validation commands in order unless the next task instructions narrow them further:
     - `docker compose exec api php artisan test --filter=CompanyTest`
     - `docker compose exec api php artisan test --filter=AuthTest`
     - `docker compose exec api php artisan test`
     - `docker compose exec web npm run build`
     - `git diff --check`

9. Review checklist for the implementation phase
   - Confirm only Laravel owns invitation/team-layer business rules and Angular only reflects them in UI state.
   - Confirm company/team/user scoping is preserved and managers remain team-scoped.
   - Confirm no individual health data, raw survey text, documents, or identifiable employee responses are exposed.
   - Confirm OpenAPI changes match only the implemented request/response/error behavior.
   - Confirm no changes are made to `../ELYO`, no destructive database commands are run, and no unrelated cleanup is included.

## Approved Review Notes

Implement this task in a narrower first slice.

### Approved scope for this patch

This patch should introduce the company team-layer setting and wire it through the core invitation/navigation flow only.

Allowed backend changes:
- Add `companies.team_layer_enabled` boolean with default false.
- Update Company model casts/fillable/resources as needed.
- Update CompanyFactory and seeders where needed.
- Expose `teamLayerEnabled` in auth/company/admin responses needed by Angular.
- Allow admin/owner employee invites without teamId when team layer is disabled.
- Reject non-null teamId on company invitations when team layer is disabled.
- Reject manager invitation workflows when team layer is disabled, because managers are team-scoped.
- Keep existing behavior for enabled companies.

Allowed frontend changes:
- Extend auth/company models with `teamLayerEnabled`.
- Hide Teams navigation when team layer is disabled.
- In invitation UI:
  - hide team select when team layer is disabled
  - do not send teamId when disabled
  - keep manager invite behavior blocked when disabled or no managed team exists
- Keep UI changes minimal.

Allowed OpenAPI changes:
- Document `teamLayerEnabled` where responses/requests change.
- Document invitation behavior if request/response/error contract changes.

Allowed tests:
- company defaults to team layer disabled
- auth/company response includes teamLayerEnabled
- admin can invite employee without teamId when disabled
- invite with teamId is rejected when disabled
- enabled company still supports teamId invitations
- manager invite flow is rejected when team layer is disabled

### Out of scope for this patch

Do not implement broad backend gating for all team-scoped features yet.

Specifically do not touch, unless strictly required by the approved scope:
- ReportController team filters
- survey team targeting
- measure team targeting
- dashboard team-level reporting logic
- broad TeamController behavior
- full team-scoped OpenAPI cleanup
- company team-layer settings UI beyond minimal current-company/admin response usage
- manager hierarchy
- replacing teams.manager_id
- privacy threshold rules

These should be handled in follow-up tasks:
1. Backend team-layer enforcement for team-scoped reports/surveys/measures/dashboard/team endpoints.
2. Angular UI gating for dashboard/report/survey/measure team controls.

## Final Scope Clarification

- Do not introduce COMPANY_OWNER as a new invitable role in this task.
- Keep the invitation role set exactly as currently supported unless the existing code already supports a role.
- References to admins/owners mean: company-wide roles already supported by the current invite flow.

## Review Follow-up Required

Apply a narrow follow-up patch based on review findings.

Required changes:
1. OpenAPI:
   - Update docs/api/openapi.yaml for the admin company create/update/show/list contract.
   - Document the team layer setting field for admin company payloads.
   - Match actual API naming. If frontend-facing JSON uses teamLayerEnabled, document that. If backend accepts team_layer_enabled and/or teamLayerEnabled, document actual behavior.
   - Do not perform broad OpenAPI cleanup.

2. Admin Angular UI:
   - Add minimal support for configuring the team layer when creating/editing companies, if those screens already exist.
   - Add a checkbox or equivalent simple control: "Teamlayer aktivieren".
   - Send the correct payload field expected by the backend.
   - Keep UI change minimal. No redesign.
   - Do not touch company/team/report/survey dashboard UI beyond this admin setting.

3. Tests:
   - Add or update focused backend tests for admin company create/update persisting team_layer_enabled.
   - Keep test scope narrow.

Out of scope:
- Do not enforce disabled team layer in TeamController yet.
- Do not change /company/teams behavior in this patch.
- Do not gate report/survey/measure/dashboard team-scoped endpoints in this patch.
- Do not change privacy threshold logic.
- Do not broaden role permissions.
- Do not run destructive database commands.

Validation:
- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test --filter=AuthTest
- docker compose exec api php artisan test
- docker compose exec web npm run build
- git diff --check

## Review Follow-up Required: Admin Company OpenAPI Contract

Apply a narrow follow-up patch based on review findings.

Required changes:
1. Fix docs/api/openapi.yaml for the admin company payload contract.
   - Do not use one shared payload schema if create and update validation differ.
   - Split schemas if needed:
     - AdminCompanyCreatePayload
     - AdminCompanyUpdatePayload
     - AdminCompanyResponse
   - Create schema must match actual AdminCompanyController create validation.
   - Update schema must match actual AdminCompanyController update validation.
   - Do not document status on create if backend does not accept it.
   - Do not allow slug: null on update if backend rejects it.
   - Document team_layer_enabled / teamLayerEnabled exactly as the backend request and response actually use it.
   - Do not perform broad OpenAPI cleanup.

2. Add or update focused tests if cheap and local:
   - admin company index/show includes the team layer setting
   - admin company create/update persists the team layer setting if not already covered

3. Keep the currently deferred backend enforcement explicitly out of scope:
   - Do not change TeamController behavior.
   - Do not change survey team targeting.
   - Do not change report, measure, or dashboard team-scoped behavior.
   - Do not change privacy thresholds.
   - Do not change Angular unless OpenAPI/client typing or build requires it.

4. Task file hygiene:
   - If the task file contains noisy generated/review text, trim it to durable task notes only.
   - Do not include unrelated review chatter in the final implementation commit.

Validation:
- docker compose exec api php artisan test --filter=CompanyTest
- docker compose exec api php artisan test --filter=AuthTest
- docker compose exec api php artisan test
- docker compose exec web npm run build
- git diff --check
