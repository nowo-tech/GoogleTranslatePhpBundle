# Makefile for Symfony 8.1 Demo (Serial Number Bundle)

.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up verify

help:
	@echo "Serial Number Bundle - Symfony 8.1 Demo"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down            Stop containers"
	@echo "  make build           Build image (no cache)"
	@echo "  make install         Install dependencies"
	@echo "  make shell           Open shell in container"
	@echo "  make logs            Show logs"
	@echo "  make test            Run tests"
	@echo "  make test-coverage   Run tests with coverage"
	@echo "  make cache-clear    Clear Symfony cache"
	@echo "  make update-bundle   Update bundle from path and clear cache"
	@echo "  make verify          Start and check HTTP 200"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	docker-compose up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 -e COMPOSER_MAX_PARALLEL_HTTP=4 php composer install --no-interaction --prefer-dist
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
down:
	docker-compose down

restart: ensure-up
	docker-compose restart

build:
	docker-compose build --no-cache

ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		docker-compose up -d; \
		sleep 3; \
	fi

install: ensure-up
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 -e COMPOSER_MAX_PARALLEL_HTTP=4 php composer install --no-interaction --prefer-dist

shell: ensure-up
	docker-compose exec php sh

logs:
	docker-compose logs -f

test: ensure-up
	docker-compose exec -e APP_ENV=test php composer test

test-coverage: ensure-up
	docker-compose exec php composer test-coverage

cache-clear: ensure-up
	docker-compose exec php php bin/console cache:clear

update-bundle: ensure-up
	docker-compose exec php composer update nowo-tech/google-translate-php-bundle --no-interaction
	docker-compose exec php php bin/console cache:clear
	@echo "Bundle updated."

verify:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@port=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); [ -z "$$port" ] && port=8001; \
	docker-compose up -d 2>/dev/null; sleep 8; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
	if [ "$$code" = "200" ]; then echo "OK (HTTP $$code)"; else echo "Failed (HTTP $$code)"; exit 1; fi


# REQ-MAKE-008: update-deps (REQ-MAKE-008)
COMPOSE := docker-compose
SERVICE_PHP := php
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
