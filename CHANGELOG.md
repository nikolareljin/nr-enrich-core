# Changelog

All notable changes to NR EnrichCore are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [0.2.0] — 2026-09-02

### Changed

- **BREAKING — requires Pimcore 12.** `pimcore/pimcore` constraint moved from `^11.0` to `^12.3.10` to clear two critical security advisories, `GHSA-9x44-4gxf-8c25` (RCE via DataObject class-definition field name) and `GHSA-w23p-wrp7-ch38` (PHP object injection via `Hotspotimage::getDataFromResource()`). Both are patched only in 12.3.10; **no fix was released for the 11.x line**, so remaining on Pimcore 11 leaves both criticals open.
- **BREAKING — PHP floor raised to 8.3** (from 8.1), required by Pimcore 12.
- Symfony constraints widened to accept the 7.x line alongside 6.4, matching Pimcore 12's own requirement of `^6.4.1 || ^7.3`.
- **CI no longer uses a PHP version matrix of its own.** It calls the shared
  `ci-helpers` `pimcore.yml` preset with `php_versions: '["8.3", "8.4"]'`, which
  runs each version as a leg inside this repository's Docker Compose stack. The
  old `['8.1','8.2','8.3']` matrix could not pass at all: Pimcore 12 requires
  `~8.3.0 || ~8.4.0`, so the 8.1 and 8.2 legs failed at dependency resolution
  rather than on anything to do with this bundle.
- The workflows pin `ci-helpers` at **`@production`**, which now carries `php_versions` — ci-helpers 0.22.0 was released on 2026-09-02 and the floating tag advanced with it.
- `make lint` and `composer lint` now read `phpcs.xml.dist` instead of passing `--standard=PSR12 src/` on the command line, so the tooling and CI share one definition of the ruleset.

### Added

- **`squizlabs/php_codesniffer` to `require-dev`.** `make lint` runs `vendor/bin/phpcs`, but the package was never declared, so the CI lint step failed with `Error 127` (command not found) on every run — the style check had never actually executed.
- **`phpcs.xml.dist`.** Restricts phpcs to PHP files. Pointed at `src/` unqualified, phpcs parsed `src/Resources/public/js/nr-enrich-core.js` as PHP and reported ten PSR-12 violations against JavaScript. It also excludes the multiple-classes sniff for `EnrichObjectMessageHandler`, which deliberately declares the same class twice in an `if`/`else` so `#[AsMessageHandler]` is applied only when symfony/messenger is present.

### Fixed

- PSR-12: the file-level docblock in `EnrichObjectMessageHandler` now follows the opening `<?php` tag rather than sitting after the `use` block.
- PSR-12: wrapped nine lines exceeding 120 characters in `EnrichObjectCommand` and `EnrichmentApiController`.
- The PSR-12 check now passes cleanly (18/18 files, exit 0).
- **The PHPUnit suite now runs.** All four `AiEnrichmentService` tests were erroring at mock construction with `CannotUseOnlyMethodsException: Trying to configure method "getClassName" with onlyMethods(), but it does not exist in class "Pimcore\Model\DataObject\AbstractObject"`. Because the lint step failed first with exit 127, `make test` never ran in CI and this was never reported. 11/11 now pass against Pimcore 12.3.12.1 on PHP 8.4 (assertions rose from 12 to 25, since the tests previously died before asserting).
- `Concrete::saveVersion()` returns `?Pimcore\Model\Version`; the stub returned `$this`, which is a `TypeError` once the method is really typed.
- `$this->throwException(...)` passed to `willReturnOnConsecutiveCalls()` is not honoured as a throw under PHPUnit 10 — it is returned as a value, so `testEnrichObjectSwallowsPerFieldErrors` never exercised the failure path it claimed to. Replaced with an explicit `willReturnCallback`.

### Changed — type correctness

- **`AiEnrichmentService` now type-hints `DataObject\Concrete` rather than `DataObject\AbstractObject`.** The service calls `$object->getClassName()`, which is declared on `Concrete`; `AbstractObject` does not have it. Passing a plain `AbstractObject` — a folder, for instance — would have been a runtime fatal. The four `DataObject::getById()` call sites now guard with `instanceof Concrete`, so a folder or missing id is rejected at the boundary with a clear message instead of failing deep in prompt rendering.

### Added — local Docker environment

