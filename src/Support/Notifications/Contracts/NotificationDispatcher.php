<?php

namespace App\Support\Notifications\Contracts;

/**
 * Kernel-owned seam for emitting an activity notification by type name.
 *
 * `silverspoonmedia/billing` knows *when* to notify (`service_subscription_grace`,
 * `service_subscription_expired`) but must not import the notification domain
 * that owns the catalog, the audience rules and the persistence. The kernel
 * declares the contract; `silverspoonmedia/notification` binds the adapter that maps
 * the string back to its own enum.
 *
 * A host without the notification package simply has no binding — call sites
 * wrap the emit in `app()->bound()` or a try/catch, and the money path never
 * fails because a bell is missing.
 */
interface NotificationDispatcher
{
    /**
     * @param  string  $type  Notification type value (`NotificationType` case value).
     * @param  array<string, mixed>  $data  Message replacements + render context.
     * @param  list<string>  $users  Target user ids (personal audience).
     * @param  list<string>|null  $roles  Target role names; null = the type's defaults.
     * @param  array<string, mixed>  $actionParams
     */
    public function emit(
        string $type,
        array $data = [],
        array $users = [],
        ?array $roles = null,
        ?string $actionRoute = null,
        array $actionParams = [],
    ): void;

    /**
     * Every type the binding can resolve: value => default audience roles
     * (`[]` = personal). A caller that must pick a type by some other key
     * resolves it through the catalogue the adapter reports, so the kernel
     * never names a package enum.
     *
     * @return array<string, list<string>>
     */
    public function types(): array;
}
