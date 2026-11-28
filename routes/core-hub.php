<?php

use App\Support\CoreHub\HubSignature;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core-hub machine surface (kernel transport)
|--------------------------------------------------------------------------
|
| Mounted by `bootstrap/app.php` under `throttle:internal` — never under
| `api/v1`, so nothing a browser reaches is ever here. A hub holds a shared
| secret, not an SPA marker, a JWT cookie or a CSRF nonce.
|
| The kernel ships the transport (prefix, signature, throttle, envelope) and
| the host owns what a push *means* by binding `App\Support\CoreHub\Contracts\CoreHubReceiver`.
| A host that binds nothing answers 501 and the hub's outbox retries, so this
| file is safe to mount in every install — standalone included.
|
| The signature middleware is `App\Http\Middleware\RequireCoreHubSignature`:
| resolved by name at request time, so a host copy under `app/` wins the PSR-4
| race and a kernel-only install falls back to the package class of the same
| name.
*/

Route::prefix('internal/v1')
    ->name('internal.v1.')
    ->middleware(\App\Http\Middleware\RequireCoreHubSignature::class)
    ->group(function (): void {
        Route::post('identity/sync', function (\Illuminate\Http\Request $request) {
            $receiver = app()->bound(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)
                ? app(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)
                : null;

            if ($receiver === null) {
                return \App\Support\ApiResponder::error(__('api/common.core_hub_receiver_absent'), 501);
            }

            $snapshot = \App\Support\CoreHub\IdentitySnapshot::fromArray((array) $request->json()->all());

            return \App\Support\ApiResponder::success(
                ['accepted' => true, 'changed' => $receiver->applyIdentity($snapshot), 'version' => $snapshot->version],
                __('api/common.core_hub_identity_synced'),
            );
        })->name('identity.sync');

        Route::post('entitlement/change', function (\Illuminate\Http\Request $request) {
            $receiver = app()->bound(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)
                ? app(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)
                : null;

            if ($receiver === null) {
                return \App\Support\ApiResponder::error(__('api/common.core_hub_receiver_absent'), 501);
            }

            $entitlement = \App\Support\CoreHub\Entitlement::fromArray((array) $request->json()->all());

            return \App\Support\ApiResponder::success(
                ['accepted' => true, 'changed' => $receiver->applyEntitlement($entitlement), 'version' => $entitlement->version],
                __('api/common.core_hub_entitlement_synced'),
            );
        })->name('entitlement.change');

        // Liveness probe a hub calls before pushing a burst: proves the secret
        // and the mount without writing a row.
        Route::get('ping', fn () => \App\Support\ApiResponder::success(
            ['signature' => HubSignature::HEADER],
            __('api/common.pong'),
        ))->name('ping');
    });
