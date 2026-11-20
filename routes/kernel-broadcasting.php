<?php

use App\Support\Broadcasting\BroadcastAuthEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Broadcast auth for cross-origin JWT clients (Vue-Workers SPA).
// The default /broadcasting/auth is registered under `web` middleware by
// Laravel's withRouting(channels:...), which requires CSRF + session —
// incompatible with a Bearer-token SPA. This route mirrors that behaviour
// under the `api` group (JWT guard, CORS-covered via api/*), delegating
// to the same channel authorization callbacks in routes/channels.php.
//
// The path lives in BroadcastAuthEndpoint so server-issued client configs
// (Pusher/Echo authEndpoint) can never point at the web twin instead.
Route::post(BroadcastAuthEndpoint::SUBPATH, function (Request $request) {
    return Broadcast::auth($request);
})->middleware(['auth:api', 'throttle:broadcast'])->name('api.broadcasting.auth');
