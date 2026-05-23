---
name: elyo-handoff-summary
description: Create a concise project handoff from git status, diffs, Docker status, Laravel tests, Angular build and current documentation.
---

# ELYO Handoff Summary Skill

Use this skill when creating a project state handoff for review.

## Inputs to Inspect

Prefer:

- AGENTS.md
- docs/ai-context/*
- docs/ai-tasks/*
- docs/ai-handoff/current-status.md
- docs/ai-handoff/current-diff.patch
- docs/api/openapi.yaml
- docs/migration/*
- git status
- git diff
- docker compose config
- Laravel test output
- Angular build output

## Output

Create or update:

    docs/ai-handoff/current-summary.md

Include:

1. Current functional state
2. Recent changes
3. Test/build status
4. Risks
5. Architecture concerns
6. Privacy concerns
7. Recommended next Codex task
8. Files most relevant for next review
