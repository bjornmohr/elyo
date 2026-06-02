# Manual QA Handoff: Measure Participation MVP

Date: 2026-06-02

## Scope Executed

Manual QA was executed through API smoke checks, Docker validation commands, Angular build/unit tests, Laravel route inspection, code inspection, and service logs.

Browser-only visual checks were not fully executed because this repository has no Playwright/Cypress setup and host-side access to `localhost:8080` was blocked from the sandbox. API behavior was verified from inside the Docker network through `http://nginx/api`.

## Test Data Used

- Admin/support: `support@elyo.de`
- Company admin: `admin@demo.de`
- Company manager: `manager@demo.de`
- Employees: `employee1@demo.de` through `employee6@demo.de`
- Password: `demo1234`
- Company: `demo-gmbh`, `company_id=28`
- Team: `Product & Engineering`, `team_id=10`
- Existing measure: `Fokuszeit am Vormittag`, `measure_id=13`
- QA-created measure: `QA Participation Smoke 095541`, `measure_id=17`

The failed backend test run emptied demo data. I restored local demo data with `docker compose exec api php artisan db:seed` and did not run `migrate:fresh`, `db:wipe`, `docker compose down -v`, or destructive Docker reset commands.

## QA Results

### 1. Admin Points Config

Status: Pass

- `GET /api/admin/points-config` returned the expected keys:
  - `daily_checkin`
  - `anamnesis_completed`
  - `medical_document_upload`
  - `measure_participation`
  - `streak_7days`
  - `streak_30days`
- `PUT /api/admin/points-config` with `measure_participation=23` returned `200`.
- Reload confirmed `measure_participation=23`.
- Point setting was restored to `measure_participation=20` after the QA check.
- Angular unit tests cover the visible `Maßnahmen-Teilnahme` field and save payload.

### 2. Company Measures

Status: Pass with browser gap

- Company admin login returned `200`.
- `GET /api/company/measures` returned `200`.
- API-created active company measure returned `201`.
- Measure list reloaded after creation and returned two measures.
- Existing team-specific measure was visible to the company admin.
- Participation summary endpoint loaded for the measure without breaking the measure list API.

Browser UI rendering and console checks were not executed.

### 3. Employee Measures

Status: Pass

- Employee login returned `200`.
- `GET /api/employee/measures` returned `200`.
- Employee saw one active eligible team measure.
- First participation with empty body `[]` returned `201`.
- Reloaded employee measures showed `participation.isParticipating=true`.
- Duplicate participation returned `409` with `MEASURE_ALREADY_PARTICIPATED`.
- A forged request body containing `user_id`, `company_id`, `team_id`, and `participated_at` was ignored by the backend.
- Stored forged-body participation used authenticated employee identity:
  - `user_id=64`
  - `company_id=28`
  - `team_id=10`
  - current `participated_at`
- No row was created with forged identity values.

### 4. Duplicate Participation

Status: Pass

- Duplicate API attempt returned:
  - HTTP `409`
  - `error.code=MEASURE_ALREADY_PARTICIPATED`
- Point transaction count for the first employee remained one for `measure_participation`.

### 5. Points

Status: Pass

- First two participation checks awarded `23` points while Admin Points Config was set to `23`.
- After restoring Admin Points Config to `20`, later participations awarded `20` points.
- `measure_participation` points were awarded once per first participation.
- Daily check-in smoke test for `employee6@demo.de` returned `200` and awarded one `daily_checkin` transaction worth `10` points.

### 6. Company Summary Privacy

Status: Pass with one follow-up

Below threshold:

- `GET /api/company/measures/13/participation-summary` returned `200`.
- Response had:
  - `isAboveThreshold=false`
  - `eligibleCount=null`
  - `participantCount=null`
  - `participationRate=null`
  - `suppressionReason=ANONYMITY_THRESHOLD_NOT_MET`
- No user IDs, names, emails, raw participation rows, or per-user timestamps were present.

Above threshold:

- After five employees participated out of six eligible employees, summary returned:
  - `isAboveThreshold=true`
  - `eligibleCount=6`
  - `participantCount=5`
  - `participationRate=83.3`
