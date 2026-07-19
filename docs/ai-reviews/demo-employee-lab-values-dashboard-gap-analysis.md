# Demo Branch Gap Analysis: employee-lab-values-dashboard

Analysis date: 2026-07-07 (revised framing: demo = reference only)
Compared refs: `main..demo/employee-lab-values-dashboard`
Author: automated branch analysis (Claude Code), reviewed input for Jira import `docs/ai-reviews/demo-employee-lab-values-dashboard-jira-import.csv`

## Guiding Principle

> The demo implementation is not approved as the production codebase. It serves exclusively as a functional and visual reference for user journeys, information hierarchy, and interaction patterns. Production features are built cleanly from the target architecture, final OpenAPI contracts, privacy decisions, and acceptance criteria — with no direct technical dependency on demo components, demo providers, demo localStorage flows, hardcoded demo content, demo seeds, or demo response formats.

Every statement in this report distinguishes between: **demo observation** (what the branch shows), **reference value** (what the demo teaches about UX and scope), **production target** (what must be built cleanly), and **non-goals/exclusions** (what must not carry over).

## Executive Summary

The demo branch demonstrates a large, demo-first feature set on top of `main`: an employee lab values dashboard (full stack, persisted), lab marker explanations, an extended employee dashboard (weekly wellbeing aggregates, sleep/body-signal blocks, "Schonmodus" safe-mode gating), an adaptive check-in (stepper + chat, localStorage-only), a demo badges/gamification layer, an employee measures hub, and a company insights suite (executive summary, risk landscape, usage funnel, infection radar, measure impact/statistics) behind an `ELYO_DATA_MODE` demo/prod provider architecture.

Key findings:

- **Privacy blocker (production):** the demo `lab_markers` table stores lab values with a direct `user_id` foreign key (`apps/api-laravel/database/migrations/2026_07_05_010000_create_lab_markers_table.php`). There is no `health_subject_id`, no pseudonymization layer, no mapping domain, no audit logging and no retention concept. This schema documents the risk, not the solution: the production lab value model must be designed new in a dedicated health domain. The demo schema is not migrated forward.
- Demo access control is route-level only: `/api/employee/lab-markers` is scoped to the authenticated user and Company/Admin access is rejected (verified by `tests/Feature/EmployeeLabMarkersTest.php`, run and passing). These tests illustrate the expected protection rules; production tests must validate the production authorization matrix independently. Nothing at the schema/service layer technically prevents company code from joining the demo table to `users` — the production architecture must make such joins impossible.
- The company insights suite is cleanly demo-gated: in `prod` mode all concept endpoints return null-provider data and feature flags disable the UI (`app/Providers/InsightsServiceProvider.php`, `app/Services/FeatureFlagService.php`). Demo aggregations and demo JSON are excluded from the production path. Production company statistics require an independently specified aggregation/suppression rule set (minimum group sizes, suppression display behavior); the existing `DbMeasureStatisticsProvider` is verified against that specification, not adopted blindly.
- One concrete defect found in the demo branch: `tests/Feature/MeasureImpactAccessTest.php` fails (3 tests) because it does not set `elyo.data_mode=demo` while `phpunit.xml` forces `ELYO_DATA_MODE=prod`, so the Null provider returns null. Verified by running the test suite.
- The demo migrates the wellbeing scale from 1–10 to canonical 1–5 including a data migration; the production adoption of the 1–5 scale is introduced via an independently reviewed migration, with the demo migration serving as a reference for the mapping semantics.
- Non-diagnostic wording is largely respected in the demo (soft status labels "unter Bereich / im Orientierungsbereich / über Bereich", explicit "ersetzt keine ärztliche Einordnung" safety notes) and provides good reference material — but explanation content is hardcoded in the frontend, which is excluded for production: all explanation content comes from a versioned backend catalog with editorial approval.

Recommended path: keep the demo fully gated as a reference environment; execute privacy foundation decisions (Sprint 1) and build the health data domain (Sprint 2) before any production persistence of lab values; then author final API contracts and production backend (Sprint 3), build the production Employee UI API-first (Sprint 4), and complete test/QA coverage against production acceptance criteria (Sprint 5). Infection radar and related predictive/health-adjacent company views stay blocked for the MVP; any later revival would be a new design, not a continuation of the demo aggregation.

## Branch Comparison

- Current branch at analysis time: `demo/employee-lab-values-dashboard` (HEAD `40c6a92 merged demo`).
- **Working tree is dirty** (reported before generating files): untracked `.claude/launch.json`, `apps/web-angular/src/app/features/employee/components/`, three untracked Angular spec files (badges/dashboard-badges/employee-badges-demo.service), `docs/ai-tasks/2026-07-05-gamification-badges-redesign.md`, `prototypes/`, `prototypes.zip`. No tracked files modified. Analysis is based on committed state `main..demo/employee-lab-values-dashboard`.
- Diff size: **224 files changed, 14,712 insertions, 1,117 deletions**.
- Commit range includes merged demo sub-branches: employer dashboard demo, new employee features (check-in, measures hub, history, screening), gamification badges, lab values feature (`5165ec6 First draft lab values feature`, `bfdec35 Added infoicon`).
- `git fetch --all --prune` executed; no divergence relevant to the analysis.

## Changed Files by Domain

Each domain summary states what the demo shows (observation) and what that means for the production path (reference vs. exclusion).

