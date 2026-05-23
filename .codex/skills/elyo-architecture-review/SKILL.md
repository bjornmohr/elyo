---
name: elyo-architecture-review
description: Review ELYO changes against the target Angular/Laravel/PostgreSQL architecture, Docker constraints, and migration rules.
---

# ELYO Architecture Review Skill

Use this skill when reviewing broad changes, refactors, Docker changes, API structure, data model changes, or migration work.

## Review Focus

Check that changes preserve:

- Angular frontend in apps/web-angular
- Laravel API in apps/api-laravel
- PostgreSQL as primary database
- Redis only for cache, sessions and queues
- n8n only for integrations, not business logic
- Docker Compose as local runtime
- OpenAPI as frontend/backend contract
- legacy ../ELYO as read-only reference

## Hard Rejections

Flag as must-fix if a change:

- modifies ../ELYO
- introduces microservices
- switches database away from PostgreSQL
- moves business logic into Angular
- moves business logic into n8n
- weakens health-data privacy
- bypasses Laravel authorization
- hardcodes secrets
- breaks docker compose config

## Output Format

Return:

1. Verdict
2. Must-fix issues
3. Should-fix issues
4. Nice-to-have improvements
5. Missing tests
6. Suggested next task
