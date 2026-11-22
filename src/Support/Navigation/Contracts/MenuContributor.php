<?php

namespace App\Support\Navigation\Contracts;

/**
 * A domain's contribution to the seeded navigation tree.
 *
 * The kernel owns the tree's shape (`workspace`, `products`, `administration`);
 * a product owns the nodes it adds to it. `silverspoonmedia/navigation` ships the
 * seeder and this contract, and a host with no product installed seeds the same
 * tree with the product groups empty and dropped.
 *
 * Keys are the seeder's internal node keys, not route names:
 *
 *  - `products` — a top-level group; its nodes land as children of that header.
 *  - `administration.settings` — a nested parent; its nodes land among that
 *    node's children, positioned relative to `after` (a sibling key).
 *
 * A contribution is plain arrays only — the kernel must not import a package
 * enum, so `type` stays `h|p|c` and `roles` stays role names.
 */
interface MenuContributor
{
    /**
     * @return array<string, array{nodes: list<array<string, mixed>>, after?: string|null}>
     */
    public function contributions(): array;
}
