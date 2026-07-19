#!/usr/bin/env bash
#
# Asserts the runtime-role boundaries provisioned by
# infra/postgres/initdb/01-databases-and-roles.sh (ELYO-104 / ADR-001 §2.10).
# Consumed by CI and by the boundary test lane (prompt 06).
#
# psql runs inside the compose `postgres` service over the local socket
# (trust auth), so what is exercised is the CONNECT / table grants — not
# passwords. CONNECT and table privileges are enforced regardless of the
# authentication method.
#
# Exit 0 => all boundaries hold. Exit 1 => at least one assertion failed.

set -uo pipefail

COMPOSE=${COMPOSE:-docker compose}
PSQL="$COMPOSE exec -T postgres psql -v ON_ERROR_STOP=1 -q"

fail=0
pass() { echo "PASS: $1"; }
die()  { echo "FAIL: $1"; fail=1; }

run() { # role db sql
  $PSQL -U "$1" -d "$2" -c "$3" >/dev/null 2>&1
}

expect_fail() { # role db sql desc
  if run "$1" "$2" "$3"; then die "$4"; else pass "$4"; fi
}
expect_ok() { # role db sql desc
  if run "$1" "$2" "$3"; then pass "$4"; else die "$4"; fi
}

# 1. identity runtime cannot read the mapping database
expect_fail elyo_identity_rt elyo_subject_mapping "SELECT 1" \
  "elyo_identity_rt cannot access elyo_subject_mapping"

# 2. company runtime cannot connect to health (nor to mapping)
expect_fail elyo_company_rt elyo_health "SELECT 1" \
  "elyo_company_rt cannot connect to elyo_health"
expect_fail elyo_company_rt elyo_subject_mapping "SELECT 1" \
  "elyo_company_rt cannot connect to elyo_subject_mapping"

# positive sanity: allowed paths do work
expect_ok elyo_mapping_svc elyo_subject_mapping "SELECT 1" \
  "elyo_mapping_svc can connect to elyo_subject_mapping"
expect_ok elyo_employee_rt elyo_health "SELECT 1" \
  "elyo_employee_rt can connect to elyo_health"

# 3. audit is append-only: INSERT allowed, UPDATE/DELETE rejected.
# Scaffold a probe table as the migrator so it inherits the default privileges.
$PSQL -U elyo_migrator -d elyo_audit \
  -c 'CREATE TABLE IF NOT EXISTS _grant_probe (id bigserial PRIMARY KEY, note text);' \
  >/dev/null 2>&1

expect_ok   elyo_employee_rt elyo_audit "INSERT INTO _grant_probe(note) VALUES('probe')" \
  "elyo_employee_rt can INSERT into audit"
expect_fail elyo_employee_rt elyo_audit "UPDATE _grant_probe SET note='x'" \
  "audit rejects UPDATE (append-only)"
expect_fail elyo_employee_rt elyo_audit "DELETE FROM _grant_probe" \
  "audit rejects DELETE (append-only)"

$PSQL -U elyo_migrator -d elyo_audit -c 'DROP TABLE IF EXISTS _grant_probe;' >/dev/null 2>&1

if [ "$fail" -ne 0 ]; then
  echo "grant checks FAILED"
  exit 1
fi
echo "all grant checks passed"
exit 0
