#!/usr/bin/env bash
#
# Smoke test for the compose runtime split (ADR-001 §2.4, ADR-003 D2).
#
# Verifies three properties of the local stack:
#   1. Credential isolation — each runtime container holds only its own role's
#      credentials; none holds the migrator role or a foreign domain's role.
#   2. Path routing — nginx sends every /api prefix to the runtime that owns it,
#      and a runtime that does not own a prefix has no such route (404).
#   3. Session continuity — a Sanctum token issued by the identity runtime is
#      accepted by the employee and company runtimes (shared identity DB read).
#
# Prerequisites:
#   docker compose up -d
#   docker compose run --rm migrate       # seeds the demo accounts used below
#
# Usage:
#   bash infra/smoke-runtime-split.sh
#
# Environment overrides:
#   COMPOSE    compose command                (default: "docker compose")
#   BASE_URL   public API base URL via nginx  (default: http://localhost:8080/api)
#
# Exit code 0 when every check passes, 1 otherwise. Deliberately no `set -e`:
# every assertion must run so the report lists all failures at once.

set -uo pipefail

COMPOSE=${COMPOSE:-docker compose}
BASE_URL=${BASE_URL:-http://localhost:8080/api}

fail=0

pass() { echo "PASS: $*"; }
die()  { echo "FAIL: $*"; fail=$((fail + 1)); }

# --- helpers ----------------------------------------------------------------

# env_of <service> — the container environment as KEY=VALUE lines.
env_of() {
  $COMPOSE exec -T "$1" env 2>/dev/null
}

# routes_of <service> — the registered route table as JSON with the slash
# escaping removed, so URIs can be matched in their natural form.
routes_of() {
  $COMPOSE exec -T "$1" php artisan route:list --json 2>/dev/null | tr -d '\\'
}

# expect_env_absent <service> <env-dump> <pattern> <description>
expect_env_absent() {
  local service="$1" dump="$2" pattern="$3" description="$4"
  if grep -Eq "$pattern" <<<"$dump"; then
    die "$service exposes $description: $(grep -E "$pattern" <<<"$dump" | cut -d= -f1 | tr '\n' ' ')"
  else
    pass "$service has no $description"
  fi
}

# expect_env_present <service> <env-dump> <pattern> <description>
expect_env_present() {
  local service="$1" dump="$2" pattern="$3" description="$4"
  if grep -Eq "$pattern" <<<"$dump"; then
    pass "$service has $description"
  else
    die "$service is missing $description ($pattern)"
  fi
}

# expect_route <service> <route-json> <uri> <description>
expect_route() {
  local service="$1" routes="$2" uri="$3"
  if grep -q "\"uri\":\"$uri\"" <<<"$routes"; then
    pass "$service serves $uri"
  else
    die "$service does not serve $uri"
  fi
}

# expect_no_route <service> <route-json> <uri-fragment> <description>
expect_no_route() {
  local service="$1" routes="$2" fragment="$3"
  if grep -q "\"uri\":\"$fragment" <<<"$routes"; then
    die "$service serves foreign routes matching $fragment"
  else
    pass "$service serves no routes matching $fragment"
  fi
}

# http_status <method> <path> [auth-token]
http_status() {
  local method="$1" path="$2" token="${3:-}"
  if [ -n "$token" ]; then
    curl -s -o /dev/null -w '%{http_code}' -X "$method" \
      -H 'Accept: application/json' -H "Authorization: Bearer $token" \
      "$BASE_URL$path"
  else
    curl -s -o /dev/null -w '%{http_code}' -X "$method" \
      -H 'Accept: application/json' "$BASE_URL$path"
  fi
}

# expect_status <expected> <method> <path> [token]
expect_status() {
  local expected="$1" method="$2" path="$3" token="${4:-}"
  local actual
  actual=$(http_status "$method" "$path" "$token")
  if [ "$actual" = "$expected" ]; then
    pass "$method $path -> $actual"
  else
    die "$method $path -> $actual (expected $expected)"
  fi
}

# login <email> <password> — echoes the plain-text Sanctum token, empty on failure.
login() {
  curl -s -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d "{\"email\":\"$1\",\"password\":\"$2\"}" "$BASE_URL/auth/login" \
    | sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p'
}

echo "== 1. credential isolation =========================================="

IDENTITY_ENV=$(env_of api-identity)
EMPLOYEE_ENV=$(env_of api-employee)
COMPANY_ENV=$(env_of api-company)

for service in api-identity api-employee api-company; do
  case "$service" in
    api-identity) dump="$IDENTITY_ENV" ;;
    api-employee) dump="$EMPLOYEE_ENV" ;;
    api-company)  dump="$COMPANY_ENV" ;;
  esac
  if [ -z "$dump" ]; then
    die "$service is not running (no environment readable)"
  fi
