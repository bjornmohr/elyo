# Task: Gate Angular team-layer UI controls

## Goal

Update the Angular company UI so team-related controls are hidden, disabled, or adjusted when `teamLayerEnabled` is false.

Backend enforcement already exists. This task is UI/UX alignment only: Angular should not expose controls that the backend now rejects when the company team layer is disabled.

## Architectural decisions

- Laravel remains the source of truth and authorization boundary.
- Angular gating is presentation and UX only.
- Every user belongs to exactly one company.
- `users.team_id` remains nullable.
- Team layer disabled means team-scoped company workflows should not be shown as normal available actions.
- Company-level dashboard, reporting, surveys, and measures remain available.
- No individual health data, survey text, documents, or identifiable responses may be exposed.
- Do not broaden manager permissions.

## Current context

Previous tasks established:

- `companies.team_layer_enabled` exists.
- Auth/company payloads expose `teamLayerEnabled`.
- Invitation UI and backend respect the setting.
- Backend now enforces disabled team layer for direct API calls:
  - team management write/details/members blocked
  - team list returns empty list
  - team-scoped dashboard/report/survey/measure behavior is blocked
  - company-level flows continue to work

## Scope

Frontend-only UI gating for existing Angular company/admin screens.

Do not change Laravel behavior unless a frontend build/type mismatch reveals a clear contract issue.

## Required Angular changes

1. Shared/current company state
   - Use the existing `teamLayerEnabled` value from auth/current user/company state.
   - Do not create a separate frontend-only business rule source.
   - Keep naming consistent with existing TypeScript models.

2. Company navigation
   - Ensure Teams navigation is hidden when `teamLayerEnabled` is false.
   - If already done in Task A, verify and do not duplicate logic.

3. Dashboard UI
   - Hide or disable team-level dashboard filters/controls when `teamLayerEnabled` is false.
   - Company-level dashboard remains visible.
   - Do not alter privacy threshold rendering.

4. Reports UI
   - Hide or disable team filters or team-scoped report controls when disabled.
   - Company-level reports remain visible.
   - Do not reintroduce respondents/count metadata removed by privacy hardening.

5. Survey UI
   - Hide or disable team targeting controls when disabled.
   - Company-wide surveys remain creatable/editable.
   - Do not allow the UI to submit `teamIds` or team scope when disabled.
   - Existing survey result display should remain company-level compatible.

6. Measures UI
   - Hide or disable team targeting controls when disabled if such controls exist.
   - Company-wide measures remain creatable/editable.
   - Do not introduce new measure features.

7. Manager UX
   - If `teamLayerEnabled` is false, manager team-scoped workflows should show a clear unavailable message or be hidden.
   - Do not make managers company-wide admins.
   - Do not send team-scoped requests when disabled.

## OpenAPI / types

- Do not update OpenAPI unless the Angular work reveals a real API contract mismatch.
- Do not regenerate clients unless this project already uses generated clients and it is required by existing workflow.
- Keep any manual TypeScript model changes minimal.

## Tests / validation

Prefer build/type safety and focused Angular tests only if existing test setup already covers the touched components.

Run:

- docker compose exec web npm run build
- git diff --check

If backend files are changed unexpectedly, also run:

- docker compose exec api php artisan test

## Out of scope

- Do not change backend enforcement.
- Do not change database schema/migrations.
- Do not change privacy threshold logic.
- Do not change OpenAPI unless required by a discovered mismatch.
- Do not redesign dashboard/reporting/survey/measure UX broadly.
- Do not implement team manager hierarchy.
- Do not replace `teams.manager_id`.
- Do not normalize API casing.
- Do not run `migrate:fresh`.
- Do not run `db:wipe`.
- Do not run `docker compose down -v`.
- Do not run destructive database commands.

## Expected output

After implementation, report:

- changed Angular files
- UI behavior changes
- model/type changes
- validation commands run
- any backend/API contract issues discovered
- risks/open questions

## Implementation Plan

1. Keep the source of truth in existing auth state.
   - Use `AuthStore.teamLayerEnabled()` wherever UI decisions need the team-layer flag.
   - Do not add a second company settings service or duplicate frontend business rules.
   - Leave existing auth models alone unless TypeScript reveals a real mismatch.

