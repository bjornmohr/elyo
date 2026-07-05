# Employee Screening Questionnaire MVP

## Scope

Add a demo-first recurring Employee screening questionnaire in
`apps/web-angular/src/app/features/employee/pages/profile/profile.component.ts`.

## Constraints

- Prefer frontend/demo state over backend persistence for this MVP.
- Do not expose individual screening answers to Company/Admin views.
- Do not add diagnosis-like wording or analytics.
- Represent the 8-week cycle and +50 points reward clearly.
- Keep backend/API/auth/routing unchanged unless a small existing contract is useful.
- Follow the Employee typography guideline.

## Validation

- `grep -rn "text-\[1[01]px\]" apps/web-angular/src/app/features/employee`
- `docker compose exec web npm run build`
- Inspect `git diff -- apps/web-angular/src/app/features/employee/pages/profile/profile.component.ts`
