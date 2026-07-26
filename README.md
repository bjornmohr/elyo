# ELYO Target

This repository contains the target ELYO platform architecture:

- `apps/web-angular`: Angular frontend
- `apps/api-laravel`: Laravel API
- `docker-compose.yml`: local Docker setup for the frontend, API, PostgreSQL, Redis, n8n, Nginx, and Mailpit
- `infra/docker`: Docker support configuration, including the Nginx virtual host
- `docs`: API, deployment, and migration notes

## Prerequisites

Install these tools before cloning or starting the project.

### 1. Git

Git is required to clone the repository.

```bash
git --version
```

If it is missing:

- macOS: install Xcode Command Line Tools with `xcode-select --install`, or install Git with Homebrew.
- Windows: install Git for Windows from <https://git-scm.com/download/win>.
- Linux: install Git with your package manager, for example `sudo apt install git`.

### 2. Docker and Docker Compose

Docker runs the local services: PostgreSQL, Redis, Nginx, Laravel PHP-FPM, Angular, n8n, and Mailpit.

Install Docker Desktop:

- macOS: <https://docs.docker.com/desktop/setup/install/mac-install/>
- Windows: <https://docs.docker.com/desktop/setup/install/windows-install/>
- Linux: <https://docs.docker.com/desktop/setup/install/linux/>

After installation, start Docker Desktop and verify both commands work:

```bash
docker --version
docker compose version
```

This project uses the modern `docker compose` command. If your system only has `docker-compose`, update Docker Desktop or install the Compose plugin.

### 3. PHP and Composer

The API requires PHP 8.4 or newer and Composer for local development commands outside Docker.

```bash
php -v
composer --version
```

Install options:

- macOS with Homebrew: `brew install php composer`
- Windows: install PHP from <https://windows.php.net/download/> and Composer from <https://getcomposer.org/download/>
- Linux: install PHP and Composer with your package manager, or follow <https://getcomposer.org/download/>

The Docker API container also contains PHP and Composer, so you can run most API commands through Docker once the containers are built.

### 4. Node.js and npm

The frontend requires Node.js 22 or newer and npm.

```bash
node -v
npm -v
```

Install options:

- Use `nvm`, `fnm`, or another Node version manager.
- Or install Node.js from <https://nodejs.org/>.

### 5. Make

The root `Makefile` contains helper commands. Make is optional because every command is also shown directly in this README.

```bash
make --version
```

## First Clone Setup

Clone the repository and enter the project root.

```bash
git clone <repository-url> ELYO_TARGET
cd ELYO_TARGET
```

Copy the root environment file:

```bash
cp .env.example .env
```

The default database settings are:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=elyo
DB_USERNAME=elyo
DB_PASSWORD=elyo_secret
```

These values are used by `docker-compose.yml` to create the PostgreSQL database and by the Laravel container to connect to it.

Create the Laravel API environment file:

```bash
cp apps/api-laravel/.env.example apps/api-laravel/.env
```

Edit `apps/api-laravel/.env` so the database points to the Docker PostgreSQL service:

```env
APP_NAME=ELYO_API
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=elyo
DB_USERNAME=elyo
DB_PASSWORD=elyo_secret

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

REDIS_HOST=redis
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Install local dependencies:

```bash
cd apps/api-laravel
composer install
php artisan key:generate

cd ../web-angular
npm install

cd ../..
```

Equivalent helper:

```bash
make install
```

The helper installs dependencies and generates the Laravel app key. The checked-in
Laravel example environment is PostgreSQL-only and already matches the supported
Docker test and development path shown above.

## Start the Docker Setup

From the project root, build and start all containers:

```bash
docker compose up -d --build
```

Check that the services are running:

```bash
docker compose ps
```

The important services are:

- `web`: Angular frontend on <http://localhost:4200>
- `nginx`: single API entry point on <http://localhost:8080>
- `api-identity`, `api-employee`, `api-company`: the three Laravel runtimes
- `api-tooling`: local-only container for tests and artisan commands
- `postgres`: PostgreSQL database on local port `5432`
- `redis`: Redis on local port `6379`
- `mailpit`: test mailbox UI on <http://localhost:8025>
- `n8n`: workflow automation on <http://localhost:5678>

### The API Runtime Split

