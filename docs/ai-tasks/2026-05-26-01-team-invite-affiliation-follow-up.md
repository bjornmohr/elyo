# Task: Team invite affiliation follow-up

## Goal

Apply the updated architecture decision that `users.team_id` is a user's own team affiliation and is separate from manager permission scope.

## Scope

- Harden invite acceptance against invalid persisted invite teams.
- Allow invite `team_id` for company-internal roles, not only employees.
- Keep manager invitation authority scoped to managed teams.
- Update the Angular invitation form, OpenAPI contract, and focused tests.

## Constraints

- Do not implement company `team_layer_enabled`.
- Do not replace `teams.manager_id`.
- Do not implement manager hierarchy.
- Do not change privacy thresholds.
- Do not normalize snake_case/camelCase.
- Do not run destructive database commands.
