# Employee Dashboard Safe Mode Gating

## Scope

Fix the Employee dashboard so the Schonmodus banner and measure-card safe-mode
states only appear after today's check-in is completed and the available
check-in/dashboard data indicates safe mode.

## Constraints

- Frontend-only dashboard change.
- Do not change backend, APIs, DTOs, auth, routing, migrations, or business logic.
- Use existing fields and demo localStorage only.
- Keep helper logic local to `dashboard.component.ts` unless a shared pattern is required.
- Follow the Employee typography guideline.

## Validation

- `grep -rn "text-\[1[01]px\]" apps/web-angular/src/app/features/employee`
- `docker compose exec web npm run build`
- `git diff -- apps/web-angular/src/app/features/employee/pages/dashboard/dashboard.component.ts`
