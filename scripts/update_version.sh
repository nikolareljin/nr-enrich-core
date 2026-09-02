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

# python3 is required for the JSON-aware edits below. Checked up front so a
# missing interpreter is a clear message rather than a bare
# "python3: command not found" from halfway through the run.
if ! command -v python3 >/dev/null 2>&1; then
    echo "[error] python3 is required to update JSON version fields." >&2
    echo "        Install python3 and re-run." >&2
    exit 1
fi

if [[ ! "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "[error] Invalid version: $NEW_VERSION" >&2
    echo "        Expected semantic version format X.Y.Z" >&2
    exit 1
fi

# Update the version in a JSON file.
#
#   $1  path
#   $2  "manifest" (composer.json, package.json) or "lockfile"
#       (package-lock.json, npm-shrinkwrap.json)
#
# The distinction matters. An npm lockfile records the package's own version
# in two places -- top level and packages[""] -- and a plain search-and-replace
# updates whichever comes first and leaves the other disagreeing with it. A
# global replace would be worse still: it would rewrite the version of every
# dependency in the lockfile.
update_json_version() {
    local file="$1"
    local kind="$2"

    if [ ! -f "$file" ]; then
        return
    fi

    NEW_VERSION="$NEW_VERSION" python3 - "$file" "$kind" <<'PYEOF'
import json
import os
import sys

path, kind = sys.argv[1], sys.argv[2]
version = os.environ["NEW_VERSION"]

with open(path, encoding="utf-8") as handle:
    data = json.load(handle)

targets = [data]
if kind == "lockfile":
    packages = data.get("packages")
    if isinstance(packages, dict) and isinstance(packages.get(""), dict):
        targets.append(packages[""])

for target in targets:
    target["version"] = version

# Manifests are hand-edited, so their formatting is preserved by rewriting only
# the top-level version string. Lockfiles are generated, so they are re-emitted
# in npm's own two-space form.
if kind == "lockfile":
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(data, handle, indent=2, ensure_ascii=False)
        handle.write("\n")
else:
    import re

    with open(path, encoding="utf-8") as handle:
        raw = handle.read()

    updated, count = re.subn(
        r'"version"\s*:\s*"[^"]*"',
        '"version": "%s"' % version,
        raw,
        count=1,
    )
    if count != 1:
        sys.exit("[error] no version field found in %s" % path)

    # Fail loudly rather than write a file that no longer parses.
    if json.loads(updated).get("version") != version:
        sys.exit("[error] top-level version not updated in %s" % path)

    with open(path, "w", encoding="utf-8") as handle:
        handle.write(updated)
PYEOF
}

printf '%s\n' "$NEW_VERSION" >"$REPO_ROOT/VERSION"
update_json_version "$REPO_ROOT/composer.json" manifest
update_json_version "$REPO_ROOT/package.json" manifest
update_json_version "$REPO_ROOT/package-lock.json" lockfile
update_json_version "$REPO_ROOT/npm-shrinkwrap.json" lockfile

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