### Angular Employee UI
Observation: new pages/components — `lab-markers.component.ts` (571 lines), `checkin-stepper.component.ts`, `checkin-chat.component.ts`, `checkin-state.ts`, `badges.component.ts`, `measure-detail.component.ts`, `measure-exercise.component.ts`; heavy rework of `dashboard.component.ts` (badges, metric tiles, Schonmodus banner, lab preview) and `history.component.ts` (+585 lines timeline). New models, data catalog (`data/lab-marker-catalog.ts`), demo services (`checkin-demo-storage.service.ts`, `employee-badges-demo.service.ts`). Old `checkin.component.ts` deleted.
Assessment: strong UX reference for user journeys, information hierarchy and interaction patterns. `main` has no equivalent. Production Employee UI is implemented as new components API-first against the final contract; hardcoded content, demo storage and demo response models are excluded from the production path.

### Angular Company UI
Observation: new pages — `dashboard-executive-summary.component.ts`, `company-infection-radar.component.ts`, `company-risk-landscape.component.ts`, `company-usage-funnel.component.ts`, `company-measure-statistics.component.ts`, `measure-impact-dialog.component.ts`; new `company-insights.service.ts`. Routes gated by `featureFlagGuard(...)`.
Assessment: concept demonstrations, demo-only in prod mode (flags off). Health-adjacent aggregates (infection radar) conflict with MVP scope and stay blocked. Any production company reporting is specified independently (aggregation rules, minimum group sizes, suppression) — demo aggregations are not carried over.

### Angular Admin UI
Observation: system exercise / measure template admin pages extended for new catalog fields (pictograms, steps, categories).
Assessment: product-relevant scope reference, low privacy impact; production adoption goes through the normal contract/review path.

### Laravel API (controllers/routes)
Observation: new `Employee/LabMarkerController.php` (employee-only read), `Employee/UserSystemMeasureController.php`, `Company/InsightsController.php`, `Company/MeasureImpactController.php`, `Company/MeasureStatisticsController.php`. `EmployeeController::dashboard` extended with weekly aggregates + demo provider blocks + lever resolution. Nine new routes in `routes/api.php`.
Assessment: the demo endpoints document the needed capabilities and expected authorization behavior. Production endpoints are implemented new on the health domain against the final OpenAPI contract; demo response formats are not binding.

### Laravel models
Observation: new `App\Models\LabMarker` (direct `belongsTo(User)`, `user_id` fillable); `User::labMarkers()` hasMany added. `SystemExercise`/`SystemMeasureTemplate` extended (catalog fields).
Assessment: **conflicts with the target identity/health separation** — classified demo-only. The production lab value model is a new design in the health domain (`health_subject_id`).

### Laravel migrations
- `2026_07_05_010000_create_lab_markers_table.php`: `foreignId('user_id')->constrained()->cascadeOnDelete()`, `unique(['user_id','marker_key'])` (one value per marker, no history), German status strings with CHECK constraint. **Demo-only; documents the production blocker and the history limitation the production schema must avoid.**
- `2026_07_05_000000_migrate_wellbeing_entries_to_scale_1_5.php`: data migration 1–10 → 1–5. Reference for the mapping semantics; production migration is independently reviewed before adoption.
- `2026_07_04_000000_add_catalog_fields_to_system_measure_tables.php`: measures catalog fields. Non-health.

### Laravel seeders
Observation: `LabValueDemoSeeder.php` seeds per-employee lab profiles for `employee1@demo.de`–`employee6@demo.de`, with explicit privacy comment (employee-facing only). `DemoDataSeeder` extended.
Assessment: demo-only; values are synthetic but structurally identical to real health data. Demo seeds stay in the demo path; production fixtures are defined independently.

### Laravel services
Observation: insights provider architecture — contracts + `Demo/*` (JSON-backed, seeded variance) + `Prod/Null*` + `Db/DbMeasureStatisticsProvider` (anonymity threshold via `AnonymityService`). `FeatureFlagService` derives concept-module flags from `config('elyo.data_mode')`. Also `EmployeeDashboardService` (real weekly wellbeing aggregates), `LabMarkerRegistry` (hardcoded marker metadata incl. reference ranges), `MeasureExecutionService`, `CompanyMeasureAccessService`.
Assessment: the demo/prod isolation pattern is the strongest architectural asset of the branch and is kept as the gating mechanism. `EmployeeDashboardService`'s aggregate logic serves as a functional specification reference. The hardcoded `LabMarkerRegistry` is a content reference only — production marker metadata is modeled in the backend content/data model.

### OpenAPI
Observation: `docs/api/openapi.yaml` +910 lines covering the 9 new paths plus schemas.
Assessment: a draft/reference input. The final production contract is authored API-first from the domain model and privacy decisions (ELYO-114); no field is adopted merely because the demo delivers it. Deviations from the draft are documented.

### Tests
Observation: new `EmployeeLabMarkersTest`, `EmployeeDashboardTest`, `InsightsEndpointsTest`, `MeasureImpactAccessTest` (**3 failures, see Bug ELYO-147**), `MeasureStatisticsTest`, `MeasureExecutionTest`, `EmployeeAssignedMeasuresTest`, `DemoDataSeederTest`; several existing suites extended.
Assessment: the demo tests illustrate the expected privacy rules (company/admin denied) and authorization cases. Production tests are derived from production acceptance criteria and the final contract; they must not conserve demo behavior or demo response formats.

