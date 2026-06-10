# Measure Domain Concept v1

Date: 2026-06-10

## 1. Current State

### Technical model

The current measure domain is intentionally small:

- `measures` stores company-owned measures with `company_id`, optional `team_id`, `title`, `category`, `description`, `status`, lifecycle timestamps, and `created_by`.
- `Measure` exposes company, team, and participation relations.
- `measure_participations` stores one participation row per `(measure_id, user_id)` with denormalized `company_id`, optional `team_id`, and `participated_at`.
- `MeasureParticipation` links back to measure, user, company, and team.

The current measure status flow is:

- `SUGGESTED -> ACTIVE`
- `SUGGESTED -> DISMISSED`
- `ACTIVE -> COMPLETED`
- `ACTIVE -> DISMISSED`

Categories are currently hardcoded to:

- `workshop`
- `flexibility`
- `sport`
- `mental`
- `nutrition`

### Existing company APIs

Company measure APIs are under `/api/company/measures`.

- `GET /company/measures` lists measures for the authenticated company context.
- `POST /company/measures` creates a company-wide or team-scoped measure.
- `PATCH /company/measures/{id}` updates status only.
- `GET /company/measures/{id}/participation-summary` returns aggregate participation metrics only.

Scoping behavior:

- Company admins/owners operate inside their single company.
- Team-scoped measures are hidden or blocked when the company team layer is disabled.
- Manager-only users are limited to their managed team when the team layer is enabled.
- Manager-only users are blocked from the company portal when the team layer is disabled.

### Existing employee APIs

Employee measure APIs are under `/api/employee/measures`.

- `GET /employee/measures` returns active company-wide measures and active measures for the employee's current team.
- `POST /employee/measures/{measure}/participate` creates participation for the authenticated employee.

The participation request body is ignored. User, company, team, and participation time are derived server-side.

### Existing frontend flows

The employee Angular flow:

- Lists visible active measures.
- Shows title, description, category, team label, and the employee's own participation state.
- Allows a single "Teilnehmen" self-report action.
- Handles duplicate participation and inactive measure conflicts.

The company Angular flow:

- Lists company measures.
- Lets eligible company users create a measure with title, category, description, optional team, and initial status.
- Loads aggregate participation summaries per measure.
- Shows suppressed text when the anonymity threshold is not met.

### Existing points behavior

`MeasureParticipationService` creates the participation inside a transaction and awards `measure_participation` points immediately.

`PointsService::DEFAULT_POINTS` currently defines:

- `daily_checkin`: 10
- `anamnesis_completed`: 100
- `medical_document_upload`: 25
- `measure_participation`: 20
- `streak_7days`: 50
- `streak_30days`: 200

There is no measure-specific points policy yet. All successful measure participations use the same points action.

### Existing participation summary behavior

`MeasureParticipationSummaryService` returns:

- `measureId`
- `isAboveThreshold`
- `eligibleCount`
- `participantCount`
- `participationRate`
- `suppressionReason`
- `teamBreakdown`

The summary is suppressed unless both eligible employee count and participant count meet the company anonymity threshold. Suppressed responses return null counts and null rate with `ANONYMITY_THRESHOLD_NOT_MET`.

`teamBreakdown` is explicitly always null today and reserved for a future privacy-reviewed feature.

### Existing privacy and scoping constraints

The current implementation preserves the main privacy boundary:

- Employees see only their own participation state.
- Company users see only aggregate participation metrics.
- Company responses do not return participant user IDs, names, emails, raw participation rows, or per-user timestamps.
- Company/manager access is scoped by company and, for managers, managed team context.
- Daily check-in notes are encrypted in `WellbeingEntry` and are not shown to company users.

## 2. Problem Statement

The current model is enough for company-created measures and simple self-reported participation. It is not enough for the planned broader measures domain.

### Measures Hub

A Measures Hub needs to list different kinds of content together: company measures, ELYO templates, individual suggestions, guided sessions, onsite events, challenges, and self-reported habits. The current table has only company/team ownership, text fields, category, status, and lifecycle timestamps. It cannot distinguish source, format, verification needs, capacity, scheduling, or whether a measure is personal versus company-created.

