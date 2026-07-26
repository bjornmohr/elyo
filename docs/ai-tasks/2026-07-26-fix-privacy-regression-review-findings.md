# Task: Fix privacy regression suite review findings

## Goal

Close every High and Medium finding from the task 16 code review. Keep
production changes limited to fix-forward defects proven by failing tests.

## Functional behavior

1. The dynamic company/admin route sweep must reach a successful response for
   every registered route with at least one authorized seeded role. Error
   responses from other swept roles remain leak-checked.
2. A newly registered route without a valid synthetic request fixture must fail
   the privacy suite instead of silently exercising only a 4xx path.
3. `score` and `value` must be rejected on sensitive company reporting surfaces,
   including dashboards, reports, and survey results.
4. Any ULID-shaped value in company response context must be rejected unless a
   narrow reviewed allowlist entry permits it.
5. Standalone lab fields must be rejected when their endpoint, JSON path, or
   sibling keys establish lab context.
6. Every swept error response, including a 5xx response, must be inspected for
   health leaks before its status failure is reported.
7. A no-content response carrying an unexpected body must fail without printing
   that body; diagnostics identify only the response path and endpoint.

## Test seams

- Laravel HTTP responses through `CompanyAdminRoutePrivacyTest`.
- Recursive response inspection through `HealthLeakAssertions`.

## Test-first slices

1. Add sensitivity examples proving the current helper misses a direct
   dashboard score, a generic-path company ULID, and standalone lab metadata.
2. Make each sensitivity example pass through catalog/assertion changes.
3. Require the route sweep to record at least one 2xx response per route.
4. Add valid synthetic URI/payload fixtures until every current company/admin
   route satisfies the success requirement.
5. Add error-response sensitivity examples proving 5xx bodies are leak-checked
   first and no-content diagnostics never disclose response content.

## Constraints

- Production changes require a failing regression test and remain limited to
  review-proven privacy or authorization defects.
- Update OpenAPI whenever effective API behavior or schemas change.
- Keep dynamic route discovery and minimum-count guards.
- Synthetic fixtures only.
- Preserve explicit allowlisting; do not weaken existing patterns.

## Validation

```bash
docker compose exec api-tooling php artisan test --testsuite=privacy
docker compose exec api-tooling php artisan test
docker compose config
git diff --check HEAD
```

## Known assumptions

- Success means any 2xx response, including 204.
- Every current route receives a valid request definition. Future routes fail
  until their successful synthetic request is defined.
- ULID allowlisting is the intended escape hatch for legitimate company-domain
  ULIDs, matching task 16's explicit allowlist rule.

## Second review findings

The branch review against task 16 added four required fixes:

1. Enforce ADR-001 §2.5's platform anonymity minimum of 10. A customer
   threshold below 10 must not release survey or participation aggregates.
2. Do not allowlist any below-threshold value. The privacy suite must prove that
   a customer threshold below 10 remains suppressed; the existing survey
   distribution exception applies only after effective global and bucket
   thresholds pass.
3. Exercise the real `Partner` principal and token path against every lab route,
   not only an identity `User` carrying the `PARTNER` role.
4. Remove the nonexistent wellbeing-detail request whose fallback 404 did not
   prove ownership scoping, and make the User relation guard detect untyped
   health relations.

## Third review findings

The pre-commit review found two additional Medium gaps:

1. The individual-record regex matched `wellbeing_entries` but not the singular
   camelCase response key `wellbeingEntry`. A sensitivity test must prove the
   singular key is rejected.
2. The measure-summary suppression fixture had only two participants, so both
   the old threshold of 3 and the platform minimum of 10 suppressed it. Use
   five participants out of ten eligible employees and cover this seam in the
   standalone privacy suite.

The same review found a Low OpenAPI gap: the 403
`AnonymityThresholdError.minRequired` field must document its effective minimum
of 10.

## Fourth review findings

The first memory-cleared `origin/main...HEAD` review found two further policy
gaps:

1. A survey distribution allowlist match used only pattern, endpoint and path,
   so a suppressed or 403 payload at the same path could evade detection.
   Allowlisting must additionally require a 2xx response, global release,
   an unsuppressed question and a bucket with at least five contributors.
2. The global threshold of 10 was also being used for category suppression.
   ADR-001 §2.5 instead requires separate policies: the effective global
   threshold is at least 10, while categories below five contributors are
   suppressed.

Sensitivity tests were added before both fixes. The review also requested the
task 16 output artifact with route statistics, validation results and known
ELYO-144 gaps.

## Additional test seams

- `GET /api/company/surveys/{id}/results` for effective-threshold suppression.
- `GET /api/company/measures/{id}/participation-summary` for the same shared
  threshold policy.
- Real partner bearer-token requests through employee lab routes.
- Reflection over zero-required-argument methods declared on `User`.

## Intentional scope deviation

Task 16 originally prohibited application changes. The High review finding
proved that the existing shared threshold service violated ADR-001 §2.5, and
the real-partner test exposed a 500 path in role middleware. The user explicitly
requested all High and Medium findings be fixed, so these production defects
are handled as fix-forward changes in the same review cycle. OpenAPI is updated
for the effective platform minimum; no route or response shape changes.
