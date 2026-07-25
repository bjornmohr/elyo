# Migrations — per-domain layout (ELYO-104 / prompt 03)

Migrations are split by domain, one directory per database connection. Each
directory is migrated on its own connection so the domain-separated PostgreSQL
topology (ADR-001 §2.1–2.4) is reproduced from schema up.

```
database/migrations/
├── identity/   → connection `identity`   (elyo_identity)          — populated
├── mapping/    → connection `mapping`    (elyo_subject_mapping)    — empty (prompt 04)
├── health/     → connection `health`     (elyo_health)            — populated
└── audit/      → connection `audit`      (elyo_audit)             — populated
```

`health/` holds `health_subjects` (prompt 04) and `wellbeing_entries` (prompt 08,
ELYO-110). Health tables are keyed on `health_subject_id` and carry no `user_id`
or `company_id` — asserted for the whole schema by
`tests/Boundary/HealthSchemaBoundaryTest.php`. The identity-side
`wellbeing_entries` table is dropped by a follow-up migration in `identity/`
rather than by editing the reviewed baseline.

## Consolidated baseline

The previous ~23 incremental migrations were **replaced by a single consolidated
baseline** and **deleted**. This is safe because the app is pre-production — there
is no data to migrate, and `git history` is the archive of the old files.

Identity baseline files:

- `identity/2024_01_01_000001_create_identity_tables.php` — all 33 application
  tables. Columns originally added by later `ALTER` migrations were folded into
  the `CREATE` statements and appended at the end of each table to match
  PostgreSQL column ordering (Postgres ignores Blueprint `->after()`).
- `identity/2024_01_01_000002_create_identity_framework_tables.php` — the 8
  Laravel framework tables (cache, cache_locks, job_batches, jobs, failed_jobs,
  sessions, notifications, personal_access_tokens). These are infrastructure and
  live on the identity/default connection.

Pure data-backfill steps from the old migrations (e.g. `visibility_scope = TEAM`
for team-scoped measures, `verified_at = participated_at`) were dropped — a fresh
baseline has no rows to backfill.

Schema parity is checked against pre-restructure commit `10cd1c6` by rebuilding
both migration sets in disposable PostgreSQL databases and comparing normalized
schema-only dumps. All 41 tables and their columns, defaults, indexes and foreign
keys match after folding the incremental `ALTER` migrations into their owning
`CREATE` statements, with one deliberate, verifier-allowlisted correction: the old
`unique()->whereNull()` call compiled as a full PostgreSQL unique constraint
because Laravel's PostgreSQL schema grammar ignores that fluent attribute. The
baseline uses an explicit partial unique index (`WHERE revoked_at IS NULL`),
which preserves the intended and tested token-rotation behavior.

The former sqlite-only fallback for that index was removed because the schema
and test suite are Postgres-only (D9). No application/service SQLite workaround
was changed in this task.

PostgreSQL is the only active and supported test path. SQLite references under
`docs/ai-tasks/` and in historical handoffs describe earlier completed work and
are intentionally preserved as historical records; they do not describe the
current test configuration.

## Verifying schema and routes

From the repository root, run:

```bash
make verify-migration-restructure
```

The verifier checks that route source files are unchanged from `10cd1c6`, that
Artisan reports exactly 78 routes in JSON output, and that the rebuilt legacy and
consolidated PostgreSQL schemas differ only by the reviewed active-token partial
index correction. It creates and removes two disposable databases named
`elyo_schema_legacy_verify` and `elyo_schema_current_verify`.

## Running migrations

Never run bare `php artisan migrate`; it only sees the default path/connection.
Use the multi-connection command, which fresh-migrates every domain in
dependency order (identity → mapping → health → audit) as the `elyo_migrator`
role and then optionally seeds on the runtime connection:

```bash
docker compose exec api php artisan elyo:migrate-fresh          # rebuild schema
docker compose exec api php artisan elyo:migrate-fresh --seed   # rebuild + seed
make migrate-all                                                # apply pending migrations
make fresh-all                                                  # rebuild + seed
```

Per the execution plan, schema changes after this prompt are made with **new**
migration files inside the relevant domain directory — reviewed baselines are
never edited.
