#!/usr/bin/env bash
set -euo pipefail

BASE_BRANCH=""
RUN_VALIDATION="false"

for arg in "$@"; do
  case "$arg" in
    --validate)
      RUN_VALIDATION="true"
      ;;
    *)
      BASE_BRANCH="$arg"
      ;;
  esac
done

OUT_DIR="docs/ai-handoff"
mkdir -p "$OUT_DIR"

CURRENT_BRANCH="$(git branch --show-current 2>/dev/null || true)"

STATUS_FILE="$OUT_DIR/current-status.md"
DIFF_FILE="$OUT_DIR/current-diff.patch"
DIFF_STAT_FILE="$OUT_DIR/current-diff-stat.txt"
BRANCH_SUMMARY_FILE="$OUT_DIR/current-branch-summary.md"

{
  echo "# ELYO Current Handoff"
  echo
  echo "## Date"
  date
  echo
  echo "## Working Directory"
  pwd
  echo
  echo "## Git Branch"
  echo "${CURRENT_BRANCH:-unknown}"
  echo
  echo "## Base Branch"
  if [ -n "$BASE_BRANCH" ]; then
    echo "$BASE_BRANCH"
  else
    echo "none, using working-tree diff"
  fi
  echo
  echo "## Git Status"
  git status --short 2>/dev/null || true
  echo
  echo "## Recent Commits"
  git log --oneline -10 2>/dev/null || true

  if [ -n "$BASE_BRANCH" ]; then
    echo
    echo "## Task Branch Commits"
    git log --oneline "$BASE_BRANCH"..HEAD 2>/dev/null || true
  fi

  echo
  echo "## Diff Mode"
  if [ -n "$BASE_BRANCH" ]; then
    echo "Branch diff: $BASE_BRANCH...HEAD"
  else
    echo "Working-tree diff: git diff"
  fi

  if [ "$RUN_VALIDATION" = "true" ]; then
    echo
    echo "## Docker Compose Config Check"
    docker compose config >/tmp/elyo-compose-check.txt 2>&1 && echo "docker compose config: OK" || cat /tmp/elyo-compose-check.txt

    echo
    echo "## Laravel Routes"
    docker compose exec -T api php artisan route:list 2>/dev/null || true

    echo
    echo "## Laravel Tests"
    docker compose exec -T api php artisan test 2>/dev/null || true

    echo
    echo "## Angular Build"
    docker compose exec -T web npm run build 2>/dev/null || true
  else
    echo
    echo "## Validation"
    echo "Skipped. Run with --validate to include Docker config, route:list, Laravel tests, and Angular build."
  fi
} > "$STATUS_FILE"

{
  echo "# ELYO Branch Summary"
  echo
  echo "## Current Branch"
  echo "${CURRENT_BRANCH:-unknown}"
  echo
  echo "## Base Branch"
  if [ -n "$BASE_BRANCH" ]; then
    echo "$BASE_BRANCH"
  else
    echo "none"
  fi
  echo
  echo "## Git Status"
  git status --short 2>/dev/null || true

  if [ -n "$BASE_BRANCH" ]; then
    echo
    echo "## Commits Since Base"
    git log --oneline "$BASE_BRANCH"..HEAD 2>/dev/null || true
  fi
} > "$BRANCH_SUMMARY_FILE"

if [ -n "$BASE_BRANCH" ]; then
  git diff "$BASE_BRANCH"...HEAD > "$DIFF_FILE" || true
  git diff --stat "$BASE_BRANCH"...HEAD > "$DIFF_STAT_FILE" || true
else
  git diff > "$DIFF_FILE" || true
  git diff --stat > "$DIFF_STAT_FILE" || true
fi

echo "Created:"
echo "- $STATUS_FILE"
echo "- $BRANCH_SUMMARY_FILE"
echo "- $DIFF_FILE"
echo "- $DIFF_STAT_FILE"

if [ "$RUN_VALIDATION" != "true" ]; then
  echo
  echo "Validation skipped. Use:"
  echo "  $0 ${BASE_BRANCH:-<base-branch>} --validate"
fi