<?php

namespace App\Support\Navigation;

use App\Support\Navigation\Contracts\MenuContributor;

/**
 * Kernel-owned registry of navigation contributions.
 *
 * Registered by product providers, consumed by `silverspoonmedia/navigation`'s
 * `MenuSeeder`. An empty registry is a legal state: a host with no product
 * installed seeds the core tree and every product group collapses.
 *
 * Contributors are stored as class strings, so a long-lived worker never pins a
 * product service — Octane-safe by construction.
 */
final class MenuContributorRegistry
{
    /** @var list<class-string<MenuContributor>> */
    private array $contributors = [];

    /**
     * @param  class-string<MenuContributor>  ...$contributors
     */
    public function register(string ...$contributors): void
    {
        foreach ($contributors as $contributor) {
            if (! in_array($contributor, $this->contributors, true)) {
                $this->contributors[] = $contributor;
            }
        }
    }

    /**
     * @return list<class-string<MenuContributor>>
     */
    public function contributors(): array
    {
        return $this->contributors;
    }

    /**
     * Every contribution, grouped by target key, in registration order.
     *
     * @return array<string, list<array{nodes: list<array<string, mixed>>, after: string|null}>>
     */
    public function contributions(): array
    {
        $merged = [];

        foreach ($this->contributors as $contributor) {
            foreach (app($contributor)->contributions() as $target => $contribution) {
                $nodes = $contribution['nodes'] ?? [];

                if ($nodes === []) {
                    continue;
                }

                $merged[$target][] = [
                    'nodes' => array_values($nodes),
                    'after' => $contribution['after'] ?? null,
                ];
            }
        }

        return $merged;
    }
}
