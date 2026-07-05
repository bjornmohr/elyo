# Employee Shell Sticky Sidebar

## Scope

Fix `apps/web-angular/src/app/shared/shells/employee-shell.component.ts` so the
Employee sidebar is viewport-height based and independent from main content
height.

## Constraints

- Frontend shell layout only.
- Do not change backend, APIs, services, routing, auth, or page content.
- Preserve current Employee navigation styling.

## Validation

- `grep -rn "text-\[1[01]px\]" apps/web-angular/src/app/features/employee apps/web-angular/src/app/shared/shells`
- `docker compose exec web npm run build`
- `git diff -- apps/web-angular/src/app/shared/shells/employee-shell.component.ts`
