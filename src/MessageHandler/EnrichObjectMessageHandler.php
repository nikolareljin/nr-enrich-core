<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\MessageHandler;

use Nikos\NrEnrichCore\Message\EnrichObjectMessage;
use Nikos\NrEnrichCore\Model\EnrichmentConfig;
use Nikos\NrEnrichCore\Service\AiEnrichmentService;
use Pimcore\Model\DataObject;
use Psr\Log\LoggerInterface;

/**
 * Symfony Messenger handler for async enrichment jobs.
 *
 * When symfony/messenger is available and a transport is configured, this
 * handler runs on a background worker process. The #[AsMessageHandler]
 * attribute is used for auto-wiring in Symfony 6.4+.
 *
 * The services.yaml registration is guarded by a class_exists check on
 * MessageBusInterface so the bundle does not break if messenger is absent.
 */
// Conditionally apply the attribute only when the Messenger component is present.
// This avoids a hard class-not-found error on installations without messenger.
if (class_exists(\Symfony\Component\Messenger\Attribute\AsMessageHandler::class)) {
    #[\Symfony\Component\Messenger\Attribute\AsMessageHandler]
    class EnrichObjectMessageHandler
    {
        public function __construct(
            private readonly AiEnrichmentService $enrichmentService,
            private readonly array $classFieldConfigs,
            private readonly LoggerInterface $logger,
        ) {
        }

        public function __invoke(EnrichObjectMessage $message): void
        {
            $object = DataObject::getById($message->objectId);

            if (!$object) {
                $this->logger->warning('NrEnrichCore: object not found for async enrichment', [
                    'objectId' => $message->objectId,
                ]);
                return;
            }

            $configs = $this->resolveConfigs($message);

            if (empty($configs)) {
                $this->logger->warning('NrEnrichCore: no field configs found for class', [
                    'objectId'  => $message->objectId,
                    'className' => $message->className,
                ]);
                return;
            }

            $results = $this->enrichmentService->enrichObject($object, $configs);

            $this->logger->info('NrEnrichCore: async enrichment completed', [
                'objectId' => $message->objectId,
                'fields'   => count($results),
            ]);
        }

        /** @return EnrichmentConfig[] */
        private function resolveConfigs(EnrichObjectMessage $message): array
        {
            $allConfigs = $this->classFieldConfigs[$message->className] ?? [];

            if (empty($message->fields)) {
                return $allConfigs;
            }

            return array_filter(
                $allConfigs,
                fn(EnrichmentConfig $c) => in_array($c->fieldName, $message->fields, true)
            );
        }
    }
} else {
    // Fallback stub — identical interface, no attribute.
    class EnrichObjectMessageHandler
    {
        public function __construct(
            private readonly AiEnrichmentService $enrichmentService,
            private readonly array $classFieldConfigs,
            private readonly LoggerInterface $logger,
        ) {
        }

        public function __invoke(EnrichObjectMessage $message): void
        {
            $object = DataObject::getById($message->objectId);
            if (!$object) {
                return;
            }
            $configs = $this->classFieldConfigs[$message->className] ?? [];
            $this->enrichmentService->enrichObject($object, $configs);
        }
    }
}
