<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Service\Provider;

/**
 * Immutable value object returned by every AI provider adapter.
 * Captures the generated content along with usage metadata.
 */
final class AiProviderResponse
{
    public function __construct(
        /** The AI-generated text content. */
        public readonly string $content,
        /** Model identifier as reported by the provider (e.g. 'gpt-4o', 'claude-3-5-sonnet'). */
        public readonly string $model,
        /** Provider key matching AiProviderInterface::getName(). */
        public readonly string $provider,
        /** Number of tokens consumed by the prompt. */
        public readonly int $promptTokens,
        /** Number of tokens in the completion. */
        public readonly int $completionTokens,
        /** Raw decoded API response for debugging or audit logging. */
        public readonly array $rawResponse = [],
    ) {
    }

    public function getTotalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
