# Provider Integration Guide

## OpenAI

**Endpoint:** `https://api.openai.com/v1/chat/completions`  
**Auth:** Bearer token (`Authorization: Bearer <key>`)  
**Models:** `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`, `gpt-3.5-turbo`

```yaml
providers:
  openai:
    type: openai
    api_key: '%env(OPENAI_API_KEY)%'
    model: gpt-4o
```

## Anthropic

**Endpoint:** `https://api.anthropic.com/v1/messages`  
**Auth:** `x-api-key` header + `anthropic-version: 2023-06-01`  
**Models:** `claude-3-5-sonnet-20241022`, `claude-3-5-haiku-20241022`, `claude-3-opus-20240229`

```yaml
providers:
  anthropic:
    type: anthropic
    api_key: '%env(ANTHROPIC_API_KEY)%'
    model: claude-3-5-sonnet-20241022
```

## Mistral

**Endpoint:** `https://api.mistral.ai/v1/chat/completions`  
**Auth:** Bearer token  
**Models:** `mistral-large-latest`, `mistral-small-latest`, `open-mistral-7b`

```yaml
providers:
  mistral:
    type: mistral
    api_key: '%env(MISTRAL_API_KEY)%'
    model: mistral-large-latest
```

## Ollama (local)

**Endpoint:** `http://localhost:11434/api/chat` (configurable)  
**Auth:** None required  
**Models:** Any model pulled via `ollama pull <model>` — e.g. `llama3.2`, `mistral`, `phi3`, `qwen2.5`

```bash
# Pull a model before first use
ollama pull llama3.2
```

```yaml
providers:
  ollama:
    type: ollama
    base_url: 'http://localhost:11434'
    model: llama3.2
```

## LM Studio

LM Studio runs a local OpenAI-compatible server on port 1234. Use the `openai` adapter type with a custom `base_url`.

```yaml
providers:
  lmstudio:
    type: openai
    api_key: 'lm-studio'            # literal string, LM Studio ignores the key
    base_url: 'http://localhost:1234/v1'
    model: ''                       # LM Studio uses whichever model is currently loaded
```

## Azure OpenAI

Azure OpenAI uses the same schema as OpenAI but with a deployment-specific URL.

```yaml
providers:
  azure_openai:
    type: openai
    api_key: '%env(AZURE_OPENAI_KEY)%'
    base_url: 'https://<resource>.openai.azure.com/openai/deployments/<deployment>'
    model: gpt-4o
```

## Custom provider

1. Implement the interface:

```php
use Nikos\NrEnrichCore\Service\Provider\AiProviderInterface;

class MyProvider implements AiProviderInterface
{
    public function getName(): string { return 'my_provider'; }
    public function complete(string $prompt, EnrichmentConfig $config): AiProviderResponse { ... }
    public function healthCheck(): bool { ... }
}
```

2. Register and tag it:

```yaml
# config/services.yaml
App\Ai\MyProvider:
  tags: ['nr_enrich_core.provider']
```

3. Reference in bundle config:

```yaml
nr_enrich_core:
  providers:
    my_provider:
      type: custom
      service_id: App\Ai\MyProvider
```
