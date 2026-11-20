<?php

use App\Support\DomainAccess\Http\Controllers\API\V1\AdminDomainAccessController;
use Illuminate\Support\Facades\Route;

// Admin: product-domain visibility (Public/Private) registry.
Route::middleware(['auth:api', 'permission:view domain access'])
    ->prefix('admin/domain-access')
    ->name('admin.domain-access.')
    ->group(function (): void {
        Route::get('/', [AdminDomainAccessController::class, 'index'])->name('index');

        Route::middleware('permission:manage domain access')->group(function (): void {
            Route::put('{productKey}', [AdminDomainAccessController::class, 'update'])->name('update');
        });
    });
