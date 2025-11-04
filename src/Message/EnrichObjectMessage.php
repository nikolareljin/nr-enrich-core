<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore\Message;

/**
 * Symfony Messenger message DTO for async field enrichment.
 *
 * Dispatched by the REST controller or CLI command when the --async flag is used.
 * Consumed by EnrichObjectMessageHandler on a background worker.
 *
 * NOTE: This class is used only when symfony/messenger is installed in the
 * host application. The bundle remains functional without it (sync mode).
 */
final class EnrichObjectMessage
{
    /**
     * @param int      $objectId  Pimcore DataObject ID.
     * @param string   $className Pimcore class name (used to look up field configs).
     * @param string[] $fields    Specific fields to enrich. Empty = all configured fields.
     * @param string   $provider  Named provider key or 'default'.
     */
    public function __construct(
        public readonly int $objectId,
        public readonly string $className,
        public readonly array $fields = [],
        public readonly string $provider = 'default',
    ) {
    }
}
