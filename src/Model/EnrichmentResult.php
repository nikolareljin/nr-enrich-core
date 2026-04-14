<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Model;

/**
 * Immutable record of a completed enrichment operation.
 * Suitable for audit logging, API responses, and test assertions.
 */
final class EnrichmentResult
{
    public function __construct(
        public readonly int $objectId,
        public readonly string $fieldName,
        public readonly mixed $originalValue,
        public readonly mixed $enrichedValue,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $tokensUsed,
        public readonly \DateTimeImmutable $enrichedAt,
        public readonly bool $versionCreated,
    ) {
    }

    public function toArray(): array
    {
        return [
            'objectId' => $this->objectId,
            'fieldName' => $this->fieldName,
            'originalValue' => $this->originalValue,
            'enrichedValue' => $this->enrichedValue,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokensUsed' => $this->tokensUsed,
            'enrichedAt' => $this->enrichedAt->format(\DateTimeInterface::ATOM),
            'versionCreated' => $this->versionCreated,
        ];
    }
}
