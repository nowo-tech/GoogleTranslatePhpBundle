# Makefile for Google Translate PHP Bundle Demo
# Delegates to each demo (symfony7, symfony8).

DEMOS := symfony7 symfony8

.PHONY: help clean test-all test-coverage-all verify-all release-verify
.PHONY: $(foreach d,$(DEMOS),up-$d down-$d restart-$d build-$d install-$d update-bundle-$d shell-$d logs-$d test-$d test-coverage-$d verify-$d)
.PHONY: up down restart build install update-bundle test shell logs test-coverage verify

help:
	@echo "Google Translate PHP Bundle - Demo Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Per demo (symfony7, symfony8):"
	@echo "  up-<demo>           Start demo (e.g. make up-symfony8)"
	@echo "  down-<demo>         Stop demo"
	@echo "  restart-<demo>      Restart demo containers"
	@echo "  build-<demo>        Rebuild image (no cache)"
	@echo "  install-<demo>      Install dependencies"
	@echo "  update-bundle-<demo> Update bundle from path and clear cache"
	@echo "  shell-<demo>        Open shell in container"
	@echo "  logs-<demo>         Show logs"
	@echo "  test-<demo>         Run tests"
	@echo "  test-coverage-<demo> Run tests with coverage"
	@echo "  verify-<demo>       Start demo and check HTTP 200"
	@echo ""
	@echo "Generic (use DEMO=<name>): up, down, install, test, shell, logs, update-bundle"
	@echo "  make up DEMO=symfony8"
	@echo ""
	@echo "Other:"
	@echo "  test-all            Run tests in all demos"
	@echo "  test-coverage-all   Run tests with coverage in all demos"
	@echo "  verify-all          Verify all demos (up + HTTP 200)"
	@echo "  release-verify      For release-check: up each demo, HTTP 200, down"
	@echo "  clean               Remove vendor and var from all demos"
	@echo ""
	@echo "Access (after make up-<demo>): see .env PORT in each demo (default 8001)."

# Per-demo targets (delegate to demo Makefile)
up-symfony7:
	@$(MAKE) -C symfony7 up
up-symfony8:
	@$(MAKE) -C symfony8 up

down-symfony7:
	@$(MAKE) -C symfony7 down
down-symfony8:
	@$(MAKE) -C symfony8 down

restart-symfony7:
	@$(MAKE) -C symfony7 restart
restart-symfony8:
	@$(MAKE) -C symfony8 restart

build-symfony7:
	@$(MAKE) -C symfony7 build
build-symfony8:
	@$(MAKE) -C symfony8 build

install-symfony7:
	@$(MAKE) -C symfony7 install
install-symfony8:
	@$(MAKE) -C symfony8 install

update-bundle-symfony7:
	@$(MAKE) -C symfony7 update-bundle
update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

shell-symfony7:
	@$(MAKE) -C symfony7 shell
shell-symfony8:
	@$(MAKE) -C symfony8 shell

logs-symfony7:
	@$(MAKE) -C symfony7 logs
logs-symfony8:
	@$(MAKE) -C symfony8 logs

test-symfony7:
	@$(MAKE) -C symfony7 test
test-symfony8:
	@$(MAKE) -C symfony8 test

test-coverage-symfony7:
	@$(MAKE) -C symfony7 test-coverage
test-coverage-symfony8:
	@$(MAKE) -C symfony8 test-coverage

verify-symfony7:
	@$(MAKE) -C symfony7 verify
verify-symfony8:
	@$(MAKE) -C symfony8 verify

# Generic with DEMO=
up:
	@[ -n "$(DEMO)" ] || { echo "Usage: make up DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) up-$(DEMO)
down:
	@[ -n "$(DEMO)" ] || { echo "Usage: make down DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) down-$(DEMO)
restart:
	@[ -n "$(DEMO)" ] || { echo "Usage: make restart DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) restart-$(DEMO)
build:
	@[ -n "$(DEMO)" ] || { echo "Usage: make build DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) build-$(DEMO)
install:
	@[ -n "$(DEMO)" ] || { echo "Usage: make install DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) install-$(DEMO)
update-bundle:
	@[ -n "$(DEMO)" ] || { echo "Usage: make update-bundle DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) update-bundle-$(DEMO)
shell:
	@[ -n "$(DEMO)" ] || { echo "Usage: make shell DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) shell-$(DEMO)
logs:
	@[ -n "$(DEMO)" ] || { echo "Usage: make logs DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) logs-$(DEMO)
test:
	@[ -n "$(DEMO)" ] || { echo "Usage: make test DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) test-$(DEMO)
test-coverage:
	@[ -n "$(DEMO)" ] || { echo "Usage: make test-coverage DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) test-coverage-$(DEMO)
verify:
	@[ -n "$(DEMO)" ] || { echo "Usage: make verify DEMO=symfony7|symfony8"; exit 1; }
	@$(MAKE) verify-$(DEMO)

test-all:
	@$(MAKE) test-symfony7
	@$(MAKE) test-symfony8
	@echo "All demo tests passed."

test-coverage-all:
	@$(MAKE) test-coverage-symfony7
	@$(MAKE) test-coverage-symfony8

verify-all:
	@$(MAKE) verify-symfony7
	@$(MAKE) verify-symfony8

release-check: test-coverage-all release-verify
	@echo "All demos passed release-check."

release-verify:
	@for demo in $(DEMOS); do \
		port=$$(grep '^PORT=' $$demo/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
		[ -z "$$port" ] && port=8001; \
		echo "Release verify: $$demo (port $$port)..."; \
		$(MAKE) up-$$demo; \
		sleep 10; \
		code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
		$(MAKE) down-$$demo 2>/dev/null || true; \
		if [ "$$code" != "200" ]; then echo "Demo $$demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
		echo "OK $$demo (HTTP $$code)"; \
	done
	@echo "All demos passed release-verify."

clean:
	@rm -rf $(addsuffix /vendor,$(DEMOS)) $(addsuffix /var,$(DEMOS)) $(addsuffix /.phpunit.cache,$(DEMOS))
	@echo "Cleaned demos."


# REQ-MAKE-008: update-deps-all (REQ-MAKE-008)
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