2. Verify existing navigation and invitation gating.
   - Confirm `apps/web-angular/src/app/shared/shells/company-shell.component.ts` continues to hide the Teams nav item when `teamLayerEnabled()` is false.
   - Confirm `apps/web-angular/src/app/features/company/pages/invitations/company-invitations.component.ts` continues to avoid loading teams, hide team selection, hide manager invitation role choices, and omit `teamId` from payloads when the team layer is disabled.
   - Do not duplicate this logic in additional helpers unless needed for readability.

3. Add direct Teams page protection.
   - Update `apps/web-angular/src/app/features/company/pages/teams/company-teams.component.ts` to inject `AuthStore`.
   - When `teamLayerEnabled()` is false, render a clear unavailable/disabled message instead of the team creation form and team list.
   - Do not call `/company/teams` or `/company/users` from that component when the team layer is disabled.
   - Keep backend enforcement as the authorization boundary; this is only UX alignment for direct URL access.

4. Gate company dashboard team display.
   - Update `apps/web-angular/src/app/features/company/pages/dashboard/dashboard.component.ts` to inject `AuthStore`.
   - Hide the "Aktive Teams" summary card when `teamLayerEnabled()` is false.
   - Keep the company-level score, participation, trend, privacy threshold labels, and existing dashboard request unchanged.
   - Do not add or change respondent/count metadata beyond the existing rendering.

5. Gate survey team targeting.
   - Update `apps/web-angular/src/app/features/company/pages/surveys/company-surveys.component.ts` to inject `AuthStore`.
   - Load `/company/teams` only when `teamLayerEnabled()` is true.
   - Hide the "Teams (Zielgruppe)" multi-select and its manager/team-scope helper copy when disabled.
   - Ensure `resetForm()` and `patchForm()` keep `teamIds` empty when disabled.
   - Ensure the submit payload never includes team-scoped IDs when disabled, while preserving company-wide survey creation/editing.
   - Keep survey results privacy rendering unchanged and do not show raw text answers.

6. Gate measure team targeting.
   - Update `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts` to inject `AuthStore`.
   - Load `/company/teams` only when `teamLayerEnabled()` is true.
   - Hide the team selector and team column when disabled, or relabel company-wide scope if the existing table needs a visible scope indicator.
   - Ensure measure create payloads submit `teamId: null` or omit team scope when disabled.
   - Keep company-wide measure creation/editing available and do not introduce new measure features.

7. Review reports for current team controls.
   - Inspect `apps/web-angular/src/app/features/company/pages/reports/company-reports.component.ts`.
   - If it has no team filters or team-scoped controls, leave it unchanged and mention that in the handoff.
   - If a team filter is discovered during implementation, gate it with `AuthStore.teamLayerEnabled()` and avoid team-scoped requests when disabled.

8. Manager UX and portal boundaries.
   - Preserve the existing role guard behavior; do not make `COMPANY_MANAGER` company-wide.
   - For disabled team layer, manager-only team-scoped pages/forms should be hidden or show an unavailable message instead of sending blocked team requests.
   - Do not broaden manager access to company-level admin workflows beyond what routing and backend already allow.

9. Validation for the later implementation pass.
   - Run `docker compose exec web npm run build`.
   - Run `git diff --check`.
   - Do not run backend tests unless backend files are unexpectedly changed.
   - Do not run migrations, `migrate:fresh`, `db:wipe`, `docker compose down -v`, or other destructive database/Docker commands.

10. Expected implementation report.
   - Files changed: list touched Angular files only.
   - Behavior changed: describe hidden/disabled team-layer UI and stopped team-scoped requests.
   - Commands run: include build and diff check results.
   - Test/build result: report pass/fail and any blocking error.
   - Open questions: explicitly note any uncertain backend/API contract assumptions.
   - Intentional deviations: call out if OpenAPI, backend, tests, or additional docs were left untouched as planned.

## Final Scope Clarification

- When teamLayerEnabled is false, prefer omitting team-scoped fields from Angular payloads instead of sending null, unless the existing API contract explicitly expects null.
- Do not add fallback company-wide manager permissions in the UI.
- Do not create duplicate frontend state for teamLayerEnabled; use AuthStore.teamLayerEnabled().

## Review Follow-up Required

Apply a narrow follow-up patch based on review findings.

