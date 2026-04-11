# NR EnrichCore

> Provider-agnostic AI enrichment engine for **Pimcore 11**.  
> Enrich Data Object fields and Assets using any LLM — OpenAI, Anthropic, Mistral, Ollama, LM Studio, or any OpenAI-compatible endpoint.

---

## Overview

NR EnrichCore is a Pimcore 11 bundle that adds AI-powered content enrichment to the admin backend without locking you into a single cloud vendor. A clean provider interface makes it trivial to add new backends.

**Key capabilities (Basic):**
- Class-level AI configuration — prompt templates per field, per Pimcore class
- "Enrich with AI" button in the DataObject editor toolbar
- Bulk enrichment via the REST API
- Translation support (`language` field, `auto` detection)
- Pimcore versioning + audit log per enrichment
- Async queue support via Symfony Messenger
- Admin UI config panel (provider selector, field picker, prompt preview)
- REST endpoint: `POST /admin/nrec/enrich`
- CLI command: `bin/console nrec:enrich <objectId>`

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.1 |
| Pimcore | ^11.0 |
| Symfony | ^6.4 (included with Pimcore 11) |
| symfony/messenger | ^6.4 *(optional, for async queue)* |

---

## Installation

```bash
composer require nikolareljin/nr-enrich-core
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Nikos\NrEnrichCore\NrEnrichCoreBundle::class => ['all' => true],
];
```

Import the bundle routes in `config/routes.yaml`:

```yaml
nr_enrich_core:
    resource: '@NrEnrichCoreBundle/src/Resources/config/routes.yaml'
```

Install public assets:

```bash
php bin/console assets:install --symlink
```

---

## Local Docker Workflow

Use the top-level lifecycle scripts when working on the bundle locally. They create a root `.env` from `.env.example` on first run, provision a default Pimcore app, and mount this repository into that app as a path-based Composer dependency.

The default bootstrap is pinned to a Pimcore 11 compatible skeleton and package version so `./start` stays reproducible even when newer Pimcore releases raise the required PHP version.
The local `pimcore` container serves HTTP via PHP's built-in web server on port `8080`, which is sufficient for bundle development and smoke testing.

### Quick start

```bash
# 1. Create or reuse .env and bootstrap the Pimcore stack
./start

# 2. Run both test stages:
#    - isolated PHPUnit in the dedicated test runner
#    - Pimcore smoke test in the mounted app container
./test

# 3. Stop the running containers when you are done
./stop
```

### What `./start` does

- Copies `.env.example` to `.env` if the file does not exist yet
- Creates a local Pimcore skeleton in `.pimcore-app/`
- Starts MySQL + Pimcore via Docker Compose
- Installs Pimcore with the default credentials from `.env`
- Requires this repository into the app through a local Composer path repository
- Writes the default bundle config and route import into the generated Pimcore app
- Installs and enables the bundle, then refreshes public assets

### What `./test` does

- Ensures the Pimcore stack is bootstrapped
- Runs the isolated PHPUnit suite in the dedicated `docker-test/` Docker stack
- Runs a Pimcore smoke test against the mounted bundle inside the real app container

### Environment file

The root `.env` controls both the Pimcore app stack and the isolated PHPUnit stack. Review and adjust the defaults after the first run, especially:

- `PIMCORE_HTTP_PORT`
- `PIMCORE_DB_PORT`
- `PIMCORE_SKELETON_VERSION`
- `PIMCORE_PACKAGE_VERSION`
- `PIMCORE_ADMIN_UI_CLASSIC_BUNDLE_VERSION`
- `PIMCORE_ADMIN_USERNAME`
- `PIMCORE_ADMIN_PASSWORD`
- provider credentials such as `OPENAI_API_KEY`

Use `./stop --purge` to remove the generated Pimcore app and Docker volumes completely.

---

## Release Workflow

Update the project version with:

```bash
./scripts/update_version.sh 0.1.1
```

This script updates:

- `VERSION`
- `composer.json`
- `package.json`, `package-lock.json`, and `npm-shrinkwrap.json` when those files exist

---

## Configuration

