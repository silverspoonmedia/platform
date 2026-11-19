<?php

namespace App\Support\Product\Http\Resources;

use App\Http\Resources\Concerns\FormatsPresentation;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductActivationResource extends JsonResource
{
    use FormatsPresentation;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'product_key' => $this->product_key,
            'activation_type' => $this->whenNotNull($this->activation_type?->value),
            'state' => $this->state->value,
            'provisioning_payload' => $this->provisioning_payload,
            ...$this->dateFields('activated_at', $this->activated_at),
            ...$this->dateFields('suspended_at', $this->suspended_at),
            ...$this->dateFields('expired_at', $this->expired_at),
            'source' => $this->source,
            ...$this->dateFields('updated_at', $this->updated_at),
            ...$this->dateFields('created_at', $this->created_at),

            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];
    }
}
