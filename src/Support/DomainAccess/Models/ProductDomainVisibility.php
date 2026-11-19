<?php

namespace App\Support\DomainAccess\Models;

use App\Support\DomainAccess\Enums\DomainVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductDomainVisibility extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visibility' => DomainVisibility::class,
        ];
    }
}
