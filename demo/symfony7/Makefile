# Makefile for Symfony 7.4 Demo (Serial Number Bundle)

.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up verify

help:
	@echo "Serial Number Bundle - Symfony 7.4 Demo"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down            Stop containers"
	@echo "  make build           Build image (no cache)"
	@echo "  make install        Install dependencies"
	@echo "  make shell          Open shell in container"
	@echo "  make logs            Show container logs"
	@echo "  make test            Run tests"
	@echo "  make test-coverage   Run tests with coverage"
	@echo "  make cache-clear    Clear Symfony cache"
	@echo "  make update-bundle   Update bundle from path and clear cache"
	@echo "  make verify          Start and check HTTP 200"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
down:
	$(COMPOSE) down

restart: ensure-up
	$(COMPOSE) restart

build:
	$(COMPOSE) build --no-cache

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		$(COMPOSE) up -d; \
		sleep 3; \
	fi

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction

shell: ensure-up
	$(COMPOSE) exec php sh

logs:
	$(COMPOSE) logs -f

test: ensure-up
	$(COMPOSE) exec -e APP_ENV=test php composer test

test-coverage: ensure-up
	$(COMPOSE) exec php composer test-coverage

cache-clear: ensure-up
	$(COMPOSE) exec php php bin/console cache:clear

update-bundle: ensure-up
	$(COMPOSE) exec php composer update nowo-tech/google-translate-php-bundle --no-interaction
	$(COMPOSE) exec php php bin/console cache:clear
	@echo "Bundle updated."

verify:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@port=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); [ -z "$$port" ] && port=8001; \
	$(COMPOSE) up -d 2>/dev/null; sleep 8; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
	if [ "$$code" = "200" ]; then echo "OK (HTTP $$code)"; else echo "Failed (HTTP $$code)"; exit 1; fi


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
SERVICE_PHP := php
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
