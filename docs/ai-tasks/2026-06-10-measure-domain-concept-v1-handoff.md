# Handoff: Measure Domain Concept v1

Date: 2026-06-10

## Summary

Created `docs/ai-context/measure-domain-concept-v1.md` as the fachlich-technical concept for the future ELYO measure domain. No application code, migrations, API behavior, or Angular behavior was changed.

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

## Files Changed

- Added `docs/ai-context/measure-domain-concept-v1.md`
- Added `docs/ai-tasks/2026-06-10-measure-domain-concept-v1-handoff.md`

## Current-State Findings

- Measures are currently company-owned records with optional team scope, a hardcoded category, lifecycle status, and timestamps.
- Employee participation is one self-reported row per measure/user and awards global `measure_participation` points immediately.
- Employees can only see their own participation state.
- Company users only receive aggregate participation summaries.
- Aggregate participation metrics are suppressed unless both eligible and participant counts meet the company anonymity threshold.
- `teamBreakdown` is documented and implemented as null today pending a separate privacy-reviewed feature.

## Proposed Target Model

- Keep one concrete `measures` instance table for company/team/private-user visible measures.
- Add `measure_templates` later for reusable ELYO-authored definitions.
- Model future recommendation instances separately from company-created measure instances.
- Add stable additive fields for origin, type, delivery, execution, verification, visibility, scheduling, instructions, location, capacity, and points policy.
- Add participation verification state separately from the later QR/admin/partner flows.
- Keep persona, health path, recommendation ranking, and scoring logic pluggable until the questionnaire/persona concept is final.

## Recommended Next Task

Recommended next coding task: **Measure Domain Fields v1**.

Implement only additive stable fields with defaults that preserve current behavior. Update Laravel model/request/resource/factory/test coverage and OpenAPI together. Do not implement Measures Hub, QR/admin verification, partner confirmation, final persona scoring, or recommendation ranking in that slice.

## Validation Performed

- Confirmed the required task file and inspected source files exist.
- Kept changes documentation-only.
- Ran targeted file existence checks for the new concept and handoff files.
- Ran `docker compose config`.
- Ran `git diff --check`.
- Backend tests and Angular build are not required for behavior because no code changed, but can be run if desired.

## Risks / Open Questions

- Final persona/questionnaire concepts are not yet defined.
- Recommendation signal consent boundaries and free-text usage need separate privacy review.
- Whether self-reported participation should count as verified for points should be decided before adding verification fields.
- Partner verification ownership and partner/company data boundaries need a dedicated design.
- Team breakdown reporting remains intentionally blocked until bucket-level anonymity behavior is reviewed.
