#!/usr/bin/env bash
#
# run-ai-task.sh — run one ELYO work package with Claude Code or Codex CLI,
# then cross-review it with the other agent and fix the critical findings.
#
# Usage:
#   scripts/run-ai-task.sh <package> [options]
#
#   <package>   Package number: 1, 01, 16 (or legacy 8a). Not 00 (that is the plan).
#
# Options:
#   --agent claude|codex   Implementing agent. Default: claude.
#   --stage N              Run only stage N of the package (see "Umsetzung in Etappen").
#                          Recommended for packages with more than 5 stages.
#   --no-review            Skip the cross-review phase.
#   --no-fix               Run the review, skip the fix phase.
#   --review-only          Skip implementation, run review (+fix) on the current branch.
#   --tier T --effort E    Override implement_tier / implement_effort from the task file.
#                          Effort: low|medium|high|xhigh|max (codex caps at high).
#   --review-tier T
#   --review-effort E      Override review_tier / review_effort.
#   --plain                Disable caveman-ultra output style.
#   --baseline             Record a test baseline before implementing.
#   --continue             Resume work on an existing package branch.
#   --yes                  Do not ask for confirmation at the preflight gate.
#   --dry-run              Print the plan, run nothing.
#   --force                Skip the clean-working-tree check.
#   --date YYYY-MM-DD      Disambiguate if a package number exists in several series.
#   -h, --help             This help.
#
# Environment:
#   RUN_AI_TASK_BRANCH_PREFIX   Branch prefix. Default: findings
#   RUN_AI_CLAUDE_MODEL_HIGH|STANDARD|FAST   Default: opus / sonnet / haiku
#   RUN_AI_CODEX_MODEL_HIGH|STANDARD|FAST    Default: gpt-5.6-sol / -terra / -luna
#   RUN_AI_CLAUDE_ARGS / RUN_AI_CODEX_ARGS   Extra CLI arguments
#   RUN_AI_SKIP_PREFLIGHT=1                  Skip the docker/api-tooling check
#   RUN_AI_CODEX_EXEC                        Non-interactive codex command.
#                                            Default: "codex exec"
#
# Flow:
#   preflight -> branch -> implement (interactive TUI; quit it when the stage
#                                     is done, the script then continues)
#             -> handoff snapshot
#             -> cross-review by the other agent (headless, no TUI; its report
#                is captured into docs/ai-reviews/)
#             -> fix critical findings (interactive TUI — it touches
#                production code, so you watch it)
#
# Cross-review rule: Codex implements -> Claude reviews.
#                    Claude implements -> Codex reviews.

set -euo pipefail

TASKS_DIR="docs/ai-tasks"
REVIEW_DIR="docs/ai-reviews"
RESULT_DIR="docs/ai-results"
BRANCH_PREFIX="${RUN_AI_TASK_BRANCH_PREFIX:-findings}"

err()   { printf '\033[31merror:\033[0m %s\n' "$*" >&2; exit 1; }
info()  { printf '\033[36m==>\033[0m %s\n' "$*"; }
warn()  { printf '\033[33mwarn:\033[0m %s\n' "$*" >&2; }
head2() { printf '\n\033[1m%s\033[0m\n' "$*"; }

# ---------------------------------------------------------------------------
# args
# ---------------------------------------------------------------------------

TASK_RAW=""
AGENT="claude"
STAGE=""
DO_REVIEW=1
DO_FIX=1
REVIEW_ONLY=0
TIER_OVERRIDE=""
EFFORT_OVERRIDE=""
REVIEW_TIER_OVERRIDE=""
REVIEW_EFFORT_OVERRIDE=""
CAVEMAN=1
BASELINE=0
CONTINUE=0
ASSUME_YES=0
DRY_RUN=0
FORCE=0
DATE_FILTER=""

while [ $# -gt 0 ]; do
  case "$1" in
    --agent)         shift; AGENT="${1:-}" ;;
    --stage)         shift; STAGE="${1:-}" ;;
    --no-review)     DO_REVIEW=0; DO_FIX=0 ;;
    --no-fix)        DO_FIX=0 ;;
    --review-only)   REVIEW_ONLY=1 ;;
    --tier)          shift; TIER_OVERRIDE="${1:-}" ;;
    --effort)        shift; EFFORT_OVERRIDE="${1:-}" ;;
    --review-tier)   shift; REVIEW_TIER_OVERRIDE="${1:-}" ;;
    --review-effort) shift; REVIEW_EFFORT_OVERRIDE="${1:-}" ;;
    --plain)         CAVEMAN=0 ;;
    --baseline)      BASELINE=1 ;;
    --continue)      CONTINUE=1 ;;
    --yes|-y)        ASSUME_YES=1 ;;
    --dry-run)       DRY_RUN=1 ;;
    --force)         FORCE=1 ;;
    --date)          shift; DATE_FILTER="${1:-}" ;;
    -h|--help)       sed -n '2,44p' "$0" | sed 's/^#\{1\} \{0,1\}//'; exit 0 ;;
    -*)              err "unknown option: $1" ;;
    *)               [ -n "$TASK_RAW" ] && err "only one package number allowed"; TASK_RAW="$1" ;;
  esac
  shift
done

[ -n "$TASK_RAW" ] || err "missing package number (e.g. 04). See --help."

case "$AGENT" in
  claude|codex) : ;;
  *) err "--agent must be 'claude' or 'codex', got: $AGENT" ;;
