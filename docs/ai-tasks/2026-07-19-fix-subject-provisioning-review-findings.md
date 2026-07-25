# Task: Fix subject provisioning review findings

## Goal

Close every finding from the review of `2026-07-19-05-subject-provisioning.md` without exposing mapping identifiers or weakening revoked tombstones.

## Confirmed behavior and test seams

- Preserve the task-required invite behavior: identity commit succeeds when provisioning fails; a generic warning flags repair work and the backfill command repairs it.
- Formally align ADR-001 and its source decision document with that compensating-repair policy.
- Test public seams only: invite-accept HTTP behavior, `elyo:provision-subjects` output/exit status, the privacy service contract, and repeatable demo seeding.
- `REVOKED` is terminal. The command reports revoked users as an aggregate and never attempts to recreate their mapping.
- An ACTIVE mapping whose identity user no longer exists must not reduce the missing count for current users.
- Mapping inspection must occur through `App\Services\Privacy\MappingService`, require `PurposeCode::PROVISIONING`, return lifecycle state only, and emit an audit event without raw identifiers.

## Test-first slices

1. Add a privacy-service test for `MISSING`, `ACTIVE`, and `REVOKED` provisioning state plus invalid-purpose rejection.
2. Add command tests proving orphan ACTIVE mappings cannot mask missing users and revoked mappings are skipped without failure.
3. Add command failure-log coverage proving exception text and identifiers never reach logs or console output.
4. Add a seeder rerun test proving every seeded user resolves and the same subject references remain stable.
5. Run new tests red before production edits, then implement the smallest passing changes.

## Implementation constraints

- No direct mapping-table reads outside `MappingService`.
- No health subject ids in HTTP, command, seeder, exception, or log output.
- No API route or response-shape changes; OpenAPI remains unchanged.
- No new package, migration, queue, frontend, or legacy-code change.
- Keep the existing `ACTIVE`/`REVOKED` tombstone model.

## Validation

    docker compose exec api php artisan test tests/Feature/Privacy
    docker compose exec api php artisan test tests/Feature/AuthTest.php
    docker compose exec api php artisan test
    docker compose config
    git diff --check HEAD

The destructive development-database reset is run only after explicit confirmation or when an isolated disposable database is used.

## Test-first evidence

- Red: `DemoDataSeederSubjectProvisioningTest::test_subject_provisioning_failure_does_not_expose_identifiers`
  failed because the sensitive exception message escaped unchanged.
- Green: the demo-seeder and provisioning-command feature tests passed after
  replacing the seeder failure with an identifier-free exception and adding
  state-inspection failure coverage.
- Existing manager invite acceptance was extended at the public HTTP seam to
  prove subject provisioning without returning the subject identifier.
