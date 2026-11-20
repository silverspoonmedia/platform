<?php

namespace App\Providers;

use App\Support\Http\ApiExceptionEnvelope;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the API error envelope for an install that has no monolith
 * `bootstrap/app.php` wiring.
 *
 * `bootstrap/app.php` is host code and never ships, so a consumer that installs
 * `silverspoonmedia/platform` alone receives the package's controllers — which
 * answer with `{status, message}` — and Laravel's default handler, which answers
 * 401/403/404/429 with `{message}` only. The two shapes would sit on the same
 * endpoint. This provider closes that gap by registering the same
 * `ApiExceptionEnvelope` the host registers.
 *
 * Both paths use one implementation and {@see ApiExceptionEnvelope::register()}
 * is idempotent per handler, so the monolith — where both run — answers once.
 */
class ApiExceptionEnvelopeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $register = static function (mixed $handler): void {
            if ($handler instanceof Handler) {
                ApiExceptionEnvelope::register($handler);
            }
        };

        // A consumer's handler is usually bound but not yet resolved when
        // providers boot; the host's `bootstrap/app.php` has already resolved it
        // by then. Cover both: register now when resolved, otherwise on resolve.
        if ($this->app->resolved(ExceptionHandler::class)) {
            $register($this->app->make(ExceptionHandler::class));
        } else {
            $this->app->afterResolving(ExceptionHandler::class, $register);
        }
    }
}
