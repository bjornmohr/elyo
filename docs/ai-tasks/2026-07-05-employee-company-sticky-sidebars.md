# Employee And Company Sticky Sidebars

## Scope

Apply the same viewport-height sidebar layout to:

- `apps/web-angular/src/app/shared/shells/employee-shell.component.ts`
- `apps/web-angular/src/app/shared/shells/company-shell.component.ts`

## Constraints

- Shell layout only.
- Preserve existing navigation styling and routes.
- Do not change backend, APIs, services, auth, routing behavior, or page content.

## Validation

- `grep -rn "text-\[1[01]px\]" apps/web-angular/src/app/features/employee apps/web-angular/src/app/features/company apps/web-angular/src/app/shared/shells`
- `docker compose exec web npm run build`
- Inspect shell diffs.
