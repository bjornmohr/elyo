#!/usr/bin/env bash
#
# run-codex-task.sh — execute an ELYO AI task with Codex CLI.
# Based on run-ai-task.sh and its task/branch resolution rules.
#
# Usage:
#   scripts/run-codex-task.sh <task-number> [options]
#
#   <task-number>       e.g. 1, 01, 8a, 08a, 17
#
# Options:
#   --continue          Resume the most recent Codex session on the existing
#                       task branch.
#   --continue-claude   Continue Claude's current implementation from the task
#                       specification and the changes already present in Git.
#                       Existing modifications are treated as intentional and
#                       Codex must finish only the remaining work.
#   --dry-run           Show what would happen, run nothing.
#   --force             Skip safety checks for branch/worktree state.
#   --date YYYY-MM-DD   Select a task series date explicitly.
#   -h, --help          Show this help.
#
# Environment:
#   RUN_AI_TASK_BRANCH_PREFIX   Branch prefix (default: elyo-91)
#   RUN_CODEX_ARGS              Additional arguments passed to Codex.
#                              Example: RUN_CODEX_ARGS='--full-auto'
#
# Fresh task protocol:
#   1. resolve prompt file from task number
#   2. require a clean working tree
#   3. create branch <prefix>/<NN>-<slug>
#   4. run Codex non-interactively with the task instructions
#
# Claude handoff protocol:
#   1. resolve task and expected branch
#   2. keep/check out the existing task branch
#   3. capture Git status, staged and unstaged diffs, and untracked files
#   4. ask Codex to determine completed acceptance criteria and finish only
#      the remaining work without reverting valid existing changes

set -euo pipefail

TASKS_DIR="docs/ai-tasks"
BRANCH_PREFIX="${RUN_AI_TASK_BRANCH_PREFIX:-elyo-91}"

err()  { printf 'error: %s\n' "$*" >&2; exit 1; }
info() { printf '==> %s\n' "$*"; }

show_help() {
  sed -n '2,/^$/p' "$0"
}

TASK_RAW=""
MODE="fresh"
DRY_RUN=0
FORCE=0
DATE_FILTER=""

while [ $# -gt 0 ]; do
  case "$1" in
    --continue)
      [ "$MODE" = "fresh" ] || err "--continue and --continue-claude are mutually exclusive"
      MODE="continue-codex"
      ;;
    --continue-claude)
      [ "$MODE" = "fresh" ] || err "--continue and --continue-claude are mutually exclusive"
      MODE="continue-claude"
      ;;
    --dry-run) DRY_RUN=1 ;;
    --force) FORCE=1 ;;
    --date)
      shift
      [ $# -gt 0 ] || err "--date requires YYYY-MM-DD"
      DATE_FILTER="$1"
      ;;
    -h|--help)
      show_help
      exit 0
      ;;
    -*) err "unknown option: $1" ;;
    *)
      [ -z "$TASK_RAW" ] || err "only one task number allowed"
      TASK_RAW="$1"
      ;;
  esac
  shift
done

[ -n "$TASK_RAW" ] || err "missing task number (e.g. 04 or 8a). See --help."

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || err "not inside a git repository"
cd "$REPO_ROOT"
[ -d "$TASKS_DIR" ] || err "$TASKS_DIR not found"

TASK_LOWER="$(printf '%s' "$TASK_RAW" | tr '[:upper:]' '[:lower:]')"
NUM="$(printf '%s' "$TASK_LOWER" | sed -E 's/^([0-9]+).*/\1/')"
SUFFIX="$(printf '%s' "$TASK_LOWER" | sed -E 's/^[0-9]+//')"

case "$NUM" in
  ''|*[!0-9]*) err "invalid task number: $TASK_RAW" ;;
esac
case "$SUFFIX" in
  ''|[a-z]) : ;;
  *) err "invalid task suffix in: $TASK_RAW" ;;
esac

