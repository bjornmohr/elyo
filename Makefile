.PHONY: help install up down restart ps web-dev api-dev migrate migrate-all fresh-all verify-migration-restructure

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

install: ## Install dependencies for all apps
	cd apps/web-angular && npm install
	cd apps/api-laravel && composer install
	cp apps/api-laravel/.env.example apps/api-laravel/.env
	cd apps/api-laravel && php artisan key:generate

up: ## Start docker containers
	docker-compose up -d

down: ## Stop docker containers
	docker-compose down

restart: ## Restart docker containers
	docker-compose restart

ps: ## Show running containers
	docker-compose ps

web-dev: ## Start Angular development server
	cd apps/web-angular && npm start

api-dev: ## Start Laravel development server
	cd apps/api-laravel && php artisan serve

migrate-all: ## Run pending migrations for every ELYO domain database in order (docker)
	docker compose exec api php artisan migrate --database=identity_migrator --path=database/migrations/identity --force
	docker compose exec api php artisan migrate --database=mapping_migrator --path=database/migrations/mapping --force
	docker compose exec api php artisan migrate --database=health_migrator --path=database/migrations/health --force
	docker compose exec api php artisan migrate --database=audit_migrator --path=database/migrations/audit --force

fresh-all: ## Fresh-migrate every ELYO domain database and seed (docker)
	docker compose exec api php artisan elyo:migrate-fresh --seed

verify-migration-restructure: ## Compare consolidated schema and routes with the pre-restructure baseline
	./tests/scripts/verify-migration-restructure.sh

migrate: migrate-all ## Alias for migrate-all
