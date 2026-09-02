# Changelog

All notable changes to NR EnrichCore are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Changed

- **BREAKING — requires Pimcore 12.** `pimcore/pimcore` constraint moved from `^11.0` to `^12.3.10` to clear two critical security advisories, `GHSA-9x44-4gxf-8c25` (RCE via DataObject class-definition field name) and `GHSA-w23p-wrp7-ch38` (PHP object injection via `Hotspotimage::getDataFromResource()`). Both are patched only in 12.3.10; **no fix was released for the 11.x line**, so remaining on Pimcore 11 leaves both criticals open.
- **BREAKING — PHP floor raised to 8.3** (from 8.1), required by Pimcore 12.
- Symfony constraints widened to accept the 7.x line alongside 6.4, matching Pimcore 12's own requirement of `^6.4.1 || ^7.3`.
- **A CI matrix update is still required and is _not_ part of this PR.** `.github/workflows/ci.yml` still targets PHP 8.1, 8.2 and 8.3. The 8.1 and 8.2 legs cannot satisfy Pimcore 12 and will fail until the matrix becomes `['8.3', '8.4']`. The change could not be included here because pushing workflow files needs an OAuth `workflow` scope that the authoring token lacks.
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
