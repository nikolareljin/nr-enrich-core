# Configuration Reference

All bundle configuration lives under the `nr_enrich_core` key in your Symfony YAML config.

## Full schema

```yaml
nr_enrich_core:

  # Which provider to use when a field config specifies "default".
  # Must match a key in the providers map below.
  default_provider: openai   # required

  providers:                 # required — at least one provider

    <name>:                  # arbitrary key, used in field configs and CLI --provider flag
      type: openai|anthropic|ollama|mistral|custom   # required
      api_key: ''            # required for cloud providers; use %env(KEY)%
      model: ''              # default model for this provider (empty = adapter default)
      base_url: ''           # optional — custom inference endpoint
      service_id: ''         # required for type=custom; DI service ID of your adapter class
```

## Provider types

| type | Adapter class | Notes |
|---|---|---|
| `openai` | `OpenAiProvider` | Also works for LM Studio, vLLM, Azure OpenAI |
| `anthropic` | `AnthropicProvider` | Requires `anthropic-version` header, set automatically |
| `mistral` | `MistralProvider` | OpenAI-compatible schema |
| `ollama` | `OllamaProvider` | No API key, `base_url` points to local Ollama server |
| `custom` | Your class | Must implement `AiProviderInterface`; tag the service manually |

## Environment variables

Recommended pattern — define variables in `.env` and reference them with `%env()%`:

```dotenv
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
MISTRAL_API_KEY=...
OLLAMA_BASE_URL=http://localhost:11434
```

```yaml
providers:
  openai:
    type: openai
    api_key: '%env(OPENAI_API_KEY)%'
```

## Multiple providers of the same type

You can register multiple instances of the same adapter type under different names:

```yaml
providers:
  gpt4o:
    type: openai
    api_key: '%env(OPENAI_API_KEY)%'
    model: gpt-4o
  gpt4o_mini:
    type: openai
    api_key: '%env(OPENAI_API_KEY)%'
    model: gpt-4o-mini
```

Then reference them by name in per-field configs or the CLI `--provider` flag.