The Laravel API is not one container. It is built as **one image** and started as
**three containers**, each selecting its route subset and its database connection
allowlist through `ELYO_RUNTIME` (ADR-001 §2.4, ADR-003 D2). Every container
receives only the PostgreSQL credentials its own domain needs.

| Service | `ELYO_RUNTIME` | Serves | PostgreSQL roles |
| --- | --- | --- | --- |
| `api-identity` | `identity` | `/api/auth/*`, `/api/admin/*`, `/api/partner/*`, `/api/health` | `elyo_identity_rt` |
| `api-employee` | `employee` | `/api/employee/*`, `/api/health` | `elyo_employee_rt` (identity read, health, audit), `elyo_mapping_svc` (mapping) |
| `api-company` | `company` | `/api/company/*`, `/api/health` | `elyo_company_rt` (identity, audit) |
| `migrate` | `full` | one-shot schema + seed | `elyo_migrator` |
| `api-tooling` | `full` | tests and artisan, local only | all runtime roles + `elyo_migrator` |

`nginx` routes by path prefix, so the frontend keeps one base URL
(`http://localhost:8080/api`) and knows nothing about the split. A runtime that
does not own a prefix simply has no such route and answers `404` — there is no
aggregator container and no runtime-to-runtime traffic.

`api-tooling` exists because the test suite needs every route and every
connection, which only `ELYO_RUNTIME=full` provides. It has no published port and
no nginx upstream. It is a development convenience and is never deployed.

Two further services are prepared but not implemented; they start idle
placeholders and are not wired into nginx:

```bash
docker compose --profile future up -d reporting-worker api-privacy
```

Follow logs when something does not start:

```bash
docker compose logs -f
```

Or follow one service:

```bash
docker compose logs -f api-identity
docker compose logs -f api-employee
docker compose logs -f api-company
docker compose logs -f web
docker compose logs -f postgres
```

## Initialize the Database

Wait until PostgreSQL is healthy:

```bash
docker compose ps postgres
```

Build the schema for all four domain databases and seed them. This runs in the
one-shot `migrate` service, the only container that ever receives the
`elyo_migrator` role:

```bash
docker compose run --rm migrate
```

The service runs `php artisan elyo:migrate-fresh --seed`, which fresh-migrates
`identity`, `mapping`, `health` and `audit` in that order and then seeds. It
exits when done; `--rm` removes the container.

The demo seed creates these useful accounts:

| Role | Email | Password |
| --- | --- | --- |
| Company admin | `admin@demo.de` | `demo1234` |
| Manager | `manager@demo.de` | `demo1234` |
| Employee | `employee1@demo.de` | `demo1234` |
| ELYO support | `support@elyo.de` | `demo1234` |

To create or update a local admin account with the helper script:

```bash
docker compose exec api-tooling php scripts/create_admin.php
```

Optional custom credentials:

```bash
docker compose exec \
  -e ADMIN_EMAIL=admin@elyo.local \
  -e ADMIN_PASSWORD='ChangeMe123!' \
  api-tooling php scripts/create_admin.php
```

Verify the API and database connection:

```bash
curl http://localhost:8080/api/health
```

Expected result (`/api/health` is served by every runtime; the default nginx
upstream is identity):

```json
{"status":"up","runtime":"identity"}
```

Verify the runtime split itself — credential isolation, path routing and session
continuity across the three containers:

```bash
bash infra/smoke-runtime-split.sh
```

## Daily Development

Start the full stack:

```bash
docker compose up -d
```

Open:

- Frontend: <http://localhost:4200>
- API health check: <http://localhost:8080/api/health>
- Mailpit: <http://localhost:8025>
- n8n: <http://localhost:5678>

Stop the stack:

```bash
docker compose down
```

Restart a single service:

```bash
docker compose restart api-employee
docker compose restart web
```

Rebuild after Dockerfile, dependency, or environment changes. All API runtimes
share one image tag, so this builds once:

```bash
docker compose up -d --build
```

Run Laravel commands. Tests and cross-domain artisan commands go to
`api-tooling`, because only `ELYO_RUNTIME=full` registers every route and
connection:

```bash
docker compose exec api-tooling php artisan test
docker compose exec api-tooling php artisan test --testsuite=boundary
docker compose exec api-tooling composer deptrac
```

To see what a single runtime actually serves, ask that runtime:

```bash
docker compose exec api-identity php artisan route:list
docker compose exec api-employee php artisan route:list
docker compose exec api-company php artisan route:list
```

Schema changes always run through the one-shot migrator:

```bash
docker compose run --rm migrate
```

Run frontend commands locally:

```bash
cd apps/web-angular
npm start
npm run build
npm test
```

Run API commands locally:

```bash
cd apps/api-laravel
php artisan serve
php artisan test
composer test
```

If you run the API locally instead of through Docker, change `DB_HOST` in `apps/api-laravel/.env` from `postgres` to `127.0.0.1`, because `postgres` is only resolvable inside the Docker network.

## Reset the Database

To reset every domain database and seed fresh data:

```bash
docker compose run --rm migrate
```

To remove the PostgreSQL Docker volume completely (this also re-runs the initdb
script that creates the databases, roles and grants):

```bash
docker compose down -v
docker compose up -d --build
docker compose run --rm migrate
```

`docker compose down -v` deletes Docker volumes for this project, including database data. Use it only when you want a clean local database.

## Optional Legacy App

The compose file contains an optional `legacy-next` service behind the `legacy` profile. It expects a sibling `../ELYO` directory.

```bash
docker compose --profile legacy up -d
```

The legacy app is exposed on <http://localhost:3000>.

## Troubleshooting

### Port Already in Use

If `4200`, `5432`, `5678`, `6379`, `8025`, or `8080` is already used by another process, change the matching port mapping in `docker-compose.yml`.

### API Cannot Connect to Database

Each API runtime receives its database credentials from `docker-compose.yml`,
not from `apps/api-laravel/.env`. Check which role the failing runtime is using:

```bash
docker compose exec api-employee env | grep DB_
```

The role names must match the ones created by
`infra/postgres/initdb/01-databases-and-roles.sh`. Passwords come from the root
`.env` (`ELYO_*_PASSWORD`). Verify the grants themselves with:

```bash
bash infra/postgres/check-grants.sh
```

Then restart the affected runtime:

```bash
docker compose restart api-employee
```

If a runtime reports `Database connection [x] not configured`, the connection is
not in that profile's allowlist. That is intentional — the feature belongs in a
different runtime, not in a widened credential set.

### Laravel Key Missing

If Laravel reports that no application encryption key is set:

```bash
docker compose exec api-tooling php artisan key:generate
```

### Stale Configuration Cache

All API containers share the same bind-mounted source, so they also share
`bootstrap/cache/config.php`. A config cache built under one `ELYO_RUNTIME` is
rejected by the other runtimes on boot. Do not run `config:cache` or `optimize`
locally. If you already did:

```bash
docker compose exec api-tooling php artisan config:clear
```

### Laravel Storage Permissions

If the API cannot write logs, cache, or uploaded files:

```bash
docker compose exec api-tooling chown -R www-data:www-data storage bootstrap/cache
```

### Frontend Dependencies

If the Angular container fails after dependency changes:

```bash
docker compose down
docker volume rm elyo_target_angular_node_modules
docker compose up -d --build web
```

If your Docker project name is different, list volumes first:

```bash
docker volume ls
```

### Composer Dependencies in the API Container

`vendor/` is installed in the image, but the bind mount shadows it with the host
copy, so all API containers share one `vendor/`. If it is missing or outdated:

```bash
docker compose exec api-tooling composer install
```

### npm Dependencies in the Web Container

If `node_modules` is missing or outdated:

```bash
docker compose exec web npm install
```

## Useful Files

- `docker-compose.yml`: all local services and ports
- `.env.example`: root Docker/database defaults
- `apps/api-laravel/.env.example`: Laravel environment template
- `apps/api-laravel/Dockerfile`: API PHP-FPM image, shared by all three runtimes
- `apps/web-angular/Dockerfile`: Angular development image
- `infra/docker/nginx/default.conf`: Nginx path routing to the three API runtimes
- `infra/smoke-runtime-split.sh`: runtime-split smoke test
- `infra/postgres/initdb/01-databases-and-roles.sh`: databases, roles and grants
- `infra/postgres/check-grants.sh`: asserts the PostgreSQL role boundaries
- `apps/api-laravel/app/Runtime/RuntimeProfile.php`: route and connection sets per `ELYO_RUNTIME`
- `apps/api-laravel/database/seeders`: default and demo data seeders