TASK_NN="$(printf '%02d' "$((10#$NUM))")$SUFFIX"
[ "$TASK_NN" != "00" ] || err "task 00 is the execution plan itself, not a runnable task"

if [ -z "$DATE_FILTER" ]; then
  LATEST_PLAN="$(find "$TASKS_DIR" -maxdepth 1 -type f -name '*-00-*execution-plan.md' -print | sort | tail -n 1)"
  [ -n "$LATEST_PLAN" ] || err "no execution plan (*-00-*execution-plan.md) found in $TASKS_DIR"
  DATE_FILTER="$(basename "$LATEST_PLAN" | cut -d- -f1-3)"
  info "series    : $DATE_FILTER (latest execution plan; override with --date)"
fi

case "$DATE_FILTER" in
  ????-??-??) : ;;
  *) err "invalid --date value: $DATE_FILTER (expected YYYY-MM-DD)" ;;
esac

MATCHES=()
while IFS= read -r file; do
  [ -n "$file" ] && MATCHES+=("$file")
done < <(find "$TASKS_DIR" -maxdepth 1 -type f -name "${DATE_FILTER}-${TASK_NN}-*.md" -print | sort)

if [ "${#MATCHES[@]}" -eq 0 ]; then
  err "no prompt file found for task $TASK_NN in $TASKS_DIR"
elif [ "${#MATCHES[@]}" -gt 1 ]; then
  printf 'error: task %s is ambiguous:\n' "$TASK_NN" >&2
  printf '  %s\n' "${MATCHES[@]}" >&2
  err "disambiguate with --date YYYY-MM-DD"
fi

TASK_FILE="${MATCHES[0]}"
BASENAME="$(basename "$TASK_FILE" .md)"
SERIES_DATE="$(printf '%s' "$BASENAME" | cut -d- -f1-3)"
SLUG="$(printf '%s' "$BASENAME" | sed -E "s/^${SERIES_DATE}-${TASK_NN}-//")"
BRANCH="${BRANCH_PREFIX}/${TASK_NN}-${SLUG}"

PLAN_FILE="$(find "$TASKS_DIR" -maxdepth 1 -type f -name "${SERIES_DATE}-00-*.md" -print | sort | head -n 1)"
[ -n "$PLAN_FILE" ] || err "no execution plan (${SERIES_DATE}-00-*) found next to $TASK_FILE"

info "task file : $TASK_FILE"
info "plan file : $PLAN_FILE"
info "branch    : $BRANCH"
info "mode      : $MODE"

command -v codex >/dev/null 2>&1 || err "codex CLI not found in PATH"

BRANCH_EXISTS=0
git show-ref --verify --quiet "refs/heads/$BRANCH" && BRANCH_EXISTS=1
CURRENT_BRANCH="$(git branch --show-current)"
WORKTREE_DIRTY=0
[ -n "$(git status --porcelain)" ] && WORKTREE_DIRTY=1

if [ "$MODE" = "fresh" ]; then
  if [ "$FORCE" -ne 1 ] && [ "$WORKTREE_DIRTY" -eq 1 ]; then
    err "working tree not clean — commit/stash first, use --continue-claude for a handoff, or use --force"
  fi
  [ "$BRANCH_EXISTS" -eq 0 ] || err "branch $BRANCH already exists — use --continue, --continue-claude, or delete it for a clean restart"
fi

if [ "$MODE" = "continue-codex" ]; then
  [ "$BRANCH_EXISTS" -eq 1 ] || err "--continue: branch $BRANCH does not exist yet"
  if [ "$CURRENT_BRANCH" != "$BRANCH" ] && [ "$WORKTREE_DIRTY" -eq 1 ] && [ "$FORCE" -ne 1 ]; then
    err "cannot switch from dirty branch $CURRENT_BRANCH to $BRANCH; commit/stash first or use --force"
  fi
fi

if [ "$MODE" = "continue-claude" ]; then
  [ "$BRANCH_EXISTS" -eq 1 ] || err "--continue-claude: branch $BRANCH does not exist"
  if [ "$CURRENT_BRANCH" != "$BRANCH" ] && [ "$WORKTREE_DIRTY" -eq 1 ] && [ "$FORCE" -ne 1 ]; then
    err "Claude changes are on dirty branch $CURRENT_BRANCH, but task branch is $BRANCH; switch safely or use --force only if intentional"
  fi
fi

set --
if [ -n "${RUN_CODEX_ARGS:-}" ]; then
  # Deliberate shell-style splitting for a user-controlled environment variable.
  # shellcheck disable=SC2086
  set -- ${RUN_CODEX_ARGS}
