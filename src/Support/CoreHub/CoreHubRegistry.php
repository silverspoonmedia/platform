<?php

namespace App\Support\CoreHub;

use App\Support\CoreHub\Contracts\CoreHubClient;

/**
 * Where a consumer registers the reader it bound for the hub.
 *
 * The registry stores a class string, never an instance, so a long-lived worker
 * (Octane) never pins a request-scoped service. Register from the provider that
 * owns the implementation; read through {@see client()}, which answers `null`
 * for a host with no hub — the same contract an empty `FulfillmentRegistry` has.
 */
final class CoreHubRegistry
{
    /** @var class-string<CoreHubClient>|null */
    private ?string $client = null;

    /**
     * @param  class-string<CoreHubClient>  $client
     */
    public function register(string $client): void
    {
        $this->client = $client;
    }

    public function has(): bool
    {
        return $this->client !== null;
    }

    /**
     * @return class-string<CoreHubClient>|null
     */
    public function clientClass(): ?string
    {
        return $this->client;
    }

    public function client(): ?CoreHubClient
    {
        return $this->client !== null ? app($this->client) : null;
    }
}
