#!/usr/bin/env bash
##
## bin/install-pimcore-tests.sh
##
## Set up the NR EnrichCore Docker test environment.
## Analogous to WordPress's install-wp-tests.sh — prepares everything needed
## to run the test suite before the first `bin/run-tests.sh` call.
##
## Usage:
##   bin/install-pimcore-tests.sh [--no-build] [--reset]
##
## Options:
##   --no-build   Skip Docker image rebuild (use existing image).
##   --reset      Tear down any existing stack before starting (wipes DB data).
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
RESET=false
for arg in "$@"; do
    case "$arg" in
        --no-build) NO_BUILD=true ;;
        --reset)    RESET=true ;;
        *) print_error "Unknown option: $arg"; exit 1 ;;
    esac
done

# ── Load .env if present ──────────────────────────────────────────────────
if [ -f "$REPO_ROOT/.env" ]; then
    load_env "$REPO_ROOT/.env"
fi
if [ -f "$REPO_ROOT/docker-test/.env" ]; then
    load_env "$REPO_ROOT/docker-test/.env"
fi

export BUNDLE_SRC="${BUNDLE_SRC:-$REPO_ROOT}"
mkdir -p "$TMP_DIR"

print_info "NR EnrichCore — installing test environment"
print_info "Bundle source : $BUNDLE_SRC"
print_info "Compose file  : $COMPOSE_FILE"

# ── Optionally reset existing stack ───────────────────────────────────────
if [ "$RESET" = "true" ]; then
    print_info "Resetting existing test stack..."
    docker compose -f "$COMPOSE_FILE" down -v --remove-orphans 2>/dev/null || true
fi

# ── Build / pull images ───────────────────────────────────────────────────
if [ "$NO_BUILD" = "false" ]; then
    print_info "Building PHP test image..."
    docker compose -f "$COMPOSE_FILE" build php
fi

# ── Start services ────────────────────────────────────────────────────────
print_info "Starting test stack (db + php)..."
docker compose -f "$COMPOSE_FILE" up -d db php

# ── Wait for MySQL ────────────────────────────────────────────────────────
print_info "Waiting for MySQL to be ready..."
elapsed=0
until docker compose -f "$COMPOSE_FILE" exec -T db \
      mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null; do
    sleep 2
    elapsed=$((elapsed + 2))
    if [ "$elapsed" -ge 60 ]; then
        print_error "MySQL did not become ready within 60 seconds."
        docker compose -f "$COMPOSE_FILE" logs db
        exit 1
    fi
done
print_success "MySQL is ready."

# ── Install Composer dependencies ─────────────────────────────────────────
print_info "Running composer install..."
docker compose -f "$COMPOSE_FILE" exec -T php \
    composer config audit.block-insecure false
docker compose -f "$COMPOSE_FILE" exec -T php \
    composer install --no-interaction --prefer-dist

print_success "Test environment ready."
print_info  "Run tests with: bin/run-tests.sh"
