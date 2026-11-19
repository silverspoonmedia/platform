<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ResolvesResourceArray
{
    /**
     * Resolve a resource (or collection) to a plain array for the ApiResponse envelope.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    protected function resolveResource(JsonResource|ResourceCollection $resource): array
    {
        return $resource->resolve(request());
    }
}
