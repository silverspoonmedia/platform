<?php

namespace App\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;

/**
 * Kernel-owned seam for the activity trail's subject-filter aliases.
 *
 * The trail can filter by subject type (`?subject=user|menu`), and the model
 * behind a type belongs to whichever domain owns it. Declaring the alias from
 * this package would make `silverspoonmedia/audit` depend on `silverspoonmedia/navigation`
 * or on any future packaged domain, so the kernel owns the contract instead:
 * a domain registers its alias in its own provider, behind `app()->bound()`,
 * and a host without that domain simply offers fewer filters.
 */
interface SubjectAliasRegistrar
{
    /**
     * Declare a short filter alias for an Eloquent model.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function register(string $alias, string $modelClass): void;
}
