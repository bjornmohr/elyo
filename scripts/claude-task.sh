#!/usr/bin/env bash
set -euo pipefail

TASK_FILE="${1:-}"

if [ -z "$TASK_FILE" ]; then
  echo "Usage: ./scripts/codex-task.sh docs/ai-tasks/<task>.md"
  exit 1
fi

if [ ! -f "$TASK_FILE" ]; then
  echo "Task file not found: $TASK_FILE"
  exit 1
fi

codex "Read AGENTS.md and execute ${TASK_FILE}. Keep changes minimal. Run the validation commands from the task if possible. At the end, summarize files changed, commands run, test/build results, and open questions."
./scripts/create-handoff.sh