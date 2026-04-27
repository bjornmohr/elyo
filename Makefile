.PHONY: help install up down restart ps web-dev api-dev migrate

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

migrate: ## Run Laravel migrations
	cd apps/api-laravel && php artisan migrate