Create `config/packages/nr_enrich_core.yaml`:

```yaml
nr_enrich_core:
  default_provider: openai
  providers:
    openai:
      type: openai
      api_key: '%env(OPENAI_API_KEY)%'
      model: gpt-4o
    ollama:
      type: ollama
      base_url: 'http://localhost:11434'
      model: llama3.2
```

Add environment variables to `.env`:

```dotenv
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
OLLAMA_BASE_URL=http://localhost:11434
```

---

## Provider Integration

### OpenAI

```yaml
providers:
  openai:
    type: openai
    api_key: '%env(OPENAI_API_KEY)%'
    model: gpt-4o          # or gpt-4o-mini, gpt-3.5-turbo
```

### Anthropic

```yaml
providers:
  anthropic:
    type: anthropic
    api_key: '%env(ANTHROPIC_API_KEY)%'
    model: claude-3-5-sonnet-20241022
```

### Mistral

```yaml
providers:
  mistral:
    type: mistral
    api_key: '%env(MISTRAL_API_KEY)%'
    model: mistral-large-latest
```

### Ollama (local)

```yaml
providers:
  ollama:
    type: ollama
    base_url: 'http://localhost:11434'
    model: llama3.2
```

### LM Studio / vLLM / any OpenAI-compatible endpoint

```yaml
providers:
  local:
    type: openai                         # reuses the OpenAI adapter
    api_key: 'lm-studio'
    base_url: 'http://localhost:1234/v1'
    model: ''
```

### Custom provider

Implement `Nikos\NrEnrichCore\Service\Provider\AiProviderInterface` and register it:

```yaml
services:
  App\Ai\MyCustomProvider:
    tags: ['nr_enrich_core.provider']
```

```yaml
nr_enrich_core:
  providers:
    my_custom:
      type: custom
      service_id: App\Ai\MyCustomProvider
```

---

## Usage

### Admin UI

Open any DataObject in the Pimcore admin. An **Enrich with AI** button appears in the editor toolbar.

Clicking it opens a panel where you can:
- Choose which fields to enrich (or leave empty for all configured fields)
- Override the AI provider
- Preview / override the prompt template

> *(Screenshot placeholder — toolbar button)*

> *(Screenshot placeholder — enrichment panel)*

### REST API

**Single object:**

```http
POST /admin/nrec/enrich
Content-Type: application/json

{
  "objectId": 42,
  "className": "Product",
  "fields": [
    {
      "fieldName": "description",
      "promptTemplate": "Rewrite this product description to be engaging and SEO-friendly:\n\n{{ value }}",
      "provider": "openai"
    }
  ]
}
```

**Response:**

```json
{
  "success": true,
  "results": [
    {
      "objectId": 42,
      "fieldName": "description",
      "originalValue": "Red widget, 10cm.",
      "enrichedValue": "Discover our vibrant red widget — compact at 10 cm and built to last…",
      "provider": "openai",
      "model": "gpt-4o",
      "tokensUsed": 312,
      "enrichedAt": "2026-04-07T10:00:00+00:00",
      "versionCreated": true
    }
  ]
}
```

**Bulk:**

```http
POST /admin/nrec/enrich/bulk
Content-Type: application/json

{
  "jobs": [
    { "objectId": 42, "className": "Product", "fields": [{ "fieldName": "description" }] },
    { "objectId": 43, "className": "Product", "fields": [{ "fieldName": "description" }] }
  ]
}
```

**Health check:**

```http
GET /admin/nrec/health
```

### CLI

```bash
# Enrich all configured fields on object #42
php bin/console nrec:enrich 42

# Enrich specific fields
php bin/console nrec:enrich 42 --field=description --field=shortDescription

# Use a specific provider
php bin/console nrec:enrich 42 --provider=anthropic

# Async (dispatches to Symfony Messenger queue)
php bin/console nrec:enrich 42 --async

# Dry-run — preview prompts without calling the AI
php bin/console nrec:enrich 42 --dry-run
```

### Prompt templates

Templates support three placeholders:

| Placeholder | Description |
|---|---|
| `{{ value }}` | Current field value |
| `{{ objectId }}` | Pimcore object ID |
| `{{ class }}` | Pimcore class name |

