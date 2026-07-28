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

## Fifth review findings

The second memory-cleared review found three Medium coverage gaps:

1. Lab-route 403 responses asserted authorization but were not passed through
   the shared leak scanner.
2. Normalized variants `answerText`, `healthScore`, `averageScore` and
   `scoreValue` were missing from the catalog.
3. Actual lab response fields `name` and `status` were not rejected when the
   endpoint or JSON path established lab context.

Sensitivity tests were added first and failed for the six missing catalog
variants. Lab access tests now scan every forbidden response before asserting
403. The relation guard and handoff structure were also tightened to close the
two Low findings from the same review.

## Sixth review findings

The third memory-cleared review found two Medium hardening gaps:

1. Generic `answers[*].text` and broader score variants such as
   `wellbeingScore` and `overallScore` were not detected.
2. The survey distribution exception validated release state and bucket size
   but did not require the question type to be `SCALE`.

Four focused sensitivity tests were added first and failed. The contextual
catalog now rejects generic text only in answer collections and any normalized
score token on health/reporting surfaces. The aggregate exception now requires
`type: SCALE`.

## Seventh review findings

The fourth memory-cleared review found two Medium response/boundary gaps:

1. The User relation guard rejected Health models but not direct
   `SubjectMapping`/Privacy-domain relations.
2. The foreign lab-record 404 body was not leak-scanned before its status and
   error code were asserted.

A direct mapping-relation sensitivity test was added first and failed. The
guard now classifies both Health and Privacy model namespaces as forbidden.
The foreign-resource response now uses the shared leak scanner with seeded
subject IDs before its 404 assertions.

## Eighth review findings

The fifth memory-cleared review found two Medium release/catalog gaps:

1. Aggregate allowlisting trusted `isAboveThreshold` without independently
   verifying `minRequired >= 10`, contributor count and eligible count.
2. A generic `note` in wellbeing/check-in context was not rejected as raw
   health text.

Four sensitivity cases covering missing/insufficient global counts and a
wellbeing note were added first and failed. Released survey paths now require
integer global counts satisfying the effective threshold, and contextual notes
are forbidden.

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

## Implementation Plan

### Desired outcome and scope

- Close every High and Medium finding with test-first, reviewable slices while
  preserving dynamic route discovery, minimum route-count guards, portal
  boundaries, and synthetic-only fixtures.
- Treat every company/admin response as leak-scannable regardless of status.
  Require at least one authorized `2xx` response for every dynamically
  discovered route, while continuing to scan the error responses produced by
  other roles.
- Enforce separate reporting policies: effective global anonymity threshold
  `max(10, customer threshold, metric threshold)` and category/bucket
  suppression below five contributors.
- Keep application changes limited to the two defects demonstrated by failing
  tests: shared anonymity-threshold behavior and non-`User` principals reaching
  user-role middleware. Do not add routes, change response shapes, modify
  migrations, or touch Angular.

### Test-first implementation slices

1. **Lock down scanner sensitivity before changing helpers.**
   - Extend
     `apps/api-laravel/tests/Privacy/HealthLeakAssertionsTest.php` with failing
     examples for direct `score`/`value` fields on dashboard, report, and survey
     result surfaces; normalized score variants; generic
     `answers[*].text`; raw wellbeing/check-in `note`; singular
     `wellbeingEntry`; company-context ULIDs at generic paths; and standalone
     lab metadata established by endpoint, JSON path, or sibling context.
   - Include false-positive controls proving short lab context does not match
     ordinary keys such as `available`, `label`, or scale labels.
   - Add failing response-state cases proving survey distribution values are
     not allowlisted for non-`2xx` responses, global suppression, missing or
     insufficient global counts, suppressed questions, buckets below five, or
     non-`SCALE` questions.
   - Add failing no-content and server-error examples proving unexpected
     `204`/`205` content is reported by endpoint/path only and a leaking `5xx`
     payload fails on the leak before the status assertion.
   - Run the focused sensitivity tests and record the expected failures before
     modifying catalog, scanner, or allowlist code.