### Docs
12 new `docs/ai-tasks/*.md` handoff docs (lab values dashboard, lab marker explanations, safe-mode gating, sticky sidebars, screening MVP, badges, typography guideline, employer dashboard fixes). Documentation of demo intent; useful scope reference. Referenced `docs/ai-context/health-data-guardrails.md`.

### Styling / shared components
Observation: shell components reworked (sticky sidebars, new nav entries "Laborwerte", "Badges"), typography guideline, real ELYO logo asset, `elyo-logo.png` at repo root (stray file), auth layout polish.
Assessment: UI-chrome reference; typography guideline is directly usable as a design input for the production UI.

### Demo/mock data
Observation: `database/demo/*.json` (7 files), frontend hardcoded arrays in `lab-markers.component.ts` (`FOCUS_ROUTINES`, `MEASURE_CARDS`), `lab-marker-catalog.ts` explanations, badge definitions, localStorage check-in persistence.
Assessment: all demo-only. Risk: demo constructs (especially the hardcoded recommendation cards) could be mistaken for product logic. Exclusion from the production import graph is an explicit acceptance criterion (ELYO-128, ELYO-130, ELYO-134).

### Privacy / health-data relevant
Demo `lab_markers` table + model + controller + seeder; check-in `note` freetext (`CheckinRequest`, max 2000 chars); localStorage check-in data incl. symptoms/illness; `employee-dashboard.json` body signals/health flag; infection radar aggregates; document upload endpoint (pre-existing in `main`). See dedicated review below.

## Feature Gap Overview

| Feature | Main State | Demo State | Gap | Privacy Risk | Suggested Epic | Suggested Sprint | Dependencies |
|---|---|---|---|---|---|---|---|
| Employee lab values dashboard | absent | full-stack demo on `user_id` model; UX validated | clean production build: new health-domain model, final contract, new UI (demo = UX reference) | **Blocker** (demo data model; excluded from production) | ELYO-91/92/93 | 2–4 | health domain before real data |
| Lab marker explanations (popover/modal) | absent | hardcoded frontend catalog, non-diagnostic wording | new versioned backend catalog + approval process; new accessible explanation component; nothing hardcoded | Medium | ELYO-94 | 4 | final contract (ELYO-92/114) |
| Lab-linked routine/measure cards | absent | hardcoded `FOCUS_ROUTINES`/`MEASURE_CARDS` arrays (scope illustration only) | exclude from production path; rule-based linkage would be a new health-domain design (Later) | High (implied advice) | ELYO-95 | 4 / Later | health domain, wording review |
| Employee dashboard blocks (sleep, body signals, health flag, Schonmodus) | basic dashboard | real wellbeing aggregates + demo-JSON blocks, null-safe in prod | per-block production decision; production payload built new against final contract; no demo-provider dependency | High if built naively | ELYO-96 | 3–4 | check-in build, privacy review |
| Wellbeing scale 1–5 | 1–10 scale | 1–5 + data migration + validation (reference) | production introduction via independently reviewed migration; contract consistency | Low | ELYO-96 | 3 | none |
| Adaptive check-in (stepper + chat) | simple check-in | localStorage-only demo run (symptoms, illness, sleep) | new production check-in built API-first on the health domain; demo storage excluded | High (health data in localStorage) | ELYO-96 | 3 / Later | ELYO-91 health domain |
| Check-in history timeline | simple history | rich timeline blending real + demo data (UX reference) | new production timeline feature on final contracts; no demo blending in production code | Medium | ELYO-96 | 4 | check-in persistence |
| Badges / gamification | absent | frontend demo service, incl. LAB badge category | product decision pending; any production version is a new backend-modeled feature | Medium (LAB badge leaks marker semantics) | ELYO-96 | Later | needs-decision |
| Employee measures hub (detail, exercises, pictograms) | list only | near-product demo implementation | verify against final contract, authorization matrix, tests; route semantics decided cleanly | Low–Medium | ELYO-95 | 3–4 | final contract |
| Company insights suite | reports basic | demo providers + flags; prod = null / threshold-guarded stats | scope decision; independent aggregation/suppression specification; infection radar blocked; demo aggregations excluded | High (re-identification if built naively) | ELYO-97 | 1 (decision), 3 (verification), Later (radar) | privacy review |
| Data-mode provider architecture (`ELYO_DATA_MODE`) | absent | demo/prod bindings, feature flags in auth payload | keep as gating mechanism; document | Low | ELYO-90 | 1 | none |
| OpenAPI additions (9 paths) | absent | drafted (reference input) | final contract authored API-first; validation tooling | Low | ELYO-92 | 3 | contract decisions |

## Detailed Feature Analysis

Each subsection separates demo observation, reference value, production target, and exclusions.

