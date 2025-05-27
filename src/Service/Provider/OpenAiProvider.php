<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Service\Provider;

use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI provider adapter for the OpenAI Chat Completions API.
 * Also compatible with any OpenAI-compatible endpoint (LM Studio, vLLM, etc.)
 * by setting a custom $baseUrl.
 */
final class OpenAiProvider implements AiProviderInterface
{
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $defaultModel = 'gpt-4o',
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function complete(string $prompt, EnrichmentConfig $config): AiProviderResponse
    {
        $model = $config->model !== '' ? $config->model : $this->defaultModel;

        $body = [
            'model'    => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $config->temperature,
        ];

        if ($config->maxTokens > 0) {
            $body['max_tokens'] = $config->maxTokens;
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . '/chat/completions', [
            'auth_bearer' => $this->apiKey,
            'json'        => $body,
        ]);

        $data = $response->toArray();

        return new AiProviderResponse(
            content: $data['choices'][0]['message']['content'] ?? '',
            model: $data['model'] ?? $model,
            provider: $this->getName(),
            promptTokens: $data['usage']['prompt_tokens'] ?? 0,
            completionTokens: $data['usage']['completion_tokens'] ?? 0,
            rawResponse: $data,
        );
    }

    public function healthCheck(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/models', [
                'auth_bearer' => $this->apiKey,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}