esac

REVIEW_AGENT="claude"
[ "$AGENT" = "claude" ] && REVIEW_AGENT="codex"

# ---------------------------------------------------------------------------
# repo + package resolution
# ---------------------------------------------------------------------------

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)" || err "not inside a git repository"
cd "$REPO_ROOT"
[ -d "$TASKS_DIR" ] || err "$TASKS_DIR not found"

TASK_LOWER="$(printf '%s' "$TASK_RAW" | tr '[:upper:]' '[:lower:]')"
NUM="$(printf '%s' "$TASK_LOWER" | sed -E 's/^([0-9]+).*/\1/')"
SUFFIX="$(printf '%s' "$TASK_LOWER" | sed -E 's/^[0-9]+//')"

case "$NUM" in ''|*[!0-9]*) err "invalid package number: $TASK_RAW" ;; esac
case "$SUFFIX" in ''|[a-z]) : ;; *) err "invalid suffix in: $TASK_RAW" ;; esac

TASK_NN="$(printf '%02d' "$((10#$NUM))")$SUFFIX"
[ "$TASK_NN" = "00" ] && err "00 is the execution plan, not a runnable package"

if [ -z "$DATE_FILTER" ]; then
  LATEST_PLAN="$(ls "$TASKS_DIR"/*-00-*execution-plan.md 2>/dev/null | sort | tail -n 1)"
  [ -n "$LATEST_PLAN" ] || err "no execution plan (*-00-*execution-plan.md) in $TASKS_DIR"
  DATE_FILTER="$(basename "$LATEST_PLAN" | cut -d- -f1-3)"
fi

MATCHES=()
for f in "$TASKS_DIR"/${DATE_FILTER}-"$TASK_NN"-*.md; do
  [ -e "$f" ] && MATCHES+=("$f")
done
[ "${#MATCHES[@]}" -eq 0 ] && err "no package file for $TASK_NN in $TASKS_DIR (series $DATE_FILTER)"
if [ "${#MATCHES[@]}" -gt 1 ]; then
  printf 'error: package %s is ambiguous:\n' "$TASK_NN" >&2
  printf '  %s\n' "${MATCHES[@]}" >&2
  err "disambiguate with --date YYYY-MM-DD"
fi

TASK_FILE="${MATCHES[0]}"
BASENAME="$(basename "$TASK_FILE" .md)"
SERIES_DATE="$(printf '%s' "$BASENAME" | cut -d- -f1-3)"
SLUG="$(printf '%s' "$BASENAME" | sed -E "s/^${SERIES_DATE}-${TASK_NN}-//")"
BRANCH="${BRANCH_PREFIX}/${TASK_NN}-${SLUG}"
[ -n "$STAGE" ] && BRANCH="${BRANCH}-s${STAGE}"

PLAN_FILE=""
for f in "$TASKS_DIR"/${SERIES_DATE}-00-*.md; do
  [ -e "$f" ] && PLAN_FILE="$f" && break
done
[ -n "$PLAN_FILE" ] || err "no execution plan (${SERIES_DATE}-00-*) next to $TASK_FILE"

PKG_TITLE="$(head -n 1 "$TASK_FILE" | sed 's/^# *//')"
STAGE_COUNT="$(grep -c '^### Etappe' "$TASK_FILE" || true)"

# ---------------------------------------------------------------------------
# parse the ai-run metadata block
# ---------------------------------------------------------------------------

meta() {
  awk -v key="$1" '
    /^```ai-run/ { inb=1; next }
    inb && /^```/ { exit }
    inb {
      line = $0
      sub(/^[ \t]+/, "", line); sub(/[ \t]+$/, "", line)
      idx = index(line, ":")
      if (idx == 0) next
      k = substr(line, 1, idx - 1)
      v = substr(line, idx + 1)
      sub(/^[ \t]+/, "", v); sub(/[ \t]+$/, "", v)
      if (k == key) { print v; exit }
    }
  ' "$TASK_FILE"
}

COMPLEXITY="$(meta complexity)"
IMPL_TIER="${TIER_OVERRIDE:-$(meta implement_tier)}"
IMPL_EFFORT="${EFFORT_OVERRIDE:-$(meta implement_effort)}"
REV_TIER="${REVIEW_TIER_OVERRIDE:-$(meta review_tier)}"
REV_EFFORT="${REVIEW_EFFORT_OVERRIDE:-$(meta review_effort)}"
BLOCKED_BY="$(meta blocked_by)"
DEPENDS_ON="$(meta depends_on)"

[ -n "$IMPL_TIER" ]   || err "no ai-run block found (implement_tier missing) in $TASK_FILE"
[ -n "$IMPL_EFFORT" ] || err "ai-run block incomplete: implement_effort missing"
[ -n "$REV_TIER" ]    || REV_TIER="standard"
[ -n "$REV_EFFORT" ]  || REV_EFFORT="medium"

validate_tier()   { case "$1" in high|standard|fast) : ;; *) err "invalid tier '$1' (high|standard|fast)" ;; esac; }
validate_effort() {
  case "$1" in
    low|medium|high|xhigh|max) : ;;
    *) err "invalid effort '$1' (low|medium|high|xhigh|max)" ;;
  esac
  # codex only accepts low|medium|high; map the two extra claude levels down
  # so a shared ai-run block stays usable with either agent.
  :
}

