.PHONY: install test lint lint-fix clean help

help: ## Show available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

install: ## Install Composer dependencies
	composer install

test: ## Run PHPUnit test suite
	vendor/bin/phpunit --testdox

test-coverage: ## Run tests with HTML coverage report (requires Xdebug or PCOV)
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage/

lint: ## Check PSR-12 code style
	vendor/bin/phpcs --standard=PSR12 src/

lint-fix: ## Auto-fix PSR-12 violations
	vendor/bin/phpcbf --standard=PSR12 src/

clean: ## Remove generated artifacts
	rm -rf vendor/ .phpunit.result.cache coverage/
