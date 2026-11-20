<?php

namespace App\Support\Http;

use App\Domain\Identity\Models\User;
use App\Support\ApiResponder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Spatie\Permission\Exceptions\UnauthorizedException as PermissionUnauthorizedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * The API error envelope, in one place.
 *
 * Every API failure renders `{status: "error", message: …}` at the status the
 * cause deserves. That shape is kernel policy — `ApiResponder` and
 * `BusinessRuleException` already live here — but it used to be assembled only
 * in the monolith's `bootstrap/app.php`, so a consumer that installed
 * `silverspoonmedia/platform` got Laravel's bare `{message: …}` for 401/403/404
 * and 429 while the package's own controllers returned the envelope. Same
 * endpoint, two shapes, depending on whether the request failed inside or
 * outside the domain.
 *
 * The host registers this from `bootstrap/app.php`; the kernel registers it in
 * `ApiExceptionEnvelopeServiceProvider`, so the two can never drift. Host-only
 * concerns stay in the host: the 419 session branch and the CORS stamp on
 * exception responses.
 */
final class ApiExceptionEnvelope
{
    /**
     * Handlers already configured, by object id.
     *
     * Two registration paths exist on purpose: the host calls this from
     * `bootstrap/app.php`, the kernel provider calls it for a consumer that has
     * no such file. In the monolith both run, and a second set of renderables
     * would re-answer every exception with an identical response.
     *
     * @var array<int, true>
     */
    private static array $registered = [];

    public static function register(Handler $handler): void
    {
        $id = spl_object_id($handler);

        if (isset(self::$registered[$id])) {
            return;
        }

        self::$registered[$id] = true;

        $handler->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $handler->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $message = match ($previous->getModel()) {
                    User::class => __('api/users.not_found'),
                    default => __('api/common.data_not_found'),
                };

                return ApiResponder::error($message, 404);
            }

            return ApiResponder::error(__('api/common.resource_not_found'), 404);
        });

        $handler->renderable(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = $request->bearerToken()
                ? __('api/auth.token_invalid')
                : __('api/auth.bearer_required');

            return ApiResponder::error($message, 401);
        });

        $handler->renderable(function (UnauthorizedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match ($e->getMessage()) {
                'Token not provided' => __('api/auth.bearer_required'),
                'User not found' => __('api/auth.token_invalid'),
                default => $e->getMessage() ?: __('api/auth.could_not_authenticate'),
            };

            return ApiResponder::error($message, 401);
        });

        $handler->renderable(function (JWTException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // `TokenBlacklistedException` extends `TokenInvalidException`, so
            // the narrower branch has to come first or `token_revoked` is
            // unreachable.
            $message = match (true) {
                $e instanceof TokenExpiredException => __('api/auth.token_expired'),
                $e instanceof TokenBlacklistedException => __('api/auth.token_revoked'),
                $e instanceof TokenInvalidException => __('api/auth.token_invalid'),
                default => __('api/auth.could_not_authenticate'),
            };

            return ApiResponder::error($message, 401);
        });

        $handler->renderable(function (PermissionUnauthorizedException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponder::error(__('api/common.unauthorized'), 403);
        });

        $handler->renderable(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponder::error(
                $e->getMessage() ?: __('api/common.unauthorized'),
                403,
            );
        });

        $handler->renderable(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponder::error(
                $e->getMessage() ?: __('api/common.unauthorized'),
                403,
            );
        });

        // The login throttle throws this from inside AuthService, and route
        // middleware throws it too; both owe the client the envelope and the
        // `Retry-After` header.
        $handler->renderable(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $response = ApiResponder::error(
                $e->getMessage() ?: __('api/common.too_many_requests'),
                429,
            );

            foreach ($e->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }

            return $response;
        });
    }
}