- **Isolated PHPUnit stack** in `docker-test/` — a PHP CLI runner with the
  extensions Pimcore 12 requires, plus MySQL 8.0. The PHP version is a build
  argument (`PHP_VERSION`, default `8.3`) because Pimcore 12 supports only
  `~8.3` and `~8.4`, so the image tracks the CI matrix rather than drifting
  from it.
- **Full Pimcore dev stack** in `docker/pimcore-compose.yml`, bootstrapped by
  `./start`, which generates a Pimcore app and mounts this repository into it
  as a path Composer dependency.
- **Top-level `./start`, `./stop` and `./test`** lifecycle scripts, seeding a
  gitignored `.env` from `.env.example` on first run.
- **`bin/` scripts** following the WordPress plugin-check convention:
  `install-pimcore-tests.sh` (set up the stack), `run-tests.sh` (PHPUnit, with
  optional coverage) and `check-pimcore-bundle.sh` (the full gate: PHP lint,
  PHPCS, static analysis, PHPUnit).
- **`scripts/script-helpers`** as a submodule pinned to the shared library's
  `production` ref, which the `bin/` scripts source for logging and Docker
  helpers.
- **`scripts/update_version.sh`** to keep `VERSION` and `composer.json` in step;
  this release was cut with it. It is JSON-aware per file type: an npm
  lockfile records the package's own version **twice** (top level and
  `packages[""]`), and a plain search-and-replace updates whichever comes
  first and leaves the other disagreeing — while a global replace would
  rewrite the version of every dependency in the lockfile. Manifests keep
  their hand-written formatting; lockfiles are re-emitted in npm's own form.
- **`make docker-build|up|down|test|coverage|check`** targets.

### Changed — the dev stack now targets Pimcore 12

- Test runner image moved from `php:8.1-cli` to a parameterised
  `php:${PHP_VERSION}-cli`, default `8.3`. **PHP 8.1 and 8.2 cannot resolve
  Pimcore 12 at all**, so they are no longer buildable targets.
- `./start` bootstrap retargeted: skeleton `2024.4.2` → `2025.4.2` (the first
  line requiring `pimcore/pimcore ^12.3`), package `^11.0` → `^12.3.10`, and
  `pimcore/admin-ui-classic-bundle` `^1.7` → `^2.3`, which is the line
  supporting Pimcore 12.3.
- Local Pimcore image `pimcore/pimcore:php8.2-latest` → `php8.3-latest`.
- **The Pimcore version pins are commented out in `.env.example`.** `./start` copies that file to `.env` and sources it, so any value set there overrides `bin/dev-stack.sh`'s defaults. The file still pinned `2024.4.2`, `^11.0` and `^1.7` after the bootstrap moved to Pimcore 12, which meant a fresh `./start` quietly built a Pimcore **11** stack while every other part of this release said 12. The defaults now live in one place; the file documents how to override them.
- **Two shipped strings still said Pimcore 11.** `NrEnrichCoreBundle::getDescription()` — which the Pimcore admin displays in its bundle list, so it was telling users the wrong supported version — and the header comment of the admin UI JavaScript. Both now say 12.
- **`./start` could register the bundle twice, or silently not at all.** The PHP that injects `NrEnrichCoreBundle` into the generated app's `bundles.php` guarded itself with `strpos($contents, "Nikos\\\\NrEnrichCore\\\\...")` — a double-backslash string the file never contains — so the guard never matched and a second run appended a duplicate entry. Worse, `preg_replace` returns its subject unchanged when nothing matches and `null` only on a regex error, so a `bundles.php` whose `return [` was followed by CRLF fell straight through the `=== null` check: the bootstrap reported success while the bundle was never registered. The class name is now passed as an argument, so the same single-backslash form is used for both the search and the insertion, the pattern tolerates CRLF, and a genuine non-match exits non-zero with a message naming the file.
- **The MySQL readiness loops in `bin/install-pimcore-tests.sh` and `bin/check-pimcore-bundle.sh` passed no credentials.** `mysqladmin ping` answers while the server is refusing the connection, so the loop could fall through on a database nothing can log in to — a readiness check that did not check readiness. They now pass `-uroot -p`, as the compose healthcheck already did.
- **`scripts/update_version.sh` requires `python3` and did not check for it.** It moved off `perl` for the JSON-aware edits, so a missing interpreter surfaced as a bare `python3: command not found` partway through. It now fails up front with an actionable message and leaves `VERSION` untouched.
- **The `make docker-*` targets mounted the wrong directory.** They passed `BUNDLE_SRC=.`, and Compose resolves a relative bind path against the compose file's directory rather than the shell's — so `docker-test/` was mounted at `/var/www/bundle` and the container came up with no `composer.json` in it. `BUNDLE_SRC` now defaults to `$(CURDIR)`, matching the absolute `$REPO_ROOT` the `bin/` scripts already exported.
- **Dropped `composer config audit.block-insecure false`** from the bootstrap.
  It existed to let an insecure Pimcore 11 install proceed; on patched
  Pimcore 12 the flag would only hide the next advisory. If the bootstrap ever
  fails on it again, that is a signal to read rather than a flag to restore.
