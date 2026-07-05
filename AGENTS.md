# ELYO Agent Instructions

## Project Context

This repository is ELYO_TARGET.

The legacy reference codebase may exist at ../ELYO and must be treated as read-only.

Target architecture:
- Angular frontend in apps/web-angular
- Laravel API in apps/api-laravel
- PostgreSQL as primary database
- Redis for cache, sessions and queues
- Docker Compose for local development
- Mailpit for local mail testing
- n8n for integrations only, not business logic

## Non-negotiable Rules

- Do not modify ../ELYO.
- Do not introduce microservices.
- Do not switch PostgreSQL to MySQL or MariaDB.
- Do not create medical diagnosis or therapy claims.
- Do not expose individual employee health data to company, HR or manager users.
- Keep business logic in Laravel, not in Angular and not in n8n.
- Use OpenAPI as the contract between frontend and backend.
- Prefer small, reviewable patches.
- Do not change unrelated areas.
- Mark unknowns explicitly.
- Do not invent legacy behavior.

## Backend Rules

Laravel lives in:

    apps/api-laravel

Use:
- Controllers for HTTP entry points
- Form Requests for validation
- Resources for API responses
- Services for domain logic
- Policies and middleware for authorization
- Feature tests for API behavior

Important validation commands:

    docker compose exec api php artisan test
    docker compose exec api php artisan route:list

If migrations change:

    docker compose exec api php artisan migrate:fresh

## Frontend Rules

Angular lives in:

    apps/web-angular

Use:
- Feature folders
- Angular services for API calls
- Guards for role access
- Environment config for API base URL
- No direct fetch calls inside components

Important validation command:

    docker compose exec web npm run build

## Docker Rules

The local stack must remain valid.

Before or after infrastructure changes, run:

    docker compose config

Do not use localhost for inter-container communication.

Use service names such as:
- postgres
- redis
- mailpit
- api
- web

## Health Data and Company Reporting Rules

Company users may only see aggregated data.

Never expose:
- individual employee health records
- raw free-text survey answers
- individual wellbeing entries
- identifiable survey responses
- personal medical documents

Respect anonymity thresholds when aggregating data.

For survey results:
- Global anonymity threshold must be met before showing results.
- Small answer buckets must not reveal tiny groups.
- Text answers must not be shown raw to company users.

## Output Expectations

For every task, report:

1. Files changed
2. Behavior changed
3. Commands run
4. Test/build result
5. Open questions
6. Intentional deviations, if any

For feature development, extensions, or relevant behavior changes, include:

```md
## Tests & Validation

- Test-first applied: yes/no
- Tests added/updated:
  - ...
- ACs covered by tests:
  - ...
- Validation commands executed:
  - ...
- Known gaps / intentionally not tested:
  - ...
```

## Review Expectations

Before considering a task done, check:

- Does it preserve architecture?
- Does it preserve portal boundaries?
- Does it preserve company/team/user scoping?
- Does it avoid leaking health data?
- Are tests included for changed backend behavior?
- Does Angular still build?
- Is OpenAPI updated if API behavior changed?

## Test-First Development Workflow

For every new feature, extension, new endpoint, UI flow, business rule, aggregation, permission rule, validation, or relevant bugfix with reproducible behavior, a test-first workflow is required before implementation.

Allowed exceptions are limited to:
- Documentation-only changes.
- Styling-only changes without logic or behavior changes.
- Refactorings with no behavior change.
- Technical spikes.

Every exception must be explicitly justified in the plan, handoff, or final response.

Required workflow:

1. Read the task and acceptance criteria.
2. Summarize the desired functional behavior.
3. Derive test cases from the desired behavior and acceptance criteria.
4. Inspect existing tests and decide whether to extend existing tests, add new tests, or intentionally skip tests with justification.
5. Implement tests before the production implementation.
6. Run the tests and confirm that they fail as expected before implementation, or at minimum that they do not yet fully cover the new behavior.
7. Implement the change.
8. Re-run the new and relevant existing tests repeatedly during development.
9. Run the appropriate final test suites, static checks, build commands, and validation commands.
10. Document in the handoff which tests were added or changed, which acceptance criteria they cover, which validations were executed, and any intentionally untested areas.

Tests must be derived from:
- Task description.
- Acceptance criteria.
- Domain rules.
- API contract and OpenAPI where relevant.
- UI expectations and user journeys.
- Role, scope, security, and privacy requirements.
- Relevant edge cases and error cases.

If acceptance criteria are missing or unclear, the agent must formulate reasonable, testable assumptions before implementation and document them in the plan or handoff.

Tests are the primary development validation loop, not only a final check. If a test fails after implementation begins, do not simply change the test to match the current implementation. First determine whether the implementation is wrong, the test is wrong, or the acceptance criteria need clarification. Any test changes after implementation begins must be justified, especially when they change the functional expectation.

Test quality requirements:
- Tests must verify observable behavior, not private implementation details.
- Tests must cover relevant success cases, error cases, permissions, scoping, validation, and boundaries.
- Tests must be deterministic.
- Tests must avoid unnecessary sleeps, uncontrolled random data, and fragile selectors.
- Tests must use clear names that express the functional expectation.
- API changes require tests for HTTP status, response shape, and relevant error cases.
- UI changes require tests for relevant user interactions, visible states, and error messages where the repo has a suitable UI test layer.
- Privacy, role, company/team scoping, and authorization changes require negative tests proving that foreign or unauthorized data is not visible or mutable.

The existing codebase may be used for orientation, but it is not the sole source of truth. If existing behavior conflicts with the acceptance criteria, tests must encode the acceptance criteria and the conflict must be documented in the plan or handoff. Do not cement legacy behavior without checking it against the requested behavior.

Keep the workflow pragmatic. Small changes need focused tests. Complex features should use multiple levels where appropriate, such as unit, feature/integration, and UI/E2E tests. Do not require isolated tests for every internal helper when a higher-value feature test covers the observable behavior cleanly.

## Codex Workflow

For programming tasks, follow the workflow described in:

    docs/ai-context/codex-workflow.md

Default process:

1. Create a task file in docs/ai-tasks/.
2. Run plan mode before patch mode.
3. Keep patches small.
4. Run validation commands.
5. Create handoff files.
6. Review diff before commit.
7. Do not mix unrelated cleanup into feature/refactor commits.
