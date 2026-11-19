<?php

namespace App\Support\Broadcasting;

use App\Support\Broadcasting\Contracts\ChannelRegistrar;

/**
 * Kernel-owned list of package channel registrars.
 *
 * `routes/channels.php` is loaded once by the framework inside `booted`, so it
 * iterates this registry instead of importing a package's models. Empty by
 * default — a host with no packaged broadcaster behaves exactly as before.
 */
final class ChannelRegistrarRegistry
{
    /** @var array<class-string<ChannelRegistrar>, class-string<ChannelRegistrar>> */
    private array $registrars = [];

    /**
     * @param  class-string<ChannelRegistrar>  $registrar
     */
    public function register(string $registrar): void
    {
        $this->registrars[$registrar] = $registrar;
    }

    /**
     * @return list<ChannelRegistrar>
     */
    public function all(): array
    {
        return array_values(array_map(
            static fn (string $class): ChannelRegistrar => app($class),
            array_values($this->registrars),
        ));
    }
}
