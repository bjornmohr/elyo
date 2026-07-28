#!/usr/bin/env bash
#
# Provisions the ELYO domain databases, per-runtime roles and minimal grants
# inside the single `postgres` container (ELYO-104 / ADR-001 §2.1-2.4, §2.7).
#
# Runs automatically once, on a fresh data volume, from
# /docker-entrypoint-initdb.d/. Re-run the whole thing with:
#     docker compose down -v && docker compose up -d postgres
#
# No application schema is created here — only databases, roles and grants.
# Table-level access is expressed through ALTER DEFAULT PRIVILEGES so that
# every table later created by `elyo_migrator` inherits the correct grants
# (migrations land in prompt 03).
#
# Passwords come from the environment and are required. Provide them through
# Docker secrets outside local development.

set -euo pipefail

# --- required runtime-role passwords -----------------------------------------
: "${ELYO_IDENTITY_RT_PASSWORD:?ELYO_IDENTITY_RT_PASSWORD must be set}"
: "${ELYO_EMPLOYEE_RT_PASSWORD:?ELYO_EMPLOYEE_RT_PASSWORD must be set}"
: "${ELYO_COMPANY_RT_PASSWORD:?ELYO_COMPANY_RT_PASSWORD must be set}"
: "${ELYO_MAPPING_SVC_PASSWORD:?ELYO_MAPPING_SVC_PASSWORD must be set}"
: "${ELYO_MIGRATOR_PASSWORD:?ELYO_MIGRATOR_PASSWORD must be set}"

PSQL=(psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --no-psqlrc)

# --- 1. roles ----------------------------------------------------------------
"${PSQL[@]}" --dbname "$POSTGRES_DB" \
  -v identity_rt_pw="$ELYO_IDENTITY_RT_PASSWORD" \
  -v employee_rt_pw="$ELYO_EMPLOYEE_RT_PASSWORD" \
  -v company_rt_pw="$ELYO_COMPANY_RT_PASSWORD" \
  -v mapping_svc_pw="$ELYO_MAPPING_SVC_PASSWORD" \
  -v migrator_pw="$ELYO_MIGRATOR_PASSWORD" <<'SQL'
DO $do$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'elyo_identity_rt') THEN CREATE ROLE elyo_identity_rt LOGIN; END IF;
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'elyo_employee_rt') THEN CREATE ROLE elyo_employee_rt LOGIN; END IF;
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'elyo_company_rt')  THEN CREATE ROLE elyo_company_rt  LOGIN; END IF;
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'elyo_mapping_svc') THEN CREATE ROLE elyo_mapping_svc LOGIN; END IF;
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'elyo_migrator')    THEN CREATE ROLE elyo_migrator    LOGIN; END IF;
END $do$;

ALTER ROLE elyo_identity_rt PASSWORD :'identity_rt_pw';
ALTER ROLE elyo_employee_rt PASSWORD :'employee_rt_pw';
ALTER ROLE elyo_company_rt  PASSWORD :'company_rt_pw';
ALTER ROLE elyo_mapping_svc PASSWORD :'mapping_svc_pw';
ALTER ROLE elyo_migrator    PASSWORD :'migrator_pw';
SQL

# --- 2. databases (owned by the migrator role) -------------------------------
create_database() {
  local db="$1"
  "${PSQL[@]}" --dbname "$POSTGRES_DB" -v db="$db" <<'SQL'
SELECT format('CREATE DATABASE %I OWNER elyo_migrator', :'db')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'db')\gexec
SQL
}

for db in elyo_identity elyo_subject_mapping elyo_health elyo_audit elyo_reporting \
          elyo_identity_test elyo_subject_mapping_test elyo_health_test elyo_audit_test; do
  create_database "$db"
done

# --- 3. per-domain grants (identical for prod + *_test databases) ------------
# The migrator owns schema `public`; every table it creates later inherits the
# default privileges granted below.

grant_identity() {
  local db="$1"
  "${PSQL[@]}" --dbname "$db" -v db="$db" <<'SQL'
ALTER SCHEMA public OWNER TO elyo_migrator;
REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"db" TO elyo_migrator, elyo_identity_rt, elyo_employee_rt, elyo_company_rt;
GRANT USAGE  ON SCHEMA public   TO elyo_identity_rt, elyo_employee_rt, elyo_company_rt;

-- identity RW
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO elyo_identity_rt;
-- company RW (row/column scope enforced in the app layer)
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO elyo_company_rt;
-- employee: read identity for auth only
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT SELECT ON TABLES TO elyo_employee_rt;
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO elyo_identity_rt, elyo_company_rt;
SQL
}

grant_mapping() {
  local db="$1"
  "${PSQL[@]}" --dbname "$db" -v db="$db" <<'SQL'
ALTER SCHEMA public OWNER TO elyo_migrator;
REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
REVOKE ALL ON SCHEMA public  FROM PUBLIC;
-- reachable only by the dedicated mapping service role and the migrator
GRANT CONNECT ON DATABASE :"db" TO elyo_migrator, elyo_mapping_svc;
GRANT USAGE  ON SCHEMA public   TO elyo_mapping_svc;
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO elyo_mapping_svc;
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO elyo_mapping_svc;
SQL
}

grant_health() {
  local db="$1"
  "${PSQL[@]}" --dbname "$db" -v db="$db" <<'SQL'
ALTER SCHEMA public OWNER TO elyo_migrator;
REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
-- employee runtime only; company has NO connect privilege on health
GRANT CONNECT ON DATABASE :"db" TO elyo_migrator, elyo_employee_rt;
GRANT USAGE  ON SCHEMA public   TO elyo_employee_rt;
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO elyo_employee_rt;
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO elyo_employee_rt;
SQL
}

grant_audit() {
  local db="$1"
  "${PSQL[@]}" --dbname "$db" -v db="$db" <<'SQL'
ALTER SCHEMA public OWNER TO elyo_migrator;
REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
REVOKE ALL ON SCHEMA public  FROM PUBLIC;
GRANT CONNECT ON DATABASE :"db" TO elyo_migrator, elyo_employee_rt, elyo_company_rt, elyo_mapping_svc;
GRANT USAGE  ON SCHEMA public   TO elyo_employee_rt, elyo_company_rt, elyo_mapping_svc;
-- append-only: INSERT for all writers, no UPDATE/DELETE anywhere
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT INSERT ON TABLES TO elyo_employee_rt, elyo_company_rt, elyo_mapping_svc;
-- SELECT for the mapping service only (needed by boundary tests)
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT SELECT ON TABLES TO elyo_mapping_svc;
ALTER DEFAULT PRIVILEGES FOR ROLE elyo_migrator IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO elyo_employee_rt, elyo_company_rt, elyo_mapping_svc;
SQL
}

grant_reporting() {
  local db="$1"
  "${PSQL[@]}" --dbname "$db" -v db="$db" <<'SQL'
ALTER SCHEMA public OWNER TO elyo_migrator;
REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
-- prepared but unpopulated: only the migrator has access until a reporting
-- worker runtime is introduced (ADR-001 §2.5, not part of the pilot).
GRANT CONNECT ON DATABASE :"db" TO elyo_migrator;
SQL
}

grant_identity  elyo_identity
grant_identity  elyo_identity_test
grant_mapping   elyo_subject_mapping
grant_mapping   elyo_subject_mapping_test
grant_health    elyo_health
grant_health    elyo_health_test
grant_audit     elyo_audit
grant_audit     elyo_audit_test
grant_reporting elyo_reporting

echo "ELYO databases, roles and grants provisioned."
