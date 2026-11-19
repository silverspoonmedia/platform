<?php

namespace App\Support\ErrorLogging;

use Throwable;

/**
 * Kernel-owned contract for persisting reportable exceptions.
 *
 * `bootstrap/app.php` is kernel, so it must not import the error-log domain
 * that owns `error_logs`. The kernel declares the contract; `silverspoonmedia/audit`
 * binds `App\Domain\ErrorLog\Support\ErrorLogRecorder` to it. When the package
 * is absent the binding is absent too, and the reporter is skipped rather than
 * breaking exception handling.
 */
interface ExceptionRecorder
{
    /**
     * Persist a grouped, fingerprinted occurrence. Implementations never throw.
     */
    public function recordException(Throwable $e): void;
}
