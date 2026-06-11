#!/usr/bin/env bash
set -euo pipefail

mkdir -p docs/ai-results

git diff > docs/ai-results/latest.diff
git diff --stat > docs/ai-results/latest.diffstat.txt

codex "Read AGENTS.md and review the current git diff. Do not modify files. Focus on architecture, privacy, tests, API contract consistency, and unnecessary changes."
