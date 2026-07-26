# Privacy Regression Suite Handoff

## Delivered behavior

- A standalone PostgreSQL-backed `privacy` PHPUnit suite runs locally and in a
  dedicated GitHub Actions job without skip paths.
- Dynamic response scanning covers 24 company routes and 24 admin routes.
  Every discovered route requires an explicit valid synthetic request and at
  least one observed 2xx response.
- All four employee lab-marker routes reject company, admin and real partner
  principals.
- Mapping non-joinability, employee cross-access, reporting-pending responses
  and the audit user/subject invariant have dedicated regression tests.
- The effective global reporting threshold is
  `max(10, customer threshold, metric threshold)`. Survey categories with fewer
  than five contributors are suppressed separately.
- The only current aggregate exception is the released survey scale
  distribution value. It requires a 2xx response, global threshold release, an
  unsuppressed question and a bucket count of at least five.

## Pattern catalog v1

The catalog rejects:

- wellbeing dimensions (`mood`, `stress`, `energy`);
- lab marker identifiers and measurement timestamps;
- health-subject references and known synthetic subject identifiers;
- raw health/free-text answer keys;
- individual wellbeing, anamnesis, document and wearable record keys;
- `score` or `value` in health and company-reporting contexts;
- lab metadata in lab/measurement context;
- lab value/unit and value/measurement-time object shapes;
- ULID-shaped identifiers in health or company response context.

Failures report only the offending JSON path and catalog rule, never the full
payload.

## Files changed

- Privacy CI and PHPUnit suite registration.
- Privacy tests and reusable test support under
  `apps/api-laravel/tests/Privacy/` and `tests/Support/`.
- Threshold and role-middleware fixes with focused feature/unit coverage.
- Survey category suppression and response-state-aware aggregate allowlisting.
- OpenAPI and health-data guardrail documentation.

## Validation

- `php artisan test --testsuite=privacy`: 64 tests, 360 assertions.
- `php artisan test`: 575 tests, 3,281 assertions.
- Boundary suite: 21 tests, 97 assertions.
- Deptrac: 0 violations, 0 errors.
- Angular production build: passed.
- `docker compose config`: passed.
- OpenAPI and privacy-workflow YAML parse: passed.
- Laravel Pint and `git diff --check`: passed.

## Route coverage

| Surface | Routes swept |
| --- | ---: |
| `/api/company/*` | 24 |
| `/api/admin/*` | 24 |
| `/api/employee/lab-markers/*` access matrix | 4 |

## Tests & Validation

- Test-first applied: yes
- Tests added/updated:
  - `HealthLeakAssertionsTest` sensitivity cases were written first and shown
    red for suppressed allowlist payloads, sub-five buckets, singular
    wellbeing records, normalized raw-text/score variants and lab-context
    `name`/`status`.
  - `CompanyTest` proves global release at 10 and category release/suppression
    at 5.
  - `LabAccessPrivacyTest` leak-checks every forbidden-role response, including
    a real Partner token, before asserting 403.
  - `MappingNonJoinabilityPrivacyTest` detects untyped and inherited User-to-
    health relations plus direct User-to-SubjectMapping relations.
  - Threshold unit and measure/survey feature tests cover the production
    fix-forward changes.
- ACs covered by tests:
  - Dynamic company/admin discovery, explicit valid requests, route-count
    guards and at least one 2xx response per route.
  - Lab access bans, employee ownership isolation, mapping non-joinability,
    reporting-pending responses and audit user/subject separation.
  - Recursive pattern detection, safe diagnostics, response-state-aware
    allowlisting, global threshold 10 and category threshold 5.
- Validation commands executed:
  - `docker compose exec api-tooling php artisan test --testsuite=privacy`
  - `docker compose exec api-tooling php artisan test`
  - `docker compose exec api-tooling php artisan test --testsuite=boundary`
  - `docker compose exec api-tooling composer deptrac`
  - `docker compose exec api-tooling ./vendor/bin/pint --dirty`
  - `docker compose exec web npm run build`
  - `docker compose config --quiet`
  - YAML parsing for `docs/api/openapi.yaml` and
    `.github/workflows/privacy.yml`
  - `git diff --check HEAD`
- Known gaps / intentionally not tested:
  - Reporting-worker snapshots and future metric-specific thresholds remain
    ELYO-144 work; no production reporting worker exists to exercise.
  - No medical/lab aggregate or raw-text allowlist is intentionally provided.

## Open questions / ELYO-144 gaps

- Company wellbeing dashboard/report blocks remain `reporting_pending`; the
  planned reporting worker and immutable quarterly snapshots do not exist yet.
- Future reporting metrics still require explicit metric thresholds, Privacy
  Review and narrow allowlist entries before release.
- Medical/lab data, raw text and individual records remain non-reportable and
  have no allowlist path.
