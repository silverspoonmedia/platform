<?php

namespace App\Domain\Identity\Tests\Feature;

use App\Domain\Identity\Tests\Concerns\ResetsIdentityTables;
use App\Domain\Identity\Tests\TestCase;
use App\Support\Testing\PackageTestCase;

/**
 * Base for Identity's feature tests inside the package.
 *
 * A package feature test owns real rows (users, roles, pivots, OAuth accounts)
 * and the kernel schema survives the whole process, so every test starts and
 * ends on an empty set. Cleaning in `tearDown` too means a failure mid-test
 * cannot poison the next class.
 *
 * The host's `RefreshDatabase` is deliberately absent: a transaction rollback
 * over the shared in-memory connection would reach tables a sibling package
 * created. See {@see PackageTestCase}.
 *
 * `TestCase` is reached through the package's own base so
 * `PackageTestSuiteTest` can resolve the chain from the `use` imports and prove
 * the tree lands on the kernel harness.
 */
abstract class FeatureTestCase extends TestCase
{
    use ResetsIdentityTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetIdentityTables();
    }

    protected function tearDown(): void
    {
        $this->resetIdentityTables();

        parent::tearDown();
    }
}
