<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Model;

/**
 * Carries the per-field enrichment configuration.
 *
 * Instances are built from the admin UI JSON config or programmatically.
 * This is a plain PHP DTO — it is NOT a Pimcore DataObject.
 */
final class EnrichmentConfig
{
    /**
     * @param string $className      Pimcore class name (e.g. 'Product').
     * @param string $fieldName      Pimcore field getter/setter name (e.g. 'description').
     * @param string $promptTemplate Template string. Placeholders: {{ value }}, {{ objectId }}, {{ class }}.
     * @param string $provider       Named provider key from bundle config, or 'default'.
     * @param string $language       ISO 639-1 target language code, or 'auto' to keep source language.
     * @param string $model          Override model for this field. Empty string = use provider default.
     * @param float  $temperature    Sampling temperature (0.0–2.0).
     * @param int    $maxTokens      Max completion tokens. 0 = use provider default.
     * @param bool   $createVersion  Whether to create a Pimcore version snapshot before writing.
     */
    public function __construct(
        public readonly string $className,
        public readonly string $fieldName,
        public readonly string $promptTemplate,
        public readonly string $provider = 'default',
        public readonly string $language = 'auto',
        public readonly string $model = '',
        public readonly float $temperature = 0.7,
        public readonly int $maxTokens = 0,
        public readonly bool $createVersion = true,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            className: $data['className'] ?? '',
            fieldName: $data['fieldName'] ?? '',
            promptTemplate: $data['promptTemplate'] ?? '',
            provider: $data['provider'] ?? 'default',
            language: $data['language'] ?? 'auto',
            model: $data['model'] ?? '',
            temperature: (float) ($data['temperature'] ?? 0.7),
            maxTokens: (int) ($data['maxTokens'] ?? 0),
            createVersion: (bool) ($data['createVersion'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'className'      => $this->className,
            'fieldName'      => $this->fieldName,
            'promptTemplate' => $this->promptTemplate,
            'provider'       => $this->provider,
            'language'       => $this->language,
            'model'          => $this->model,
            'temperature'    => $this->temperature,
            'maxTokens'      => $this->maxTokens,
            'createVersion'  => $this->createVersion,
        ];
    }
}
