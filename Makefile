.PHONY: help install up down restart ps logs web-dev api-dev migrate migrate-all fresh-all test test-boundary deptrac smoke check-grants verify-migration-restructure

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies for all apps
	cd apps/web-angular && npm install
	cd apps/api-laravel && composer install
	cp apps/api-laravel/.env.example apps/api-laravel/.env
	cd apps/api-laravel && php artisan key:generate

up: ## Start docker containers (three API runtimes + nginx + infra + api-tooling)
	docker compose up -d

down: ## Stop docker containers
	docker compose down

restart: ## Restart docker containers
	docker compose restart

ps: ## Show running containers
	docker compose ps

logs: ## Follow the logs of all three API runtimes
	docker compose logs -f api-identity api-employee api-company

web-dev: ## Start Angular development server
	cd apps/web-angular && npm start

api-dev: ## Start Laravel development server
	cd apps/api-laravel && php artisan serve

# Schema work runs in the one-shot `migrate` service, the only container that
# ever receives the elyo_migrator role (ADR-001 §2.4).
migrate-all: ## Run pending migrations for every ELYO domain database in order (docker)
	docker compose run --rm --entrypoint php migrate artisan migrate --database=identity_migrator --path=database/migrations/identity --force
	docker compose run --rm --entrypoint php migrate artisan migrate --database=mapping_migrator --path=database/migrations/mapping --force
	docker compose run --rm --entrypoint php migrate artisan migrate --database=health_migrator --path=database/migrations/health --force
	docker compose run --rm --entrypoint php migrate artisan migrate --database=audit_migrator --path=database/migrations/audit --force

fresh-all: ## Fresh-migrate every ELYO domain database and seed (docker)
	docker compose run --rm migrate

# The test suite needs every route and every connection (ELYO_RUNTIME=full), so
# it runs in the local-only api-tooling service, never in a deployable runtime.
test: ## Run the full API test suite (api-tooling)
	docker compose exec api-tooling php artisan test

test-boundary: ## Run the boundary testsuite (api-tooling)
	docker compose exec api-tooling php artisan test --testsuite=boundary

deptrac: ## Run the static boundary analysis (api-tooling)
	docker compose exec api-tooling composer deptrac

smoke: ## Verify runtime split: credential isolation, path routing, session continuity
	bash infra/smoke-runtime-split.sh

check-grants: ## Assert the PostgreSQL role boundaries created by the initdb script
	bash infra/postgres/check-grants.sh

verify-migration-restructure: ## Compare consolidated schema and routes with the pre-restructure baseline
	./tests/scripts/verify-migration-restructure.sh

migrate: migrate-all ## Alias for migrate-all