### 1. Employee Lab Values Dashboard
- **Demo observation:** Employee-only page "Laborwerte & Marker" (`/employee/lab-markers`): highlighted (abnormal) markers first, grouped cards (Blutbild, Immun-/Entzündungssignale, Mikronährstoffe), value + soft status vs. orientation range, privacy banner ("Deine Laborwerte sind nur für dich sichtbar…"), explanation popovers, plus static routine/measure suggestion cards. Backend: `LabMarkerController` returns only `request->user()->labMarkers()` with metadata from the hardcoded `LabMarkerRegistry`; demo-grade data model (direct `user_id`, single value per marker, no measurement-date semantics); seed-only population; passing demo privacy tests (company/admin get 403/404).
- **Portal:** Employee. **Main state:** does not exist.
- **Reference value:** validated user journey, grouping/highlight hierarchy, privacy banner placement, soft status labels, explanation interaction. The field set (name, unit, range, group, status) is a scope reference for the production contract.
- **Production target:** new lab value model in the health domain (`health_subject_id`, history-capable, provenance decision — ELYO-105/113); final OpenAPI contract authored API-first (ELYO-114); new production endpoint with resources and defined empty/error states (ELYO-112/115/118); new Employee UI built against that contract with typed view models and a dedicated data-access layer (ELYO-119/124); backend content catalog for explanations (ELYO-94).
- **Exclusions:** the demo table, demo model, demo controller, demo seeds, demo response format, hardcoded registry and hardcoded UI content are not part of the production path.
- **Privacy impact:** Blocker on the demo data model; route-level access control alone is insufficient — the production architecture must make identity↔health joins technically impossible.
- **Jira:** Epics ELYO-91 (health domain), ELYO-92 (contract/backend), ELYO-93 (UI); Feature ELYO-119. **Sprint order:** 2 → 3 → 4. **Blockers:** ELYO-104/105 before any real-data persistence.

### 2. Lab Marker Explanations
- **Demo observation:** hover popover per marker ("Was beschreibt …?", context note, safety note "Diese Erklärung ersetzt keine ärztliche Einordnung."), content hardcoded in `lab-marker-catalog.ts`; the demo's own TODO names the target direction (versioned backend catalog with fachliche Freigabe).
- **Reference value:** information structure (title, short explanation, context note, safety note) and careful non-diagnostic tone; the 12 texts are candidate content for editorial review.
- **Production target:** new versioned backend content model with source metadata and approval workflow (ELYO-125–127); new accessible explanation component with defined desktop (click, optional hover), touch/mobile (modal/sheet) and keyboard behavior (ELYO-121); production frontend consumes the backend catalog exclusively (ELYO-128).
- **Exclusions:** no explanation content hardcoded in the production frontend; the demo catalog file is demo-only.
- **Privacy:** content itself is not personal data; risk is wording drifting into medical interpretation. Medium.
- **Jira:** Epic ELYO-94, Sprint 4 (content model design can start Sprint 3).

### 3. Lab-linked Routine/Measure Cards
- **Demo observation:** static "Fokus-Routinen" and measure suggestion cards below lab values, thematically implying marker→measure linkage (e.g. Vitamin-D-Routine) — hardcoded arrays, no logic, no personalization.
- **Reference value:** illustrates the product idea (preventive routines near lab values); nothing more.
- **Production target:** exclude the static cards from the production path (ELYO-130). If the linkage is commissioned, design it from scratch as a rule-based, preventive, non-diagnostic feature in the health domain (ELYO-129, Later) — recommendations derived from marker status are derived health data.
- **Exclusions:** no hardcoded pseudo-recommendations in production; no text suggesting logic that does not exist.
- **Privacy:** High if built naively (recommendation implies marker status).
- **Jira:** Epic ELYO-95; ELYO-129 (Later), ELYO-130 (Sprint 4).

### 4. Employee Dashboard Extension & Schonmodus
- **Demo observation:** metric tiles (mood/energy/stress, week-over-week), wellbeing sparkline, sleep block, body signals, health flag, "Schonmodus" banner gating levers, levers resolved to the user's own assigned measures. Wellbeing aggregates are computed from real 1–5 entries (`EmployeeDashboardService`); sleep/bodySignals/healthFlag/levers come from company-level demo JSON with seeded variance and are null in prod (UI null-safe).
- **Reference value:** block layout and priorities; `EmployeeDashboardService` aggregate logic as functional specification; `resolveLevers` as a scope reference for "only the user's own measures".
- **Production target:** per-block decision (production source from the health domain / drop / explicitly demo-only), production dashboard payload defined in the final contract and implemented new (ELYO-117); Schonmodus rules defined from scratch as a documented, preventive rule set (ELYO-136).
- **Exclusions:** no production dependency on `DemoEmployeeDashboardProvider` or demo JSON.
- **Privacy:** health flag and body signals are individual health data once real — health-domain only. Demo JSON is company-keyed and synthetic: acceptable for the demo environment.
- **Jira:** Epic ELYO-96; Stories ELYO-117 (Sprint 3), ELYO-136 (Sprint 4).

### 5. Wellbeing Scale 1→5
- **Demo observation:** canonical 1–5 with data migration for existing entries, `CheckinRequest` min:1 max:5, factory updated. **Main:** 1–10 validation.
- **Production target:** introduce the 1–5 scale in production via an independently reviewed migration (mapping semantics documented, verified on production-like data); contract documents the scale; aggregates verified (ELYO-135). The demo migration is the reference for the mapping semantics, adopted only after review.
- **Privacy:** Low (existing data category).

