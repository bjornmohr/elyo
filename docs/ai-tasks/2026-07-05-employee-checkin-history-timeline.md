# Employee Check-in History Timeline MVP

## Scope

Redesign `apps/web-angular/src/app/features/employee/pages/history/history.component.ts`
into a readable Employee check-in history experience with:

- Header for "Dein Check-in Verlauf"
- Summary cards based on available `WellbeingEntry` data
- Clickable daily insight cards for the recent range
- Inline detail panel for selected completed check-ins

## Constraints

- Frontend-only MVP.
- Do not change backend, API contracts, DTOs, migrations, auth, routing, or business logic.
- Use only available frontend fields: `id`, `score`, `mood`, `stress`, `energy`, `notes`, `createdAt`.
- Omit or mark future-only sections where backend fields are not available.
- Follow `docs/ai-context/frontend-typography-guideline.md`.

## Validation

- `grep -rn "text-\[1[01]px\]" apps/web-angular/src/app/features/employee`
- `docker compose exec web npm run build`
- `git diff -- apps/web-angular/src/app/features/employee/pages/history/history.component.ts`
