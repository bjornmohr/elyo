# Task: Stabilize Insights Foundation before further frontend/dashboard work

## Context

We are working on the ELYO platform, a Laravel + Angular MVP for a BGM/health platform.

A first frontend round with demo data has been implemented for company insights/dashboard modules. A code review identified several architectural and standards issues. Before continuing with new insight modules or more UI work, the foundation must be corrected.

The goal of this task is **not** to add new product features. The goal is to harden the existing implementation so that demo mode, prod mode, access control, OpenAPI contracts and future DB providers remain consistent.

Current repo areas involved:

- `apps/api-laravel`
- `apps/web-angular`
- `docs/api/openapi.yaml`
- `docs/ai-handoff/prd-insights-produktivierung.md`
- demo JSON files under `apps/api-laravel/database/demo`
- existing company measure and insights controllers/services/resources/tests

Follow existing project standards from `AGENTS.md`, especially:

- preserve company/team/user scoping
- keep business logic in Laravel, not Angular
- use API Resources for API responses
- keep patches small and reviewable
- update OpenAPI when API contracts change
- add/adjust feature tests for behavior changes

---

## Main problems to fix

### 1. Centralize company measure access/scoping

There is currently duplicated measure visibility logic in multiple places, for example:

- `MeasureController::index`
- `MeasureController::findCompanyReadableMeasure`
- `MeasureController::findCompanyManageableMeasure`
- `DbMeasureStatisticsProvider::scopedMeasures`

This duplication already caused a real bug:

`MeasureImpactController::show()` fetches a measure only by `id` and `company_id`. This bypasses team/manager scoping. A `COMPANY_MANAGER` can fetch impact data for a measure of another team inside the same company. Also, team measures can be exposed even when the team layer is disabled.

Create a dedicated backend service for company measure access, for example:

```php
CompanyMeasureAccessService
```

Expected responsibilities:

```php
readableMeasureFor(User $user, string|int $measureId): Measure
manageableMeasureFor(User $user, string|int $measureId): Measure
readableMeasureQueryFor(User $user): Builder
manageableMeasureQueryFor(User $user): Builder
```

Use existing behavior as the source of truth from `MeasureController::findCompanyReadableMeasure()` and `findCompanyManageableMeasure()`.

The service must preserve the current rules:

- Company admins can access all measures in their company.
- Company managers can access company-wide measures.
- Company managers can access team measures only for their own team.
- Company managers must not access other teams’ measures.
- Team-scoped measures must not be readable/manageable when the team layer is disabled.
- Cross-company access must remain impossible.
- Behavior should stay consistent with existing route guards and policies.
- Prefer returning 404 for out-of-scope measure lookup if that is the existing convention.

Refactor the existing controller/provider code to use this service instead of duplicating visibility rules.

At minimum update:

- `MeasureController`
- `MeasureImpactController`
- `DbMeasureStatisticsProvider`
- any other newly introduced insights/statistics code that queries measures with company/team visibility assumptions

---

### 2. Add feature tests for manager/team scoping

Add or extend feature tests so the scoping bug cannot return.

At minimum cover `MeasureImpactController`:

- company admin can access impact for any measure in own company
- company manager can access impact for a company-wide measure
- company manager can access impact for a measure assigned to their own team
- company manager cannot access impact for a measure assigned to another team in the same company
- company manager cannot access team-scoped impact data when team layer is disabled
- cross-company access stays blocked

Also add/adjust tests for any refactored measure listing/statistics behavior if the new shared access service changes queries.

The tests should validate behavior, not private implementation details.

---

### 3. Move measure execution status derivation fully to backend

The frontend currently re-implements measure execution status derivation logic, apparently in something like:

```ts
company-measures.component.ts
derivedStatus()
```

This duplicates backend logic from:

```php
MeasureExecutionService::deriveStatus()
```

This violates the project rule: business logic belongs in Laravel, not Angular.

Fix this by exposing the derived execution status from the backend wherever the frontend needs it.

Preferred solution:

