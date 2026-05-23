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

codex "Read AGENTS.md and ${TASK_FILE}. Create an implementation plan only. Do not modify files."
