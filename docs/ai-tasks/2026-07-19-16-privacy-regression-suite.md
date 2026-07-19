# Task: Privacy regression test suite (ELYO-111 groundwork)

## Goal

Stand up the durable `privacy` test suite validating the production privacy rules pattern-based — so any future endpoint leaking individual health data fails CI — plus CI wiring.

## Context

Relevant files:

- tests/ (existing feature tests), tests/Boundary/ (prompt 06)
- phpunit.xml
- docs/ai-context/health-data-guardrails.md
- Jira ELYO-111; ADR-001 §2.10; ADR-003 (D9)

Background:

- Rules derive from the target architecture and privacy decisions, NOT from demo behavior. Suite completion continues through Sprint 5 (ELYO-144) — this is the extensible skeleton with the mandatory minimum.
- Pattern approach: a shared assertion helper walks JSON responses recursively for forbidden health-value keys/patterns (mood, energy, stress, score-in-health-context, markerKey, value+unit pairs, measuredAt, health_subject-like ULIDs in company context, etc.) — pattern list versioned in one file with rationale comments.

## Scope

Change only:

- New: tests/Privacy/ (suite), tests/Support/ (HealthLeakAssertions helper + forbidden-pattern catalog)
- phpunit.xml (testsuite `privacy`)
- CI config: dedicated privacy job (PG-backed where needed)
- docs/ai-context/health-data-guardrails.md (append: how to extend the pattern catalog when adding endpoints)

Do not change:

- Application code (findings become fix-forward micro-tasks; do not silently patch here)
- Existing test suites

## Requirements

1. Coverage minimum:
   - Every registered /company/* and /admin/* route (iterate the route table dynamically, seeded auth per role) responds without any forbidden health pattern; new routes are picked up automatically.
   - Lab access bans: company/admin/partner → 403 on all lab routes; no company/admin route emits lab fields.
   - Mapping non-joinability: standard connection query attempt fails (reuse boundary helper); no Eloquent relation path User → health models exists (reflection test).
   - Employee cross-access: employee A cannot read employee B's wellbeing/lab data (404 semantics).
   - Company wellbeing blocks return reporting_pending (no numbers).
   - Audit invariant: sampled audit rows never contain user_ref and subject_ref together.
2. Suite runs standalone: `php artisan test --testsuite=privacy` against the Postgres test databases (D9) — no skip paths, locally and in CI identical.
3. Synthetic seeds only; a dedicated PrivacySeeder if DemoDataSeeder is unsuitable.
4. Document in the pattern catalog how a legitimate new aggregate (future allowlist per ADR-001 §2.5) would be exempted — explicit allowlist file, never pattern removal.

## Constraints

- No new packages.
- Dynamic route iteration must not silently shrink (assert minimum route count guard).

## Privacy and Security Requirements

- The suite itself uses synthetic data only.
- Failure output shows the offending key path, not full payload dumps.

## Validation

Run:

    docker compose exec api-tooling php artisan test --testsuite=privacy
    docker compose exec api-tooling php artisan test

Expected result:

- Privacy suite green; full suite green; CI job configured and passing.

## Output Required

1. Files changed
2. Pattern catalog v1 (list + rationale)
3. Route-coverage stats (how many company/admin routes swept)
4. Commands run and results
5. Open questions / known gaps left for ELYO-144

## Review Checklist

- Would a new company endpoint returning `mood` fail the suite without any test change?
- Is the allowlist mechanism explicit and reviewed, not pattern deletion?
- Are skip paths absent entirely?
