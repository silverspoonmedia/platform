<?php

use App\Http\Middleware\RequireCoreHubSignature;
use App\Support\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Machine surface: a core hub pushes identity and entitlement snapshots here.
// Never mounted under `api/v1` (browser traffic) — see `RequireSpaRequestHeader`
// and the `internal` group in `bootstrap/app.php`.
//
// The handlers are closures on purpose: the kernel ships the transport (route,
// signature, prefix) and the consumer owns what a push *means* (bind
// `CoreHubReceiver`). A consumer that binds nothing answers 501 instead of
// crashing, and the hub's outbox retries.
Route::middleware(RequireCoreHubSignature::class)
    ->prefix('internal/v1')
    ->name('internal.v1.')
    ->group(function (): void {
        Route::post('identity/sync', function (Request $request) {
            if (! app()->bound(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)) {
                return ApiResponder::error(__('api/common.core_hub_receiver_absent'), 501);
            }

            return ApiResponder::success([
                'accepted' => app(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)
                    ->applyIdentity(\App\Support\CoreHub\IdentitySnapshot::fromArray((array) $request->all())),
            ], __('api/common.core_hub_identity_synced'));
        })->name('identity.sync');

        Route::post('entitlement/change', function (Request $request) {
            if (! app()->bound(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)) {
                return ApiResponder::error(__('api/common.core_hub_receiver_absent'), 501);
            }

            return ApiResponder::success([
                'accepted' => app(\App\Support\CoreHub\Contracts\CoreHubReceiver::class)
                    ->applyEntitlement(\App\Support\CoreHub\Entitlement::fromArray((array) $request->all())),
            ], __('api/common.core_hub_entitlement_synced'));
        })->name('entitlement.change');
    });
