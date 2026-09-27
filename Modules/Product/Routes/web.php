<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Controllers\AdminProductController;
use Modules\Product\Controllers\ProductController;

Route::get('products/{product:slug}', [ProductController::class, 'show'])
    ->name('products.show');

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function (): void {
    Route::get('products', [AdminProductController::class, 'index']);
    Route::get('products/create', [AdminProductController::class, 'create']);
    Route::post('products', [AdminProductController::class, 'store'])
        ->defaults('catalog', 'products');
    Route::get('products/{id}/edit', [AdminProductController::class, 'edit'])
        ->whereNumber('id');
    Route::put('products/{id}', [AdminProductController::class, 'update'])
        ->whereNumber('id')
        ->defaults('catalog', 'products');
    Route::delete('products/{id}', [AdminProductController::class, 'destroy'])
        ->whereNumber('id');

    Route::get('products/{product}/media', [AdminProductController::class, 'mediaEdit'])
        ->name('admin.products.media.edit');
    Route::post('products/{product}/media/upload', [AdminProductController::class, 'mediaUpload'])
        ->name('admin.products.media.upload');
    Route::post('products/{product}/media', [AdminProductController::class, 'mediaUpdate'])
        ->name('admin.products.media.update');
    Route::patch('products/{product}/exchange', [AdminProductController::class, 'toggleExchange'])
        ->name('admin.products.exchange.toggle');
});