done

# No runtime container may ever carry the migrator role (ADR-001 §2.4).
for service in api-identity api-employee api-company; do
  case "$service" in
    api-identity) dump="$IDENTITY_ENV" ;;
    api-employee) dump="$EMPLOYEE_ENV" ;;
    api-company)  dump="$COMPANY_ENV" ;;
  esac
  expect_env_absent "$service" "$dump" '^DB_MIGRATOR_|elyo_migrator' 'migrator credentials'
done

# identity: elyo_identity_rt only.
expect_env_present "api-identity" "$IDENTITY_ENV" '^ELYO_RUNTIME=identity$' 'ELYO_RUNTIME=identity'
expect_env_present "api-identity" "$IDENTITY_ENV" '^DB_IDENTITY_USERNAME=elyo_identity_rt$' 'the identity runtime role'
expect_env_absent  "api-identity" "$IDENTITY_ENV" '^DB_MAPPING_|elyo_mapping_svc' 'mapping credentials'
expect_env_absent  "api-identity" "$IDENTITY_ENV" '^DB_HEALTH_' 'health credentials'
expect_env_absent  "api-identity" "$IDENTITY_ENV" '^MAPPING_(ENCRYPTION|HMAC|SUBJECT_DERIVATION)_KEY=.+' 'mapping key material'
expect_env_absent  "api-identity" "$IDENTITY_ENV" 'elyo_employee_rt|elyo_company_rt' 'foreign runtime roles'

# employee: elyo_employee_rt + elyo_mapping_svc.
expect_env_present "api-employee" "$EMPLOYEE_ENV" '^ELYO_RUNTIME=employee$' 'ELYO_RUNTIME=employee'
expect_env_present "api-employee" "$EMPLOYEE_ENV" '^DB_HEALTH_USERNAME=elyo_employee_rt$' 'the health connection'
expect_env_present "api-employee" "$EMPLOYEE_ENV" '^DB_MAPPING_USERNAME=elyo_mapping_svc$' 'the mapping connection'
expect_env_absent  "api-employee" "$EMPLOYEE_ENV" 'elyo_identity_rt|elyo_company_rt' 'foreign runtime roles'

# company: elyo_company_rt only — the check named in the task description.
expect_env_present "api-company" "$COMPANY_ENV" '^ELYO_RUNTIME=company$' 'ELYO_RUNTIME=company'
expect_env_present "api-company" "$COMPANY_ENV" '^DB_IDENTITY_USERNAME=elyo_company_rt$' 'the company runtime role'
expect_env_absent  "api-company" "$COMPANY_ENV" '^DB_MAPPING_|elyo_mapping_svc' 'mapping credentials'
expect_env_absent  "api-company" "$COMPANY_ENV" '^DB_HEALTH_' 'health credentials'
expect_env_absent  "api-company" "$COMPANY_ENV" '^MAPPING_(ENCRYPTION|HMAC|SUBJECT_DERIVATION)_KEY=.+' 'mapping key material'
expect_env_absent  "api-company" "$COMPANY_ENV" 'elyo_identity_rt|elyo_employee_rt' 'foreign runtime roles'

echo
echo "== 2. route topology per runtime ===================================="

