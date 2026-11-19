<?php

namespace App\Support\Product\Console\Commands;

use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductAnalyticsService;
use Illuminate\Console\Command;

class RecomputeProductAnalyticsCommand extends Command
{
    protected $signature = 'product:recompute-analytics {--product= : product key, default all}';

    protected $description = 'Recompute stored product_analytics rows from source tables. Idempotent.';

    public function handle(ProductAnalyticsService $analytics): int
    {
        $productKey = $this->option('product');

        $products = $productKey !== null && $productKey !== ''
            ? [$productKey]
            : array_keys(ProductRegistry::all());

        foreach ($products as $key) {
            if (! ProductRegistry::has($key)) {
                $this->error("Unknown product key: {$key}");

                continue;
            }

            $this->info("Recomputing analytics for [{$key}]...");
            $analytics->recomputeProduct($key);
            $this->info("Done [{$key}].");
        }

        return self::SUCCESS;
    }
}
