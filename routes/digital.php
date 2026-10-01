<?php

use App\Http\Controllers\Admin\DigitalOrderController as AdminDigitalOrderController;
use App\Http\Controllers\Admin\DigitalProductController as AdminDigitalProductController;
use App\Http\Controllers\DigitalOrderAttachmentController;
use App\Http\Controllers\DigitalOrderController;
use App\Http\Controllers\DigitalStoreController;
use Illuminate\Support\Facades\Route;

Route::get('digital', [DigitalStoreController::class, 'index'])->name('digital.index');
Route::get('digital/{digitalProduct:slug}', [DigitalStoreController::class, 'show'])->name('digital.show');

Route::middleware('auth')->group(function () {
    Route::get('digital-order-files/{message}', DigitalOrderAttachmentController::class)
        ->name('digital-order-files.show');
    Route::post('digital/{digitalProduct:slug}/orders', [DigitalStoreController::class, 'order'])->name('digital.orders.store');
    Route::post('digital/{digitalProduct:slug}/price-inquiry', [DigitalStoreController::class, 'priceInquiry'])
        ->middleware('throttle:6,1')
        ->name('digital.price-inquiry');

    Route::prefix('account/digital-orders')->name('account.digital-orders.')->group(function () {
        Route::get('/', [DigitalOrderController::class, 'index'])->name('index');
        Route::get('{digitalOrder}', [DigitalOrderController::class, 'show'])->name('show');
        Route::get('{digitalOrder}/messages', [DigitalOrderController::class, 'messages'])->name('messages');
        Route::post('{digitalOrder}/messages', [DigitalOrderController::class, 'reply'])->name('reply');
        Route::post('{digitalOrder}/receipt', [DigitalOrderController::class, 'receipt'])->name('receipt');
        Route::patch('{digitalOrder}/confirm', [DigitalOrderController::class, 'confirm'])->name('confirm');
        Route::patch('{digitalOrder}/problem', [DigitalOrderController::class, 'problem'])->name('problem');
        Route::patch('{digitalOrder}/cancel', [DigitalOrderController::class, 'cancel'])->name('cancel');
    });
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('digital-products', [AdminDigitalProductController::class, 'index'])->name('digital-products.index');
    Route::get('digital-products/game-options', [AdminDigitalProductController::class, 'gameOptions'])->name('digital-products.game-options');
    Route::get('digital-products/create', [AdminDigitalProductController::class, 'create'])->name('digital-products.create');
    Route::post('digital-products', [AdminDigitalProductController::class, 'store'])->name('digital-products.store');
    Route::get('digital-products/{digitalProduct:id}/edit', [AdminDigitalProductController::class, 'edit'])->name('digital-products.edit');
    Route::put('digital-products/{digitalProduct:id}', [AdminDigitalProductController::class, 'update'])->name('digital-products.update');

    Route::get('digital-orders', [AdminDigitalOrderController::class, 'index'])->name('digital-orders.index');
    Route::get('digital-orders/{digitalOrder}', [AdminDigitalOrderController::class, 'show'])->name('digital-orders.show');
    Route::get('digital-orders/{digitalOrder}/messages', [AdminDigitalOrderController::class, 'messages'])->name('digital-orders.messages');
    Route::post('digital-orders/{digitalOrder}/messages', [AdminDigitalOrderController::class, 'reply'])->name('digital-orders.reply');
    Route::patch('digital-orders/{digitalOrder}/payment', [AdminDigitalOrderController::class, 'confirmPayment'])->name('digital-orders.payment');
    Route::patch('digital-orders/{digitalOrder}/preparing', [AdminDigitalOrderController::class, 'preparing'])->name('digital-orders.preparing');
    Route::post('digital-orders/{digitalOrder}/delivery', [AdminDigitalOrderController::class, 'deliver'])->name('digital-orders.delivery');
    Route::patch('digital-orders/{digitalOrder}/cancel', [AdminDigitalOrderController::class, 'cancel'])->name('digital-orders.cancel');
});
