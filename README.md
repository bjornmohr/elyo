# ELYO Migration - Target Repository

This repository contains the new architecture for the ELYO platform.

## Architecture
- **Frontend**: Angular (`apps/web-angular`)
- **Backend**: Laravel (`apps/api-laravel`)
- **Database**: PostgreSQL
- **Infrastructure**: Docker (`infra/docker`)

## Prerequisites
- Docker & Docker Compose
- Node.js (v22+)
- PHP (v8.4+)
- Composer
- Make

## Local Development

### 1. Setup Environment
```bash
cp .env.example .env
make install
```

### 2. Start Services
```bash
# Start all core services in detached mode
docker compose up -d

# Start including legacy Next.js app
docker compose --profile legacy up -d

# Check service status
docker compose ps

# View logs
docker compose logs -f
```

### 3. Docker Maintenance
- `docker compose build`: Rebuild services after Dockerfile or configuration changes.
- `docker compose down`: Stop and remove containers.
- `docker compose down -v`: Stop containers and remove volumes (reset database).
- `docker compose restart [service]`: Restart a specific service.

### 4. Common Commands
- `make help`: Show all available commands.
- `make web-dev`: Start Angular development server.
- `make api-dev`: Start Laravel development server.
- `make migrate`: Run database migrations.

## Troubleshooting
- **Database Connection**: Ensure `DB_HOST=postgres` in `apps/api-laravel/.env`.
- **Port Conflicts**: If port 5432 or 8080 is taken, change the mapping in `docker-compose.yml`.
- **Permissions**: If Laravel storage is not writable, run `docker compose exec api chown -R www-data:www-data storage bootstrap/cache`.
- **Node Modules**: If Angular fails to start, try removing `apps/web-angular/node_modules` and restarting the container.

## Project Structure
- `apps/`: Application source code.
- `docs/`: Documentation and migration maps.
- `infra/`: Documentation and migration maps.