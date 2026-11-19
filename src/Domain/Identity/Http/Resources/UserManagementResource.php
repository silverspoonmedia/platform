<?php

namespace App\Domain\Identity\Http\Resources;

use App\Domain\Identity\Models\User;
use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserManagementResource extends JsonResource
{
    use FormatsPresentation;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            ...$this->dateFields('email_verified_at', $this->email_verified_at),
            'roles' => UserRoleSummaryResource::collection($this->roles),
            ...$this->dateFields('created_at', $this->created_at),
            ...$this->dateFields('updated_at', $this->updated_at),
        ];
    }
}