### Individual recommendations

Individual recommendations should be private to the employee and derived from personal signals. The current `measures.company_id` model assumes company-created visibility and company/team scope. It does not have a private recommendation instance, reason codes, ranking, dismissal state, or recommendation source.

### Persona-based targeting

The current model has no target persona, target health path, target issue category, or eligibility metadata. Adding hardcoded persona logic now would be risky because the final questionnaire and persona concept is not defined.

### Guided remote sessions

Guided remote sessions need fields for delivery format, instructions, media/session references, duration, prerequisites, completion behavior, and whether participation is self-attested or system-confirmed. The current model only has title, category, and description.

### Structured issue-based recommendations

Daily Check-in currently records mood, stress, energy, score, and optional note. Free text is not structured enough to power reliable recommendations, and raw notes must remain private. A future recommendation engine needs structured, consent-aware signals such as issue category, body area, intensity, recurrence, and whether the user wants recommendations.

### QR/admin/partner verified participation

The current participation model has a single `participated_at` timestamp and awards points immediately. QR, admin, or partner verification requires additional state: claim time, verification mode, verification status, verified time, verifier actor, and rejection/expiry handling.

### Differentiated points logic

The current `measure_participation` action is global. Future logic needs points to vary by measure type, verification status, company challenge rules, recurrence, caps, and fraud prevention. This should remain backend-owned.

## 3. Target Measure Types

The target domain should support these fachlich relevant types:

- Company-created measure: Created by a company admin/owner/manager for a company or team.
- ELYO template measure: Platform-curated reusable definition that can be activated by companies or recommended to employees.
- Individual recommendation: Private employee-specific recommendation generated from allowed signals.
- Guided remote session: Remote/self-guided content with instructions, duration, and optional media/session flow.
- Self-reported habit/action: Lightweight repeated action the employee can record without an event.
- Onsite/event participation: Scheduled event, workshop, course, screening, or onsite activity with location/capacity and stronger verification needs.
- Company challenge: Time-bound company/team initiative with participation rules and aggregate company reporting.

Recommended representation:

- Keep one `measures` table as the concrete company/employee-visible measure instance table.
- Add an optional `measure_templates` table later for ELYO-curated reusable definitions.
- Add separate participation/verification fields or a `measure_participation_verifications` table when verification history becomes necessary.
- Avoid separate measure tables per type. The shared lifecycle, visibility, participation, points, and reporting behavior should stay consistent and policy-driven.

Conceptual split:

- Template: reusable ELYO-authored definition, not company-specific by default.
- Measure instance: company/team/private-user visible object with lifecycle, schedule, visibility, and points policy.
- Recommendation instance: private employee-specific surface that may point to a template or measure instance and carry reason/ranking/dismissal metadata.
- Participation: user action/claim for a measure instance.
- Verification: state and evidence that a participation claim was confirmed.

## 4. Proposed Domain Fields

### Safe to add now

These fields are stable enough for a low-risk "Measure Domain Fields v1" slice, provided API/OpenAPI/tests are updated together:

- `origin`: `COMPANY`, `ELYO_TEMPLATE`, `ELYO_RECOMMENDATION`
- `measure_type`: `COMPANY_MEASURE`, `GUIDED_REMOTE_SESSION`, `ONSITE_EVENT`, `SELF_REPORTED_ACTION`, `COMPANY_CHALLENGE`
- `delivery_type`: `REMOTE`, `ONSITE`, `HYBRID`, `SELF_GUIDED`
- `execution_type`: `ONE_TIME`, `RECURRING`, `OPEN_ENDED`
- `verification_mode`: `SELF_REPORT`, `QR_CODE`, `ADMIN_CONFIRMATION`, `PARTNER_CONFIRMATION`, `SYSTEM_CONFIRMATION`
- `points_policy`: JSON object or enum-backed policy placeholder, defaulting to today's global `measure_participation` behavior.
- `visibility_scope`: `COMPANY`, `TEAM`, `PRIVATE_USER`
- `starts_at`, `ends_at`: explicit schedule window, separate from lifecycle timestamps.
- `duration_minutes`: nullable integer.
- `instructions`: nullable text, employee-facing.
- `location_name`, `location_address`: nullable strings for onsite/hybrid measures.
- `capacity`: nullable integer.

