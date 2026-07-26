# Task: Restore runtime smoke-test precondition and rerun docs closure

## Goal

Complete task 17 only after the split API runtimes and nginx are running and
the full validation battery passes without application-code fixups.

## Context

Relevant files:

- `docs/ai-tasks/2026-07-19-17-docs-closure-and-verification.md`
- `docker-compose.yml`
- `infra/smoke-runtime-split.sh`

Blocked run on 2026-07-26:

- `docker compose config`: exit 0.
- `docker compose run --rm migrate`: exit 0; fresh migration and seed completed.
- `docker compose exec api-tooling php artisan test`: exit 0; `591 passed (7604 assertions)`.
- `docker compose exec api-tooling php artisan test --testsuite=boundary`: exit 0; `21 passed (97 assertions)`.
- `docker compose exec api-tooling php artisan test --testsuite=privacy`: exit 0; `71 passed (371 assertions)`.
- `docker compose exec api-tooling composer deptrac`: exit 0; `Violations 0`, `Errors 0`.
- `bash infra/smoke-runtime-split.sh`: exit 1; `api-identity`, `api-employee`, and `api-company` were not running, nginx requests returned HTTP `000`, and the script ended with `runtime split smoke test FAILED (28 check(s))`.

Task 17 requires stopping on any failed validation step. The Angular build,
route/OpenAPI parity audit, and documentation patch were therefore not run.

## Scope

Change only:

- Runtime state needed by `infra/smoke-runtime-split.sh`.
- Documentation files listed in task 17, but only after the complete battery is
  green.

Do not change:

- Application code.
- `ADR-001` or `ADR-002`.
- `infra/smoke-runtime-split.sh` merely to bypass a runtime startup failure.

## Requirements

1. Start the existing Compose services needed by the smoke test, including
   `api-identity`, `api-employee`, `api-company`, `nginx`, and their declared
   dependencies. Confirm each is healthy or running before validation.
2. Rerun the complete task 17 validation battery from the beginning. Do not
   treat the earlier partial run as final evidence.
3. If the smoke test still fails while all required services are running,
   stop and report the reproducible defect; do not modify application or
   infrastructure code under this micro-task.
4. Continue the task 17 documentation patch only when every validation command,
   including the OpenAPI parity audit, passes.
5. Keep validation evidence free of credentials, personal data, and subject
   identifiers.

## Validation

Run:

```bash
docker compose ps
docker compose config
docker compose run --rm migrate
docker compose exec api-tooling php artisan test
docker compose exec api-tooling php artisan test --testsuite=boundary
docker compose exec api-tooling php artisan test --testsuite=privacy
docker compose exec api-tooling composer deptrac
bash infra/smoke-runtime-split.sh
docker compose exec web npm run build
docker compose exec api-tooling php artisan route:list
git diff --check
```

Also run the route/OpenAPI parity and schema audit required by task 17.

Expected result:

- Required runtime services are running.
- Every task 17 validation and parity check passes.
- Task 17 documentation is then completed exactly within its original Scope.

## Output Required

1. Runtime startup evidence.
2. Full task 17 validation battery results.
3. Route/OpenAPI parity and schema-audit result.
4. Files changed.
5. Any remaining blocker.

## Review Checklist

- Was the original failure resolved by satisfying the runtime precondition,
  without weakening the smoke script?
- Was the full battery rerun from the beginning?
- Did documentation work begin only after every validation passed?
- Were secrets, personal data, and subject identifiers excluded from evidence?
