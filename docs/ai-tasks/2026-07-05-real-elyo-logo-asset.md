# Real ELYO Logo Asset

## Scope

Replace the fake Employee shell logo rendering with the provided PNG logo asset.

## Constraints

- Frontend-only asset and shell markup change.
- Do not change backend, APIs, services, routing, auth, or business logic.
- Do not recreate the logo with text, CSS, SVG, or base64.
- Keep the logo aspect ratio and use accessible alt text.

## Validation

- `grep -rn ">ELYO<\|ELYO" apps/web-angular/src/app`
- `docker compose exec web npm run build`
- `git diff -- apps/web-angular/src/app/shared/shells/employee-shell.component.ts`
- `git diff -- apps/web-angular/public/assets`
