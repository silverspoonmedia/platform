<?php

namespace App\Support\Ai;

use App\Support\Ai\Contracts\AiUsageConsolidator;

/**
 * Kernel-owned registry of consolidation hooks for AI usage ledgers.
 *
 * Registered by the product provider that owns a ledger (`Microstock`) and
 * consumed by `silverspoonmedia/ai`. Keyed by provider slug — the same identifier the
 * product uses when it records usage — so the catalog sync consolidates against
 * the ledger that billed it and nothing else.
 */
final class AiUsageConsolidatorRegistry
{
    /** @var array<string, class-string<AiUsageConsolidator>> */
    private array $consolidators = [];

    /**
     * @param  class-string<AiUsageConsolidator>  $consolidator
     */
    public function register(string $providerSlug, string $consolidator): void
    {
        $this->consolidators[$providerSlug] = $consolidator;
    }

    public function has(string $providerSlug): bool
    {
        return isset($this->consolidators[$providerSlug]);
    }

    public function forProvider(string $providerSlug): ?AiUsageConsolidator
    {
        if (! isset($this->consolidators[$providerSlug])) {
            return null;
        }

        return app($this->consolidators[$providerSlug]);
    }

    /**
     * @return list<string>
     */
    public function providerSlugs(): array
    {
        return array_keys($this->consolidators);
    }
}