fi

if [ "$DRY_RUN" -eq 1 ]; then
  info "dry run — would do:"
  case "$MODE" in
    fresh)
      printf '  git checkout -b %q\n' "$BRANCH"
      printf '  codex exec [RUN_CODEX_ARGS] - < generated-prompt.md\n'
      ;;
    continue-codex)
      [ "$CURRENT_BRANCH" = "$BRANCH" ] || printf '  git checkout %q\n' "$BRANCH"
      printf '  codex resume --last [RUN_CODEX_ARGS]\n'
      ;;
    continue-claude)
      [ "$CURRENT_BRANCH" = "$BRANCH" ] || printf '  git checkout %q\n' "$BRANCH"
      printf '  capture git status/diffs/untracked files\n'
      printf '  codex exec [RUN_CODEX_ARGS] - < generated-handoff-prompt.md\n'
      ;;
  esac
  exit 0
fi

if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
  if [ "$MODE" = "fresh" ]; then
    info "creating branch from $CURRENT_BRANCH ($(git rev-parse --short HEAD))"
    git checkout -b "$BRANCH"
  else
    info "checking out existing task branch"
    git checkout "$BRANCH"
  fi
fi

if [ "$MODE" = "continue-codex" ]; then
  info "resuming most recent Codex session for this repository"
  exec codex resume --last "$@"
fi

PROMPT_FILE="$(mktemp "${TMPDIR:-/tmp}/run-codex-task.XXXXXX.md")"
cleanup() {
  status=$?
  rm -f "$PROMPT_FILE"
  return "$status"
}
trap cleanup EXIT

cat > "$PROMPT_FILE" <<EOF_PROMPT
# AI task execution

Read \`AGENTS.md\` first.

Task file: \`${TASK_FILE}\`
Execution plan: \`${PLAN_FILE}\`
Expected task branch: \`${BRANCH}\`

Execute the task exactly as specified.

Rules:
1. Stay strictly within the task's Scope section.
2. Implement every Requirement and Acceptance Criterion.
3. Do not expand the task or invent adjacent product behavior.
4. Run every command in the Validation section.
5. Finish with the complete \`Output Required\` report.
6. If a requirement cannot be completed as specified, stop and report the blocker instead of improvising outside scope.
EOF_PROMPT

if [ "$MODE" = "continue-claude" ]; then
  cat >> "$PROMPT_FILE" <<'EOF_HANDOFF'

# Claude handoff

Claude Code has already started implementing this task. Continue that work instead of restarting it.

Before editing:
1. Read the task and execution plan.
2. Inspect the repository and all existing changes, including untracked files.
3. Map the current implementation to the task's Requirements and Acceptance Criteria.
4. Determine explicitly what is already complete, partially complete, and still missing.

Continuation rules:
- Treat existing changes as intentional unless they clearly violate the task, architecture, or tests.
- Preserve correct work already completed by Claude.
- Do not rewrite, reformat, or revert existing code merely to impose a different style.
- Finish only the missing or incorrect parts of the requested task.
- Check both staged and unstaged changes.
- Inspect the contents of untracked files listed below.
- Ensure tests validate the requested behavior and acceptance criteria, not merely the current implementation.
- Run the full task validation after completing the remaining work.

The Git snapshot below is context captured at handoff time. Verify it against the live working tree before making changes.
EOF_HANDOFF

  {
    printf '\n## Git status\n\n```text\n'
    git status --short --branch
    printf '```\n\n## Diff summary\n\n```text\n'
    git diff --stat
    git diff --cached --stat
    printf '```\n\n## Unstaged diff\n\n```diff\n'
    git diff --no-ext-diff --unified=3
    printf '```\n\n## Staged diff\n\n```diff\n'
    git diff --cached --no-ext-diff --unified=3
    printf '```\n\n## Untracked files\n\n```text\n'
    git ls-files --others --exclude-standard
    printf '```\n'
  } >> "$PROMPT_FILE"
fi

info "starting Codex task execution"
# Codex officially supports prompts via stdin in non-interactive exec mode.
codex exec "$@" - < "$PROMPT_FILE"