- **Dropped the hardcoded `composer config platform.php 8.2.30`** so Composer
  resolves against the container's actual PHP, and overriding
  `PIMCORE_DOCKER_IMAGE` to an 8.4 image now works without a second edit.
- The async message handler is a **single class registered by an explicit
  `messenger.message_handler` tag**, replacing the previous pair of
  conditionally-declared classes behind a `class_exists()` check on the
  `#[AsMessageHandler]` attribute. One class is declared in one file, so the
  PSR-1 multiple-classes exclusion in `phpcs.xml.dist` is no longer needed and
  has been removed.
- The REST controller now distinguishes **404 for an object that does not
  exist** from **400 for one that exists but is not a concrete data object**,
  rather than reporting both as not found.

## [0.1.0] — 2026-04-07

### Added

- **Provider abstraction layer** — `AiProviderInterface` with `getName()`, `complete()`, and `healthCheck()` methods enabling any inference backend to be integrated without touching core logic.
- **OpenAI adapter** (`OpenAiProvider`) — Chat Completions API with configurable `base_url` for Azure OpenAI, LM Studio, and vLLM compatibility.
- **Anthropic adapter** (`AnthropicProvider`) — Messages API with `x-api-key` auth and `anthropic-version` header handling.
- **Ollama adapter** (`OllamaProvider`) — Local inference via `/api/chat`, no API key required.
- **Mistral adapter** (`MistralProvider`) — OpenAI-compatible Mistral AI API.
- **AiEnrichmentService** — Core orchestrator with tagged-iterator provider registry, prompt template rendering (`{{ value }}`, `{{ objectId }}`, `{{ class }}`), Pimcore field getter/setter reflection, and native Pimcore versioning support.
- **EnrichmentConfig DTO** — Per-field configuration: class, field, prompt template, provider key, language, model override, temperature, maxTokens, createVersion flag.
- **EnrichmentResult DTO** — Immutable enrichment record for audit logging and API responses.
- **REST API** — Three endpoints under `/admin/nrec/`:
  - `POST /admin/nrec/enrich` — single-object field enrichment.
  - `POST /admin/nrec/enrich/bulk` — batch enrichment across multiple objects.
  - `GET /admin/nrec/health` — provider health status check.
- **CLI command** `nrec:enrich` — enrich a DataObject by ID with `--field`, `--provider`, `--async`, `--dry-run`, and `--prompt` options.
- **Async queue support** — `EnrichObjectMessage` + `EnrichObjectMessageHandler` via Symfony Messenger (soft dependency, gracefully absent when not installed).
- **Pimcore admin UI extension** — ExtJS 6 plugin that injects an "Enrich with AI" toolbar button into every DataObject editor; opens a configuration panel for field selection, provider override, and prompt preview.
- **DependencyInjection** — Full Symfony Config TreeBuilder with multi-provider YAML support; `NrEnrichCoreExtension` auto-registers tagged provider services from config.
- **PHPUnit test suite** — Unit tests for `AiEnrichmentService` and `EnrichmentApiController`; `phpunit.xml.dist` with PHP 8.1+ configuration.
- **GitHub Actions CI** — Matrix build across PHP 8.1, 8.2, 8.3 with PSR-12 lint and test steps.
- **Documentation** — `docs/configuration.md`, `docs/providers.md`, `docs/admin-ui.md`.
- **Makefile** — `install`, `test`, `test-coverage`, `lint`, `lint-fix`, `clean` targets.

[0.1.0]: https://github.com/nikolareljin/nr-enrich-core/releases/tag/0.1.0
