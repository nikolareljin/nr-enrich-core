<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Nikos\NrEnrichCore\Service\Provider\OpenAiProvider;
use Nikos\NrEnrichCore\Service\Provider\AnthropicProvider;
use Nikos\NrEnrichCore\Service\Provider\OllamaProvider;
use Nikos\NrEnrichCore\Service\Provider\MistralProvider;

/**
 * Loads nr_enrich_core bundle configuration and registers provider services.
 *
 * For each entry in `nr_enrich_core.providers`, a tagged DI service definition
 * is created using the matching adapter class. The tag `nr_enrich_core.provider`
 * causes Symfony to inject them as a tagged iterator into AiEnrichmentService.
 */
final class NrEnrichCoreExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('nr_enrich_core.default_provider', $config['default_provider']);

        $this->registerProviders($config['providers'], $container);
    }

    private function registerProviders(array $providers, ContainerBuilder $container): void
    {
        $adapterMap = [
            'openai'    => OpenAiProvider::class,
            'anthropic' => AnthropicProvider::class,
            'ollama'    => OllamaProvider::class,
            'mistral'   => MistralProvider::class,
        ];

        foreach ($providers as $name => $providerConfig) {
            $type = $providerConfig['type'];

            if ($type === 'custom') {
                // Re-tag an existing service defined in the host app.
                $serviceId = $providerConfig['service_id'];
                if ($serviceId && $container->hasDefinition($serviceId)) {
                    $container->getDefinition($serviceId)
                        ->addTag('nr_enrich_core.provider');
                }
                continue;
            }

            if (!isset($adapterMap[$type])) {
                continue;
            }

            $class      = $adapterMap[$type];
            $serviceId  = 'nr_enrich_core.provider.' . $name;
            $definition = new Definition($class);
            $definition->addTag('nr_enrich_core.provider');
            $definition->setAutowired(false);

            // Build constructor args based on provider type.
            match ($type) {
                'openai' => $definition->setArguments([
                    /* httpClient */    null, // replaced by autowiring reference below
                    /* apiKey */        $providerConfig['api_key'],
                    /* defaultModel */  $providerConfig['model'] ?: 'gpt-4o',
                    /* baseUrl */       $providerConfig['base_url'] ?: '',
                ]),
                'anthropic' => $definition->setArguments([
                    null,
                    $providerConfig['api_key'],
                    $providerConfig['model'] ?: 'claude-3-5-sonnet-20241022',
                ]),
                'mistral' => $definition->setArguments([
                    null,
                    $providerConfig['api_key'],
                    $providerConfig['model'] ?: 'mistral-large-latest',
                ]),
                'ollama' => $definition->setArguments([
                    null,
                    $providerConfig['model'] ?: 'llama3.2',
                    $providerConfig['base_url'] ?: 'http://localhost:11434',
                ]),
            };

            // Wire the Symfony HttpClient as argument 0 via a reference.
            $definition->setArgument(
                0,
                new \Symfony\Component\DependencyInjection\Reference(
                    'Symfony\Contracts\HttpClient\HttpClientInterface'
                )
            );

            $container->setDefinition($serviceId, $definition);
        }
    }

    public function getAlias(): string
    {
        return 'nr_enrich_core';
    }
}
