#!/usr/bin/env bash

set -euo pipefail

BASE_REF="${1:-10cd1c6}"
EXPECTED_ROUTE_COUNT=78
LEGACY_DB="elyo_schema_legacy_verify"
CURRENT_DB="elyo_schema_current_verify"
CONTAINER_LEGACY_PATH="/tmp/elyo-migration-parity-legacy"
REPO_ROOT="$(git rev-parse --show-toplevel)"
TEMP_DIR="$(mktemp -d)"

cd "$REPO_ROOT"

cleanup_database() {
    local database="$1"

    docker compose exec -T postgres psql \
        --username=elyo \
        --dbname=postgres \
        --set=ON_ERROR_STOP=1 \
        --command="SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${database}' AND pid <> pg_backend_pid();" \
        >/dev/null
    docker compose exec -T postgres dropdb \
        --username=elyo \
        --if-exists \
        "$database"
}

cleanup() {
    cleanup_database "$LEGACY_DB"
    cleanup_database "$CURRENT_DB"
    docker compose exec -T api rm -rf "$CONTAINER_LEGACY_PATH"
    rm -rf "$TEMP_DIR"
}

trap cleanup EXIT

git rev-parse --verify "${BASE_REF}^{commit}" >/dev/null
git diff --quiet "$BASE_REF" -- apps/api-laravel/routes

route_count="$(docker compose exec -T api php artisan route:list --json | grep -o '"uri"' | wc -l | tr -d ' ')"
if [[ "$route_count" != "$EXPECTED_ROUTE_COUNT" ]]; then
    echo "Route count mismatch: expected ${EXPECTED_ROUTE_COUNT}, got ${route_count}." >&2
    exit 1
fi

git archive "$BASE_REF" apps/api-laravel/database/migrations | tar -x -C "$TEMP_DIR"
docker compose exec -T api rm -rf "$CONTAINER_LEGACY_PATH"
docker cp \
    "$TEMP_DIR/apps/api-laravel/database/migrations" \
    "$(docker compose ps -q api):${CONTAINER_LEGACY_PATH}"

for database in "$LEGACY_DB" "$CURRENT_DB"; do
    cleanup_database "$database"
    docker compose exec -T postgres createdb \
        --username=elyo \
        --owner=elyo_migrator \
        "$database"
done

docker compose exec -T \
    -e APP_ENV=testing \
    -e DB_URL= \
    -e DB_CONNECTION=identity \
    -e DB_IDENTITY_DATABASE="$LEGACY_DB" \
    api php artisan migrate:fresh \
    --database=identity_migrator \
    --path="$CONTAINER_LEGACY_PATH" \
    --realpath \
    --force \
    --no-interaction \
    --quiet

docker compose exec -T \
    -e APP_ENV=testing \
    -e DB_URL= \
    -e DB_CONNECTION=identity \
    -e DB_IDENTITY_DATABASE="$CURRENT_DB" \
    api php artisan migrate:fresh \
    --database=identity_migrator \
    --path=database/migrations/identity \
    --force \
    --no-interaction \
    --quiet

for version in legacy current; do
    if [[ "$version" == "legacy" ]]; then
        database="$LEGACY_DB"
    else
        database="$CURRENT_DB"
    fi
    docker compose exec -T postgres pg_dump \
        --username=elyo \
        --schema-only \
        --no-owner \
        --no-privileges \
        --exclude-table=migrations \
        "$database" \
        | sed -E '/^--/d; /^$/d; /^\\(un)?restrict /d' \
        >"$TEMP_DIR/${version}.sql"
done

legacy_constraint='    ADD CONSTRAINT measure_checkin_tokens_one_active_per_measure UNIQUE (measure_id);'
current_index='CREATE UNIQUE INDEX measure_checkin_tokens_one_active_per_measure ON public.measure_checkin_tokens USING btree (measure_id) WHERE (revoked_at IS NULL);'

if [[ "$(grep -Fxc "$legacy_constraint" "$TEMP_DIR/legacy.sql")" != "1" ]]; then
    echo "Legacy schema does not contain the expected full unique constraint exactly once." >&2
    exit 1
fi

if [[ "$(grep -Fxc "$current_index" "$TEMP_DIR/current.sql")" != "1" ]]; then
    echo "Current schema does not contain the expected partial unique index exactly once." >&2
    exit 1
fi

normalize_known_delta() {
    awk -v old_constraint="$legacy_constraint" -v new_index="$current_index" '
        pending != "" {
            if ($0 == old_constraint) {
                pending = ""
                next
            }

            print pending
            pending = ""
        }

        $0 == "ALTER TABLE ONLY public.measure_checkin_tokens" {
            pending = $0
            next
        }

        $0 == new_index {
            next
        }

        { print }

        END {
            if (pending != "") {
                print pending
            }
        }
    ' "$1" >"$2"
}

normalize_known_delta "$TEMP_DIR/legacy.sql" "$TEMP_DIR/legacy.normalized.sql"
normalize_known_delta "$TEMP_DIR/current.sql" "$TEMP_DIR/current.normalized.sql"

if ! cmp --silent "$TEMP_DIR/legacy.normalized.sql" "$TEMP_DIR/current.normalized.sql"; then
    echo "Unexpected schema difference detected:" >&2
    diff -u "$TEMP_DIR/legacy.normalized.sql" "$TEMP_DIR/current.normalized.sql"
    exit 1
fi

echo "Verified ${route_count} routes unchanged from ${BASE_REF}."
echo "Verified schema parity with only the allowlisted active-token partial-index correction."