---

## Basic vs PRO Feature Matrix

| Feature | Basic | PRO |
|---|:---:|:---:|
| Class-level AI config | ✓ | ✓ |
| "Enrich with AI" toolbar button | ✓ | ✓ |
| Bulk enrichment via API | ✓ | ✓ |
| Translation support | ✓ | ✓ |
| Versioning + audit log | ✓ | ✓ |
| Provider abstraction layer | ✓ | ✓ |
| Async Messenger queue | ✓ | ✓ |
| REST API | ✓ | ✓ |
| CLI command | ✓ | ✓ |
| **Asset tagging via vision models** | — | ✓ |
| **SEO metadata generator** | — | ✓ |
| **Attribute normalization pipelines** | — | ✓ |
| **Scheduled enrichment jobs** | — | ✓ |
| **Webhook triggers** | — | ✓ |
| **Multi-step AI pipelines** | — | ✓ |
| **Role-based permissions (RBAC)** | — | ✓ |
| **Multi-provider fallback + cost-aware routing** | — | ✓ |
| **Analytics dashboard (tokens, cost, success rate)** | — | ✓ |
| **Enterprise SSO integration** | — | ✓ |

> PRO version is available as a separate repository. Contact the author for access.

---

## Roadmap

- [ ] Admin configuration UI (save field-level configs without code)
- [ ] Per-class bulk enrichment from grid view
- [ ] Token usage tracking in Pimcore database
- [ ] Retry logic with exponential back-off
- [ ] Support for Pimcore localized fields
- [ ] Qwen / DeepSeek / Gemini provider adapters
- [ ] PRO: Vision model support for Asset tagging
- [ ] PRO: Scheduled cron-based enrichment

---

## Testing

The repository now supports two complementary test paths:

- `./test` runs the isolated PHPUnit container stack and then a full Pimcore smoke test
- the existing `make docker-*` targets still expose the isolated PHPUnit stack directly

The isolated PHPUnit suite runs inside a Docker stack (PHP 8.1 + MySQL 8.0) so results are fully reproducible regardless of local environment.

### Prerequisites

- Docker + Docker Compose
- Git submodules initialised: `git submodule update --init --recursive`

### Quick start

```bash
# Preferred end-to-end workflow
./test

# Or run only the isolated PHPUnit stack
make docker-up
bin/install-pimcore-tests.sh --no-build
make docker-test
make docker-down
```

### Run with coverage

```bash
make docker-coverage
# Reports written to docker-test/tmp/coverage-clover.xml and docker-test/tmp/coverage-html/
```

### Full bundle quality gate (lint + static analysis + tests)

```bash
make docker-check
# Equivalent to: bin/check-pimcore-bundle.sh
```

This mirrors the WordPress plugin-check pattern — all five checks run in order:

| # | Check | Tool | Blocking |
|---|-------|------|:---:|
| 1 | PHP syntax lint | `php -l` | yes |
| 2 | Code style | PHPCS PSR-12 | yes |
| 3 | Static analysis | PHPStan (if `phpstan.neon` present) | yes |
| 4 | Unit tests | PHPUnit | yes |
| 5 | Style warnings | PHPCS advisory summary | no |

### Individual Makefile targets

| Target | Description |
|--------|-------------|
| `make docker-build` | Rebuild the PHP test image |
| `make docker-up` | Start db + php services |
| `make docker-down` | Stop and remove volumes |
| `make docker-test` | Run PHPUnit in the container |
| `make docker-coverage` | PHPUnit + Xdebug coverage |
| `make docker-check` | Full quality gate |

---

## Contributing

1. Fork the repository and create a branch: `git checkout -b feat/my-feature`
2. Install dependencies: `make install`
3. Write tests: `make test`
4. Check code style: `make lint` (auto-fix with `make lint-fix`)
5. Open a pull request describing what the change does and why.

Please follow PSR-12 coding standards. All new provider adapters must implement `AiProviderInterface` and include a unit test.

---

## License

MIT — see [LICENSE](LICENSE).
