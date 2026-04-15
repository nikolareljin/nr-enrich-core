<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Service;

use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Nikos\NrEnrichCore\Model\EnrichmentResult;
use Nikos\NrEnrichCore\Service\Provider\AiProviderInterface;
use Pimcore\Model\DataObject\Concrete;
use Psr\Log\LoggerInterface;

/**
 * Core orchestrator for AI-driven field enrichment.
 *
 * Receives a tagged collection of AiProviderInterface instances at construction
 * time (injected via Symfony's tagged-iterator pattern) and resolves the correct
 * one by name at runtime.
 */
class AiEnrichmentService
{
    /** @var array<string, AiProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<AiProviderInterface> $providers      Tagged service collection.
     * @param string                        $defaultProvider Named provider key to use when config says 'default'.
     */
    public function __construct(
        iterable $providers,
        private readonly string $defaultProvider,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->getName()] = $provider;
        }
    }

    /**
     * Enrich a single field on a Pimcore DataObject.
     *
     * Reads the current field value, renders the prompt template, calls the
     * configured AI provider, optionally creates a Pimcore version, then saves
     * the enriched value back.
     *
     * @throws \InvalidArgumentException if the named provider is not registered.
     * @throws \RuntimeException         on AI API transport or response errors.
     */
    public function enrichField(Concrete $object, EnrichmentConfig $config): EnrichmentResult
    {
        $provider = $this->resolveProvider($config->provider);
        $currentValue = $this->extractFieldValue($object, $config->fieldName);
        $prompt = $this->renderPrompt($config->promptTemplate, $currentValue, $object);

        $this->logger->info('NrEnrichCore: enriching field', [
            'objectId' => $object->getId(),
            'class' => $config->className,
            'field' => $config->fieldName,
            'provider' => $provider->getName(),
        ]);

        $response = $provider->complete($prompt, $config);

        $versionCreated = false;
        if ($config->createVersion) {
            $object->saveVersion();
            $versionCreated = true;
        }

        $this->writeFieldValue($object, $config->fieldName, $response->content);
        $object->save();

        $this->logger->info('NrEnrichCore: field enriched', [
            'objectId' => $object->getId(),
            'field' => $config->fieldName,
            'tokensUsed' => $response->getTotalTokens(),
        ]);

        return new EnrichmentResult(
            objectId: $object->getId(),
            fieldName: $config->fieldName,
            originalValue: $currentValue,
            enrichedValue: $response->content,
            provider: $response->provider,
            model: $response->model,
            tokensUsed: $response->getTotalTokens(),
            enrichedAt: new \DateTimeImmutable(),
            versionCreated: $versionCreated,
        );
    }

    /**
     * Enrich all configured fields on a DataObject in sequence.
     *
     * @param EnrichmentConfig[] $configs One config per field to enrich.
     * @return EnrichmentResult[]
     */
    public function enrichObject(Concrete $object, array $configs): array
    {
        $results = [];
        foreach ($configs as $config) {
            try {
                $results[] = $this->enrichField($object, $config);
            } catch (\Throwable $e) {
                $this->logger->error('NrEnrichCore: field enrichment failed', [
                    'objectId' => $object->getId(),
                    'field' => $config->fieldName,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $results;
    }

    /**
     * Return all registered provider names.
     *
     * @return string[]
     */
    public function getProviderNames(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Run health checks on all registered providers.
     *
     * @return array<string, bool>
     */
    public function healthCheckAll(): array
    {
        $results = [];
        foreach ($this->providers as $name => $provider) {
            $results[$name] = $provider->healthCheck();
        }
        return $results;
    }

    private function resolveProvider(string $name): AiProviderInterface
    {
        $key = ($name === 'default') ? $this->defaultProvider : $name;

        if (!isset($this->providers[$key])) {
            throw new \InvalidArgumentException(sprintf(
                'AI provider "%s" is not registered. Available providers: %s',
                $key,
                implode(', ', array_keys($this->providers))
            ));
        }

        return $this->providers[$key];
    }

    /**
     * Render a prompt template by substituting known placeholders.
     * Intentionally avoids a Twig dependency to keep the bundle lightweight.
     */
    private function renderPrompt(string $template, mixed $currentValue, Concrete $object): string
    {
        return strtr($template, [
            '{{ value }}' => (string) $currentValue,
            '{{value}}' => (string) $currentValue,
            '{{ objectId }}' => (string) $object->getId(),
            '{{objectId}}' => (string) $object->getId(),
            '{{ class }}' => $object->getClassName(),
            '{{class}}' => $object->getClassName(),
        ]);
    }

    private function extractFieldValue(Concrete $object, string $fieldName): mixed
    {
        $getter = 'get' . ucfirst($fieldName);
        if (!method_exists($object, $getter)) {
            throw new \InvalidArgumentException(sprintf(
                'Getter %s::%s() not found.',
                get_class($object),
                $getter
            ));
        }
        return $object->$getter();
    }

    private function writeFieldValue(Concrete $object, string $fieldName, mixed $value): void
    {
        $setter = 'set' . ucfirst($fieldName);
        if (!method_exists($object, $setter)) {
            throw new \InvalidArgumentException(sprintf(
                'Setter %s::%s() not found.',
                get_class($object),
                $setter
            ));
        }
        $object->$setter($value);
    }
}