IDENTITY_ROUTES=$(routes_of api-identity)
EMPLOYEE_ROUTES=$(routes_of api-employee)
COMPANY_ROUTES=$(routes_of api-company)

expect_route    "api-identity" "$IDENTITY_ROUTES" 'api/auth/login'
expect_route    "api-identity" "$IDENTITY_ROUTES" 'api/admin/companies'
expect_route    "api-identity" "$IDENTITY_ROUTES" 'api/partner/login'
expect_route    "api-identity" "$IDENTITY_ROUTES" 'api/health'
expect_no_route "api-identity" "$IDENTITY_ROUTES" 'api/employee'
expect_no_route "api-identity" "$IDENTITY_ROUTES" 'api/company'

expect_route    "api-employee" "$EMPLOYEE_ROUTES" 'api/employee/dashboard'
expect_route    "api-employee" "$EMPLOYEE_ROUTES" 'api/health'
expect_no_route "api-employee" "$EMPLOYEE_ROUTES" 'api/auth'
expect_no_route "api-employee" "$EMPLOYEE_ROUTES" 'api/admin'
expect_no_route "api-employee" "$EMPLOYEE_ROUTES" 'api/company'
expect_no_route "api-employee" "$EMPLOYEE_ROUTES" 'api/partner'

expect_route    "api-company" "$COMPANY_ROUTES" 'api/company/dashboard'
expect_route    "api-company" "$COMPANY_ROUTES" 'api/health'
expect_no_route "api-company" "$COMPANY_ROUTES" 'api/auth'
expect_no_route "api-company" "$COMPANY_ROUTES" 'api/admin'
expect_no_route "api-company" "$COMPANY_ROUTES" 'api/employee'
expect_no_route "api-company" "$COMPANY_ROUTES" 'api/partner'

echo
echo "== 3. nginx path routing (single base URL) =========================="

# /api/health is served by every profile; the default upstream is identity.
health_body=$(curl -s -H 'Accept: application/json' "$BASE_URL/health")
if grep -q '"runtime":"identity"' <<<"$health_body"; then
  pass "GET /health served by the identity runtime: $health_body"
else
  die "GET /health did not report the identity runtime: $health_body"
fi

# A 401 proves the request reached a runtime that OWNS the prefix: any other
# runtime would have answered 404 because the route is not registered there.
expect_status 401 GET /auth/me
expect_status 401 GET /employee/dashboard
expect_status 401 GET /company/dashboard

# Cross-path / unknown paths must 404 on the runtime that owns the prefix.
expect_status 404 GET /employee/not-a-route
expect_status 404 GET /company/not-a-route
expect_status 404 GET /auth/not-a-route
expect_status 404 GET /admin/not-a-route

echo
echo "== 4. session continuity across runtimes ============================"

EMPLOYEE_TOKEN=$(login 'employee1@demo.de' 'demo1234')
COMPANY_TOKEN=$(login 'admin@demo.de' 'demo1234')

if [ -n "$EMPLOYEE_TOKEN" ]; then
  pass "identity runtime issued a Sanctum token for employee1@demo.de"
  # Accepted by a different container than the one that issued it.
  expect_status 200 GET /employee/dashboard "$EMPLOYEE_TOKEN"
  # Company runtime authenticates the same token, then rejects on role (403,
  # not 401) — proof that token validation itself succeeded there too.
  expect_status 403 GET /company/dashboard "$EMPLOYEE_TOKEN"
else
  die "identity runtime did not issue a token for employee1@demo.de (is the stack seeded?)"
fi

if [ -n "$COMPANY_TOKEN" ]; then
  pass "identity runtime issued a Sanctum token for admin@demo.de"
  expect_status 200 GET /company/dashboard "$COMPANY_TOKEN"
  expect_status 403 GET /employee/dashboard "$COMPANY_TOKEN"
else
  die "identity runtime did not issue a token for admin@demo.de (is the stack seeded?)"
fi

echo
if [ "$fail" -gt 0 ]; then
  echo "runtime split smoke test FAILED ($fail check(s))"
  exit 1
fi

echo "runtime split smoke test passed"