validate_tier "$IMPL_TIER";  validate_effort "$IMPL_EFFORT"
validate_tier "$REV_TIER";   validate_effort "$REV_EFFORT"

# tier -> concrete model per agent; override via environment
model_for() {
  case "$1:$2" in
    claude:high)     printf '%s' "${RUN_AI_CLAUDE_MODEL_HIGH:-opus}" ;;
    claude:standard) printf '%s' "${RUN_AI_CLAUDE_MODEL_STANDARD:-sonnet}" ;;
    claude:fast)     printf '%s' "${RUN_AI_CLAUDE_MODEL_FAST:-haiku}" ;;
    codex:high)      printf '%s' "${RUN_AI_CODEX_MODEL_HIGH:-gpt-5.6-sol}" ;;
    codex:standard)  printf '%s' "${RUN_AI_CODEX_MODEL_STANDARD:-gpt-5.6-terra}" ;;
    codex:fast)      printf '%s' "${RUN_AI_CODEX_MODEL_FAST:-gpt-5.6-luna}" ;;
  esac
}

IMPL_MODEL="$(model_for "$AGENT" "$IMPL_TIER")"
REV_MODEL="$(model_for "$REVIEW_AGENT" "$REV_TIER")"
[ -n "$IMPL_MODEL" ] || err "no model mapped for $AGENT/$IMPL_TIER"
[ -n "$REV_MODEL" ]  || err "no model mapped for $REVIEW_AGENT/$REV_TIER"

# Claude Code has no reasoning-effort flag; effort becomes a thinking directive
# in the prompt. Codex uses -c model_reasoning_effort.

# ---------------------------------------------------------------------------
# preflight
# ---------------------------------------------------------------------------

# Argument-level validation first — these must fail even in --dry-run.
if [ -n "$STAGE" ]; then
  case "$STAGE" in ''|*[!0-9]*) err "--stage must be a number" ;; esac
  { [ "$STAGE" -ge 1 ] && [ "$STAGE" -le "$STAGE_COUNT" ]; } \
    || err "--stage $STAGE out of range (package has $STAGE_COUNT stages)"
fi

# Environment checks. In --dry-run these downgrade to warnings so the plan can be
# inspected on a machine that has neither CLI installed.
CLI_MISSING=""
command -v "$AGENT" >/dev/null 2>&1 || CLI_MISSING="$AGENT"
if [ "$DO_REVIEW" -eq 1 ] && ! command -v "$REVIEW_AGENT" >/dev/null 2>&1; then
  CLI_MISSING="${CLI_MISSING:+$CLI_MISSING, }$REVIEW_AGENT"
fi

if [ -n "$CLI_MISSING" ] && [ "$DRY_RUN" -ne 1 ]; then
  err "CLI not found in PATH: $CLI_MISSING (use --no-review if only the reviewer is missing)"
fi

if [ "$DRY_RUN" -ne 1 ] && [ "$FORCE" -ne 1 ] && [ "$CONTINUE" -ne 1 ] \
   && [ "$REVIEW_ONLY" -ne 1 ] && [ -n "$(git status --porcelain)" ]; then
  err "working tree not clean — commit/stash first, or use --force"
fi

BRANCH_EXISTS=0
BRANCH_EXISTS_NOTE=""
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
  BRANCH_EXISTS=1
  if [ "$CONTINUE" -eq 1 ]; then
    BRANCH_EXISTS_NOTE="exists — resuming (--continue)"
  elif [ "$REVIEW_ONLY" -eq 1 ]; then
    BRANCH_EXISTS_NOTE="exists — review only"
  else
    BRANCH_EXISTS_NOTE="ALREADY EXISTS — run will abort; use --continue"
  fi
fi

DOCKER_OK="skipped"
if [ "${RUN_AI_SKIP_PREFLIGHT:-0}" != "1" ]; then
  if command -v docker >/dev/null 2>&1 \
     && [ -n "$(docker compose ps --quiet api-tooling 2>/dev/null)" ]; then
    DOCKER_OK="api-tooling running"
  else
    DOCKER_OK="api-tooling NOT running — validation commands will fail"
  fi
fi

# ---------------------------------------------------------------------------
# preflight report + gate
# ---------------------------------------------------------------------------

head2 "Package"
printf '  %-16s %s\n' "file"       "$TASK_FILE"
printf '  %-16s %s\n' "title"      "$PKG_TITLE"
printf '  %-16s %s\n' "plan"       "$PLAN_FILE"
printf '  %-16s %s\n' "branch"     "$BRANCH"
printf '  %-16s %s\n' "stages"     "$STAGE_COUNT${STAGE:+  (running stage $STAGE only)}"
printf '  %-16s %s\n' "complexity" "${COMPLEXITY:-n/a}"

head2 "Execution parameters"
printf '  %-16s %s\n' "implement" "$AGENT  model=$IMPL_MODEL  tier=$IMPL_TIER  effort=$IMPL_EFFORT"
if [ "$DO_REVIEW" -eq 1 ]; then
  printf '  %-16s %s\n' "review"  "$REVIEW_AGENT  model=$REV_MODEL  tier=$REV_TIER  effort=$REV_EFFORT"
else
  printf '  %-16s %s\n' "review"  "skipped (--no-review)"
