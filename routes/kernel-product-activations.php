<?php

use App\Support\Product\Http\Controllers\API\V1\AdminProductActivationController;
use App\Support\Product\Http\Controllers\API\V1\ProductActivationController;
use Illuminate\Support\Facades\Route;

// Self-service: current user's own activations.
Route::middleware('auth:api')->prefix('product-activations')->name('product-activations.')->group(function (): void {
    Route::get('/', [ProductActivationController::class, 'index'])->name('index');
    Route::get('{productKey}', [ProductActivationController::class, 'show'])->name('show');
});

// Admin: analytics + management across all users/products.
Route::middleware(['auth:api', 'permission:view product activations'])->prefix('admin/product-activations')->name('admin.product-activations.')->group(function (): void {
    Route::get('products', [AdminProductActivationController::class, 'products'])->name('products');
    Route::get('products/{productKey}', [AdminProductActivationController::class, 'productShow'])->name('product.show');
    Route::get('products/{productKey}/users/{userId}', [AdminProductActivationController::class, 'userDetail'])->name('product.user.detail');

    Route::get('/', [AdminProductActivationController::class, 'index'])->name('index');
    Route::get('stats', [AdminProductActivationController::class, 'stats'])->name('stats');
    Route::get('{productActivation}', [AdminProductActivationController::class, 'show'])->whereUuid('productActivation')->name('show');

    Route::middleware('permission:manage product activations')->group(function (): void {
        Route::post('{productActivation}/approve', [AdminProductActivationController::class, 'approve'])->whereUuid('productActivation')->name('approve');
        Route::post('{productActivation}/reject', [AdminProductActivationController::class, 'reject'])->whereUuid('productActivation')->name('reject');
        Route::post('{productActivation}/suspend', [AdminProductActivationController::class, 'suspend'])->whereUuid('productActivation')->name('suspend');
        Route::post('{productActivation}/reactivate', [AdminProductActivationController::class, 'reactivate'])->whereUuid('productActivation')->name('reactivate');
    });
});
