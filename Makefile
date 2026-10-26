# Makefile for Google Translate PHP Bundle
# All dev targets use the root docker-compose.yml.

COMPOSE_FILE := docker-compose.yml
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE_BIN := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
COMPOSE     := $(COMPOSE_BIN) -f $(COMPOSE_FILE)
SERVICE_PHP := php

.PHONY: help up down down-dev build shell install test test-coverage coverage-check test-coverage-100 coverage-php-percent cs-check cs-fix qa clean release-check release-check-demos demo-smoke composer-sync rector rector-dry phpstan igor update validate setup-hooks check-no-cursor-coauthor check-open-prs strip-cursor-coauthor-from-history check-no-raw-html-form

help:
	@echo "Google Translate PHP Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  down-dev      Stop root $(COMPOSE) (dev) and remove orphans"
	@echo "  build         Rebuild Docker image (no cache)"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  assets        No-op (no frontend in this bundle)"
	@echo "  test          Run PHPUnit tests (starts container if needed)"
	@echo "  test-coverage Run tests with code coverage (starts container if needed)"
	@echo "  coverage-check Fail if PHP line coverage is under 100% (REQ-TEST-006)"
	@echo "  test-coverage-100 Alias for coverage-check"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  rector        Apply Rector refactoring"
	@echo "  rector-dry    Run Rector in dry-run mode"
	@echo "  phpstan       Run PHPStan static analysis"
	@echo "  igor          Run Igor worker-state audit (REQ-CS-008)"
	@echo "  qa            Run all QA checks (cs-check + test)"
	@echo "  release-check Pre-release (REQ-MAKE-002): git hygiene, open PRs, raw-form gate, QA, Igor, coverage, demos"
	@echo "  check-no-raw-html-form  Fail on raw <form>/<input>/<textarea>/<select> in Twig (REQ-TWIG-005)"
	@echo "  demo-smoke    Boot demos and assert HTTP 200 (REQ-TEST-011)"
	@echo "  composer-sync Validate composer.json and align composer.lock (no install)"
	@echo "  clean         Remove vendor and cache"
	@echo "  update        Update composer.lock (composer update)"
	@echo "  update-deps   Update bundle and demo Composer locks (Docker)"
	@echo "  validate      Run composer validate --strict"
	@echo "  setup-hooks   Install .githooks (REQ-GIT-001)"
	@echo "  check-no-cursor-coauthor  Fail if Cursor co-author trailers in history"
	@echo "  check-open-prs Fail if unresolved open GitHub PRs (REQ-REL-003)"
	@echo ""
	@echo "Demos (Symfony 7/8): make -C demo help"
	@echo ""

build:
	$(COMPOSE) build --no-cache

up:
	$(COMPOSE) build
	$(COMPOSE) up -d
	@echo "Installing dependencies..."
	$(COMPOSE) exec $(SERVICE_PHP) composer install --no-interaction
	@echo "✅ Container ready!"

down:
	$(COMPOSE) down

down-dev:
	$(COMPOSE) down --remove-orphans

shell:
	$(COMPOSE) exec $(SERVICE_PHP) sh

install: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer install
	@echo "✅ Dependencies installed."

ensure-up:
	@if ! $(COMPOSE) exec -T $(SERVICE_PHP) true 2>/dev/null; then \
		echo "Starting container (root $(COMPOSE))..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
		$(COMPOSE) exec -T $(SERVICE_PHP) composer install --no-interaction; \
	fi

test: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test

test-coverage: ensure-up
	$(COMPOSE) exec $(SERVICE_PHP) composer test-coverage | tee coverage-php.txt
	./.scripts/php-coverage-percent.sh coverage-php.txt

# REQ-TEST-006: exits non-zero when PHP line coverage is under 100% (composer coverage-check → .scripts/coverage-fail-under.sh).
coverage-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer coverage-check

test-coverage-100: coverage-check

cs-check: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-check

cs-fix: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer cs-fix

rector: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector

rector-dry: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer rector-dry

phpstan: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer phpstan

qa: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer qa

update: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-interaction

validate: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict


# Run Igor worker-state audit (REQ-CS-008)
igor: ensure-up
	$(COMPOSE) exec -T php composer igor
# REQ-MAKE-002 order.
release-check: ensure-up check-no-cursor-coauthor check-open-prs check-no-raw-html-form composer-sync cs-fix cs-check rector-dry phpstan igor coverage-check release-check-demos

check-no-raw-html-form:
	@bash .scripts/check-no-raw-html-form.sh

release-check-demos:
	@$(MAKE) -C demo release-check

# REQ-TEST-011: boot demos and assert HTTP 200
demo-smoke:
	@if [ -f demo/Makefile ]; then $(MAKE) -C demo release-verify; else echo "No demo/Makefile"; exit 1; fi

composer-sync: ensure-up

	$(COMPOSE) exec -T $(SERVICE_PHP) composer validate --strict
	$(COMPOSE) exec -T $(SERVICE_PHP) composer update --no-install

clean: ensure-up
	$(COMPOSE) exec -T $(SERVICE_PHP) sh -c "rm -rf vendor .phpunit.cache coverage coverage.xml .php-cs-fixer.cache"

assets:
	@echo "No frontend assets in this bundle."



setup-hooks:
	@chmod +x .githooks/pre-commit 2>/dev/null || true
	@chmod +x .githooks/commit-msg 2>/dev/null || true
	@git config core.hooksPath .githooks
	@echo "✅ Git hooks installed (.githooks — pre-commit + commit-msg for REQ-GIT-001)."

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.update-deps.mk
check-no-cursor-coauthor:
	@chmod +x .scripts/check-no-cursor-coauthor.sh
	@./.scripts/check-no-cursor-coauthor.sh HEAD

check-open-prs:
	@chmod +x .scripts/check-open-prs.sh
	@GH_REPO=nowo-tech/GoogleTranslatePhpBundle ./.scripts/check-open-prs.sh

strip-cursor-coauthor-from-history:
	@chmod +x .scripts/strip-cursor-coauthor-from-history.sh
	@./.scripts/strip-cursor-coauthor-from-history.sh main
