<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\DomainKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasUuids;

    protected $fillable = [
        'name',
        'guard_name',
        'domain_kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain_kind' => DomainKind::class,
        ];
    }

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
     */
    public function scopeCore(Builder $query): Builder
    {
        return $query->where('domain_kind', DomainKind::Core);
    }

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
     */
    public function scopeProduct(Builder $query): Builder
    {
        return $query->where('domain_kind', DomainKind::Product);
    }
}
