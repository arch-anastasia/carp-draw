.PHONY: help setup up down restart build logs shell artisan npm test test-coverage lint format migrate seed fresh

# Default target
help:
	@echo "Available commands:"
	@echo ""
	@echo "  make setup     - Initial project setup (composer setup: install, key, migrate, build)"
	@echo "  make up        - Start Docker containers"
	@echo "  make down      - Stop Docker containers"
	@echo "  make restart   - Restart Docker containers"
	@echo "  make build     - Rebuild Docker containers"
	@echo "  make logs      - View container logs"
	@echo "  make shell     - Open shell in app container"
	@echo "  make artisan   - Run artisan command (use cmd='')"
	@echo "  make npm       - Run npm command in the node container (use cmd='')"
	@echo "  make test      - Run PHP tests"
	@echo "  make lint      - Run linters (Pint + ESLint)"
	@echo "  make format    - Format code (Pint + Prettier)"
	@echo "  make migrate   - Run migrations"
	@echo "  make seed      - Run database seeder"
	@echo "  make fresh     - Fresh migration with seeding"

# Initial setup — works with or without Docker (sqlite + file drivers).
setup:
	composer setup

# Docker commands
up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build --no-cache

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

artisan:
	docker compose exec app php artisan $(cmd)

npm:
	docker compose exec node npm $(cmd)

test:
	docker compose exec app php artisan test --compact

test-coverage:
	docker compose exec app php artisan test --coverage

lint:
	docker compose exec app ./vendor/bin/pint --dirty
	npm run lint

format:
	docker compose exec app ./vendor/bin/pint
	npm run format

migrate:
	docker compose exec app php artisan migrate

seed:
	docker compose exec app php artisan db:seed

fresh:
	docker compose exec app php artisan migrate:fresh --seed