Required Angular changes:
1. Manager-only disabled team-layer boundary.
   - In company surveys UI, if the current user is manager-only and teamLayerEnabled() is false:
     - do not show create/edit survey forms as company-wide actions
     - do not allow submit
     - show a clear unavailable message
   - In company measures UI, if the current user is manager-only and teamLayerEnabled() is false:
     - do not show create/edit measure forms as company-wide actions
     - do not allow submit
     - show a clear unavailable message
   - Reuse the existing isManagerOnly() pattern from the invitations UI if available.
   - Do not make manager-only users company-wide admins in the UI.

2. Admin/company-admin behavior.
   - Admin users may still create company-wide surveys/measures when teamLayerEnabled() is false.
   - Team fields remain hidden and teamId/teamIds must not be sent when disabled.

3. Navigation.
   - Do not broadly change survey/measure navigation in this patch.
   - Page-level unavailable state is sufficient for direct URL access and manager-only UX.

OpenAPI:
4. If docs/api/openapi.yaml is already part of this task/branch and the survey disabled-team-layer 403 contract is still ambiguous, align it narrowly:
   - Survey create/update or relevant endpoints may return 403 TEAM_LAYER_DISABLED for manager-only workflows when teamLayerEnabled=false.
   - Do not perform broad OpenAPI cleanup.
   - Do not change backend behavior.

Out of scope:
- Do not change Laravel behavior.
- Do not change database/schema/migrations.
- Do not change privacy thresholds.
- Do not broaden manager permissions.
- Do not redesign survey/measure pages.
- Do not normalize API casing.

Validation:
- docker compose exec web npm run build
- git diff --check
- If OpenAPI only is changed, no backend tests are required.
- If any backend file is changed unexpectedly, run docker compose exec api php artisan test.

## Final Review Follow-up Required

Apply a narrow Angular-only fix.

Required changes:
1. In company-surveys.component.ts:
   - If managerDisabledByTeamLayer() is true on init, do not call loadSurveys().
   - Do not send /company/surveys requests in that state.
   - Keep the unavailable banner/state visible.
   - Admin/company-admin behavior must remain unchanged.

2. In company-measures.component.ts:
   - If managerDisabledByTeamLayer() is true on init, do not call loadMeasures().
   - Do not send /company/measures requests in that state.
   - Keep the unavailable banner/state visible.
   - Admin/company-admin behavior must remain unchanged.

3. Do not change backend, OpenAPI, migrations, or privacy logic.

Validation:
- docker compose exec web npm run build
- git diff --check

## Final Review Follow-up Required

Apply a narrow final patch.

Required Angular changes:
1. Suppress misleading empty states for manager-only disabled team-layer workflows.
   - In company-surveys.component.ts template, when managerDisabledByTeamLayer() is true:
     - show only the unavailable/disabled message
     - do not show the normal empty state like "Noch keine Umfragen vorhanden"
     - do not show create/edit/list actions
   - In company-measures.component.ts template, when managerDisabledByTeamLayer() is true:
     - show only the unavailable/disabled message
     - do not show the normal empty state like "Noch keine Maßnahmen vorhanden"
     - do not show create/edit/list actions

2. Preserve admin behavior.
   - Admin/company-admin users with teamLayerEnabled=false must still see normal company-wide surveys/measures UI.
   - Do not hide empty states for admins.

OpenAPI:
3. If docs/api/openapi.yaml is part of the current branch, align the survey disabled-team-layer contract narrowly:
   - GET /company/surveys may return 403 TEAM_LAYER_DISABLED for manager-only users when teamLayerEnabled=false.
   - POST /company/surveys may return 403 TEAM_LAYER_DISABLED for manager-only users when teamLayerEnabled=false.
   - Use the existing reusable TeamLayerDisabled response/schema if present.
   - Do not perform broad OpenAPI cleanup.

Out of scope:
- Do not change Laravel behavior.
- Do not change database/schema/migrations.
- Do not change privacy thresholds.
- Do not broaden manager permissions.
- Do not add new UI features.
- Do not normalize API casing.

Validation:
- docker compose exec web npm run build
- git diff --check
- If OpenAPI is changed, inspect only the narrow survey 403 diff.
- If backend files are changed unexpectedly, run docker compose exec api php artisan test.

## Final Contract/Cleanup Follow-up

Required changes:
1. OpenAPI:
   - Add 403 TEAM_LAYER_DISABLED to GET /company/surveys.
   - Use the existing reusable TeamLayerDisabled response.
   - Keep the change narrow.

Validation:
- docker compose exec web npm run build
- git diff --check
