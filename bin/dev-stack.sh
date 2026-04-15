#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$REPO_ROOT/.env"
ENV_EXAMPLE_FILE="$REPO_ROOT/.env.example"
PIMCORE_COMPOSE_FILE="$REPO_ROOT/docker/pimcore-compose.yml"
ISOLATED_COMPOSE_FILE="$REPO_ROOT/docker-test/docker-compose.yml"
SH_DIR="$REPO_ROOT/scripts/script-helpers"

if [ ! -f "$SH_DIR/helpers.sh" ]; then
    echo "[error] script-helpers submodule not initialised."
    echo "        Run: git submodule update --init --recursive"
    exit 1
fi

# shellcheck source=scripts/script-helpers/helpers.sh
source "$SH_DIR/helpers.sh"
shlib_import logging docker env deps

ensure_env_file() {
    if [ -f "$ENV_FILE" ]; then
        return
    fi

    if [ ! -f "$ENV_EXAMPLE_FILE" ]; then
        echo "[error] Missing $ENV_EXAMPLE_FILE"
        exit 1
    fi

    cp "$ENV_EXAMPLE_FILE" "$ENV_FILE"
    echo "[info] Created $ENV_FILE from .env.example"
}

resolve_path() {
    case "$1" in
        /*) printf '%s\n' "$1" ;;
        *) printf '%s/%s\n' "$REPO_ROOT" "$1" ;;
    esac
}

load_env_file() {
    ensure_env_file

    load_env "$ENV_FILE"

    export HOST_UID="${HOST_UID:-$(id -u)}"
    export HOST_GID="${HOST_GID:-$(id -g)}"
    export PIMCORE_APP_DIR="$(resolve_path "${PIMCORE_APP_DIR:-.pimcore-app}")"
    export PIMCORE_SKELETON_VERSION="${PIMCORE_SKELETON_VERSION:-2024.4.2}"
    export PIMCORE_PACKAGE_VERSION="${PIMCORE_PACKAGE_VERSION:-^11.0}"
    export PIMCORE_ADMIN_UI_CLASSIC_BUNDLE_VERSION="${PIMCORE_ADMIN_UI_CLASSIC_BUNDLE_VERSION:-^1.7}"
}

pimcore_compose() {
    docker_compose --env-file "$ENV_FILE" -f "$PIMCORE_COMPOSE_FILE" "$@"
}

isolated_compose() {
    docker_compose --env-file "$ENV_FILE" -f "$ISOLATED_COMPOSE_FILE" "$@"
}

ensure_pimcore_skeleton() {
    mkdir -p "$PIMCORE_APP_DIR"

    if [ ! -f "$PIMCORE_APP_DIR/composer.json" ]; then
        print_info "Creating Pimcore skeleton in $PIMCORE_APP_DIR"
        docker run --rm \
            -u "$HOST_UID:$HOST_GID" \
            -e COMPOSER_HOME=/tmp/composer \
            -v "$PIMCORE_APP_DIR:/var/www/html" \
            "$PIMCORE_DOCKER_IMAGE" \
            sh -lc "
                set -e
                composer create-project --no-install pimcore/skeleton:${PIMCORE_SKELETON_VERSION} /var/www/html
            "
    fi

    if [ -f "$PIMCORE_APP_DIR/vendor/autoload.php" ]; then
        return
    fi

    print_info "Installing Pimcore app dependencies in $PIMCORE_APP_DIR"
    docker run --rm \
        -u "$HOST_UID:$HOST_GID" \
        -e COMPOSER_HOME=/tmp/composer \
        -v "$PIMCORE_APP_DIR:/var/www/html" \
        "$PIMCORE_DOCKER_IMAGE" \
        sh -lc "
            set -e
            cd /var/www/html &&
            composer config platform.php 8.2.30 &&
            composer config audit.block-insecure false &&
            composer require pimcore/pimcore:${PIMCORE_PACKAGE_VERSION} --no-update --no-interaction &&
            composer require pimcore/admin-ui-classic-bundle:${PIMCORE_ADMIN_UI_CLASSIC_BUNDLE_VERSION} --no-update --no-interaction &&
            composer install --no-interaction
        "
}

wait_for_pimcore_db() {
    local elapsed=0

    until pimcore_compose exec -T db \
        mysqladmin ping -h 127.0.0.1 -uroot "-p${PIMCORE_DB_ROOT_PASSWORD}" --silent >/dev/null 2>&1; do
        sleep 2
        elapsed=$((elapsed + 2))
        if [ "$elapsed" -ge 90 ]; then
            print_error "Pimcore database did not become ready within 90 seconds."
            pimcore_compose logs db
            exit 1
        fi
    done
}

write_pimcore_env_local() {
    cat >"$PIMCORE_APP_DIR/.env.local" <<EOF
APP_ENV=dev
DATABASE_URL="mysql://${PIMCORE_DB_USER}:${PIMCORE_DB_PASSWORD}@db:3306/${PIMCORE_DB_NAME}"
PIMCORE_INSTALL_ADMIN_USERNAME=${PIMCORE_ADMIN_USERNAME}
PIMCORE_INSTALL_ADMIN_PASSWORD=${PIMCORE_ADMIN_PASSWORD}
NR_ENRICH_CORE_DEFAULT_PROVIDER=${NR_ENRICH_CORE_DEFAULT_PROVIDER}
OPENAI_API_KEY=${OPENAI_API_KEY}
OPENAI_MODEL=${OPENAI_MODEL}
ANTHROPIC_API_KEY=${ANTHROPIC_API_KEY}
ANTHROPIC_MODEL=${ANTHROPIC_MODEL}
MISTRAL_API_KEY=${MISTRAL_API_KEY}
MISTRAL_MODEL=${MISTRAL_MODEL}
OLLAMA_BASE_URL=${OLLAMA_BASE_URL}
OLLAMA_MODEL=${OLLAMA_MODEL}
EOF
}

ensure_pimcore_installed() {
    if [ -f "$PIMCORE_APP_DIR/var/config/system.yaml" ]; then
        return
    fi

    rm -f \
        "$PIMCORE_APP_DIR/config/packages/nr_enrich_core.yaml" \
        "$PIMCORE_APP_DIR/config/routes/nr_enrich_core.yaml"

    print_info "Installing Pimcore"
    pimcore_compose exec -T pimcore vendor/bin/pimcore-install \
        --mysql-host-socket=db \
        --mysql-port=3306 \
        --mysql-username="$PIMCORE_DB_USER" \
        --mysql-password="$PIMCORE_DB_PASSWORD" \
        --mysql-database="$PIMCORE_DB_NAME" \
        --admin-username="$PIMCORE_ADMIN_USERNAME" \
        --admin-password="$PIMCORE_ADMIN_PASSWORD" \
        --no-interaction
}

ensure_bundle_wiring() {
    local bundles_file="$PIMCORE_APP_DIR/config/bundles.php"
    local routes_file="$PIMCORE_APP_DIR/config/routes/nr_enrich_core.yaml"
    local package_file="$PIMCORE_APP_DIR/config/packages/nr_enrich_core.yaml"

    mkdir -p "$(dirname "$bundles_file")" "$(dirname "$routes_file")" "$(dirname "$package_file")"

    if ! grep -q "Nikos\\\\NrEnrichCore\\\\NrEnrichCoreBundle::class" "$bundles_file"; then
        pimcore_compose exec -T pimcore php -r '
            $file = $argv[1];
            $contents = file_get_contents($file);
            $entry = "    Nikos\\\\NrEnrichCore\\\\NrEnrichCoreBundle::class => ['\''all'\'' => true],\n";

            if (strpos($contents, "Nikos\\\\NrEnrichCore\\\\NrEnrichCoreBundle::class") === false) {
                $updated = preg_replace("/return \\[\\n/", "return [\n" . $entry, $contents, 1);
                if ($updated === null) {
                    fwrite(STDERR, "Failed to update $file\n");
                    exit(1);
                }
                file_put_contents($file, $updated);
            }
        ' /var/www/html/config/bundles.php
    fi

    cat >"$routes_file" <<'EOF'
nr_enrich_core:
  resource: '@NrEnrichCoreBundle/src/Resources/config/routes.yaml'
EOF

    cat >"$package_file" <<'EOF'
nr_enrich_core:
  default_provider: '%env(NR_ENRICH_CORE_DEFAULT_PROVIDER)%'
  providers:
    openai:
      type: openai
      api_key: '%env(OPENAI_API_KEY)%'
      model: '%env(OPENAI_MODEL)%'
    anthropic:
      type: anthropic
      api_key: '%env(ANTHROPIC_API_KEY)%'
      model: '%env(ANTHROPIC_MODEL)%'
    mistral:
      type: mistral
      api_key: '%env(MISTRAL_API_KEY)%'
      model: '%env(MISTRAL_MODEL)%'
    ollama:
      type: ollama
      base_url: '%env(OLLAMA_BASE_URL)%'
      model: '%env(OLLAMA_MODEL)%'
EOF
}

ensure_bundle_installed() {
    pimcore_compose exec -T pimcore \
        composer config repositories.nr-enrich-core path /workspace/nr-enrich-core

    if ! pimcore_compose exec -T pimcore composer show nikolareljin/nr-enrich-core >/dev/null 2>&1; then
        print_info "Requiring nr-enrich-core in the Pimcore app"
        pimcore_compose exec -T pimcore \
            composer require nikolareljin/nr-enrich-core:@dev --no-interaction
    fi
}

ensure_bundle_enabled() {
    if pimcore_compose exec -T pimcore php bin/console pimcore:bundle:list | grep -q 'NrEnrichCoreBundle'; then
        pimcore_compose exec -T pimcore php bin/console pimcore:bundle:install NrEnrichCoreBundle --no-interaction || true
    fi

    pimcore_compose exec -T pimcore php bin/console cache:clear --no-warmup
    pimcore_compose exec -T pimcore php bin/console assets:install public --symlink
}

start_pimcore_stack() {
    load_env_file
    check_docker
    ensure_pimcore_skeleton

    print_info "Starting Pimcore Docker stack"
    pimcore_compose up -d db pimcore
    wait_for_pimcore_db
    write_pimcore_env_local
    ensure_pimcore_installed
    ensure_bundle_installed
    ensure_bundle_wiring
    ensure_bundle_enabled

    print_success "Pimcore is available at http://localhost:${PIMCORE_HTTP_PORT}"
    print_info "Admin login: ${PIMCORE_ADMIN_USERNAME} / ${PIMCORE_ADMIN_PASSWORD}"
}

run_pimcore_smoke_test() {
    load_env_file

    print_info "Running Pimcore stack smoke test"
    pimcore_compose exec -T pimcore php bin/console about >/dev/null
    pimcore_compose exec -T pimcore php bin/console debug:container Nikos\\NrEnrichCore\\Service\\AiEnrichmentService >/dev/null
    pimcore_compose exec -T pimcore php bin/console debug:router | grep -q '/admin/nrec'
    print_success "Pimcore smoke test passed"
}

stop_pimcore_stack() {
    load_env_file

    print_info "Stopping Pimcore Docker stack"
    pimcore_compose stop || true
    isolated_compose down -v --remove-orphans >/dev/null 2>&1 || true
}

purge_pimcore_stack() {
    load_env_file

    print_info "Removing Pimcore Docker stack and volumes"
    pimcore_compose down -v --remove-orphans || true
    isolated_compose down -v --remove-orphans >/dev/null 2>&1 || true
    rm -rf "$PIMCORE_APP_DIR"
}