2. **Harden catalog matching, recursive inspection, and diagnostics.**
   - Update
     `apps/api-laravel/tests/Support/ForbiddenHealthPatternCatalog.php` with
     stable, rationale-backed patterns for the new key and contextual variants.
     Normalize camelCase and snake_case consistently and use an anchored lab
     token.
   - Update
     `apps/api-laravel/tests/Support/HealthLeakAssertions.php` to inspect nested
     keys, compound shapes, known seeded subject IDs, and ULID-shaped values in
     company/health context. Build contextual matches from endpoint, normalized
     JSON path, and sibling keys.
   - Keep diagnostics data-minimizing: report catalog pattern, endpoint, and
     offending JSON path, never the sensitive value or response body.
   - Handle `204` and `205` before JSON parsing, accepting only an empty body.

3. **Make aggregate exceptions release-state aware and narrow.**
   - Keep explicit entries in
     `apps/api-laravel/tests/Support/HealthLeakAllowlist.php`; do not delete or
     weaken forbidden patterns.
   - Permit the survey scale-distribution `value` path only when the response is
     `2xx`, `isAboveThreshold` is true, `minRequired` is an integer of at least
     10, response and eligible counts meet that effective threshold, the
     question is an unsuppressed `SCALE`, and the specific bucket has at least
     five contributors.
   - Require pattern, endpoint glob, exact path glob, review ticket, and
     aggregate-specific rationale for every exception. Reject broad
     endpoint-wide and below-threshold exceptions.
   - Re-run the focused scanner tests until all positive, negative, and
     false-positive examples pass.

4. **Require meaningful coverage from the dynamic company/admin route sweep.**
   - Add failing cases in
     `apps/api-laravel/tests/Privacy/CompanyAdminRoutePrivacyTest.php` for an
     unregistered route fixture, a route exercised only through `4xx`
     responses, a leaking `5xx`, and unexpected no-content response bodies.
   - Keep runtime route discovery and existing minimum company/admin route
     counts. For each route, scan every role response first, then reject server
     errors, and finally assert that at least one authorized seeded role reached
     a `2xx`.
   - Make
     `apps/api-laravel/tests/Support/PrivacyRouteRequestFactory.php` fail closed
     for unknown method/URI combinations. Add narrow URI, payload, actor, and
     execution-order definitions for every currently registered company/admin
     route.
   - Extend `apps/api-laravel/tests/Support/PrivacySeeder.php` only with
     deterministic synthetic records needed to produce real success shapes,
     including separate mutable, deletable, archivable, and foreign-scope
     records where route order or mutation would otherwise invalidate later
     requests.
   - Confirm error responses from unauthorized swept roles remain scanned even
     when another role supplies the required success response.

5. **Enforce global and bucket anonymity rules through failing behavior tests.**
   - Add unit tests in
     `apps/api-laravel/tests/Unit/Services/Company/AnonymityThresholdTest.php`
     for configured thresholds below 10, absent configuration, stricter
     customer thresholds, and the independent category minimum of five.
   - Add feature/privacy regressions for
     `GET /api/company/surveys/{id}/results` and
     `GET /api/company/measures/{id}/participation-summary`. Use 10 eligible
     employees and five contributors so the test distinguishes the old
     customer threshold of three from the platform minimum of 10.
   - Update `App\Services\Company\AnonymityThreshold` only after those tests
     fail, then pass the resolved effective threshold through survey and
     participation aggregation. Keep category suppression in
     `SurveyResultsAggregationService` fixed at five rather than reusing the
     global threshold.
   - Verify suppressed responses expose no counts, percentages, or distribution
     values that could reveal a below-threshold group.

6. **Exercise real principals and strengthen authorization/boundary regressions.**
   - In `apps/api-laravel/tests/Privacy/LabAccessPrivacyTest.php`, dynamically
     sweep every employee lab route with all forbidden identity roles and with
     a real `Partner` login/token. Leak-scan every `403` before asserting status
     and error code.
   - Add a focused middleware regression demonstrating that a real partner
     principal returns `403` instead of causing a `500`; then update
     `App\Http\Middleware\RoleMiddleware` to reject authenticated principals
     that are not identity `User` models before loading user roles.
   - In
     `apps/api-laravel/tests/Privacy/EmployeeCrossAccessPrivacyTest.php`,
     leak-scan the foreign lab-record `404` before asserting its status and
     public error code. Remove any nonexistent wellbeing-detail request whose
     fallback `404` does not exercise ownership scoping.
   - In
     `apps/api-laravel/tests/Privacy/MappingNonJoinabilityPrivacyTest.php`, add
     failing sensitivity classes for untyped, inherited, and direct
     `SubjectMapping` relations. Reflect over public zero-required-argument
     methods visible on `User`, invoke relation methods, and reject related
     models from both Health and Privacy namespaces.

