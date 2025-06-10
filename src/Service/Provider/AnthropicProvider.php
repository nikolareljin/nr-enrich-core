<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Service\Provider;

use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI provider adapter for the Anthropic Messages API.
 *
 * Differences from OpenAI:
 *  - Auth via `x-api-key` header (not Bearer token)
 *  - Requires `anthropic-version` header
 *  - Response content lives at `content[0].text`
 *  - Uses `max_tokens` (required, not optional)
 */
final class AnthropicProvider implements AiProviderInterface
{
    private const BASE_URL         = 'https://api.anthropic.com';
    private const API_VERSION      = '2023-06-01';
    private const DEFAULT_MAX_TOKENS = 1024;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $defaultModel = 'claude-3-5-sonnet-20241022',
    ) {
    }

    public function getName(): string
    {
        return 'anthropic';
    }

    public function complete(string $prompt, EnrichmentConfig $config): AiProviderResponse
    {
        $model     = $config->model !== '' ? $config->model : $this->defaultModel;
        $maxTokens = $config->maxTokens > 0 ? $config->maxTokens : self::DEFAULT_MAX_TOKENS;

        $response = $this->httpClient->request('POST', self::BASE_URL . '/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ],
            'json' => [
                'model'       => $model,
                'max_tokens'  => $maxTokens,
                'temperature' => $config->temperature,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
            ],
        ]);

        $data = $response->toArray();

        return new AiProviderResponse(
            content: $data['content'][0]['text'] ?? '',
            model: $data['model'] ?? $model,
            provider: $this->getName(),
            promptTokens: $data['usage']['input_tokens'] ?? 0,
            completionTokens: $data['usage']['output_tokens'] ?? 0,
            rawResponse: $data,
        );
    }

    public function healthCheck(): bool
    {
        try {
            // Anthropic does not expose a models list endpoint; send a minimal message instead.
            $this->httpClient->request('POST', self::BASE_URL . '/v1/messages', [
                'headers' => [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $this->defaultModel,
                    'max_tokens' => 1,
                    'messages'   => [['role' => 'user', 'content' => 'ping']],
                ],
            ])->toArray();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