- Add a field like `derivedStatus`, `executionStatus`, or similarly named field to the relevant measure API response/resource.
- Use the backend `MeasureExecutionService` as the single source of truth.
- Remove duplicated status derivation from Angular.
- Angular should only display the value returned by the API.
- Avoid adding N+1 frontend API calls just to compute chip status.

Update OpenAPI and frontend models/types accordingly.

---

### 4. Introduce API Resources for new insights responses

Several new controllers currently return raw arrays via:

```php
response()->json(['data' => ...])
```

This violates the backend standard requiring Resources for API responses.

Introduce API Resources for the new insight/dashboard responses.

Candidates include:

- `MeasureImpactResource`
- `MeasureFieldStatisticsResource`
- `RiskLandscapeResource`
- `UsageFunnelResource`
- `InfectionRadarResource`
- `DashboardExecutiveSummaryResource`
- possibly nested resources for trend points, field rows, recommendations, funnel stages, locations, etc.

Do not over-engineer, but ensure:

- response shapes are explicit
- naming is consistent with OpenAPI
- demo and prod/null providers pass through the same response contract
- frontend does not need special cases for demo vs prod
- `CompanyController::dashboard()` does not return an untyped/bare `executiveSummary` array if that response is meant to be stable

Update OpenAPI schemas if field names or shapes change.

---

### 5. Resolve risk-field taxonomy drift

There is currently a serious mismatch between canonical backend risk fields, OpenAPI, PRD and demo data.

Examples:

Backend canonical fields currently appear to be:

```text
SLEEP
BACK
STRESS_MENTAL
MOVEMENT
NUTRITION
KNOWLEDGE
```

But demo JSONs use split/additional fields such as:

```text
STRESS
MENTAL_LOAD
ENERGY
```

This breaks demo/prod parity and will cause AP2/productivization problems.

Pick one canonical taxonomy and apply it consistently across:

- backend `RiskFields`
- OpenAPI enums
- demo JSON files
- measure/category mapping
- statistics providers
- frontend labels/maps
- PRD/productivization documentation

Preferred MVP direction unless there is a strong reason otherwise:

```text
SLEEP
BACK
STRESS_MENTAL
MOVEMENT
NUTRITION
KNOWLEDGE
```

If using this 6-field model:

- merge demo `STRESS` and `MENTAL_LOAD` into `STRESS_MENTAL`
- remove or explicitly map `ENERGY`
- ensure linked measures and recommendations still make sense
- update demo trend/history values accordingly
- ensure OpenAPI uses enums consistently, including `RiskField.field`
- remove free-string field definitions where a canonical enum should exist

If choosing a different taxonomy, document the decision clearly and update all affected code/spec/demo/frontend locations.

---

### 6. Clarify “resonance” vs participation rate

The PRD says:

```text
Statistiken (4b): Anzahl + Ø Resonanz je Risikofeld
```

The current DB statistics provider appears to compute:

```text
avgParticipationRate
```

That is not the same as resonance/feedback.

Decide one of the following:

Option A, preferred if no feedback data exists yet:

- Rename/spec this as average participation rate.
- Update the PRD wording from “Ø Resonanz” to “Ø Teilnahmequote” or equivalent.
- Keep OpenAPI and frontend naming aligned.

Option B:

- Define what “resonance” actually means.
- Add required data model/source if not present.
- Do not fake resonance from participation rate.

Avoid selling participation as feedback/resonance. It creates product confusion and later rework.

---

### 7. Add server-side feature gating for insights endpoints

Currently feature flags seem to be returned to the frontend, and prod mode uses null providers. However, the insights endpoints themselves do not appear to enforce feature flags server-side.

Add server-side gating for insight modules.

At minimum, protect:

- risk landscape endpoint
- usage funnel endpoint
- infection radar endpoint
- measure impact endpoint
- measure statistics endpoint if applicable
- executive summary if it contains gated module data

The solution can be middleware or controller/service-level checks, but should be consistent.

Expected behavior:

- if a feature/module is disabled for the company, the endpoint must not return module data
- use a consistent response status, preferably `403` or `404`, based on existing project conventions
- frontend flags are UX only, not security
- prod/null providers are not a replacement for access control

