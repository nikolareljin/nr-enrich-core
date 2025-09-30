<?php

declare(strict_types=1);

namespace Nikos\NrEnrichCore;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Traits\PackageVersionTrait;

/**
 * NR EnrichCore bundle entry point.
 *
 * Pimcore discovers this class via the `extra.pimcore.bundles` key in composer.json.
 * The bundle registers its admin JS file through getJsPaths() so Pimcore loads it
 * in every admin page, enabling the "Enrich with AI" toolbar button on DataObjects.
 */
class NrEnrichCoreBundle extends AbstractPimcoreBundle
{
    use PackageVersionTrait;

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Returns the Composer package name used by PackageVersionTrait to resolve
     * the bundle version from the installed packages.
     */
    protected function getComposerPackageName(): string
    {
        return 'nikolareljin/nr-enrich-core';
    }

    // ── Pimcore admin assets ─────────────────────────────────────────────────

    /**
     * Admin JS files to load in the Pimcore backend.
     * The path is relative to the bundle's public directory.
     *
     * @return string[]
     */
    public function getJsPaths(): array
    {
        return [
            '/bundles/nrenrichcore/js/nr-enrich-core.js',
        ];
    }

    /**
     * @return string[]
     */
    public function getCssPaths(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getEditmodeJsPaths(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getEditmodeCssPaths(): array
    {
        return [];
    }

    // ── Bundle metadata (shown in Pimcore extension manager) ─────────────────

    public function getNiceName(): string
    {
        return 'NR EnrichCore';
    }

    public function getDescription(): string
    {
        return 'Provider-agnostic AI enrichment for Pimcore 11 DataObjects and Assets.';
    }
}
