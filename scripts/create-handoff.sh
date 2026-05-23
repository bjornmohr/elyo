#!/usr/bin/env bash
set -euo pipefail

mkdir -p docs/ai-handoff

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
  git branch --show-current 2>/dev/null || true
  echo
  echo "## Git Status"
  git status --short 2>/dev/null || true
  echo
  echo "## Recent Commits"
  git log --oneline -10 2>/dev/null || true
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
} > docs/ai-handoff/current-status.md

git diff > docs/ai-handoff/current-diff.patch || true
git diff --stat > docs/ai-handoff/current-diff-stat.txt || true

echo "Created:"
echo "- docs/ai-handoff/current-status.md"
echo "- docs/ai-handoff/current-diff.patch"
echo "- docs/ai-handoff/current-diff-stat.txt"