For participation foundation:

- `participation_status`: `CLAIMED`, `VERIFIED`, `REJECTED`, `CANCELLED`
- `claimed_at`
- `verified_at`
- `verification_mode`
- `verified_by_user_id`
- `verification_source`: `SELF`, `COMPANY_ADMIN`, `PARTNER`, `SYSTEM`

The first implementation slice should use defaults that preserve current behavior:

- Existing measures behave as `origin=COMPANY`.
- Existing measures behave as `measure_type=COMPANY_MEASURE`.
- Existing participation behaves as `verification_mode=SELF_REPORT` and can be treated as verified for current product behavior only if the points policy explicitly says self-report grants points.

### Should wait

These fields depend on unresolved product, questionnaire, or privacy decisions:

- `target_personas`
- `target_health_paths`
- `target_issue_categories`
- `recommendation_reason_codes`
- `recommendation_rank`
- `recommendation_confidence`
- `contraindication_flags`
- `clinical_goal`
- detailed partner marketplace fields
- media/video generation metadata
- AI prompt inputs or generated recommendation explanations

They can be reserved conceptually, but should not drive behavior before the questionnaire/persona concept is final.

### Should not be hardcoded

Do not hardcode:

- Medical/persona rules.
- Health-path scoring.
- Diagnosis or therapy claims.
- Partner-specific verification rules.
- Points amounts per measure type.
- Company-specific anonymity thresholds outside company settings.
- Recommendation explanations derived from raw free text.

These should be policy/configuration or service-layer decisions once the domain concepts are finalized.

## 5. Recommendation Inputs

Future recommendation inputs should be explicit, consent-aware, and privacy-reviewed.

Potential inputs:

- Anamnesis/screening profile.
- Final persona assignment.
- Health paths from the future questionnaire model.
- Daily Check-in structured signals.
- Structured issue capture.
- Survey participation and aggregated-safe survey-derived indicators.
- Measure history.
- Participation history.
- Recommendation interaction history, such as viewed, dismissed, saved, or completed.

Important constraint:

The final persona/scoring/recommendation logic must wait until the questionnaire/persona concept is final. Until then, backend work should only create neutral fields and placeholders that keep the logic pluggable.

Recommendation outputs should avoid diagnosis or therapy wording. They should use language such as orientation, self-reflection, suggested resource, general measure, or support option.

## 6. Check-in Impact

Daily Check-in should evolve only after a separate privacy and product slice. The current fields are useful for wellbeing trends but too coarse for targeted recommendations.

Proposed structured issue fields:

- `issue_category`: e.g. stress, sleep, movement, nutrition, focus, ergonomics.
- `body_area`: optional and only for non-diagnostic self-reporting.
- `intensity`: simple 1-10 or low/medium/high scale.
- `frequency`: first time, sometimes, recurring.
- `wants_recommendation`: explicit user intent.
- `red_flags`: structured indicators that should route to safe, non-diagnostic guidance, not automated medical claims.
- `private_note`: optional encrypted text, never shown raw to company users and not used directly in company reporting.

Implementation guidance:

- Keep structured issue data private to the employee unless transformed into anonymity-safe aggregates through a reviewed service.
- Do not use raw notes for company reporting.
- Do not use free-text notes directly as recommendation engine input until an explicit privacy and safety design exists.
- Red-flag handling should be conservative and informational, not diagnostic.

## 7. Migration Strategy

Recommended sequence:

