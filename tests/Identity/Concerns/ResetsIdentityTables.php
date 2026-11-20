<?php

namespace App\Domain\Identity\Tests\Concerns;

use App\Domain\Identity\Tests\Feature\FeatureTestCase;
use App\Support\Testing\KernelSchema;

/**
 * Clears every row Identity owns before and after a feature test.
 *
 * The kernel harness creates its schema **once per process** on a shared
 * in-memory SQLite connection ({@see KernelSchema}), so a
 * row written by one test class is still there when the next boots — the same
 * reason the host's `RefreshDatabase` is unavailable here. A feature test that
 * seeds the ladder and creates users must therefore start from an empty set,
 * or role counts and list assertions depend on test order.
 *
 * Order matters: `role_has_permissions` and the model pivots reference roles and
 * permissions, so they go first.
 *
 * @see FeatureTestCase
 */
trait ResetsIdentityTables
{
    protected function resetIdentityTables(): void
    {
        $this->truncateTables(
            'identity_oauth_accounts',
            'password_reset_tokens',
            'sessions',
            'role_has_permissions',
            'model_has_roles',
            'model_has_permissions',
            'permissions',
            'roles',
            'users',
        );
    }
}
