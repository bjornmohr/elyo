---
name: elyo-angular-task
description: Implement or review Angular feature work for ELYO while keeping API access in services, role checks in guards, and business logic out of components.
---

# ELYO Angular Task Skill

Use this skill for Angular frontend implementation tasks.

## Frontend Location

    apps/web-angular

## Rules

- Use Angular services for API calls.
- Do not use direct fetch calls inside components.
- Use environment config for API base URL.
- Use guards for role restrictions.
- Keep components focused on presentation and user interaction.
- Do not invent backend data.
- Do not show placeholder charts unless clearly marked.
- Do not display individual employee health data in company views.

## Required Check

Before finishing:

    docker compose exec web npm run build

## Review Focus

Check:

- role guards
- API service usage
- loading states
- error states
- empty states
- privacy-safe display logic
- no misleading charts for suppressed data

## Output Format

Report:

1. Files changed
2. UI behavior changed
3. API services changed
4. Guards or routing changed
5. Build result
6. Open questions