1. Domain concept: finish and review this document.
2. Stable measure fields: add neutral type/origin/delivery/execution/verification/schedule fields with defaults preserving current behavior.
3. Participation verification foundation: add participation status and verification metadata without QR/admin UI.
4. QR/admin confirmation: implement claim/verify flows, delayed points where required, and tests.
5. Measures Hub v0: list existing company measures plus typed fields without final recommendation logic.
6. Persona placeholder foundation: add nullable/pluggable targeting metadata only after questionnaire/persona concepts are reviewed.
7. Final recommendation engine: implement scoring and ranking only after questionnaire/persona concept, privacy review, and OpenAPI contract are complete.

This path avoids destructive migrations, keeps company/user scoping intact, and preserves current self-report participation behavior while preparing for verified participation.

## 8. Recommended Next Implementation Slice

Recommended next coding task: **Measure Domain Fields v1**.

Scope:

- Add stable measure fields with conservative defaults:
  - `origin`
  - `measure_type`
  - `delivery_type`
  - `execution_type`
  - `verification_mode`
  - `visibility_scope`
  - `starts_at`
  - `ends_at`
  - `duration_minutes`
  - `instructions`
  - `location_name`
  - `location_address`
  - `capacity`
- Keep current create/list/participate behavior unchanged by default.
- Update Laravel model casts/fillable, request validation, resources, OpenAPI, factories, and focused feature tests.
- Do not implement QR, admin confirmation, partner confirmation, persona scoring, or recommendation ranking in this slice.

Why this is low risk:

- It is additive.
- It preserves existing APIs by defaulting new fields server-side.
- It creates the vocabulary needed by the Measures Hub without introducing recommendation logic prematurely.
- It keeps business logic in Laravel and the OpenAPI contract explicit.

## Files Inspected

- `AGENTS.md`
- `docs/ai-context/codex-workflow.md`
- `docs/ai-context/architecture-decisions.md`
- `docs/ai-context/auth-and-roles.md`
- `docs/ai-context/health-data-guardrails.md`
- `docs/api/openapi.yaml`
- `apps/api-laravel/routes/api.php`
- `apps/api-laravel/app/Http/Controllers/Employee/EmployeeController.php`
- `apps/api-laravel/app/Http/Controllers/Company/MeasureController.php`
- `apps/api-laravel/app/Http/Requests/Company/CreateMeasureRequest.php`
- `apps/api-laravel/app/Http/Requests/Company/PatchMeasureRequest.php`
- `apps/api-laravel/app/Http/Requests/Employee/CheckinRequest.php`
- `apps/api-laravel/app/Http/Resources/Company/MeasureResource.php`
- `apps/api-laravel/app/Http/Resources/Company/MeasureParticipationSummaryResource.php`
- `apps/api-laravel/app/Http/Resources/Employee/MeasureResource.php`
- `apps/api-laravel/app/Models/Measure.php`
- `apps/api-laravel/app/Models/MeasureParticipation.php`
- `apps/api-laravel/app/Models/WellbeingEntry.php`
- `apps/api-laravel/app/Services/MeasureParticipationService.php`
- `apps/api-laravel/app/Services/MeasureParticipationSummaryService.php`
- `apps/api-laravel/app/Services/PointsService.php`
- `apps/api-laravel/app/Services/WellbeingService.php`
- `apps/api-laravel/database/migrations/2024_01_01_000005_create_remaining_tables.php`
- `apps/api-laravel/database/migrations/2026_06_01_020000_create_measure_participations_table.php`
- `apps/api-laravel/tests/Feature/EmployeeTest.php`
- `apps/api-laravel/tests/Feature/MeasureParticipationPersistenceTest.php`
- `apps/api-laravel/tests/Feature/MeasureParticipationSummaryTest.php`
- `apps/api-laravel/tests/Feature/MeasureParticipationTestEnvironmentIsolationTest.php`
- `apps/web-angular/src/app/features/employee/services/employee.service.ts`
- `apps/web-angular/src/app/features/employee/pages/checkin/checkin.component.ts`
- `apps/web-angular/src/app/features/employee/pages/measures/measures.component.ts`
- `apps/web-angular/src/app/features/company/pages/measures/company-measures.component.ts`
