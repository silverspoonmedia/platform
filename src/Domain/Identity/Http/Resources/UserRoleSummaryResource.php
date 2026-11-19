<?php

namespace App\Domain\Identity\Http\Resources;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class UserRoleSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $domainKind = $this->domain_kind;

        return [
            'name' => $this->name,
            'level' => $this->level,
            'domain_kind' => $domainKind instanceof DomainKind
                ? $domainKind->value
                : (string) $domainKind,
        ];
    }
}
