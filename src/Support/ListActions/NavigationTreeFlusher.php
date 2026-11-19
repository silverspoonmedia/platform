<?php

namespace App\Support\ListActions;

/**
 * Kernel-owned contract for "drop this viewer's cached navigation tree".
 *
 * Identity owns the viewer: creating, updating or deleting a user changes which
 * menus that viewer may see, so the kernel must drop the tree for that id. The
 * menu cache itself belongs to `silverspoonmedia/navigation`, which binds this
 * contract to its repository — the kernel never imports a package interface.
 *
 * A host without the navigation package never binds it, which is why every
 * caller stays behind `app()->bound(...)`.
 */
interface NavigationTreeFlusher
{
    public function forgetPresentedTreeForUserId(string $userId): void;
}