fi
printf '  %-16s %s\n' "fix phase" "$([ "$DO_FIX" -eq 1 ] && echo "$AGENT (implementing agent)" || echo 'skipped')"
printf '  %-16s %s\n' "style"     "$([ "$CAVEMAN" -eq 1 ] && echo 'caveman ultra' || echo 'plain')"
printf '  %-16s %s\n' "docker"    "$DOCKER_OK"
[ -n "$CLI_MISSING" ] && printf '  %-16s \033[33m%s\033[0m\n' "cli missing" "$CLI_MISSING"

if [ -n "$BRANCH_EXISTS_NOTE" ]; then
  printf '  %-16s %s\n' "branch state" "$BRANCH_EXISTS_NOTE"
fi

if [ -n "$BLOCKED_BY" ] && [ "$BLOCKED_BY" != "-" ]; then
  head2 "Open decisions required"
  printf '  Blocked by: %s\n' "$BLOCKED_BY"
  # U-questions are the cross-package list answered in package 16.
  # Anything else is package-local and is answered in this package's own stage.
  if printf '%s' "$BLOCKED_BY" | grep -qE '(^|[ ,])U[0-9]+'; then
    printf '  U-questions are answered in package 16.\n'
  fi
  printf '  Stages depending on these must be skipped and reported, not guessed.\n'
  printf '  The agent is instructed accordingly.\n'
fi
if [ -n "$DEPENDS_ON" ] && [ "$DEPENDS_ON" != "-" ]; then
  printf '\n  Recommended order: run package %s first (plan, "Empfohlene Reihenfolge").\n' "$DEPENDS_ON"
fi

# stages holding a decision that is yours, not the agent's
OWNER_ROWS="$(awk '/^### Entscheidungspunkte/,/^## Goal/' "$TASK_FILE" \
  | grep -E '^\| [0-9]+ \|.*Björn' || true)"
if [ -n "$OWNER_ROWS" ]; then
  head2 "Your decisions — these stages will be skipped"
  printf '%s\n' "$OWNER_ROWS" | while IFS='|' read -r _ st what _; do
    # strip markdown emphasis and backticks, wrap long text at 66 chars
    what="$(printf '%s' "$what" | sed 's/^ *//;s/ *$//;s/\*\*//g;s/`//g')"
    printf '  Etappe %-3s %s\n' "$(echo "$st" | tr -d ' ')" \
      "$(printf '%s' "$what" | fold -s -w 66 | sed '2,$s/^/             /')"
  done
  printf '\n  Decide these yourself, or let the agent write up the options and\n'
  printf '  come back to them. It will not decide them on its own.\n'
fi

if [ "$DRY_RUN" -eq 1 ]; then
  head2 "Dry run — nothing executed"
  exit 0
fi

if [ "$ASSUME_YES" -ne 1 ] && [ -t 0 ]; then
  printf '\nProceed with these parameters? [y/N] '
  read -r reply
  case "$reply" in [yY]*) : ;; *) err "aborted" ;; esac
fi

mkdir -p "$REVIEW_DIR" "$RESULT_DIR"
RUN_ID="$(date +%Y%m%d-%H%M%S)"
RUN_TAG="${TASK_NN}${STAGE:+-s$STAGE}"
REVIEW_FILE="${REVIEW_DIR}/${SERIES_DATE}-${RUN_TAG}-${REVIEW_AGENT}-review-${RUN_ID}.md"

# ---------------------------------------------------------------------------
# prompt fragments
# ---------------------------------------------------------------------------

style_block() {
  [ "$CAVEMAN" -eq 1 ] || return 0
  cat <<'EOF'

## Output style

Use caveman ultra for all prose output (skill: `.agents/skills/caveman/SKILL.md`,
intensity `ultra`). Strip filler, articles, hedging. State each fact once.

Never compress: code, diffs, file paths, class and method names, CLI commands,
error strings, commit messages, or the required report sections. Technical
accuracy beats brevity every time.
EOF
}

