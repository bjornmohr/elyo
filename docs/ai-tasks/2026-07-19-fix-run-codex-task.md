# Fix `run-codex-task.sh`

## Problem

`./scripts/run-codex-task.sh --help` exits with status 126 and `permission denied`, so the documented direct invocation does not work.

## Desired Behavior

- The script can be invoked directly from the repository root.
- `--help` exits successfully and shows the documented usage.
- A forced dry run resolves a task, execution plan, and branch without modifying Git state or launching Codex.
- Existing task, branch, continuation, and handoff behavior remains unchanged.

## Scope

- `scripts/run-codex-task.sh`
- Focused shell regression coverage for the script
- This task record

## Acceptance Criteria

1. `./scripts/run-codex-task.sh --help` exits 0.
2. The script is stored as executable in Git.
3. A dry run for a valid task exits 0 and prints the expected task, plan, branch, mode, and Codex command.
4. The regression test fails when the script is not executable and passes after the fix.
5. `bash -n` and `git diff --check` pass.
6. `--continue-claude` invokes Codex when `RUN_CODEX_ARGS` is unset under macOS Bash 3.2.
7. Optional `RUN_CODEX_ARGS` remain forwarded in order.
8. A Codex execution failure is returned by the wrapper after temporary-file cleanup.

## Tests & Validation

- Test-first required: yes
- Add a shell regression test at the actual CLI boundary.
- Run the regression test before the fix and confirm the executable assertion fails.
- Run after the fix:
  - `bash tests/scripts/run-codex-task-test.sh`
  - `bash -n scripts/run-codex-task.sh tests/scripts/run-codex-task-test.sh`
  - `git diff --check`

## Assumptions

- The reported failure is the reproducible direct-execution failure in the current worktree.
- Existing uncommitted migration and infrastructure changes are intentional and unrelated.
- Full backend, frontend, and Docker validation is unnecessary because this change only affects a standalone shell wrapper.

## Implementation Plan

1. Add a CLI-boundary regression test for executable mode, help output, and fresh-task dry-run resolution.
2. Run the test before implementation and confirm it fails on the missing executable bit.
3. Mark `scripts/run-codex-task.sh` executable without changing its existing behavior.
4. Run the regression test, direct CLI commands, shell syntax checks, and `git diff --check`.
5. Review the scoped diff and record validation results.

## Handoff

### Files Changed

- `scripts/run-codex-task.sh`: recorded executable mode and made help extraction stop at the end of the header comment.
- `tests/scripts/run-codex-task-test.sh`: added CLI regression coverage.
- `docs/ai-tasks/2026-07-19-fix-run-codex-task.md`: recorded task, plan, and results.

### Behavior Changed

- Direct invocation now works instead of exiting 126 with `permission denied`.
- `--help` no longer prints `set -euo pipefail`.
- Task resolution and all three dry-run modes remain functional.
- Empty Codex arguments now use Bash positional parameters, avoiding Bash 3.2's `CODEX_ARGS[@]: unbound variable` failure under `set -u`.
- Temporary prompt cleanup now preserves Codex's exit status instead of masking failures as success.

### Tests & Validation

- Test-first applied: yes
- Tests added/updated:
  - `tests/scripts/run-codex-task-test.sh`
- ACs covered by tests:
  - executable mode
  - successful and clean help output
  - valid task, plan, branch, mode, and Codex command resolution
  - real `--continue-claude` dispatch through an isolated Git fixture and stub Codex command
  - empty and non-empty `RUN_CODEX_ARGS` handling under `/bin/bash`
  - Codex failure status propagation after cleanup
- Validation commands executed:
  - `bash tests/scripts/run-codex-task-test.sh`
  - `bash -n scripts/run-codex-task.sh tests/scripts/run-codex-task-test.sh`
  - direct `--help`
  - fresh, `--continue`, and `--continue-claude` dry runs
  - `codex resume --help`
  - `codex exec --help`
  - `git diff --check`
  - `git diff --cached --check`
- Known gaps / intentionally not tested:
  - Real Codex sessions were not launched because they would create or switch task branches and start paid interactive work; installed CLI help verified command compatibility.

### Open Questions

- None.

### Intentional Deviations

- Full Laravel, Angular, Docker, and OpenAPI validation was omitted because no application or API behavior changed.
- `scripts/codex-plan.sh` was attempted but its interactive Codex TUI stalled; it was exited cleanly and this implementation plan was recorded directly.
- Generated `docs/ai-handoff` snapshots were not created because they would include substantial unrelated worktree changes; this task-scoped handoff is recorded here instead.
