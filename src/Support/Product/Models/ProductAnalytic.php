<?php

namespace App\Support\Product\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Denormalized per-(product, tier, metric) aggregate row.
 *
 * One row per bucket — e.g. (Archivd, paid, storage_used_bytes) holds the
 * summed storage across all paid-tier users. Not per-user: drill-down reads
 * source tables directly (single-user indexed queries are cheap).
 */
class ProductAnalytic extends Model
{
    use HasUuids;

    protected $table = 'product_analytics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metric_value' => 'integer',
        ];
    }

    public function scopeForProduct($query, string $productKey)
    {
        return $query->where('product_key', $productKey);
    }

    public function scopeForMetric($query, string $metricKey)
    {
        return $query->where('metric_key', $metricKey);
    }

    public function scopeForTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }
}
