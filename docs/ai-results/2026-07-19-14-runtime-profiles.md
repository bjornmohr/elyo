# Result: Laravel runtime profiles (ELYO_RUNTIME)

Task: `docs/ai-tasks/2026-07-19-14-runtime-profiles.md` (ELYO-106) · Branch: `elyo-91/14-runtime-profiles`

## 1. Files changed

| File | Change |
| --- | --- |
| `apps/api-laravel/app/Runtime/RuntimeProfile.php` | new — single source of truth: profile resolution, validation, connection set, route set, cached-config guard |
| `apps/api-laravel/config/runtime.php` | new — `runtime.profile`, resolved fail-safe at config load |
| `apps/api-laravel/config/database.php` | connection set restricted to the active profile |
| `apps/api-laravel/app/Providers/AppServiceProvider.php` | boot-time guard against a configuration cache built for another runtime |
| `apps/api-laravel/routes/api.php` | loader only — requires the health route plus the profile's route files |
| `apps/api-laravel/routes/api/{identity,identity-partner,employee,company,health}.php` | new — former `routes/api.php` split by profile, paths/middleware/controllers unchanged |
| `.env.example` (repo root) | `ELYO_RUNTIME=full` |
| `apps/api-laravel/phpunit.xml`, `tests/bootstrap.php` | default suite runs `full` |
| `apps/api-laravel/tests/Feature/Runtime/RuntimeProfileBootTest.php` | new — profile boot tests |
| `docs/adr-documents/ADR-003-Deployment-Topologie-Pilot.md` | D2 concretization: admin routes, migration/retention role |

## 2. Profile → routes / connections matrix

| Profile | Route files | Registered paths | Connections |
| --- | --- | --- | --- |
| `identity` | `health`, `identity`, `identity-partner` | `/api/health`, `/api/auth/*`, `/api/admin/*`, `/api/partner/*` | `identity`, `audit` |
| `employee` | `health`, `employee` | `/api/health`, `/api/employee/*` | `identity`, `mapping`, `health`, `audit` |
| `company` | `health`, `company` | `/api/health`, `/api/company/*` | `identity`, `audit` |
| `full` | all of the above | everything (local/testing only) | all, incl. `*_migrator` |

Exclusion is structural: a profile never requires the other profiles' route files, so their endpoints are not registered and cannot leak. Connections not in the profile are absent from `config('database.connections')`, so constructing them throws `InvalidArgumentException: Database connection [x] not configured.`

The stock `sqlite`/`mysql`/`mariadb`/`pgsql`/`sqlsrv` keys are pinned to `null` in restricted profiles: Laravel's `LoadConfiguration` merges the framework's own database config for connection keys the application does not define, which would otherwise resurrect them.

## 3. route:list evidence

| Profile | `/api/*` routes | total routes |
| --- | --- | --- |
| `identity` | 35 | 40 |
| `employee` | 19 | 24 |
| `company` | 25 | 30 |
| `full` | 77 | 82 |

`full` versus pre-task `routes/api.php` (origin/main): `route:list --json` compared on `(method, uri, action, middleware)` — **82 routes, ordered-identical, no diff**. Requirement "no behavior change under `full`" holds for the route table.

One deliberate exception outside the route table: `/api/health` no longer returns `database` / `error` and returns `runtime` instead (task §"Privacy and Security Requirements"). The only consumer, `apps/web-angular/src/app/app.ts`, evaluates the status code, not the body.

## 4. Fail-safe behavior

| Situation | Result |
| --- | --- |
| `ELYO_RUNTIME` empty, `APP_ENV` not local/testing | boot abort: `ELYO_RUNTIME is required outside local/testing environments.` |
| `ELYO_RUNTIME` empty, `APP_ENV` local/testing | defaults to `full` |
| Unknown value | boot abort: `Invalid ELYO_RUNTIME [x].` |
| `full` outside local/testing | boot abort: `ELYO_RUNTIME [full] is allowed only when APP_ENV is local or testing.` |
| Unknown profile reaching the route/connection lookup | boot abort: `Unknown runtime profile [x]` |
| Configuration cache built for another runtime | boot abort in `AppServiceProvider::boot()` — the config files no longer execute when cached, so the checks above would silently not run |

## 5. Commands run and results

```
docker compose exec api php artisan test               → 507 passed (2914 assertions)
docker compose exec api php artisan test --filter=Runtime → 17 passed (140 assertions)
ELYO_RUNTIME=<profile> … php artisan route:list --json → counts per §3
```

## 6. Open questions

1. **Retention job has no runtime.** `elyo:enforce-retention` needs `health`, `identity` and `audit_migrator`; `audit_migrator` is in no deployed profile (ADR-001 §2.4: the migration role never lives in a runtime container) and a single profile holding both `health` and `audit_migrator` would be the Privacy/Admin runtime, which ADR-003 defers. Until then the job only runs under `full` locally. Decision needed with the privacy runtime, not in prompt 15.
2. **Configuration caching in the shared image (prompt 15).** Three services from one image must not share one config cache. Either skip `config:cache`/`optimize` at build time or build the cache per runtime at container start; the boot guard turns a mismatch into a hard failure rather than a silent credential leak, but it only fires when `ELYO_RUNTIME` is set in the container environment.
3. **Admin routes** sit in `identity` for now (ADR-003 D2 concretization); they move when the Privacy/Admin runtime ships.
