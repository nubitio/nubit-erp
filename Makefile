SHELL := /bin/bash

DC := docker compose
# mercure must be here: resources with mercure:true publish post-flush, so a
# missing hub turns every create into a 500 (row persisted, response failed).
SERVICES := database redis otel-collector web vite mercure
EXEC_WEB := $(DC) exec -T web sh -lc
EXEC_VITE := $(DC) exec -T --user root vite sh -lc
PHP_DEV := php -d memory_limit=256M

.DEFAULT_GOAL := help

.PHONY: help up down restart reset bootstrap install install-back install-front migrate migrate-control-plane migrate-tenants cache-clear dev build-front lint lint-php format format-php test test-back test-front e2e logs ps shell-web shell-vite

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
	@printf "  make dev              (Re)start Vite dev server so the app hot-reloads through :8080\n"
	@printf "  make build-front      Production frontend build for local testing (auto-restores dev mode after)\n"
	@printf "  make lint             Run frontend lint\n"
	@printf "  make lint-php         Run Mago lint + analyze + format check\n"
	@printf "  make format           Run frontend formatter\n"
	@printf "  make format-php       Run Mago formatter\n"
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

# Always restart (not `up -d`, which no-ops if the container is already
# running): `vite dev` only writes the dev-mode public/build/.vite/entrypoints.json
# (with a live `viteServer` URL) once, at startup. If a `yarn build` ever ran
# afterward — e.g. `make build-front`, or by hand inside the container — it
# overwrites that file with a static production manifest, and :8080 silently
# starts serving frozen prod assets with no hot reload until vite is restarted.
# Restarting here means `make dev` always self-heals that, whatever state
# things were left in.
dev:
	$(DC) restart vite
	$(DC) logs -f vite

# One-off production build for local testing (e.g. checking a PWA/offline
# behavior that only exists in the built bundle). Restarts vite immediately
# after so :8080 goes back to live dev mode instead of being left stuck
# serving the static build — see the comment on `dev` above for why that
# matters.
build-front:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn build'
	$(DC) restart vite
	@printf "\nProduction build done, dev server restarted — :8080 is back to hot-reload mode.\n"

lint:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn lint'

lint-php:
	$(EXEC_WEB) 'cd /app && mago lint && mago analyze && mago fmt --check'

format:
	$(EXEC_VITE) 'cd /home/node/app && corepack enable && yarn format'

format-php:
	$(EXEC_WEB) 'cd /app && mago fmt'

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
