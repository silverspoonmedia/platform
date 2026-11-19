<?php

namespace App\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;

/**
 * One subject type's API label, owned by the domain that owns the model.
 *
 * A menu's label is a translatable column, a user's is name + email, and a future
 * subject may need something else again. Rendering that inside
 * `silverspoonmedia/audit` would make the package import the owning domain, so the
 * label is declared from the domain's own provider instead.
 */
interface SubjectLabelPresenter
{
    /**
     * Human-readable label for the row's `subject_label` field.
     */
    public function label(Model $subject): string;
}
