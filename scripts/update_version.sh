#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

usage() {
    cat <<'EOF'
Usage: scripts/update_version.sh <version>

Updates the repository version across known project files.

Currently updates:
  - VERSION
  - composer.json
  - package.json            (if present)
  - package-lock.json       (if present)
  - npm-shrinkwrap.json     (if present)

Version must match semantic version format: X.Y.Z
EOF
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    usage
    exit 0
fi

if [ "$#" -ne 1 ]; then
    usage >&2
    exit 1
fi

NEW_VERSION="$1"

if [[ ! "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "[error] Invalid version: $NEW_VERSION" >&2
    echo "        Expected semantic version format X.Y.Z" >&2
    exit 1
fi

update_json_version() {
    local file="$1"

    if [ ! -f "$file" ]; then
        return
    fi

    perl -0pi -e 's/"version"\s*:\s*"[^"]*"/"version": "'"$NEW_VERSION"'"/' "$file"
}

printf '%s\n' "$NEW_VERSION" >"$REPO_ROOT/VERSION"
update_json_version "$REPO_ROOT/composer.json"
update_json_version "$REPO_ROOT/package.json"
update_json_version "$REPO_ROOT/package-lock.json"
update_json_version "$REPO_ROOT/npm-shrinkwrap.json"

echo "Updated version to $NEW_VERSION in:"
echo "  - VERSION"

if [ -f "$REPO_ROOT/composer.json" ]; then
    echo "  - composer.json"
fi

if [ -f "$REPO_ROOT/package.json" ]; then
    echo "  - package.json"
fi

if [ -f "$REPO_ROOT/package-lock.json" ]; then
    echo "  - package-lock.json"
fi

if [ -f "$REPO_ROOT/npm-shrinkwrap.json" ]; then
    echo "  - npm-shrinkwrap.json"
fi
