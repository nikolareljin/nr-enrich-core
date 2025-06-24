<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Service\Provider;

use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI provider adapter for Ollama (local inference server).
 *
 * Ollama exposes an OpenAI-compatible /api/chat endpoint.
 * No API key required — suitable for air-gapped or privacy-sensitive deployments.
 * Default base URL assumes Ollama running on localhost:11434.
 */
final class OllamaProvider implements AiProviderInterface
{
    private const DEFAULT_BASE_URL = 'http://localhost:11434';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $defaultModel = 'llama3.2',
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
    }

    public function getName(): string
    {
        return 'ollama';
    }

    public function complete(string $prompt, EnrichmentConfig $config): AiProviderResponse
    {
        $model = $config->model !== '' ? $config->model : $this->defaultModel;

        $body = [
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'stream'   => false,
            'options'  => [
                'temperature' => $config->temperature,
            ],
        ];

        if ($config->maxTokens > 0) {
            $body['options']['num_predict'] = $config->maxTokens;
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . '/api/chat', [
            'json' => $body,
        ]);

        $data = $response->toArray();

        return new AiProviderResponse(
            content: $data['message']['content'] ?? '',
            model: $data['model'] ?? $model,
            provider: $this->getName(),
            promptTokens: $data['prompt_eval_count'] ?? 0,
            completionTokens: $data['eval_count'] ?? 0,
            rawResponse: $data,
        );
    }

    public function healthCheck(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/api/tags');
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}