- This is aggregate-only and does not expose individual participation data.

Manager scope:

- `manager@demo.de` could fetch the managed team summary.
- Targeted backend tests for manager scoping are present, but backend validation command failed because of test database configuration/runtime issues described below.

Contract note:

- The API response includes `teamBreakdown: null`. This is the accepted current contract and has no privacy impact while the value remains `null`. Non-null team breakdown data remains out of scope and requires a separate privacy-reviewed feature.

### 7. Team Transfer Regression

Status: Not executed manually

Reason: the available safe demo setup did not include a second managed team for this manager flow, and changing team assignments manually would have expanded the QA data mutation scope. Existing focused backend tests cover current-team scoping, but the test command failed due environment issues.

### 8. Browser/UX Smoke Test

Status: Partially executed

- Angular production build passed.
- Angular unit tests passed.
- Docker web logs showed successful rebuilds and no obvious fatal frontend errors in the checked tail.
- Full browser console inspection, visual loading states, and German label review were not executed because browser automation was unavailable in the repo and host-side `localhost:8080` access was blocked from the sandbox.

## Bugs / Follow-Ups

1. Non-null team breakdown data remains out of scope.
   - Current contract: `teamBreakdown: null` is accepted in company participation summary responses.
   - Privacy impact: no individual or team-level data is leaked while the value is `null`.
   - Future requirement: any non-null team breakdown feature requires separate privacy review and contract/test coverage.

2. Backend test commands used PostgreSQL and failed before/around migrations even though `apps/api-laravel/phpunit.xml` declares SQLite in-memory.
   - Severity: Validation blocker.
   - Observed failures included missing/duplicate PostgreSQL tables and migration table errors.
   - Running the two backend filtered test commands concurrently likely amplified the collision, but the key issue is that the test runner did not isolate from the local PostgreSQL database.
   - Follow-up: make `php artisan test` reliably use the configured test database/environment inside Docker.

3. Browser manual QA remains incomplete.
   - Follow-up: run a real browser pass outside the sandbox or add Playwright/Cypress smoke coverage for Admin Points, Company Measures, and Employee Measures.

## Commands Run

- `git status --short`
- `docker compose ps`
- `rg -n "measure_participation|MeasureParticipation|MEASURE_ALREADY_PARTICIPATED|Teilnehmen|Teilgenommen|Maßnahmen-Teilnahme|Massnahmen" apps docs database -S`
- `rg -n "@|password|Password|demo|seed|Seeder|COMPANY_ADMIN|COMPANY_MANAGER|ELYO_ADMIN|EMPLOYEE" apps/api-laravel/database docs -S`
- `rg --files apps/web-angular | rg '(measure|points|auth|login|e2e|spec)'`
- `rg --files apps/api-laravel | rg '(Measure|Participation|Points|Auth|Test|Seeder|Factory)'`
- `docker compose exec api php artisan route:list`
- `docker compose exec web npm run build`
- `docker compose exec web npm test -- --watch=false`
- `docker compose exec api php artisan test --filter=MeasureParticipation`
- `docker compose exec api php artisan test --filter=MeasureParticipationSummary`
- `docker compose exec api php artisan migrate:status`
- `docker compose exec api php artisan db:seed`
- Multiple `docker compose exec api php artisan tinker --execute=...` API/data smoke checks
- `docker compose logs --tail=80 web`
- `docker compose logs --tail=80 api`

## Validation Result

- Frontend build: Pass
- Angular unit tests: Pass, 5 files / 12 tests
- Laravel route list: Pass, 61 routes shown
- Backend `MeasureParticipation` filtered tests: Fail due test database/runtime issue
- Backend `MeasureParticipationSummary` filtered tests: Fail due test database/runtime issue
- API smoke checks: Pass; `teamBreakdown: null` is accepted current behavior

## Privacy Confirmation

No individual participation data was visible in company summary UI-facing API responses during the smoke checks. Suppressed summaries hid `eligibleCount`, `participantCount`, and `participationRate`. Above-threshold summaries showed aggregate counts/rate only.

Admin Points Config saves and reloads `measure_participation`.