### 6. Adaptive Check-in (Stepper + Chat)
- **Demo observation:** two variants (stepper `2a`, chat `2c`); the entire run stored only in browser localStorage (`elyo.demo.checkin.<date>`), including location, mood/energy/stress, sleep, symptoms with pain regions/severity, illness types — deliberately without API writes. **Main:** single simple check-in with API write.
- **Reference value:** flow, question logic and adaptive steps as UX reference.
- **Production target:** new production check-in built API-first (ELYO-133): final contract separates immediately persistable fields (mood/energy/stress 1–5, location, sleep per decision) from hardening-gated fields (symptoms, illness — only after ELYO-91); new UI implementation; freetext minimized per ELYO-109.
- **Exclusions:** `CheckinDemoStorageService` and localStorage persistence are demo-only and excluded from the production import graph (ELYO-134); no silent localStorage fallback in prod mode.
- **Privacy:** High. Structured symptom/illness capture also needs a product decision against the "no medical interpretation" boundary.
- **Jira:** ELYO-133 (Sprint 3), ELYO-134 (Sprint 4), ELYO-109 (Sprint 2).

### 7. Check-in History Timeline
- **Demo observation:** rich timeline (+585 lines) blending API history with local demo check-ins. **Main:** simple history.
- **Production target:** new production timeline feature built independently on the final backend contracts (ELYO-138): API data only, empty state for new users, defined error/loading states.
- **Exclusions:** demo blending stays in the demo path; no demo-storage imports in production code.
- **Privacy:** Medium (renders health details client-side; source data follows the health domain).

### 8. Badges / Gamification
- **Demo observation:** frontend-only `EmployeeBadgesDemoService`, badge definitions incl. category `LAB` ("Labor") — badge award semantics could reveal marker status. Untracked spec files in the working tree fail against branch code (mock lacks `getLabMarkers`) — working-tree issue, not a branch defect.
- **Production target:** product decision first (ELYO-137). Any production badge feature would be designed new with its own backend model and a privacy check that no badge encodes health status. Until then: demo-only, flag-gated.
- **Privacy:** Medium. **Jira:** ELYO-137, Later, needs-decision.

### 9. Employee Measures Hub
- **Demo observation:** assigned system measures (`UserSystemMeasureController` index/show), measure detail with steps/pictograms, exercise player with countdown/auto-advance, `MeasureExecutionService`, catalog fields migration, 60+ pictogram SVGs, admin editing. Route semantics changed: `/employee/measures` now = personal system measures; company measures moved to `/employee/company-measures`. Authorization tests present and passing.
- **Reference value:** the closest-to-product part of the branch; behavior and field set are a strong specification reference.
- **Production target:** confirm the capability in the final OpenAPI contract, decide the route semantics deliberately (breaking-change assessment vs. `main`), validate the authorization matrix with production tests (ELYO-102/114/116/132). Adoption of concrete code goes through the normal review path — the contract, not the demo, is the source of truth.
- **Privacy:** Low–Medium (exercise completion is health-adjacent activity data; falls under the target health model long-term).

### 10. Company Insights Suite
- **Demo observation:** executive summary, risk landscape, usage funnel, infection radar (respiratory warning logic per handoff docs), measure impact dialog, measure statistics page. Architecture: contract interfaces; demo providers read seeded JSON with per-company variance; prod bindings are Null providers except `DbMeasureStatisticsProvider`, which applies `anonymity_threshold` with a suppression signal (`isAboveThreshold`). Feature flags off in prod; Angular routes guarded by `featureFlagGuard`.
- **Reference value:** the demo/prod gating mechanism itself; the anonymity threshold as a starting input for the suppression specification; the module ideas as concept demonstrations for the Sprint-1 scope decision.
- **Production target:** Sprint-1 scope decision per module (ELYO-103). For any module approved for production: an independently authored aggregation/suppression specification (metrics, minimum group sizes, suppression display) against which the implementation is verified (ELYO-140). Demo/prod isolation verified systematically (ELYO-141).
- **Exclusions:** demo aggregations and demo JSON are not carried into production. Infection radar = health-adjacent, near predictive-absence territory — out of MVP, formally blocked (ELYO-139); a later revival would be a new design.
- **Privacy:** currently Low (demo-gated); High if flags were enabled with real data without a suppression specification.

### 11. Bug: MeasureImpactAccessTest failures
`php artisan test --filter=MeasureImpactAccessTest`: 3 of its tests fail ("Failed asserting that null is identical to 1") because the test asserts demo-provider payloads without setting `config(['elyo.data_mode' => 'demo'])`, while `phpunit.xml:23` forces `ELYO_DATA_MODE=prod` → `NullMeasureImpactProvider` returns null. Either the test must set demo mode (as `InsightsEndpointsTest` does) or the endpoint should return an explicit empty state the test asserts. A demo-branch hygiene fix, independent of the production build. Jira: ELYO-147.

## Health Data Separation Review

Technical risk classification only; no legal advice. Findings describe the demo branch; the "production consequence" per finding states what the clean build must guarantee.

