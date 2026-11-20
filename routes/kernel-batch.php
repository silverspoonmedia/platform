<?php

use App\Http\Controllers\API\BatchController;
use Illuminate\Support\Facades\Route;

// Read-only fan-in for the SPA: one HTTP round-trip hydrates a whole view
// instead of one per widget. Tighter throttle than the global API limit
// because a single call multiplies into several sub-requests.
Route::post('batch', BatchController::class)
    ->middleware(['auth:api', 'throttle:30,1'])
    ->name('batch');