7. **Align the binding contract and suite documentation.**
   - Update `docs/api/openapi.yaml` only for effective behavior already changed:
     document `AnonymityThresholdError.minRequired` with a minimum of 10 and
     keep existing routes and response shapes unchanged.
   - Update `docs/ai-context/health-data-guardrails.md` with the effective global
     threshold, independent bucket minimum, fail-closed route-fixture rule,
     response-first leak scanning, contextual-pattern guidance, and narrow
     aggregate allowlisting rules.
   - Update
     `docs/further_docs/privacy-regression-suite-handoff.md` with route
     statistics, test/validation results, privacy verdict, and known ELYO-144
     gaps. Do not include response bodies or sensitive fixture values.

8. **Validate the complete change without broadening scope.**
   - Run focused red/green tests after each slice, then execute:

     ```bash
     docker compose exec api-tooling php artisan test --testsuite=privacy
     docker compose exec api-tooling php artisan test
     docker compose config
     git diff --check HEAD
     ```

   - Confirm the privacy suite does not skip for missing PostgreSQL databases,
     runtime roles, credentials, or schemas; infrastructure absence is a
     failure.
   - Review the final diff for company/team/user scoping, health-data leakage,
     route-count guard preservation, synthetic-only fixtures, OpenAPI
     alignment, and absence of frontend, migration, or unrelated changes.

### Acceptance criteria coverage

- Functional behaviors 1, 2, 6, and 7 are covered by the fail-closed request
  factory, per-route success assertion, response-first scanner ordering, and
  body-free no-content diagnostics.
- Functional behaviors 3, 4, and 5 are covered by catalog sensitivity tests,
  contextual matching, ULID detection, and narrow release-state-aware
  allowlisting.
- Second through eighth review findings are covered by the effective-threshold
  tests, five-contributor fixture, separate bucket threshold, real partner
  token sweep, relation guard, singular/normalized catalog variants, scanned
  `403`/`404` responses, and aggregate release-count checks.
- Test-first applied: yes. Each helper or application change must be preceded
  by a focused failing test that demonstrates the reviewed defect.

### Known gaps and intentional exclusions

- Preserve documented ELYO-144 gaps as known follow-up work; do not simulate
  missing reporting-domain capabilities or weaken leak rules to accommodate
  them.
- No Angular, migration, database schema, route, or API response-shape work is
  planned.
- No production change is permitted unless a new failing regression proves the
  privacy or authorization defect described above.

## Final Verification

All High and Medium findings listed in this task remain closed under the final
Task 17 battery:

- Privacy suite: `71 passed (371 assertions)`.
- Full Laravel suite: `593 passed (7618 assertions)`.
- Boundary suite: `23 passed (111 assertions)`.
- Deptrac: `Violations 0, Warnings 0, Errors 0`.
- Runtime split smoke: passed, including forbidden cross-portal access.
- OpenAPI: 77 Laravel operations, 77 OpenAPI operations, 0 missing, 0 stale;
  semantic schema audit passed.

The Task 17 smoke exposed a separate employee-runtime Sanctum timestamp grant
defect, and the parity audit exposed a separate pre-existing OpenAPI inventory
gap. Both were stopped and handled through explicit fix-forward tasks:
`2026-07-26-fix-employee-runtime-sanctum-token-grant.md` and
`2026-07-26-fix-openapi-route-parity.md`. Neither fix weakens the privacy
catalog, aggregate thresholds, role restrictions, health scoping, or route
coverage established here.

ELYO-144 remains intentionally open for the future Reporting Worker, immutable
quarterly snapshots, and metric-by-metric allowlist coverage.