This should also prepare for future AP0 per-company module columns like:

```text
risk_landscape_enabled
usage_funnel_enabled
infection_radar_enabled
measure_impact_enabled
```

If AP0 is not implemented yet, keep the gating compatible with the current `FeatureFlagService`, but do not hardcode assumptions that make per-company flags difficult later.

---

### 8. Review demo provider threshold behavior

Check whether demo providers respect company anonymity thresholds consistently.

For example, measure impact demo data should not hardcode a minimum group size of 5 if the company threshold can be higher.

Expected:

- participant and control group threshold logic should be compatible with `company.anonymity_threshold`
- demo mode should not teach the frontend semantics that differ from production behavior
- OpenAPI descriptions and actual provider output should agree

---

### 9. Tighten interfaces/PHPDoc/contracts

Some provider interfaces appear to have outdated or too-generic PHPDoc.

Example pattern:

```php
@return array<int, array<string, mixed>>
```

for something that actually returns:

```php
[
  'fields' => [...],
  'recommendations' => [...]
]
```

Tighten provider contracts where reasonable.

Do not turn this into a massive DTO rewrite unless it is clearly worth it, but avoid misleading contracts.

At minimum:

- make PHPDoc match actual returned shapes
- keep OpenAPI and resource shapes aligned
- avoid ambiguous “array of mixed” for stable API-level contracts

---

## Important constraints

- Do not introduce new product features unless strictly required for the fixes above.
- Do not implement AP1/AP2/AP3/AP5 productivization yet.
- Do not build the infection radar further in this task.
- Do not invent health data semantics.
- Keep patches small enough to review.
- Prefer clean backend architecture over frontend workarounds.
- Do not silently change API response shapes without updating OpenAPI and frontend types.
- Preserve existing behavior unless it is clearly a bug or a documented standards violation.

---

## Suggested implementation order

Use separate commits or at least clearly separated patches:

### Patch 1: Central measure access service + scoping tests

- Add `CompanyMeasureAccessService`
- Refactor existing measure lookup/query code
- Fix `MeasureImpactController`
- Add manager/team-layer tests

### Patch 2: Backend-owned derived status

- Add backend-derived execution status to relevant resource/response
- Update OpenAPI
- Remove Angular duplicate derivation
- Update frontend types/display

### Patch 3: Risk field taxonomy unification

- Decide canonical taxonomy
- Update backend constants/mapping
- Update demo JSONs
- Update OpenAPI enums
- Update frontend labels/maps
- Update PRD notes

### Patch 4: API Resources for insights

- Add resources for new insight responses
- Refactor controllers to return resources
- Update OpenAPI if needed
- Keep demo/prod/null provider contract stable

### Patch 5: Server-side feature gating + threshold cleanup

- Add consistent module gating
- Prepare for per-company flags later
- Fix threshold semantics in demo providers
- Add tests for disabled modules if feasible

### Patch 6: Spec cleanup

- Clarify “resonance” vs participation rate
- Update PRD wording
- Tighten provider PHPDoc/contracts
- Remove inconsistencies in docs

---

## Validation checklist

Run and report results for:

```bash
composer test
php artisan test
php artisan route:list
```

If available in the project workflow, also run the existing frontend checks, for example:

```bash
npm test
npm run build
npm run lint
```

Use the project’s existing scripts if they exist.

Also verify manually/static:

- OpenAPI contains all changed fields
- Angular models match API changes
- no duplicated Angular status derivation remains
- no raw insight response arrays remain where Resources are expected
- manager/team scoping is consistently applied
- demo/prod taxonomy is identical
- feature-gated endpoints cannot be accessed just by direct URL/API call

---

## Expected handoff

Provide a concise handoff with:

1. Summary of changed files
2. Explanation of scoping behavior after the change
3. Chosen risk-field taxonomy and affected places
4. API contract changes
5. Test results
6. Known limitations or intentionally deferred items
7. Any follow-up tasks that should become separate tickets

Do not hide failing tests. If something cannot be validated locally, state why.
