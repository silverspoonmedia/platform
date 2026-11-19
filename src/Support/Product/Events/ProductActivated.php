<?php

namespace App\Support\Product\Events;

use App\Support\Product\Models\ProductActivation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly ProductActivation $activation) {}
}
