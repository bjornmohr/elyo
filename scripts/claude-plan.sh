#!/usr/bin/env bash
set -euo pipefail

TASK_FILE="${1:-}"

if [ -z "$TASK_FILE" ]; then
  echo "Usage: ./scripts/codex-plan.sh docs/ai-tasks/<task>.md"
  exit 1
fi

if [ ! -f "$TASK_FILE" ]; then
  echo "Task file not found: $TASK_FILE"
  exit 1
fi

claude "Read AGENTS.md and docs/ai-context/* first. Then read ${TASK_FILE}. Create an implementation plan only. Do not modify any files except ${TASK_FILE}. If ${TASK_FILE} already contains a section named '## Implementation Plan', replace only that section. Otherwise append a new '## Implementation Plan' section at the end. Do not rewrite or remove any other task content. Do not modify production, test, config, OpenAPI, frontend, backend, migration, or other documentation files. Do not run tests or build commands. Do not run destructive database or Docker commands. After updating the plan section, stop."