<?php

namespace App\Support\Billing;

use App\Support\Billing\Contracts\FulfillmentHandler;
use App\Support\Billing\Contracts\TierObserver;

/**
 * Kernel-owned registry of fulfillment handlers and tier observers.
 *
 * Registered by product providers (`Archivd`, `Microstock`, …) and consumed by
 * `silverspoonmedia/billing`. An empty registry is a legal state: a host with no
 * product installed runs the same billing code and every hook is a no-op.
 *
 * Handlers are stored as class strings, not instances, so a long-lived worker
 * never pins a product service — `Octane`-safe by construction.
 */
final class FulfillmentRegistry
{
    /** @var array<string, class-string<FulfillmentHandler>> */
    private array $handlers = [];

    /** @var array<string, class-string<TierObserver>> */
    private array $observers = [];

    /**
     * Declare the handler for one or more `fulfillment_target` values.
     *
     * @param  class-string<FulfillmentHandler>  $handler
     */
    public function register(string $handler, ?string $target = null): void
    {
        foreach ($target !== null ? [$target] : $this->declaredTargets($handler) as $key) {
            $this->handlers[$key] = $handler;
        }
    }

    /**
     * @param  class-string<TierObserver>  $observer
     */
    public function registerTierObserver(string $observer): void
    {
        foreach ($this->declaredTargets($observer) as $key) {
            $this->observers[$key] = $observer;
        }
    }

    public function has(string $target): bool
    {
        return isset($this->handlers[$target]);
    }

    public function forTarget(?string $target): ?FulfillmentHandler
    {
        if ($target === null || ! isset($this->handlers[$target])) {
            return null;
        }

        return app($this->handlers[$target]);
    }

    /**
     * Record a derived tier transition for the target's owner, if it has one.
     *
     * @param  'paid'|'grace'|'free'  $after
     */
    public function tierChanged(?string $target, string $userId, ?string $before, string $after): void
    {
        if ($target === null || ! isset($this->observers[$target])) {
            return;
        }

        if ($before === $after) {
            return;
        }

        app($this->observers[$target])->tierChanged($userId, $before, $after);
    }

    /**
     * Every distinct registered handler, resolved once per call.
     *
     * @return list<FulfillmentHandler>
     */
    public function all(): array
    {
        $instances = [];

        foreach (array_unique($this->handlers) as $class) {
            $instances[] = app($class);
        }

        return $instances;
    }

    /**
     * Catalog codes every registered product retired — a paid probe filters on
     * this instead of hard-coding a product's free SKU.
     *
     * @return list<string>
     */
    public function freeCodes(): array
    {
        $codes = [];

        foreach ($this->all() as $handler) {
            foreach ($handler->freeCodes() as $code) {
                if (is_string($code) && $code !== '') {
                    $codes[$code] = $code;
                }
            }
        }

        return array_values($codes);
    }

    /**
     * @return list<string>
     */
    public function targets(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * @return list<string>
     */
    private function declaredTargets(string $class): array
    {
        if (! class_exists($class) || ! method_exists($class, 'targets')) {
            return [];
        }

        return array_values(array_unique(array_filter(
            app($class)->targets(),
            static fn (mixed $target): bool => is_string($target) && $target !== '',
        )));
    }
}
