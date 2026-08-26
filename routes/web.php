<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\HomeSettingsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SocialContentController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('{type}/{content:slug}', [SocialContentController::class, 'show'])
    ->whereIn('type', ['posts', 'videos', 'shorts'])
    ->name('content.show');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');
        Route::get('home', [HomeSettingsController::class, 'edit'])->name('home.edit');
        Route::post('home', [HomeSettingsController::class, 'update'])->name('home.update');

        Route::prefix('{catalog}')
            ->whereIn('catalog', ['categories', 'brands', 'games', 'platforms', 'products'])
            ->name('catalog.')
            ->controller(CatalogController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{id}/edit', 'edit')->whereNumber('id')->name('edit');
                Route::put('{id}', 'update')->whereNumber('id')->name('update');
                Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy');
            });

        Route::get('{resource}', AdminResourceController::class)
            ->where('resource', 'users|creators|inventory|orders|payments|coupons|reviews|trades|posts|videos|shorts|comments|reports|moderation|notifications|banners|pages|settings|audit-logs|support')
            ->name('resources.index');
    });
});
