# Task: Fix team invite assignment and team member listing

## Goal

Complete the team onboarding flow so invited employees can be assigned to a valid team during invite acceptance.

## Context

Current Codex analysis found:

- `users.team_id` exists.
- `/company/teams/{teamId}/members` returns an empty collection with a TODO.
- Invite acceptance references `$invite->team_id`.
- `invite_tokens` does not currently have a `team_id` column.
- Team scoping is already used in several dashboard/survey/measure/report/user flows.

## Scope

Inspect and adjust:

- invite token migration/model
- invite creation flow
- invite acceptance flow
- team controller
- team member listing endpoint
- tests for invite/team scoping

## Required changes

1. Add team assignment support to invites.
2. Assign users to teams during invite acceptance.
3. Implement team member listing.
4. Add focused tests.

## Hard constraints

- Do not run `migrate:fresh`.
- Do not run `db:wipe`.
- Do not run `docker compose down -v`.
- Do not introduce multi-company users.
- Do not do a broad refactor.
- Do not change frontend unless strictly necessary.

## Expected output

After implementation, report:

- changed files
- migration changes, if any
- tests added or updated
- commands to run
- risks or open questions
