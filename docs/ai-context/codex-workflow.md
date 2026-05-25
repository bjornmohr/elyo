# Codex Workflow

## Purpose

All programming tasks in this project should follow a structured ChatGPT + Codex CLI workflow.

ChatGPT is used for:
- task shaping
- architecture decisions
- privacy/security review
- prompt creation
- diff review
- next-step planning

Codex CLI is used for:
- local repository inspection
- implementation
- running tests/builds
- producing diffs and command output

## Standard Workflow

1. Discuss the problem with ChatGPT.
2. Create or update a task file in docs/ai-tasks/.
3. Run Codex in plan mode.
4. Review the plan before modifying files.
5. Run Codex in patch mode.
6. Run validation commands.
7. Create a handoff.
8. Run Codex review mode.
9. Share diff/status/review output with ChatGPT.
10. Commit only after review is clean.

## Commands

Plan:

    ./scripts/codex-plan.sh docs/ai-tasks/TASK.md

Patch:

    ./scripts/codex-task.sh docs/ai-tasks/TASK.md

Validation:

    docker compose exec api php artisan test
    docker compose exec web npm run build
    docker compose config
    git diff --check

Handoff:

    ./scripts/create-handoff.sh

Review:

    ./scripts/codex-review.sh

## Rules

- Never start implementation before the task is clear.
- Keep patches small and reviewable.
- Do not mix feature work, refactoring and cleanup unless explicitly planned.
- Do not commit generated handoff snapshots.
- Do not rely on Codex alone for architecture/privacy/security review.
- ChatGPT reviews the Codex output before commit.
- If tests or build fail, fix that before expanding scope.
