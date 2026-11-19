<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\DomainKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasUuids;

    protected $fillable = [
        'name',
        'guard_name',
        'domain_kind',
        'level',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain_kind' => DomainKind::class,
            'level' => 'integer',
        ];
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeCore(Builder $query): Builder
    {
        return $query->where('domain_kind', DomainKind::Core);
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeProduct(Builder $query): Builder
    {
        return $query->where('domain_kind', DomainKind::Product);
    }
}
