#!/usr/bin/env bash
##
## bin/check-pimcore-bundle.sh
##
## Full quality gate for the NR EnrichCore bundle — the Pimcore equivalent of
## the WordPress plugin-check tool. Runs inside Docker so results are
## environment-independent.
##
## Checks performed (in order):
##   1. PHP syntax lint         — php -l on all source files
##   2. PHPCS PSR-12            — code style violations
##   3. PHPStan                 — static analysis (if phpstan.neon present)
##   4. PHPUnit                 — unit + integration tests
##   5. PHPCS warnings          — non-blocking style warnings (advisory only)
##
## Usage:
##   bin/check-pimcore-bundle.sh [--no-build] [--coverage] [--fix]
##
## Options:
##   --no-build   Skip Docker image rebuild.
##   --coverage   Run PHPUnit with Xdebug coverage (output to docker-test/tmp/).
##   --fix        Run phpcbf to auto-fix PHPCS violations before checking.
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
NO_BUILD=false
COVERAGE=false
FIX=false
for arg in "$@"; do
    case "$arg" in
        --no-build) NO_BUILD=true ;;
        --coverage) COVERAGE=true ;;
        --fix)      FIX=true ;;
        *) print_error "Unknown option: $arg"; exit 1 ;;
    esac
done

export BUNDLE_SRC="${BUNDLE_SRC:-$REPO_ROOT}"
mkdir -p "$TMP_DIR"

if [ -f "$REPO_ROOT/.env" ]; then
    load_env "$REPO_ROOT/.env"
fi

ERRORS=0

exec_php() {
    docker compose -f "$COMPOSE_FILE" exec -T php sh -lc "$*"
}

# ── Ensure stack is up ────────────────────────────────────────────────────
if [ "$NO_BUILD" = "false" ]; then
    print_info "Building PHP test image..."
    docker compose -f "$COMPOSE_FILE" build php
fi

docker compose -f "$COMPOSE_FILE" up -d db php

# Wait for MySQL
elapsed=0
until docker compose -f "$COMPOSE_FILE" exec -T db \
      mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null; do
    sleep 2; elapsed=$((elapsed + 2))
    if [ "$elapsed" -ge 60 ]; then
        print_error "MySQL did not become ready."; exit 1
    fi
done

exec_php "composer install --no-interaction --prefer-dist"

# ── 1. PHP syntax lint ────────────────────────────────────────────────────
print_info "1/5  PHP syntax lint..."
if exec_php "find src/ -name '*.php' -print0 | xargs -0 php -l | grep -v 'No syntax errors'"; then
    print_error "PHP syntax errors found."
    ERRORS=$((ERRORS + 1))
else
    print_success "No PHP syntax errors."
fi

# ── 2. PHPCS PSR-12 (blocking) ────────────────────────────────────────────
print_info "2/5  PHPCS PSR-12 (blocking)..."
if [ "$FIX" = "true" ]; then
    print_info "      Running phpcbf auto-fix first..."
    exec_php "vendor/bin/phpcbf --standard=PSR12 --extensions=php src/" || true
fi
if exec_php "vendor/bin/phpcs --standard=PSR12 --extensions=php --report=checkstyle --report-file=/tmp/phpcs.xml src/"; then
    print_success "PHPCS: no PSR-12 violations."
else
    docker compose -f "$COMPOSE_FILE" cp "php:/tmp/phpcs.xml" "$TMP_DIR/phpcs.xml" 2>/dev/null || true
    print_error "PHPCS: violations found (see docker-test/tmp/phpcs.xml)."
    ERRORS=$((ERRORS + 1))
fi

# ── 3. PHPStan static analysis ────────────────────────────────────────────
print_info "3/5  PHPStan static analysis..."
if exec_php "test -f phpstan.neon || test -f phpstan.neon.dist"; then
    if exec_php "vendor/bin/phpstan analyse --error-format=checkstyle > /tmp/phpstan.xml 2>&1"; then
        print_success "PHPStan: no issues."
    else
        docker compose -f "$COMPOSE_FILE" cp "php:/tmp/phpstan.xml" "$TMP_DIR/phpstan.xml" 2>/dev/null || true
        print_error "PHPStan: issues found (see docker-test/tmp/phpstan.xml)."
        ERRORS=$((ERRORS + 1))
    fi
else
    print_info "      No phpstan.neon found — skipping static analysis."
fi

# ── 4. PHPUnit ────────────────────────────────────────────────────────────
print_info "4/5  PHPUnit..."
if [ "$COVERAGE" = "true" ]; then
    PHPUNIT_CMD="XDEBUG_MODE=coverage vendor/bin/phpunit --testdox --log-junit /tmp/phpunit.xml --coverage-clover /tmp/coverage-clover.xml"
else
    PHPUNIT_CMD="vendor/bin/phpunit --testdox --log-junit /tmp/phpunit.xml"
fi

if exec_php "$PHPUNIT_CMD"; then
    print_success "PHPUnit: all tests passed."
else
    print_error "PHPUnit: test failures."
    ERRORS=$((ERRORS + 1))
fi

docker compose -f "$COMPOSE_FILE" cp "php:/tmp/phpunit.xml" "$TMP_DIR/phpunit.xml" 2>/dev/null || true
if [ "$COVERAGE" = "true" ]; then
    docker compose -f "$COMPOSE_FILE" cp "php:/tmp/coverage-clover.xml" "$TMP_DIR/coverage-clover.xml" 2>/dev/null || true
fi

# ── 5. PHPCS warnings (advisory, non-blocking) ───────────────────────────
print_info "5/5  PHPCS warnings (advisory, non-blocking)..."
exec_php "vendor/bin/phpcs --standard=PSR12 --extensions=php --warning-severity=1 --report=summary src/" || true

# ── Summary ───────────────────────────────────────────────────────────────
echo ""
if [ "$ERRORS" -eq 0 ]; then
    print_success "Bundle check passed — all $((5)) checks clean."
else
    print_error "Bundle check failed — $ERRORS check(s) reported errors."
    print_info  "Artifacts saved to docker-test/tmp/"
    exit 1
fi
