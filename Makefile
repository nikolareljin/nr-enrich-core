.PHONY: install test lint lint-fix clean help \
        docker-build docker-up docker-down docker-test docker-check docker-coverage

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

install: ## Install Composer dependencies
	composer install

test: ## Run PHPUnit test suite
	vendor/bin/phpunit --testdox

test-coverage: ## Run tests with HTML coverage report (requires Xdebug or PCOV)
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage/

lint: ## Check PSR-12 code style
	vendor/bin/phpcs

lint-fix: ## Auto-fix PSR-12 violations
	vendor/bin/phpcbf

clean: ## Remove generated artifacts
	rm -rf vendor/ .phpunit.result.cache coverage/ docker-test/tmp/

# ── Docker test commands (Pimcore test stack) ─────────────────────────────
# PHP_VERSION selects the runner image; Pimcore 12 supports 8.3 and 8.4 only.
#
# BUNDLE_SRC must be absolute. Compose resolves a relative bind path against
# the compose file's directory, not the shell's -- so BUNDLE_SRC=. mounted
# docker-test/ and the container came up with no composer.json in it. The bin/
# scripts already export an absolute $REPO_ROOT for the same reason.
BUNDLE_SRC ?= $(CURDIR)

docker-build: ## Build the PHP Docker test image (PHP_VERSION=8.3|8.4)
	BUNDLE_SRC=$(BUNDLE_SRC) docker compose -f docker-test/docker-compose.yml build php

docker-up: ## Start the Docker test stack (db + php)
	BUNDLE_SRC=$(BUNDLE_SRC) docker compose -f docker-test/docker-compose.yml up -d

docker-down: ## Tear down the Docker test stack and remove volumes
	BUNDLE_SRC=$(BUNDLE_SRC) docker compose -f docker-test/docker-compose.yml down -v --remove-orphans

docker-test: ## Run PHPUnit inside the Docker test container
	bin/run-tests.sh

docker-coverage: ## Run PHPUnit with Xdebug coverage inside Docker
	bin/run-tests.sh --coverage

docker-check: ## Run the full bundle quality gate (lint + static + tests)
	bin/check-pimcore-bundle.sh
