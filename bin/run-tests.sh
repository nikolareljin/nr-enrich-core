#!/usr/bin/env bash
##
## bin/run-tests.sh
##
## Run the NR EnrichCore PHPUnit test suite inside the Docker test container.
## Requires the stack to already be up (run bin/install-pimcore-tests.sh first).
##
## Usage:
##   bin/run-tests.sh [--coverage] [--filter <pattern>] [--stop-on-failure]
##
## Options:
##   --coverage          Generate Clover + HTML coverage reports (requires Xdebug).
##   --filter <pattern>  Pass --filter to PHPUnit.
##   --stop-on-failure   Stop PHPUnit on first failure.
##
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/docker-test/docker-compose.yml"
TMP_DIR="$REPO_ROOT/docker-test/tmp"

# ── Load script-helpers ───────────────────────────────────────────────────
SH_DIR="$REPO_ROOT/scripts/script-helpers"
if [ ! -f "$SH_DIR/helpers.sh" ]; then
    echo "[error] script-helpers submodule not initialised."
    echo "        Run: git submodule update --init --recursive"
    exit 1
fi
# shellcheck source=scripts/script-helpers/helpers.sh
source "$SH_DIR/helpers.sh"
shlib_import logging docker env

# ── Parse args ────────────────────────────────────────────────────────────
COVERAGE=false
PHPUNIT_ARGS=("--testdox" "--log-junit" "/tmp/phpunit-results.xml")

while [[ $# -gt 0 ]]; do
    case "$1" in
        --coverage)
            COVERAGE=true
            shift
            ;;
        --filter)
            PHPUNIT_ARGS+=("--filter" "$2")
            shift 2
            ;;
        --stop-on-failure)
            PHPUNIT_ARGS+=("--stop-on-failure")
            shift
            ;;
        *)
            print_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

export BUNDLE_SRC="${BUNDLE_SRC:-$REPO_ROOT}"
mkdir -p "$TMP_DIR"

if [ -f "$REPO_ROOT/.env" ]; then
    load_env "$REPO_ROOT/.env"
fi

# ── Ensure the stack is running ───────────────────────────────────────────
if ! docker compose -f "$COMPOSE_FILE" ps --services --filter status=running 2>/dev/null | grep -q "^php$"; then
    print_info "PHP service is not running — starting the test stack..."
    docker compose -f "$COMPOSE_FILE" up -d db php
    sleep 5
fi

# ── Run PHPUnit ───────────────────────────────────────────────────────────
if [ "$COVERAGE" = "true" ]; then
    print_info "Running PHPUnit with Xdebug coverage..."
    docker compose -f "$COMPOSE_FILE" exec -T \
        -e XDEBUG_MODE=coverage \
        php \
        vendor/bin/phpunit "${PHPUNIT_ARGS[@]}" \
            --coverage-clover /tmp/coverage-clover.xml \
            --coverage-html   /tmp/coverage-html

    # Copy reports to host
    docker compose -f "$COMPOSE_FILE" cp "php:/tmp/phpunit-results.xml"  "$TMP_DIR/phpunit-results.xml"  2>/dev/null || true
    docker compose -f "$COMPOSE_FILE" cp "php:/tmp/coverage-clover.xml"  "$TMP_DIR/coverage-clover.xml"  2>/dev/null || true
    docker compose -f "$COMPOSE_FILE" cp "php:/tmp/coverage-html"        "$TMP_DIR/coverage-html"        2>/dev/null || true
    print_success "Coverage reports written to docker-test/tmp/"
else
    print_info "Running PHPUnit..."
    docker compose -f "$COMPOSE_FILE" exec -T php \
        vendor/bin/phpunit "${PHPUNIT_ARGS[@]}"

    docker compose -f "$COMPOSE_FILE" cp "php:/tmp/phpunit-results.xml" "$TMP_DIR/phpunit-results.xml" 2>/dev/null || true
fi

print_success "Tests complete. Results in docker-test/tmp/phpunit-results.xml"
