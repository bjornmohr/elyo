# Health Data Guardrails

## Non-negotiable Rules

- No diagnosis wording.
- No therapy promises.
- No individual employee health data in company views.
- No raw free-text health answers in company views.
- No identifiable survey responses in company views.
- No individual document access for company users.

## Safe Language

Prefer:
- orientation
- self-reflection
- resources
- burden indicators
- general measures
- aggregated trends

Avoid:
- diagnosis
- treatment
- cure
- medically certain claims
- individual risk classification for HR

## Health Data Canonical Rules

- Check-in scale is 1–5 (canonical). No other scale range.
- The free-text `note` field on check-ins is removed (per ELYO-102 B4). Do not reintroduce raw free-text on check-ins.
- Lab values are never reportable. Company/reporting views must never expose individual lab values or lab-value aggregates (allowlist principle, ADR-001 §2.5).

## Survey Results

Survey results shown to company users must be aggregated.

Apply:
- global anonymity threshold
- effective threshold `max(10, customer threshold, metric threshold)`; customer
  configuration can never lower the platform minimum of 10
- bucket-level suppression for small groups
- no raw text output
- no misleading charts when data is suppressed

## Privacy Regression Pattern Catalog

The standalone PostgreSQL-backed suite is:

```bash
docker compose exec api-tooling php artisan test --testsuite=privacy
```

`apps/api-laravel/tests/Support/ForbiddenHealthPatternCatalog.php` is the
versioned source of forbidden company/admin response patterns.
`HealthLeakAssertions` recursively inspects JSON keys and shapes and reports
only the offending JSON path. `PrivacySeeder` supplies deterministic synthetic
health subjects and health records; do not use demo or production-derived data
in this suite.

When adding an endpoint or a new individual-health response shape:

1. Add the key, contextual-key, compound-shape or identifier pattern to the
   catalog with a stable id and rationale.
2. Add a focused assertion-sensitivity test under
   `apps/api-laravel/tests/Privacy/`.
3. Extend `PrivacySeeder` only when synthetic records are needed to make the
   endpoint return its real response shape.
4. Keep route discovery dynamic. Do not replace the company/admin route sweep
   with a hand-maintained endpoint list or reduce its minimum route-count guard.
5. Every discovered company/admin route must reach at least one `2xx` response
   with an authorized synthetic user. When a new route needs parameters or a
   request body, add its narrow synthetic request definition to
   `PrivacyRouteRequestFactory`; a sweep that observes only `4xx` responses is
   intentionally a test failure.
6. Run the privacy suite standalone and the full Laravel suite. No privacy test
   may skip when PostgreSQL, roles, credentials or schemas are missing.

Future reporting-domain aggregates allowed by ADR-001 §2.5 do not justify
removing or weakening a forbidden pattern. Add a narrow reviewed entry to
`apps/api-laravel/tests/Support/HealthLeakAllowlist.php` instead. Every entry
must identify one catalog pattern, endpoint glob, JSON-path glob, review ticket
and aggregate-specific rationale. Broad endpoint-wide or `$.*` exceptions are
not permitted.
