# Changelog

All notable changes to NR EnrichCore are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

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
