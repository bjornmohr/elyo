---
name: elyo-laravel-api-task
description: Implement or review Laravel API changes for ELYO using controllers, requests, resources, services, policies and feature tests.
---

# ELYO Laravel API Task Skill

Use this skill for Laravel backend implementation tasks.

## Backend Location

    apps/api-laravel

## Expected Structure

Use:

- Controllers for HTTP entry points
- Form Requests for validation
- Resources for response shape
- Services for domain logic
- Middleware, policies or gates for authorization
- Feature tests for API behavior
- Migrations for schema changes

## Required Checks

Before finishing:

    docker compose exec api php artisan test

If migrations change:

    docker compose exec api php artisan migrate:fresh

If routes change:

    docker compose exec api php artisan route:list

## API Rules

- Keep error responses consistent.
- Update docs/api/openapi.yaml when route behavior changes.
- Never leak internal exceptions.
- Never expose individual employee health data to company users.
- Enforce company, team and user scoping.

## Output Format

Report:

1. Files changed
2. API behavior changed
3. Validation logic changed
4. Authorization logic changed
5. Tests added or updated
6. Commands run and results
7. Open questions
