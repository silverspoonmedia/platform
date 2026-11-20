<?php

use App\Support\Settings\Http\Controllers\API\V1\AdminSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'permission:view settings'])
    ->prefix('admin/settings')
    ->name('admin.settings.')
    ->group(function (): void {
        Route::get('groups', [AdminSettingsController::class, 'groups'])->name('groups');
        Route::get('{group}', [AdminSettingsController::class, 'show'])->name('show');

        Route::middleware('permission:manage settings')->group(function (): void {
            Route::put('{group}', [AdminSettingsController::class, 'update'])->name('update');
        });
    });
