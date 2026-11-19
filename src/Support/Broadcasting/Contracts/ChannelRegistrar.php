<?php

namespace App\Support\Broadcasting\Contracts;

/**
 * Kernel-owned seam for package-owned broadcast channels.
 *
 * `routes/channels.php` is the single authorization file the framework reads,
 * but the channels themselves belong to the domain that publishes on them. A
 * packaged domain registers its channels from its own provider through this
 * contract, so the host file never imports a package model (`Invoice`,
 * `ServicePurchase`) just to answer "may this socket subscribe?".
 *
 * Registrations run inside the framework's `booted` channel loader, after every
 * provider has booted, so `app()->bound()` is only used to stay optional.
 */
interface ChannelRegistrar
{
    /**
     * Declare this package's `Broadcast::channel()` entries.
     */
    public function register(): void;
}
