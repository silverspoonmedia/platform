<?php

namespace App\Support\Ai\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Kernel-owned seam for the AI usage ledger a product domain owns.
 *
 * `silverspoonmedia/ai` (Inference) renders the admin usage dashboard — totals,
 * per-stage and per-provider breakdowns — but the ledger table belongs to the
 * product that actually bills the tokens (`microstock_ai_usage_events`). The
 * kernel declares the shape; the product binds the adapter.
 *
 * A host without an AI ledger simply has no binding — call sites wrap the
 * resolve in `app()->bound()` and render an empty dashboard instead of failing.
 */
interface AiUsageLedger
{
    /**
     * Eloquent model class of the usage-event table. The model must expose the
     * columns the admin dashboard aggregates: `billing_source`, `stage`,
     * `alias_model`, `resolved_model`, `total_tokens`,
     * `provider_cost_micro_usd`, `billable_micro_usd`,
     * `inference_provider_id`, `user_id`, `voided_at`, `created_at`.
     *
     * @return class-string<Model>
     */
    public function usageEventModel(): string;

    /**
     * Relation names the dashboard eager-loads on a usage event row. Each must
     * exist on {@see usageEventModel()} and resolve to `null` when unset.
     *
     * @return list<string>
     */
    public function usageEventRelations(): array;

    /**
     * Format a micro-USD integer as a display string (`$1.23`). Signed mode
     * prefixes `+` / `-`, which the ledger adjustment view needs.
     */
    public function formatUsd(int $microUsd, bool $signed = false): string;
}
