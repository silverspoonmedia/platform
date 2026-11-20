<?php

namespace App\Support\Testing\Concerns;

use Illuminate\Database\Seeder;

/**
 * Seeds a billing-owned currency catalog.
 *
 * The catalog is passed in rather than imported: the kernel may not name a
 * sibling package. `App\Domain\Currency\Database\Seeders\CurrencySeeder` is what
 * the host and the billing package pass.
 */
trait SeedsCurrencies
{
    /**
     * @param  class-string<Seeder>  $seeder
     */
    protected function seedCurrencies(string $seeder): void
    {
        $this->seed($seeder);
    }
}
