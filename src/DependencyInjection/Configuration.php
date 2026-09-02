<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines the bundle configuration tree under the `nr_enrich_core` key.
 *
 * Example YAML:
 *
 *   nr_enrich_core:
 *     default_provider: openai
 *     providers:
 *       openai:
 *         type: openai
 *         api_key: '%env(OPENAI_API_KEY)%'
 *         model: gpt-4o
 *       ollama:
 *         type: ollama
 *         base_url: 'http://localhost:11434'
 *         model: llama3.2
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('nr_enrich_core');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('default_provider')
                    ->defaultValue('openai')
                    ->info('Named provider key to use when a field config specifies "default".')
                ->end()
                ->arrayNode('providers')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('type')
                                ->values(['openai', 'anthropic', 'ollama', 'mistral', 'custom'])
                                ->isRequired()
                                ->info('Adapter type. Use "custom" with a service_id for third-party adapters.')
                            ->end()
                            ->scalarNode('api_key')
                                ->defaultValue('')
                                ->info('API key. Use %%env(MY_KEY)%% to load from environment.')
                            ->end()
                            ->scalarNode('model')
                                ->defaultValue('')
                                ->info('Default model for this provider. Overridable per field.')
                            ->end()
                            ->scalarNode('base_url')
                                ->defaultValue('')
                                ->info('Custom inference endpoint (Ollama, LM Studio, vLLM, etc.).')
                            ->end()
                            ->scalarNode('service_id')
                                ->defaultValue('')
                                ->info('DI service ID for type=custom providers.')
                            ->end()
                        ->end()
                    ->end()
                ->end() // providers
            ->end();

        return $treeBuilder;
    }
}