1. **Tables/models handling health-related data (demo):** `lab_markers` / `App\Models\LabMarker` (new, demo-only); `wellbeing_entries` (existing, extended usage: mood/energy/stress/score 1–5); `user_documents` upload (pre-existing in `main`, unchanged); anamnesis profile (pre-existing); demo JSON `database/demo/employee-dashboard.json` (sleep, body signals, health flag — company-keyed, synthetic); frontend localStorage check-ins (symptoms, illness — browser only); badge definitions referencing lab category (frontend only).
2. **Directly linked to `user_id`?** Yes in the demo. `lab_markers.user_id` FK with cascade delete; `User::labMarkers()` relation; `wellbeing_entries.user_id` (pre-existing pattern). **Blocker** — production consequence: the production lab value model is designed new on `health_subject_id`; the demo schema is not continued.
3. **Indirectly linkable?** Yes: `company_id` scoping on wellbeing entries, timestamps, unique `(user_id, marker_key)`; small cohorts would be trivially re-identifiable if data were real. **High** — production consequence: mapping domain plus aggregate-only reporting with minimum group sizes.
4. **Pseudonymization layer?** None in demo or `main`. **Blocker** (production) — built new in ELYO-104.
5. **Separate `health_subject_id`?** No, nothing equivalent. **Blocker** (production) — introduced in ELYO-104.
6. **Health entries separated from identity data?** No — same schema, same DB, direct FKs, no service boundary. **Blocker** (production) — the target architecture (ELYO-100) defines schema/role separation and a mapping service boundary.
7. **Company/admin technically prevented from raw health access?** Only at route level in the demo: employee routes require employee portal auth; `EmployeeLabMarkersTest` proves company/admin receive 403/404 (test run, passing). No schema-level, policy-level or service-boundary prevention — any future company controller could query the demo `LabMarker` directly. **High** — production consequence: DB-role/schema separation and static architecture rules (ELYO-106) make such access technically impossible, verified by the privacy regression suite.
8. **Only aggregates exposed to company roles?** Yes in current demo code: insights endpoints serve synthetic demo data (demo mode) or null/threshold-guarded aggregates (prod). No endpoint returns individual health entries to company roles. **Low** (current) — production consequence: guaranteed permanently by the privacy regression suite (ELYO-111/144).
9. **Privacy threshold / suppression?** Present for measure statistics (`anonymity_threshold`, `AnonymityService::DEFAULT_THRESHOLD`, `isAboveThreshold`). Not defined for risk landscape/usage funnel/infection radar (demo-only, Null in prod). **Medium** — production consequence: an independently authored suppression specification (ELYO-140) is a precondition for any production company reporting; the existing provider is verified against it, not adopted blindly.
10. **Freetext fields that can contain health data?** Yes: check-in `note` (nullable, max 2000, `CheckinRequest`) — can carry arbitrary health statements against `user_id`. localStorage check-in also holds structured symptom data client-side. **High** — production consequence: the production check-in contract contains no unminimized freetext (ELYO-109).
11. **Documents/lab values stored with identifying medical metadata?** Demo lab markers store clinical marker keys/names (Hämoglobin, CRP, Ferritin…) — the row itself identifies medical content and is user-linked (see #2). No new document storage in this branch; pre-existing `POST /employee/documents` upload in `main` remains a separate review item. **High** (inherits #2 blocker in the demo; resolved by the new health-domain model).
12. **Acceptable for demo only:** seeded lab values for `employee*@demo.de`; demo insight JSON + variance; localStorage check-in (with the shared-device caveat); badges; hardcoded routine/measure cards; infection radar/risk landscape/usage funnel behind flags; `ELYO_DATA_MODE=demo` deployments without real users.
13. **Blockers before production/pilot with real data:** (a) any lab value persistence on `user_id` — resolved only by the new health-domain model; (b) absence of `health_subject_id` + protected mapping + audit; (c) no retention/deletion concept for health entries; (d) check-in freetext unminimized; (e) no service boundary preventing identity↔health joins in normal app code; (f) suppression rules undefined for any company-facing aggregate beyond measure statistics. Route-level guards and ID replacement alone are NOT sufficient — the mapping must not be trivially joinable by normal application code.

## Production Readiness Assessment

"Reference quality" rates how useful the demo is as a specification/UX input. No demo artifact is production code.

| Area | Demo Observation | Reference Quality | Production Path |
|---|---|---|---|
| Lab values backend | works against demo-grade `user_id` model, seed-only | good scope reference (fields, authorization cases) | new health-domain model + new endpoint against final contract; **blocked for real data until ELYO-91** |
| Lab values UI | polished UX, states partially covered, wording largely compliant | strong UX reference | new API-first implementation with typed view models (ELYO-119/124) |
| Marker explanations | frontend-hardcoded, good tone | good content candidate material | backend content model + approval process + new accessible component; nothing hardcoded |
| Employee dashboard | real aggregates + demo-JSON blocks, null-safe | aggregate logic = functional spec; blocks = UX reference | per-block production decision; new payload against final contract |
| Check-in stepper/chat | demo-only by design (localStorage) | strong flow/UX reference | new production check-in on the health domain; demo storage excluded |
| Measures hub | near-product, tested | strong specification reference | contract confirmation, deliberate route decision, production authorization tests |
| Company insights | exemplary demo/prod isolation; flags off in prod | gating mechanism reusable as concept; modules are concept demos | independent aggregation/suppression specification; infection radar blocked |
| OpenAPI | drafted (+910 lines) | reference input | final contract authored API-first (ELYO-114) + validation tooling |
| Tests | backend 234 passed / 1 skipped / 3 failed (MeasureImpactAccessTest); Angular 105 passed (branch code; 3 failures only from untracked working-tree spec) | illustrates expected privacy/authorization rules | production test base derived from acceptance criteria and final contracts; fix ELYO-147; privacy regression suite |

## Recommended Jira Breakdown

9 Epics, 1 Feature, 13 Stories, 34 Tasks, 1 Bug — 58 issues, ELYO-90…ELYO-147. Full definitions in `docs/ai-reviews/demo-employee-lab-values-dashboard-jira-import.csv`. Relevant UI/feature tickets carry the architecture principle (demo = reference only) and acceptance criteria excluding demo dependencies.

- **ELYO-90 Epic — Privacy Foundation & Produktionsentscheidungen** (Sprint 1): scope decision per feature incl. binding demo-reference role (ELYO-99), privacy target architecture (ELYO-100), DSFA pre-check (ELYO-101), API-first contract decisions (ELYO-102), company insights scope decision (ELYO-103).
- **ELYO-91 Epic — Health Data Model Hardening** (Sprint 2, Höchste, production-blocker): `health_subject_id` + mapping domain (ELYO-104), new production lab value model in the health domain (ELYO-105), mapping access protection/service boundary (ELYO-106), audit logging (ELYO-107), retention (ELYO-108), freetext minimization (ELYO-109), wellbeing model assessment (ELYO-110), privacy regression suite skeleton (ELYO-111).
- **ELYO-92 Epic — API Contract & Backend Integration** (Sprint 3): new production lab-markers API, API-first (ELYO-112), history anchored in the model/contract from the start (ELYO-113), final OpenAPI contract (ELYO-114), production response resources (ELYO-115), authorization matrix (ELYO-116), production dashboard payload (ELYO-117), contract-defined error/empty states (ELYO-118).
- **ELYO-93 Epic — Demo Lab Values Dashboard Gap / Neuimplementierung Employee UI** (Sprint 4): Feature ELYO-119 (new production lab values dashboard), new responsive card/grid layout (ELYO-120), new accessible explanation component (ELYO-121), production UI states (ELYO-122), non-diagnostic wording review (ELYO-123), new typed data-access layer (ELYO-124).
- **ELYO-94 Epic — Knowledge Base / Laborwert-Erklärungen** (Sprint 4): versioned backend catalog, new build (ELYO-125), source metadata (ELYO-126), editorial approval process (ELYO-127), production display exclusively from backend catalog (ELYO-128).
- **ELYO-95 Epic — Measures Hub & Empfehlungs-Verknüpfung** (Sprint 4/Later): rule-based marker→recommendation linkage as a new design (ELYO-129, Later), exclusion of demo recommendation surfaces from the production path (ELYO-130), non-diagnostic recommendation copy (ELYO-131), employee-only recommendation context (ELYO-132).
- **ELYO-96 Epic — Employee Dashboard & Check-in Neuimplementierung** (Sprint 3–4): production check-in API-first (ELYO-133), demo-localStorage isolation (ELYO-134), production 1–5 scale introduction (ELYO-135), Schonmodus rule set defined new (ELYO-136), badges demo isolation (ELYO-137, Later), new production history timeline (ELYO-138).
- **ELYO-97 Epic — Company Insights Demo-Suite Absicherung** (Later/decision): infection radar blocked (ELYO-139), independent aggregation/suppression specification + verification (ELYO-140), demo-provider isolation verification (ELYO-141), Bug ELYO-147.
- **ELYO-98 Epic — Testing, QA & Pilot Readiness** (Sprint 5): Laravel tests against production contracts (ELYO-142), Angular tests for the production UI (ELYO-143), privacy regression completion (ELYO-144), OpenAPI validation (ELYO-145), manual QA + pilot checklist (ELYO-146).

## Suggested Sprint Plan & Dependencies

### Sprint 1: Analysis, Privacy Foundation & Contracts
Goal: Decide per feature demo-only vs. clean production build vs. blocked; fix the binding role of the demo as reference; define the privacy target architecture and API-first contract decisions; classify company insights scope; fix the broken demo-branch test.
Included Issues: ELYO-90, ELYO-99, ELYO-100, ELYO-101, ELYO-102, ELYO-103, ELYO-147.
Dependencies / Notes: No prerequisites. Output of ELYO-100/101 gates Sprint 2 design; ELYO-102 gates Sprint 3. ELYO-147 is an immediate, independent demo-branch test fix.

### Sprint 2: Health Data Model Hardening
Goal: Build the health domain new: `health_subject_id` + protected mapping domain, production lab value model, audit/retention/freetext rules, privacy regression suite start. No continuation of the demo schema.
Included Issues: ELYO-91, ELYO-104, ELYO-105, ELYO-106, ELYO-107, ELYO-108, ELYO-109, ELYO-110, ELYO-111.
Dependencies / Notes: Requires ELYO-100 decisions. **Hard prerequisite for any production storage of lab values or check-in health details.** ELYO-105/106 depend on ELYO-104. Privacy regression tests (ELYO-111) start here, completed in Sprint 5.

### Sprint 3: Backend/API Integration
Goal: Final OpenAPI contract authored API-first; new production endpoints on the health domain (lab markers incl. history semantics, dashboard payload, check-in persistence); resources, authorization matrix, contract-defined error/empty states; insights gating and suppression specification verified.
Included Issues: ELYO-92, ELYO-112, ELYO-113, ELYO-114, ELYO-115, ELYO-116, ELYO-117, ELYO-118, ELYO-133, ELYO-135, ELYO-96, ELYO-140, ELYO-141.
Dependencies / Notes: ELYO-112/113 depend on ELYO-104/105. ELYO-114 depends on ELYO-102 and is the single source of truth for Sprint-4 frontend work. ELYO-133 writes no real health data before ELYO-91 completion. Contracts must land before Sprint 4 frontend implementation.

### Sprint 4: Employee UI Productionization (clean build)
Goal: New production Employee UI implemented API-first against the final contract: lab values dashboard, responsive layout, accessible explanation component fed by the backend catalog, production UI states, wording review, typed data-access layer; demo artifacts isolated and excluded from the production import graph; Schonmodus rule set; new history timeline.
Included Issues: ELYO-93, ELYO-119, ELYO-120, ELYO-121, ELYO-122, ELYO-123, ELYO-124, ELYO-94, ELYO-125, ELYO-126, ELYO-127, ELYO-128, ELYO-95, ELYO-130, ELYO-131, ELYO-132, ELYO-134, ELYO-136, ELYO-138.
Dependencies / Notes: Depends on Sprint 3 contracts (ELYO-112/114/118). Demo isolation work (ELYO-134, ELYO-130) may start earlier since it only fences the demo and adds no production persistence.

### Sprint 5: Testing, QA & Pilot Readiness
Goal: Complete production test coverage (contract-based, acceptance-criteria-driven), privacy regression, OpenAPI contract validation, manual QA, pilot-readiness checklist. Tests validate production functionality and demo/production separation — they do not conserve demo behavior.
Included Issues: ELYO-98, ELYO-142, ELYO-143, ELYO-144, ELYO-145, ELYO-146.
Dependencies / Notes: Depends on Sprints 2–4 implementation. ELYO-144 completes the suite started in ELYO-111.

### Later / Blocked
- ELYO-129 (rule-based marker→recommendation linkage): Later — a new design in the health domain; depends on ELYO-91 and ELYO-131; the demo cards contain no adoptable logic.
- ELYO-137 (badges): Later — product decision pending (needs-decision); any production version is a new backend-modeled feature; LAB badge privacy check required first.
- ELYO-139 (infection radar): **Blocked** — health-adjacent/predictive territory, out of MVP scope; stays demo-flag-gated; a revival would be a new design with its own suppression concept, not a continuation of the demo aggregation.
- ELYO-97 (epic): remains open as guard/decision container; only its Sprint-3 verification tasks proceed.

## Open Questions

1. Is any write path for lab values planned (manual entry, document import, occupational-health checkup import)? The demo has read + seed only; the answer drives the history/provenance design in the new model (ELYO-113).
2. Are symptom/illness details in the adaptive check-in inside MVP scope, or does structured illness capture cross the "no medical interpretation" boundary?
3. Should Schonmodus rules be centrally defined (rule catalog) and who approves them?
4. Same-DB-with-schema-separation vs. separate service for the health domain — which target does ops/DSFA prefer (ELYO-100)?
5. Do existing API clients depend on `/employee/measures` returning company measures? The route semantics must be decided deliberately for the production contract (demo changed them to `/employee/company-measures`).
6. Who owns editorial approval (fachliche Freigabe) for marker explanation content (ELYO-127)?
7. Should the stray `elyo-logo.png` at repo root and `prototypes/` working-tree artifacts be cleaned up before any merge activity?

## Suggested Next Steps

1. Import the CSV into Jira (ELYO-90…ELYO-147) and confirm the Feature issue type mapping.
2. Fix ELYO-147 (demo-branch test data-mode) immediately — one-line test change, restores a green suite.
3. Run the Sprint-1 decision workshop: demo/production scope per feature incl. the binding demo-reference role (ELYO-99), privacy target architecture (ELYO-100), insights scope (ELYO-103).
4. Do not merge the demo branch to `main` as-is; keep `ELYO_DATA_MODE=demo` environments strictly separated from any real-user environment. The demo remains a reference installation.
5. Start ELYO-104/105 design (mapping domain + new lab value model) in parallel with the DSFA pre-check.
6. Add CI jobs for OpenAPI validation and the privacy regression suite as soon as they exist (ELYO-111, ELYO-145).

---

### Validation performed (this analysis)

- `git status`, `git branch --show-current`, `git fetch --all --prune`, `git diff --stat|--name-status main..demo/...`, `git log main..demo/...`.
- `php -l` on all changed PHP files under `apps/api-laravel` — no syntax errors.
- `php artisan test` for changed backend areas: `EmployeeLabMarkersTest` (2 passed), `EmployeeDashboardTest|InsightsEndpointsTest|MeasureImpactAccessTest|DemoDataSeederTest` (20 passed, **3 failed** in `MeasureImpactAccessTest` — see ELYO-147), `EmployeeAssignedMeasuresTest|MeasureExecutionTest|MeasureStatisticsTest|EmployeeTest|CompanyTest|TenantScopeTest|AdminSystemExercise*|AdminSystemMeasureTemplate*` (234 passed, 1 skipped).
- Angular: `ng test --watch=false` — 105 passed, 3 failed; all 3 failures come from `dashboard-badges.component.spec.ts`, an **untracked working-tree file not part of the branch** (its EmployeeService mock lacks `getLabMarkers`). Branch-committed specs all pass.
- OpenAPI validation: no validator tooling found in the repo; not run (reported, not installed blindly).
- Generated CSV validated by parsing (see CSV validation section in final report).
