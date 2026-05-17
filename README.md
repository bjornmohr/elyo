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

The helper installs dependencies and generates the Laravel app key. Check `apps/api-laravel/.env` afterward because the default Laravel example file uses SQLite and may need to be changed to PostgreSQL as shown above.

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
- `nginx`: Laravel API proxy on <http://localhost:8080>
- `api`: Laravel PHP-FPM container
- `postgres`: PostgreSQL database on local port `5432`
- `redis`: Redis on local port `6379`
- `mailpit`: test mailbox UI on <http://localhost:8025>
- `n8n`: workflow automation on <http://localhost:5678>

Follow logs when something does not start:

```bash
docker compose logs -f
```

Or follow one service:

```bash
docker compose logs -f api
docker compose logs -f web
docker compose logs -f postgres
```

## Initialize the Database

Wait until PostgreSQL is healthy:

```bash
docker compose ps postgres
```

Run Laravel migrations inside the API container:

```bash
docker compose exec api php artisan migrate
```

Seed the database with the default and demo data:

```bash
docker compose exec api php artisan db:seed
```

The demo seed creates these useful accounts:

| Role | Email | Password |
| --- | --- | --- |
| Company admin | `admin@demo.de` | `demo1234` |
| Manager | `manager@demo.de` | `demo1234` |
| Employee | `employee1@demo.de` | `demo1234` |
| ELYO support | `support@elyo.de` | `demo1234` |

To create or update a local admin account with the helper script:

```bash
docker compose exec api php scripts/create_admin.php
```

Optional custom credentials:

```bash
docker compose exec \
  -e ADMIN_EMAIL=admin@elyo.local \
  -e ADMIN_PASSWORD='ChangeMe123!' \
  api php scripts/create_admin.php
```

Verify the API and database connection:

```bash
curl http://localhost:8080/api/health
```

Expected result:

```json
{"status":"up","database":"connected"}
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
docker compose restart api
docker compose restart web
```

Rebuild after Dockerfile, dependency, or environment changes:

```bash
docker compose up -d --build
```

Run Laravel commands:

```bash
docker compose exec api php artisan route:list
docker compose exec api php artisan migrate
docker compose exec api php artisan test
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

To reset only the Laravel tables and seed fresh data:

```bash
docker compose exec api php artisan migrate:fresh --seed
```

To remove the PostgreSQL Docker volume completely:

```bash
docker compose down -v
docker compose up -d --build
docker compose exec api php artisan migrate --seed
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

Check that `apps/api-laravel/.env` uses:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=elyo
DB_USERNAME=elyo
DB_PASSWORD=elyo_secret
```

Then restart the API container:

```bash
docker compose restart api
```

### Laravel Key Missing

If Laravel reports that no application encryption key is set:

```bash
docker compose exec api php artisan key:generate
```

### Laravel Storage Permissions

If the API cannot write logs, cache, or uploaded files:

```bash
docker compose exec api chown -R www-data:www-data storage bootstrap/cache
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

If `vendor` is missing or outdated:

```bash
docker compose exec api composer install
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
- `apps/api-laravel/Dockerfile`: API PHP-FPM image
- `apps/web-angular/Dockerfile`: Angular development image
- `infra/docker/nginx/default.conf`: Nginx config for the Laravel public directory
- `apps/api-laravel/database/seeders`: default and demo data seeders