plan_semantics() {
  cat <<EOF

## How to read this package

The package file is not a ticket. It is a work package from \`${PLAN_FILE}\`, which
bundles findings from the Confluence documentation (space ELYO, "Technische
Dokumentation", chapter 14 "Bekannte Inkonsistenzen und technische Risiken").

What each part means:

- **Arbeitsregeln** near the top: six rules that override everything below them. Read
  them first. Rule 2 (report instead of reinterpreting) and rule 4 (delete nothing
  without an explicit order) are the two that get violated most often.
- **Entscheidungspunkte**: a table of decisions this package contains. Rows marked
  **Björn** are not yours to make. Write up the options and their consequences, mark
  the stage blocked, move on. Rows marked *Agent* you decide — with the reasoning in
  the commit message.
- **Befunde** in the header: finding IDs from chapter 14 (A1, J17, ...). Every change
  must trace back to one of them. Do not fix things outside this list.
- **Context**: the verified current state, quoted from the code. The documentation was
  written against commit \`56b4a53\` — re-check anything you are about to change.
- **Umsetzung in Etappen**: the work, split into stages. **One stage = one commit.**
  Stages are ordered; earlier ones can be preconditions for later ones. Each stage
  names its finding IDs and ends with an **Abnahme** (acceptance criterion).
- **Achtung** callouts inside stages: traps found during the code analysis. Read them
  before touching that area. They are not decoration.
- **Out of Scope**: belongs to another package. Do not touch it, even if it looks broken.
- **Hard constraints**: invariants. Breaking one fails the package regardless of the rest.
- **Review-Checkliste**: what the reviewer will check. Satisfy it before you finish.
- **Expected output**: the report you must produce.

## Non-negotiable rules

1. Stay inside Scope. If a fix needs something Out of Scope, stop and report it.
2. One stage, one commit. Message: \`<type>(<area>): <what>\` plus the finding IDs.
3. Meet each stage's Abnahme before its commit, including its test.
4. Never edit a reviewed migration baseline. Schema changes are new migration files in
   the matching domain directory.
5. No \`migrate:fresh\`, no \`db:wipe\`, no \`docker compose down -v\`.
6. ADR-001 / ADR-003 bind you: no Identity-Health join, no health read path for the
   company runtime, mapping access only through \`MappingService\`, audit stays
   append-only and synchronous inside the domain transaction.
7. Tests run in the container: \`docker compose exec api-tooling php artisan test\`.
8. Partial unique indexes only via \`DB::statement()\` — the Blueprint fluent
   \`unique()->whereNull()\` is a silent no-op that emits a full unique index.
9. If a stage is blocked by an open decision (U-question) or by a **Björn** row in
   Entscheidungspunkte, **do not guess**. Skip it, report it, continue.
10. Verify before you change. Every claim in the package is a finding from commit
   \`56b4a53\`, not from your branch. If the code no longer matches the description —
   already fixed, moved, renamed, never was that way — stop that stage, record the
   actual state, continue with the next one. Do not substitute a different problem.
11. Aborting a stage is a valid outcome and must be reported as one. Five clean stages
   and three reported blocks beat eight stages where three were guessed.
EOF
}

decision_note() {
  local tbl
  tbl="$(awk '/^### Entscheidungspunkte/,/^## Goal/' "$TASK_FILE" | grep -E '^\| [0-9]+ \|' || true)"
  [ -n "$tbl" ] || return 0
  cat <<EOF

## Decision points in this package

| Etappe | Entscheidung | Wer |
|---|---|---|
${tbl}

Rows marked **Björn** are out of your authority. Produce the options and their
consequences in the report, mark the stage blocked, continue with the next stage.
EOF
}

blocked_note() {
  { [ -n "$BLOCKED_BY" ] && [ "$BLOCKED_BY" != "-" ]; } || return 0
  cat <<EOF

## Blocked stages

Open decisions blocking this package: **${BLOCKED_BY}**.
Listed in the plan under "Offene Entscheidungen", answered in package 16.
Stages depending on them are skipped and reported, never guessed.
EOF
}

stage_note() {
  [ -n "$STAGE" ] || return 0
  cat <<EOF

## Scope of this run

Implement **only Etappe ${STAGE}**. Read the whole file for context, but change nothing
belonging to another stage. Finish with that stage's commit.
EOF
}

# ---------------------------------------------------------------------------
# agent runners — interactive, never exec (the review chain runs afterwards)
# ---------------------------------------------------------------------------

codex_effort() {
  # codex accepts low|medium|high only
  case "$1" in xhigh|max) printf 'high' ;; *) printf '%s' "$1" ;; esac
}

run_agent() {
  # run_agent <agent> <model> <effort> <prompt_file> [mode] [outfile]
  #   mode: interactive (default) | headless
  # Headless writes the agent's answer to <outfile> instead of opening a TUI,
  # so the chain continues without the user having to quit a session.
  local agent="$1" model="$2" effort="$3" prompt_file="$4"
  local mode="${5:-interactive}" outfile="${6:-}"
  local extra=()
  # Short argv, full instruction in the file. See new_prompt() for why.
  local kick="Read ${prompt_file} and follow it. It is the complete instruction for this run — read it first, before anything else."

  # NOTE: "${extra[@]}" on an empty array is an "unbound variable" error under
  # `set -u` in bash < 4.4 — which is what macOS ships (3.2). The
  # ${arr[@]+"${arr[@]}"} form expands to nothing instead of erroring.
  if [ "$agent" = "claude" ] && [ -n "${RUN_AI_CLAUDE_ARGS:-}" ]; then
    # shellcheck disable=SC2206
    extra=($RUN_AI_CLAUDE_ARGS)
  elif [ "$agent" = "codex" ] && [ -n "${RUN_AI_CODEX_ARGS:-}" ]; then
    # shellcheck disable=SC2206
    extra=($RUN_AI_CODEX_ARGS)
  fi

  if [ "$mode" = "headless" ]; then
    info "$agent (headless, model=$model effort=$effort) -> $outfile"
    info "prompt: $prompt_file"
    case "$agent" in
      claude)
        # NOTE: --permission-mode plan was tried here and is wrong for -p.
        # Plan mode is an interactive workflow — it drafts a plan and then
        # waits for the user to approve it (ExitPlanMode). There is no user
        # in headless mode, so the session stopped after the draft and never
        # wrote a real review; the "review file" contained a plan summary
        # ending in "Proceed to output final review report?". Caught by
        # actually reading a captured review file, not by reasoning about it.
        #
        # Fix: no --permission-mode at all, just block the tools a reviewer
        # has no business using. Edit/Write/NotebookEdit are the only tools
        # that mutate files directly, so those are what's disallowed. Bash
        # stays available on purpose — the review checklist requires actually
        # running the test/deptrac/check-grants commands to verify "still
        # green", not taking the implementer's word for it. Residual risk:
        # Bash can still write a file via redirection; there is no CLI switch
        # that closes that gap without also blocking the validation runs the
        # review needs. The report goes to stdout either way.
        claude -p --model "$model" --effort "$effort" \
          --disallowedTools "Edit,Write,NotebookEdit" \
          ${extra[@]+"${extra[@]}"} "$kick" > "$outfile"
        ;;
      codex)
        # `codex exec` is the non-interactive subcommand. Unverified against a
        # local install — override with RUN_AI_CODEX_EXEC if the syntax differs.
        # shellcheck disable=SC2086
        ${RUN_AI_CODEX_EXEC:-codex exec} -m "$model" \
          -c model_reasoning_effort="$(codex_effort "$effort")" \
          ${extra[@]+"${extra[@]}"} "$kick" > "$outfile"
        ;;
    esac
    return
  fi

  # --- interactive ---------------------------------------------------------
  # Interactive TUIs need to own the terminal's foreground process group.
  # A non-interactive bash script has job control OFF, so children stay in the
  # script's process group and never become the foreground group — the TUI
  # then paints its interface but cannot read a single keystroke. `set -m`
  # makes bash put each child in its own process group and hand it the
  # terminal. This is why the original script had to use `exec`; `set -m`
  # gives the same result and still lets the review chain run afterwards.
  local had_monitor=0
  case "$-" in *m*) had_monitor=1 ;; esac
  set -m

  # Only reach for /dev/tty if stdin is NOT already a terminal (e.g. the
  # script itself was piped). Redirecting when stdin is fine can hurt.
  local redir_tty=0
  if [ ! -t 0 ] && (: < /dev/tty) 2>/dev/null; then redir_tty=1; fi

  head2 "Interactive session — $agent"
  printf '  The agent does not close itself. When it reports the stage is\n'
  printf '  done, quit the session (Ctrl-D, or /exit). The script then\n'
  printf '  continues on its own with the next phase.\n\n'
  info "prompt: $prompt_file"

  case "$agent" in
    claude)
      info "claude --model $model --effort $effort"
      if [ "$redir_tty" -eq 1 ]; then
        claude --model "$model" --effort "$effort" \
          ${extra[@]+"${extra[@]}"} "$kick" < /dev/tty
      else
        claude --model "$model" --effort "$effort" \
          ${extra[@]+"${extra[@]}"} "$kick"
      fi
      ;;
    codex)
      local ce; ce="$(codex_effort "$effort")"
      info "codex -m $model -c model_reasoning_effort=$ce"
      if [ "$redir_tty" -eq 1 ]; then
        codex -m "$model" -c model_reasoning_effort="$ce" \
          ${extra[@]+"${extra[@]}"} "$kick" < /dev/tty
      else
        codex -m "$model" -c model_reasoning_effort="$ce" \
          ${extra[@]+"${extra[@]}"} "$kick"
      fi
      ;;
  esac

  [ "$had_monitor" -eq 1 ] || set +m
}

# Prompts are written to files, not passed on argv. A multi-kilobyte markdown
# string as a positional argument does not reliably reach the agent's TUI —
# the session opens and then just sits there. The file is kept afterwards so
# you can read exactly what the agent was told.
PROMPT_DIR="docs/ai-prompts/runs"
new_prompt() {
  local phase="$1" f
  mkdir -p "$PROMPT_DIR"
  f="${PROMPT_DIR}/${SERIES_DATE}-${TASK_NN}${STAGE:+-s$STAGE}-${phase}-$(date +%Y%m%d-%H%M%S).md"
  : > "$f"
  printf '%s' "$f"
}

# ---------------------------------------------------------------------------
# branch
# ---------------------------------------------------------------------------

BASE_BRANCH="$(git rev-parse --abbrev-ref HEAD)"

if [ "$REVIEW_ONLY" -eq 1 ]; then
  info "review-only on current branch: $BASE_BRANCH"
elif [ "$CONTINUE" -eq 1 ]; then
  [ "$BRANCH_EXISTS" -eq 1 ] || err "--continue: branch $BRANCH does not exist"
  git checkout "$BRANCH"
else
  [ "$BRANCH_EXISTS" -eq 1 ] \
    && err "branch $BRANCH exists — use --continue, or delete it for a clean restart"
  info "creating $BRANCH from $BASE_BRANCH ($(git rev-parse --short HEAD))"
  git checkout -b "$BRANCH"
fi

# ---------------------------------------------------------------------------
# optional test baseline
# ---------------------------------------------------------------------------

if [ "$BASELINE" -eq 1 ] && [ "$REVIEW_ONLY" -ne 1 ]; then
  BASELINE_FILE="${RESULT_DIR}/${RUN_TAG}-baseline-${RUN_ID}.txt"
  info "recording test baseline -> $BASELINE_FILE"
  {
    printf '# Baseline before package %s\n\n' "$TASK_NN"
    printf 'commit: %s\n\n' "$(git rev-parse HEAD)"
    docker compose exec -T api-tooling php artisan test 2>&1 || true
  } > "$BASELINE_FILE"
  info "baseline recorded — a red test here was already red before your changes"
fi

# ---------------------------------------------------------------------------
# implementation
# ---------------------------------------------------------------------------

if [ "$REVIEW_ONLY" -ne 1 ]; then
  IMPL_PROMPT="$(new_prompt implement)"
  {
    cat <<EOF
# ${PKG_TITLE}

Read \`AGENTS.md\` first.

- Package file: \`${TASK_FILE}\`
- Execution plan: \`${PLAN_FILE}\`
- Branch: \`${BRANCH}\` (already checked out)
- Stages in this package: ${STAGE_COUNT}

EOF
    plan_semantics
    stage_note
    blocked_note
    decision_note
    style_block
    cat <<EOF

## Finish with

The package's **Expected output** section, plus:
- stage-by-stage list of commits with their finding IDs
- which stages you skipped and why
- every place where the code did not match the package description (rule 2), with what
  you actually found
- whether a migration was needed and in which domain directory
- the filled-in Review-Checkliste
EOF
  } > "$IMPL_PROMPT"

  head2 "Implementation — $AGENT"
  run_agent "$AGENT" "$IMPL_MODEL" "$IMPL_EFFORT" "$IMPL_PROMPT" \
    || warn "agent exited non-zero — an interactive session's exit code is not a reliable success signal, continuing"
  info "implementation session ended"
fi

# ---------------------------------------------------------------------------
# handoff snapshot
# ---------------------------------------------------------------------------

if [ -x scripts/create-handoff.sh ]; then
  info "creating handoff snapshot"
  scripts/create-handoff.sh "${BASE_BRANCH:-}" >/dev/null 2>&1 || warn "handoff script failed (continuing)"
fi

if [ -z "$(git status --porcelain)" ] \
   && [ -z "$(git log --oneline "${BASE_BRANCH}..HEAD" 2>/dev/null)" ]; then
  warn "no changes and no commits on this branch — nothing to review"
  DO_REVIEW=0
  DO_FIX=0
fi

# ---------------------------------------------------------------------------
# scope check — which touched files are never mentioned in the package?
# ---------------------------------------------------------------------------

SCOPE_STRAYS=""
if [ -n "$(git log --oneline "${BASE_BRANCH}..HEAD" 2>/dev/null)" ]; then
  while IFS= read -r f; do
    [ -n "$f" ] || continue
    # ignore artefacts the run itself produces
    case "$f" in
      docs/ai-results/*|docs/ai-reviews/*|docs/ai-prompts/*|docs/handoff*|docs/ai-tasks/*) continue ;;
    esac
    # a file counts as in scope if its path, basename or class name appears in the package
    base="$(basename "$f")"
    stem="${base%.*}"
    if ! grep -qF -e "$f" -e "$base" -e "$stem" "$TASK_FILE"; then
      SCOPE_STRAYS="${SCOPE_STRAYS}${f}"$'\n'
    fi
  done <<< "$(git diff --name-only "${BASE_BRANCH}..HEAD" 2>/dev/null)"

  if [ -n "$SCOPE_STRAYS" ]; then
    head2 "Scope check — files not mentioned anywhere in the package"
    printf '%s' "$SCOPE_STRAYS" | sed 's/^/  /'
    printf '\n  Not automatically wrong: helper classes, new tests and new migrations\n'
    printf '  legitimately have names the package could not know. The reviewer is asked\n'
    printf '  to justify each one (Arbeitsregel 3).\n'
  else
    info "scope check clean — every touched file is named in the package"
  fi
fi

scope_note() {
  [ -n "$SCOPE_STRAYS" ] || return 0
  cat <<EOF

## Scope check (automatic)

These changed files are not mentioned anywhere in the package file:

\`\`\`
${SCOPE_STRAYS}\`\`\`

For each one, decide: legitimately implied by a stage (a new test, a new migration, an
extracted helper) — or scope creep under Arbeitsregel 3. Name the stage that justifies
it, or report it as a finding. Do not wave them through as a group.
EOF
}

# ---------------------------------------------------------------------------
# cross-review
# ---------------------------------------------------------------------------

if [ "$DO_REVIEW" -eq 1 ]; then
  REVIEW_PROMPT="$(new_prompt review)"
  {
    cat <<EOF
# Cross-review: ${PKG_TITLE}

\`${AGENT}\` implemented this package. You are the independent reviewer.
Read \`AGENTS.md\` first.

- Package file: \`${TASK_FILE}\`
- Execution plan: \`${PLAN_FILE}\`
- Branch under review: \`${BRANCH}\`
- Base: \`${BASE_BRANCH}\`


## What to review

Inspect the branch diff against the base, including untracked files.

In this order:

1. **Hard constraints** of the package — a violation fails it outright.
2. **ADR-001 / ADR-003**: no Identity-Health join, no health read path for the company
   runtime, mapping access only through \`MappingService\`, audit append-only and
   synchronous inside the domain transaction.
3. **Scope**: was anything changed that the package lists as Out of Scope?
4. **Abnahme** per stage: is each acceptance criterion actually met, with a test?
5. **Review-Checkliste** of the package: item by item.
6. **Migrations**: no edited baseline; new files in the correct domain directory;
   partial unique indexes via \`DB::statement()\`, never the Blueprint fluent.
7. **Tests**: do they assert behaviour or only the current implementation? Were failing
   tests silently skipped, deleted or weakened?
8. **Privacy and boundary suites**: still green? Any new leak surface?
9. **Guessing**: did the agent implement a stage blocked by an open decision
   (${BLOCKED_BY:-none}) or a **Björn** row in Entscheidungspunkte, instead of
   skipping it? This is always Critical.
10. **Unordered deletions**: any table, column, migration, class, route, endpoint or
   component removed without the stage explicitly ordering it? Also Critical.
11. **Invented findings**: does every commit trace back to a finding ID listed in the
   package header? A change that traces to nothing is scope creep, even if correct.
12. **Silent reinterpretation**: where the code did not match the package description,
   did the agent report it — or quietly fix a different problem instead?

## Do not

- Do not modify any file. This is a review.
- Do not restate what the diff does. Report what is wrong or risky.
EOF
    decision_note
    scope_note
    style_block
    cat <<EOF

## Required output

Write the review **to standard output**. Do not create or edit any file — the
script captures your output into \`${REVIEW_FILE}\`. Output nothing but the
report itself: no preamble, no "here is the review", no closing remarks.

Use exactly these sections:

\`\`\`markdown
# Review: package ${TASK_NN} (${AGENT} implemented, ${REVIEW_AGENT} reviewed)

## Verdict
accept | accept-with-fixes | reject

## Critical
Must be fixed before merge. Each: what, where (file:line), why it matters, which hard
constraint or ADR it breaks. If none, write "none".

## Major
Should be fixed, does not block merge on its own.

## Minor
Nits and cleanups.

## Scope
Anything changed outside the package's Scope.

## Test quality
Tests asserting implementation instead of behaviour; missing tests for an Abnahme.

## Checkliste
The package's Review-Checkliste, item by item, pass/fail with one line of evidence.
\`\`\`

Be specific. "Looks fine" is not a review. If you cannot verify something, say so.
EOF
  } > "$REVIEW_PROMPT"

  head2 "Cross-review — $REVIEW_AGENT (implemented by $AGENT)"
  mkdir -p "$REVIEW_DIR"
  # Headless: the review needs no input, and having to quit a second TUI is
  # what broke the chain before. The agent reads and reasons but may not edit
  # (claude: --disallowedTools blocks Edit/Write/NotebookEdit); its report
  # is captured here.
  run_agent "$REVIEW_AGENT" "$REV_MODEL" "$REV_EFFORT" "$REVIEW_PROMPT" \
    headless "$REVIEW_FILE" \
    || warn "reviewer exited non-zero — check $REVIEW_FILE"

  if [ -s "$REVIEW_FILE" ]; then
    info "review written: $REVIEW_FILE ($(wc -l < "$REVIEW_FILE" | tr -d ' ') lines)"
    VERDICT="$(awk '/^## Verdict/{getline; while ($0 ~ /^[[:space:]]*$/) getline; print; exit}' "$REVIEW_FILE" 2>/dev/null || true)"
    [ -n "$VERDICT" ] && info "verdict: $VERDICT"
    CRITICAL_COUNT="$(awk '/^## Critical/{f=1;next} /^## /{f=0} f' "$REVIEW_FILE" \
      | grep -cE '^[-*0-9]' || true)"
    info "critical items: ${CRITICAL_COUNT:-0}"
  else
    warn "review file is empty — fix phase runs without a review"
  fi
fi

# ---------------------------------------------------------------------------
# fix phase
# ---------------------------------------------------------------------------

if [ "$DO_FIX" -eq 1 ] && [ "$DO_REVIEW" -eq 1 ]; then
  FIX_PROMPT="$(new_prompt fix)"
  {
    cat <<EOF
# Fix critical review findings — package ${TASK_NN}

\`${REVIEW_AGENT}\` reviewed your work on \`${BRANCH}\`.

- Review: \`${REVIEW_FILE}\`
- Package file: \`${TASK_FILE}\`
- Execution plan: \`${PLAN_FILE}\`


## What to do

1. Read the review file.
2. Fix **every finding under "Critical"**. Not optional.
3. Fix "Major" findings only where the fix is contained and low-risk. List the rest.
4. Do **not** touch "Minor" in this run.
5. If you disagree with a Critical finding, do not silently ignore it — fix it, or state
   the counter-argument with evidence from the code and leave it open.
6. Anything outside the package's Scope stays untouched, even if the reviewer asked for
   it. Report it as a follow-up instead.
7. Re-run the validation for every stage you touched.
8. Commit fixes separately: \`fix(review): <what>\` referencing the finding IDs.
EOF
    style_block
    cat <<EOF

## Finish with

- which Critical findings you fixed, and how
- which Major findings you fixed, which you left, and why
- anything you disagree with, with evidence
- follow-ups belonging to another package
- test results after the fixes
EOF
  } > "$FIX_PROMPT"

  head2 "Fix phase — $AGENT"
  run_agent "$AGENT" "$IMPL_MODEL" "$IMPL_EFFORT" "$FIX_PROMPT"
fi

# ---------------------------------------------------------------------------
# summary
# ---------------------------------------------------------------------------

head2 "Done"
printf '  %-16s %s\n' "package"     "$TASK_NN${STAGE:+ (stage $STAGE)}"
printf '  %-16s %s\n' "branch"      "$BRANCH"
printf '  %-16s %s\n' "implemented" "$AGENT ($IMPL_MODEL, effort=$IMPL_EFFORT)"
[ "$DO_REVIEW" -eq 1 ] && printf '  %-16s %s\n' "reviewed" "$REVIEW_AGENT ($REV_MODEL, effort=$REV_EFFORT)"
[ -f "$REVIEW_FILE" ]  && printf '  %-16s %s\n' "review file" "$REVIEW_FILE"

cat <<EOF

Next, manually:
  git log --oneline ${BASE_BRANCH}..HEAD
  docker compose exec api-tooling php artisan test
  docker compose exec api-tooling composer deptrac
  make check-grants

Then set the status of package ${TASK_NN} in ${PLAN_FILE}
and mark the findings done in Confluence chapter 14.
EOF
