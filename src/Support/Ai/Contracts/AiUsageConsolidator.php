<?php

namespace App\Support\Ai\Contracts;

/**
 * Kernel-owned seam for recalculating provisional AI usage after rates change.
 *
 * `silverspoonmedia/ai` owns the catalog and the act of curating a rate; the product
 * that billed the tokens owns the ledger, the credit balance and the refund /
 * debit posting. The kernel declares the hook; the product binds an
 * implementation that recalculates settled usage in its own ledger.
 *
 * A host without a ledger has no binding — the catalog sync still saves the new
 * rate and skips consolidation (`app()->bound()` guard).
 */
interface AiUsageConsolidator
{
    /**
     * Recalculate every settled usage event billed at a provisional rate for
     * this provider + model card, posting ledger adjustments.
     *
     * @return array{events_adjusted: int, net_adjustment_micro_usd: int}
     */
    public function consolidate(string $providerId, string $modelCardId): array;

    /**
     * Format a micro-USD integer as a display string (`$1.23`). Used by the
     * admin usage-event resource the dashboard renders.
     */
    public function formatUsd(int $microUsd, bool $signed = false): string;
}
