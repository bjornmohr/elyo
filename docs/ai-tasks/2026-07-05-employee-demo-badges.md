# Employee demo badges

## Goal

Add a frontend-only badge concept to the Employee experience as a calm prevention progress layer.

## Scope

- Dashboard badge preview
- Dedicated Employee badges page
- Employee route/sidebar link
- Optional measure detail quest hint
- Local demo badge model and progress data

## Constraints

- No backend/API changes.
- No Company/Admin exposure of individual badge progress.
- Badges reward routines, prevention, reflection, and feature use, not perfect health values.
- Production badge progress is deferred to future user-level event computation.

## Validation

- `grep -rn "text-\\[1[01]px\\]" apps/web-angular/src/app/features/employee` returned zero matches.
- `docker compose exec web npm run build` passed with existing unused-import warnings in `AppComponent`.
- `git diff --check` passed.
