SHELL := /bin/bash

DC := docker compose
SERVICES := database redis otel-collector web vite
EXEC_WEB := $(DC) exec -T web sh -lc
EXEC_VITE := $(DC) exec -T --user root vite sh -lc
PHP_DEV := php -d memory_limit=256M

.DEFAULT_GOAL := help

.PHONY: help up down restart reset bootstrap install install-back install-front migrate migrate-control-plane migrate-tenants cache-clear dev lint format test test-back test-front e2e logs ps shell-web shell-vite

help:
	@printf "\n"
	@printf "eFact dev commands\n"
	@printf "\n"
	@printf "  make up               Start dev containers\n"
	@printf "  make down             Stop containers\n"
	@printf "  make restart          Restart dev containers\n"
	@printf "  make reset            Recreate stack and volumes\n"
	@printf "  make bootstrap        Start stack, install deps, migrate, clear cache\n"
	@printf "  make install          Install backend and frontend dependencies\n"
	@printf "  make migrate          Run control plane and tenant migrations\n"
	@printf "  make cache-clear      Clear Symfony cache\n"
	@printf "  make dev              Start Vite dev server\n"
	@printf "  make lint             Run frontend lint\n"
	@printf "  make format           Run frontend formatter\n"
	@printf "  make test             Run backend and frontend tests\n"
	@printf "  make logs             Tail web/vite/database/redis/otel logs\n"
	@printf "  make shell-web        Open shell in web container\n"
	@printf "  make shell-vite       Open shell in vite container\n"
	@printf "\n"

up:
	$(DC) up -d $(SERVICES)

down:
	$(DC) down

restart: down up

reset:
	bash scripts/dev/reset.sh

bootstrap:
	bash scripts/dev/bootstrap.sh

install: install-back install-front

install-back:
	$(EXEC_WEB) 'cd /app && composer install --no-interaction --prefer-dist --no-scripts && $(PHP_DEV) bin/console cache:clear && $(PHP_DEV) bin/console assets:install public'

install-front:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn install --immutable'

migrate:
	bash scripts/dev/migrate.sh

migrate-control-plane:
	$(EXEC_WEB) 'cd /app && $(PHP_DEV) bin/console app:control-plane:migrate --no-interaction'

migrate-tenants:
	$(EXEC_WEB) 'cd /app && $(PHP_DEV) bin/console app:tenants:migrate --no-interaction'

cache-clear:
	$(EXEC_WEB) 'cd /app && $(PHP_DEV) bin/console cache:clear'

dev:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn dev --host 0.0.0.0'

lint:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn lint'

format:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn format'

test: test-back test-front

test-back:
	$(EXEC_WEB) 'cd /app && ./vendor/bin/phpunit'

test-front:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn test'

e2e:
	$(DC) --profile e2e run --rm e2e

logs:
	$(DC) logs -f web vite database redis otel-collector

ps:
	$(DC) ps

shell-web:
	$(DC) exec web sh -lc 'cd /app && exec bash'

shell-vite:
	$(DC) exec --user root vite sh -lc 'cd /home/node/app && exec bash'
