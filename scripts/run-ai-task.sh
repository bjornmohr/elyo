#!/usr/bin/env bash
#
# run-ai-task.sh — execute an ELYO-91 prompt file with Claude Code
# following docs/ai-tasks/2026-07-19-00-elyo-91-execution-plan.md.
#
# Usage:
#   scripts/run-ai-task.sh <task-number> [options]
#
#   <task-number>   e.g. 1, 01, 8a, 08a, 17
#
# Options:
#   --continue      Re-enter an existing task branch and resume the last
#                   Claude session (review iteration, protocol step 5).
#   --dry-run       Show what would happen, run nothing.
#   --force         Skip the clean-working-tree check.
#   --date YYYY-MM-DD
#                   Disambiguate if the same task number exists for
#                   multiple prompt-series dates.
#
# Protocol implemented (see execution plan, "Execution protocol"):
#   1. resolve prompt file from task number
#   2. require clean working tree
#   3. create branch elyo-91/<NN>-<slug> (or check it out with --continue)
#   4. start a fresh, interactive Claude Code session with the standard
#      instruction (or resume with --continue)
# Review gate, merge and Jira update remain manual by design.

set -euo pipefail

TASKS_DIR="docs/ai-tasks"
BRANCH_PREFIX="${RUN_AI_TASK_BRANCH_PREFIX:-elyo-91}"
PLAN_GLOB="*-00-*execution-plan.md"

err()  { printf 'error: %s\n' "$*" >&2; exit 1; }
info() { printf '==> %s\n' "$*"; }

# --- args -------------------------------------------------------------------

TASK_RAW=""
CONTINUE=0
DRY_RUN=0
FORCE=0
DATE_FILTER=""

while [ $# -gt 0 ]; do
  case "$1" in
    --continue) CONTINUE=1 ;;
    --dry-run)  DRY_RUN=1 ;;
    --force)    FORCE=1 ;;
    --date)     shift; DATE_FILTER="${1:-}" ;;
    -h|--help)  sed -n '2,26p' "$0"; exit 0 ;;
    -*)         err "unknown option: $1" ;;
    *)          [ -n "$TASK_RAW" ] && err "only one task number allowed"; TASK_RAW="$1" ;;
  esac
  shift
done

[ -n "$TASK_RAW" ] || err "missing task number (e.g. 04 or 8a). See --help."

# --- locate repo root -------------------------------------------------------

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || err "not inside a git repository"
cd "$REPO_ROOT"
[ -d "$TASKS_DIR" ] || err "$TASKS_DIR not found"

# --- normalize task number: 1 -> 01, 8a -> 08a ------------------------------

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

[ "$TASK_NN" = "00" ] && err "task 00 is the execution plan itself, not a runnable task"

# --- determine series date --------------------------------------------------
# Default: the newest prompt series, i.e. the latest date that has an
# execution plan file (…-00-…execution-plan.md). Override with --date.

if [ -z "$DATE_FILTER" ]; then
  LATEST_PLAN="$(ls "$TASKS_DIR"/*-00-*execution-plan.md 2>/dev/null | sort | tail -n 1)"
  [ -n "$LATEST_PLAN" ] || err "no execution plan (*-00-*execution-plan.md) found in $TASKS_DIR"
  DATE_FILTER="$(basename "$LATEST_PLAN" | cut -d- -f1-3)"
  info "series    : $DATE_FILTER (latest execution plan; override with --date)"
fi

# --- find the prompt file ---------------------------------------------------

DATE_PATTERN="$DATE_FILTER"

MATCHES=()
for f in "$TASKS_DIR"/${DATE_PATTERN}-"$TASK_NN"-*.md; do
  [ -e "$f" ] && MATCHES+=("$f")
done

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

# --- find the matching execution plan ---------------------------------------

PLAN_FILE=""
for f in "$TASKS_DIR"/${SERIES_DATE}-00-*.md; do
  [ -e "$f" ] && PLAN_FILE="$f" && break
done
[ -n "$PLAN_FILE" ] || err "no execution plan (${SERIES_DATE}-00-*) found next to $TASK_FILE"

info "task file : $TASK_FILE"
info "plan file : $PLAN_FILE"
info "branch    : $BRANCH"

# --- preconditions ----------------------------------------------------------

command -v claude >/dev/null 2>&1 || err "claude CLI not found in PATH"

if [ "$FORCE" -ne 1 ] && [ -n "$(git status --porcelain)" ]; then
  err "working tree not clean — commit/stash first, or use --force"
fi

BRANCH_EXISTS=0
git show-ref --verify --quiet "refs/heads/$BRANCH" && BRANCH_EXISTS=1

# --- claude instruction (per execution plan, step 2) ------------------------

CLAUDE_PROMPT="Execute the task in ${TASK_FILE} exactly as specified. Read AGENTS.md first. \
Follow the execution protocol in ${PLAN_FILE}: stay strictly within the task's Scope section, \
implement all Requirements, run every command in the Validation section, and finish with the \
full 'Output Required' report. If anything cannot be completed as specified, stop and report \
instead of improvising outside Scope."

# --- dry run ----------------------------------------------------------------

if [ "$DRY_RUN" -eq 1 ]; then
  info "dry run — would do:"
  if [ "$CONTINUE" -eq 1 ]; then
    printf '  git checkout %s\n' "$BRANCH"
    printf '  claude --continue\n'
  else
    printf '  git checkout -b %s\n' "$BRANCH"
    printf '  claude "%s"\n' "$CLAUDE_PROMPT"
  fi
  exit 0
fi

# --- branch handling --------------------------------------------------------

if [ "$CONTINUE" -eq 1 ]; then
  [ "$BRANCH_EXISTS" -eq 1 ] || err "--continue: branch $BRANCH does not exist yet"
  info "checking out existing branch (review iteration)"
  git checkout "$BRANCH"
  info "resuming last Claude session"
  exec claude --continue
fi

if [ "$BRANCH_EXISTS" -eq 1 ]; then
  err "branch $BRANCH already exists — use --continue for review iterations, or delete it for a clean restart"
fi

info "creating branch from $(git rev-parse --abbrev-ref HEAD) ($(git rev-parse --short HEAD))"
git checkout -b "$BRANCH"

info "starting fresh Claude Code session"
exec claude "$CLAUDE_PROMPT"
