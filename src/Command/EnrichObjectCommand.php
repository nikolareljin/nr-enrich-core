<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Command;

use Nikos\NrEnrichCore\Message\EnrichObjectMessage;
use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Nikos\NrEnrichCore\Service\AiEnrichmentService;
use Pimcore\Model\DataObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to trigger AI enrichment for a single Pimcore DataObject.
 *
 * Usage examples:
 *   php bin/console nrec:enrich 42
 *   php bin/console nrec:enrich 42 --field=description --field=shortDescription
 *   php bin/console nrec:enrich 42 --provider=anthropic
 *   php bin/console nrec:enrich 42 --async          (dispatches Messenger message)
 */
#[AsCommand(
    name: 'nrec:enrich',
    description: 'Enrich a Pimcore DataObject with AI-generated content.',
)]
class EnrichObjectCommand extends Command
{
    public function __construct(
        private readonly AiEnrichmentService $enrichmentService,
        private readonly array $classFieldConfigs = [],
        private readonly ?object $messageBus = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('objectId', InputArgument::REQUIRED, 'Pimcore DataObject ID to enrich.')
            ->addOption('field', 'f', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Field name(s) to enrich. Defaults to all configured fields for the class.')
            ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Named AI provider to use. Defaults to the bundle default.', 'default')
            ->addOption('prompt', null, InputOption::VALUE_REQUIRED, 'Override prompt template (single-field mode only).')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Dispatch via Symfony Messenger instead of running synchronously (requires messenger transport).')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the rendered prompt without calling the AI provider.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $objectId = (int) $input->getArgument('objectId');
        $fields = $input->getOption('field');
        $provider = $input->getOption('provider');
        $async = $input->getOption('async');
        $dryRun = $input->getOption('dry-run');

        $object = DataObject::getById($objectId);
        if (!$object) {
            $io->error("DataObject with ID $objectId not found.");
            return Command::FAILURE;
        }

        $className = $object->getClassName();
        $io->title("NR EnrichCore — enriching object #$objectId ($className)");

        if ($async) {
            return $this->dispatchAsync($io, $objectId, $className, $fields, $provider);
        }

        $configs = $this->resolveConfigs($className, $fields, $provider, $input->getOption('prompt'));

        if (empty($configs)) {
            $io->warning("No enrichment configs found for class \"$className\". Check your nr_enrich_core configuration.");
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('Dry-run mode: prompts will be printed, no AI calls made.');
            foreach ($configs as $config) {
                $io->section("Field: {$config->fieldName}");
                $io->text("Provider: {$config->provider} | Model: " . ($config->model ?: 'default'));
                $io->text("Prompt template: {$config->promptTemplate}");
            }
            return Command::SUCCESS;
        }

        $results = $this->enrichmentService->enrichObject($object, $configs);

        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                $result->fieldName,
                $result->provider,
                $result->model,
                $result->tokensUsed,
                $result->versionCreated ? 'yes' : 'no',
            ];
        }

        $io->table(['Field', 'Provider', 'Model', 'Tokens', 'Version'], $rows);
        $io->success(sprintf('Enriched %d field(s) on object #%d.', count($results), $objectId));

        return Command::SUCCESS;
    }

    private function dispatchAsync(SymfonyStyle $io, int $objectId, string $className, array $fields, string $provider): int
    {
        if ($this->messageBus === null) {
            $io->error('Async mode requires symfony/messenger. Install it and configure a transport.');
            return Command::FAILURE;
        }

        $message = new EnrichObjectMessage(
            objectId: $objectId,
            className: $className,
            fields: $fields,
            provider: $provider,
        );

        $this->messageBus->dispatch($message);
        $io->success("Enrichment job dispatched to the message bus for object #$objectId.");
        return Command::SUCCESS;
    }

    /**
     * @return EnrichmentConfig[]
     */
    private function resolveConfigs(string $className, array $requestedFields, string $provider, ?string $promptOverride): array
    {
        $configured = $this->classFieldConfigs[$className] ?? [];

        if (empty($configured) && !empty($requestedFields)) {
            // Build minimal configs from CLI options when no static config exists.
            return array_map(fn(string $field) => new EnrichmentConfig(
                className: $className,
                fieldName: $field,
                promptTemplate: $promptOverride ?? 'Improve this text: {{ value }}',
                provider: $provider,
            ), $requestedFields);
        }

        $configs = empty($requestedFields)
            ? $configured
            : array_filter($configured, fn(EnrichmentConfig $c) => in_array($c->fieldName, $requestedFields, true));

        // Apply CLI provider override.
        if ($provider !== 'default') {
            $configs = array_map(
                fn(EnrichmentConfig $c) => EnrichmentConfig::fromArray(
                    array_merge($c->toArray(), ['provider' => $provider])
                ),
                $configs
            );
        }

        return array_values($configs);
    }
}
