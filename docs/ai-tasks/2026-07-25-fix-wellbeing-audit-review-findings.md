# Task: Fix wellbeing and audit review findings

## Goal

Resolve the verified `main...HEAD` review findings for the wellbeing health-domain
move and append-only audit logging without expanding prompt-08 scope into the
deferred Angular or company-reporting tasks.

## Desired behavior

1. Check-in validation errors use the repository error envelope:
   `error.code`, `error.message`, and `error.details`.
2. OpenAPI structurally rejects the removed `note` field and documents the actual
   validation envelope.
3. A duplicate check-in resolves the subject with `HEALTH_SELF_WRITE` before the
   uniqueness decision.
4. Missing-mapping repair initiated by employee self-service records employee
   runtime/role actor context, not registration-system context.
5. Demo wellbeing entries use daily `YYYY-MM-DD` period keys.
6. Health reads share one subject-scoped query seam.
7. Score calculation has one production source of truth.
8. Audit outcomes and actor roles use typed enums rather than unconstrained
   strings.

## Scope

- `apps/api-laravel/app/Http/Requests/Employee/CheckinRequest.php`
- `apps/api-laravel/app/Http/Controllers/Employee/EmployeeController.php`
- `apps/api-laravel/app/Services/Health/`
- `apps/api-laravel/app/Services/Privacy/`
- `apps/api-laravel/database/factories/Health/WellbeingEntryFactory.php`
- `apps/api-laravel/database/seeders/DemoDataSeeder.php`
- focused feature/unit tests
- `docs/api/openapi.yaml`

Do not change:

- Angular check-in UI (prompt 10)
- company reporting-pending behavior (prompt 09)
- routes, authorization, points amounts, or database schema

## Confirmed test seams

The user approved fixing the reviewed behavior. Tests use these public seams:

- `POST /api/employee/checkin` response and duplicate behavior
- persisted `audit_events` evidence through the audit migrator connection
- employee wellbeing responses
- `DemoDataSeeder` persisted output

Pure refactors remain covered through these seams; no private-helper tests.

## Test cases

1. Off-scale and prohibited-note requests return the standard validation envelope.
2. Duplicate POST creates a write-purpose mapping-resolution audit event and no
   extra points/check-in.
3. Missing-mapping repair audit events identify employee self-service actor
   context.
4. Demo-seeded wellbeing `period_key` values all match valid `YYYY-MM-DD` dates.
5. Existing score, history, dashboard, privacy, boundary, and audit tests remain
   green after the internal refactors.

## Test-first workflow

Add or update one behavioral test, run it red, apply the smallest production
change, then rerun green before starting the next slice. Refactor duplicated
queries, score calculation, and primitive audit types only after behavior slices
are green.

## Validation

```bash
docker compose exec api php artisan test --filter='Checkin|Wellbeing|Audit|DemoDataSeeder'
docker compose exec api php artisan test --testsuite=boundary
docker compose exec api composer deptrac
docker compose exec api php artisan test
docker compose config
git diff --check
```

## Acceptance criteria

- All eight review findings resolved.
- No individual health data becomes reachable by company/manager users.
- No `health_subject_id` appears in an HTTP response.
- OpenAPI matches changed validation behavior.
- Full API and boundary suites pass.

## Implementation result

- Test-first RED confirmed all reviewed behavior gaps before production changes.
- Check-in validation now returns the coded repository error envelope.
- Duplicate submissions resolve the subject with `HEALTH_SELF_WRITE`.
- Self-service mapping repair is provisioned and re-resolved with employee actor context.
- Demo wellbeing entries use daily period keys.
- Subject-scoped reads and wellbeing score calculation each have one implementation seam.
- Audit outcomes and actor roles are typed enums.

## Validation result

- Focused wellbeing/privacy tests: 64 passed, 401 assertions.
- Privacy audit contract tests: 27 passed, 165 assertions.
- Boundary suite: 17 passed, 86 assertions.
- Full API suite: 404 passed, 1,870 assertions.
- Deptrac: 0 violations, 0 errors.
- Pint: 20 changed PHP files pass.
- Docker Compose config, OpenAPI YAML parse, and `git diff --check`: pass.
