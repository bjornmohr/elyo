# Employee readability and widget-size improvement

## Source

- Branch base: `demo/new-employee-features`
- Handoff ZIP in project root: `Employee Dashboard Größe Analyse1.zip`
- Extracted locally for implementation only: `tmp/employee-dashboard-readability-handoff`

## Scope

- Apply the extracted Employee Dashboard readability handoff to Angular Employee views.
- Create/update `docs/ai-context/frontend-typography-guideline.md` from the extracted guideline.
- Update root `AGENTS.md` with the extracted frontend style guideline snippet.

## Constraints

- Keep layout and visual direction; do not redesign.
- Do not change backend code, APIs, DTOs, routing, auth, or business logic.
- Preserve body font `DM Sans` and heading font `Fraunces`.
- Do not commit the ZIP or extracted temporary handoff folder.

## Validation

- `grep -rn "text-\[1[01]px\]" apps/web-angular/src/app/features/employee`
- `grep -rn "text-\[1[01]px\]" apps/web-angular/src`
- `docker compose exec web npm run build`
- Check `docs/migration/angular-visual-qa-checklist.md` if present.
- `git status --short`

## Implementation Plan

1. Confirm the patch remains frontend/documentation-only:
   - Do not touch Laravel backend, migrations, API routes, DTOs, auth, business logic, Docker, or OpenAPI files.
   - Do not use or modify `../ELYO`.
   - Do not commit `Employee Dashboard Größe Analyse1.zip` or anything under `tmp/employee-dashboard-readability-handoff`.

2. Add the extracted typography guidance:
   - Create or update `docs/ai-context/frontend-typography-guideline.md` from `tmp/employee-dashboard-readability-handoff/handoff/elyo-typography-guideline.md`.
   - Add the extracted `## Frontend Style Guideline (verbindlich)` snippet from `tmp/employee-dashboard-readability-handoff/handoff/AGENTS-guideline-snippet.md` to root `AGENTS.md` without changing unrelated instructions.

3. Apply Tailwind-only readability fixes in Employee Angular templates:
   - Update `apps/web-angular/src/app/features/employee/pages/dashboard/dashboard.component.ts` according to the handoff: raise page title/date contrast, pills, Schonmodus banner, wellbeing hero sublabels, metric cards, body-signal rows, and weekly measure cards.
   - Update `history.component.ts`, `surveys.component.ts`, `measures.component.ts`, `measure-detail.component.ts`, `measure-exercise.component.ts`, and `profile.component.ts` to remove `text-[10px]`/`text-[11px]`, raise important secondary text to at least `text-sm`, and keep readable secondary text at `text-slate-500` or stronger.
   - Keep layout and visual direction intact; adjust only sizing, spacing, contrast, chips, and hit-area utilities needed by the handoff.
   - Preserve the configured fonts: body `DM Sans`, headings `Fraunces`.

4. Review responsive density while editing:
   - Keep KPI/metric cards at breathable padding (`p-5` or stronger where applicable) and avoid over-compressing desktop grids.
   - Ensure buttons/icon links keep at least a 44 x 44 px effective clickable area where the handoff calls this out.
   - Avoid introducing visible instructional copy or medical/therapy claims.

5. Validate after implementation:
   - Run `grep -rn "text-\[1[01]px\]" apps/web-angular/src/app/features/employee` and expect 0 matches.
   - Run `grep -rn "text-\[1[01]px\]" apps/web-angular/src` and review any remaining matches outside Employee scope before deciding whether they are in scope.
   - Run `docker compose exec web npm run build`.
   - Check `docs/migration/angular-visual-qa-checklist.md` for relevant Employee visual QA items.
   - Run `git status --short` and verify only intended tracked files changed, with the ZIP and extracted temp folder left untracked/uncommitted.

6. Final review checklist:
   - Architecture preserved: Angular-only UI changes plus the planned documentation updates.
   - Portal boundaries preserved: no API/auth/scoping changes.
   - Privacy preserved: no company/manager access to individual employee health data introduced.
   - OpenAPI unchanged because no API behavior changes are planned.
   - Unknown: whether remaining `text-[10px]`/`text-[11px]` outside Employee views should be cleaned in the same patch; default to no unless required by the second grep and task owner approval.
