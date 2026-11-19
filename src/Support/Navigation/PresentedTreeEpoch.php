<?php

namespace App\Support\Navigation;

/**
 * Kernel-owned contract for the presented navigation tree epoch.
 *
 * A core package (Navigation) owns the epoch counter; the kernel (DomainAccess)
 * only needs to say "everything that cached a presented tree must rebuild".
 * Importing the package's repository interface from the kernel would invert the
 * dependency direction, so the kernel declares the one method it needs and the
 * package binds an implementation.
 *
 * A host that does not install the Navigation package never binds this, which is
 * why every caller must stay behind `app()->bound(...)`.
 */
interface PresentedTreeEpoch
{
    /**
     * Invalidate every presented tree, for every viewer.
     */
    public function bumpPresentedTreeEpoch(): void;
}
