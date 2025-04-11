<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Service\Provider;

use Nikos\NrEnrichCore\Model\EnrichmentConfig;

/**
 * Contract that every AI provider adapter must fulfill.
 *
 * Implement this interface to integrate any inference backend
 * (cloud API, local Ollama, OpenAI-compatible endpoint, etc.)
 * without touching the core enrichment service.
 */
interface AiProviderInterface
{
    /**
     * Unique string key identifying this provider (e.g. 'openai', 'anthropic', 'ollama').
     * Must match the key used in the bundle configuration.
     */
    public function getName(): string;

    /**
     * Send a prompt and return the AI-generated completion.
     *
     * @throws \RuntimeException on transport errors or non-2xx API responses
     */
    public function complete(string $prompt, EnrichmentConfig $config): AiProviderResponse;

    /**
     * Verify that the provider is reachable and credentials are valid.
     * Used by the admin health-check panel.
     */
    public function healthCheck(): bool;
}
