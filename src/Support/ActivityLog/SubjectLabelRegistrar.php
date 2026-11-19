<?php

namespace App\Support\ActivityLog;

use Illuminate\Database\Eloquent\Model;

/**
 * Kernel-owned seam for labelling an activity-log subject.
 *
 * The trail renders `subject_label` for every row, and the model behind a subject
 * type belongs to whichever domain owns it. Declaring the label from
 * `silverspoonmedia/audit` would make the package import another core package —
 * the one dependency the package layout forbids — so the kernel owns the contract
 * instead: a domain registers a {@see SubjectLabelPresenter} for its model class
 * from its own provider, behind `app()->bound()`, and a host without that domain
 * falls back to the generic label the package already renders.
 */
interface SubjectLabelRegistrar
{
    /**
     * Declare how a model class is labelled in the trail.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function register(string $modelClass, SubjectLabelPresenter $presenter): void;

    /**
     * Resolve the presenter for a subject, or `null` when no domain registered one.
     */
    public function resolve(Model $subject): ?SubjectLabelPresenter;
}
