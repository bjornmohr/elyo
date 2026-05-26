Read AGENTS.md and all files under docs/ai-context/ first. Then read docs/ai-tasks/2026-05-26-03-refactor-invitation-domain-services.md.

Implement this refactor plan:
1. Keep scope backend-only
    - No Angular changes.
    - No OpenAPI changes unless implementation reveals an existing contract mismatch.
    - No migration, destructive DB command, migrate:fresh, db:wipe, or volume reset.
2. Create small invitation service namespace
    - Add apps/api-laravel/app/Services/Invitations/InviteTeamValidator.php
        - Own same-company team validation.
        - Own manager managed-team checks.
        - Own “manager-only” helper behavior currently duplicated in CompanyInvitationController.
    - Add apps/api-laravel/app/Services/Invitations/CompanyInvitationService.php
        - Accept authenticated user plus validated invite payload.
        - Preserve role rules, manager restrictions, company conflict behavior, token generation, invite creation, and response data source.
    - Add apps/api-laravel/app/Services/Invitations/InviteAcceptanceService.php
        - Accept token/name/password input.
        - Preserve invite lookup, foreign-team rejection, existing-user company/team conflict checks, role assignment, optional team assignment, accepted status update, and transaction boundaries.
3. Thin CompanyInvitationController
    - Leave request validation in the controller for now to preserve Laravel’s current validation status codes and error shape.
    - Replace inline creation business rules in storeInvitation() with a service call.
    - Keep response shape exactly:
        - data.id
        - data.email
        - data.role
        - data.teamId
        - data.status
        - data.expires_at
        - data.invite_token
    - Keep list and revoke behavior unchanged unless a tiny helper extraction is needed.
4. Thin InviteController
    - Keep request validation in accept() for unchanged validation behavior.
    - Move token lookup and acceptance logic into InviteAcceptanceService.
    - Controller should only call the service, create the Sanctum token from the returned user, and return the current response shape.
    - Leave verify() unchanged unless the same token lookup helper can be reused without changing behavior.
5. Error handling approach
    - Use a small domain exception or result object only if it keeps controllers clean without changing response codes/messages.
    - Preserve current error payloads exactly:
        - INVALID_INVITE 422
        - INVALID_INVITE_TEAM 422
        - COMPANY_CONFLICT 422
        - TEAM_CONFLICT 422
        - manager FORBIDDEN 403 messages
6. Tests
    - Do not broadly rewrite feature tests.
    - Rely primarily on existing AuthTest and CompanyTest.
    - Add service-level tests only if the extracted service has meaningful branching that is awkward to validate through existing feature tests. Initial bias: no new unit tests unless a gap appears during patching.
7. Validation sequence
    - docker compose exec api php artisan test --filter=AuthTest
    - docker compose exec api php artisan test --filter=CompanyTest
    - docker compose exec api php artisan test
    - git diff --check
8. Review checklist before handoff
    - Controllers no longer contain invite creation/acceptance domain rules.
    - Behavior and response shapes are unchanged.
    - Manager scope and same-company team checks are preserved.
    - Invite acceptance still ignores request-supplied company/team/role.
    - No individual health data paths are touched.
    - No OpenAPI or Angular changes unless explicitly justified.

Files Expected To Change

- apps/api-laravel/app/Http/Controllers/Company/CompanyInvitationController.php
- apps/api-laravel/app/Http/Controllers/Auth/InviteController.php
- New files under apps/api-laravel/app/Services/Invitations/

Commands Run For Planning

- Read AGENTS.md
- Read docs/ai-tasks/2026-05-26-03-refactor-invitation-domain-services.md
- Read docs/ai-context/codex-workflow.md
- Read relevant controllers, models, enum, and invite/team feature test sections

Open Questions

None blocking. The main implementation choice is whether to use service return objects or domain exceptions for preserved error responses; either is acceptable if response payloads remain unchanged.

Scope:

- Backend-only.

- No Angular changes.

- No OpenAPI changes unless a true existing contract mismatch is discovered.

- No migrations.

- No behavior changes.

- No destructive database commands.

Required structure:

1. Create a small invitation service namespace under:

   apps/api-laravel/app/Services/Invitations/

2. Add an InviteTeamValidator or equivalent small service:

    - same-company team validation

    - manager managed-team checks

    - manager-only helper behavior currently in CompanyInvitationController

    - no HTTP responses from the service

3. Add a CompanyInvitationService or equivalent:

    - handles invitation creation domain logic

    - preserves existing role rules

    - preserves manager restrictions

    - preserves company conflict behavior

    - preserves token generation

    - preserves invite creation fields

    - returns data needed by the controller without changing response shape

    - no HTTP responses from the service

4. Add an InviteAcceptanceService or equivalent:

    - handles invite acceptance domain logic

    - preserves invite lookup behavior

    - preserves invalid invite behavior

    - preserves foreign-team rejection

    - preserves existing-user company/team conflict behavior

    - preserves role assignment

    - preserves optional team assignment

    - preserves accepted status update

    - owns transaction boundaries for acceptance mutations

    - ignores request-supplied company/team/role

    - no HTTP responses from the service

5. Thin controllers:

    - Keep request validation in controllers.

    - CompanyInvitationController::storeInvitation should delegate business rules to the service.

    - InviteController::accept should validate request, call service, create Sanctum token from returned user, and return the existing response shape.

    - Keep invitations(), revoke(), verify(), and TeamController unchanged unless a tiny extraction is unavoidable.

Error handling:

- Prefer small domain exceptions or result objects.

- Preserve current HTTP status codes and payloads exactly:

    - INVALID_INVITE 422

    - INVALID_INVITE_TEAM 422

    - COMPANY_CONFLICT 422

    - TEAM_CONFLICT 422

    - manager FORBIDDEN 403 messages

- Controllers may map domain exceptions/results to the current response payloads.

- Do not introduce a broad exception framework.

Tests:

- Do not broadly rewrite feature tests.

- Existing AuthTest and CompanyTest must continue to pass.

- Add service-level tests only if a clear gap appears.

- Do not change behavior to simplify tests.

Out of scope:

- Do not add new invitation features.

- Do not change Angular.

- Do not change OpenAPI behavior.

- Do not implement company team_layer_enabled.

- Do not replace teams.manager_id with a join table.

- Do not normalize snake_case/camelCase.

- Do not change privacy thresholds.

- Do not alter role permissions.

- Do not run migrate:fresh.

- Do not run db:wipe.

- Do not run docker compose down -v.

- Do not run destructive database commands.

Validation:

Run sequentially:

- php artisan test --filter=AuthTest

- php artisan test --filter=CompanyTest

- php artisan test

- git diff --check

Angular build is not required unless Angular files change.

After implementation, output:

- changed files

- service classes created/updated

- controller logic removed/simplified

- tests run

- confirmation that response shapes and behavior are unchanged

- risks/open questions