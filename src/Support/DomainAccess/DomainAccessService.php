<?php

namespace App\Support\DomainAccess;

use App\Domain\Identity\Models\User;
use App\Support\Domain\DomainManager;
use App\Support\DomainAccess\Enums\DomainVisibility;
use App\Support\DomainAccess\Models\ProductDomainVisibility;
use App\Support\DomainAccess\Support\DomainAccessRealtime;
use App\Support\Navigation\PresentedTreeEpoch;

/**
 * Runtime access gate for product domains.
 *
 * Visibility is a global per-domain flag (Public|Private), stored in
 * product_domain_visibilities and editable from the admin UI. Public
 * domains are reachable by anyone; Private domains only by Root, Admin,
 * or the per-domain "Beta tester {Domain}" role (or the matching
 * "access {domain} product" permission).
 *
 * Separate from product_activations (per-user runtime state) and from
 * config/domains.php module taxonomy.
 */
class DomainAccessService
{
    /**
     * Roles that always bypass the Private gate.
     *
     * @var list<string>
     */
    public const PRIVILEGED_ROLES = ['Root', 'Admin'];

    /** @var array<string, DomainVisibility>|null Request-scoped cache. */
    private ?array $cache = null;

    public function __construct(
        private readonly DomainManager $domains,
        private readonly DomainAccessRealtime $realtime,
    ) {}

    /**
     * Resolve a product domain's visibility. Defaults to Public when no
     * row exists (backward compatible — pre-feature domains stay open).
     */
    public function visibilityFor(string $productKey): DomainVisibility
    {
        return $this->all()[$productKey] ?? DomainVisibility::Public;
    }

    /**
     * Set (upsert) a product domain's visibility. Rejects non-product keys.
     */
    public function setVisibility(string $productKey, DomainVisibility $visibility): void
    {
        if (! $this->domains->isRegistered($productKey) || ! $this->domains->isProduct($productKey)) {
            throw new \InvalidArgumentException("[{$productKey}] is not a registered product domain.");
        }

        ProductDomainVisibility::query()->updateOrCreate(
            ['product_key' => $productKey],
            ['visibility' => $visibility->value],
        );

        $this->cache = null;

        // Presented trees encode Domain Access outcomes — bump epoch globally.
        // The kernel only knows the contract; a host without the Navigation
        // package never binds it, so the bound() check stays load-bearing.
        if (app()->bound(PresentedTreeEpoch::class)) {
            app(PresentedTreeEpoch::class)->bumpPresentedTreeEpoch();
        }

        $this->realtime->visibilityChanged($productKey, $visibility->value);
    }

    /**
     * Whether the given user may access the product domain right now.
     * Public → always true. Private → privileged role or beta access.
     */
    public function canAccess(?User $user, string $productKey): bool
    {
        if ($this->visibilityFor($productKey)->isPublic()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($user->hasAnyRole(self::PRIVILEGED_ROLES)) {
            return true;
        }

        if ($user->hasRole(ProductNames::betaRole($productKey))) {
            return true;
        }

        return $user->can(ProductNames::accessPermission($productKey));
    }

    /**
     * All stored visibilities keyed by product_key (request-scoped cache).
     *
     * @return array<string, DomainVisibility>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = ProductDomainVisibility::query()
                ->get(['product_key', 'visibility'])
                ->mapWithKeys(fn (ProductDomainVisibility $row): array => [
                    $row->product_key => $row->visibility,
                ])
                ->all();
        }

        return $this->cache;
    }

    public function forgetCache(): void
    {
        $this->cache = null;
    }
}
