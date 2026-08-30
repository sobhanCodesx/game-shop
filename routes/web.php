<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\CommerceSettingsController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\HomeSettingsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductMediaController;
use App\Http\Controllers\Admin\ProductTypeController;
use App\Http\Controllers\Admin\ShortController as AdminShortController;
use App\Http\Controllers\Admin\TemporaryUploadController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaStreamController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SocialContentController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('media/{path}', MediaStreamController::class)->where('path', '.*')->name('media.stream');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::post('login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('register', [AuthController::class, 'register'])->name('register');
    Route::post('register', [AuthController::class, 'storeRegistration'])->middleware('throttle:3,1')->name('register.store');
    Route::get('verify-email', [AuthController::class, 'verifyEmail'])->name('verification.notice');
    Route::post('verify-email', [AuthController::class, 'confirmEmail'])->middleware('throttle:8,1')->name('verification.verify');
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:2,1')->name('verification.resend');
    Route::get('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetCode'])->middleware('throttle:3,1')->name('password.email');
    Route::get('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:8,1')->name('password.update');
});
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('dashboard');
    Route::patch('profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('password', [AccountController::class, 'updatePassword'])->name('password.update');
    Route::post('addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
    Route::put('addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/replies', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::get('notifications/{notification}', [AccountController::class, 'readNotification'])->name('notifications.read');
    Route::patch('notifications/{notification}', [AccountController::class, 'readNotification'])->name('notifications.mark-read');
    Route::patch('notifications', [AccountController::class, 'readAllNotifications'])->name('notifications.read-all');
});

// Keep the public navigation useful until dedicated listing and information
// pages are introduced. These destinations point to real sections on home
// instead of sending visitors to a 404 page.
Route::get('shop', [StorefrontController::class, 'shop'])->name('shop.index');
Route::get('products', [StorefrontController::class, 'shop'])->name('products.index');
Route::get('discover', [StorefrontController::class, 'discover'])->name('discover');
Route::get('categories', [StorefrontController::class, 'shop'])->name('categories.index');
Route::get('categories/{category:slug}', [StorefrontController::class, 'category'])->name('categories.show');
Route::get('games', [StorefrontController::class, 'shop'])->name('games.index');
Route::get('offers', [StorefrontController::class, 'shop'])->defaults('sort', 'latest')->name('offers.index');
Route::get('videos', [StorefrontController::class, 'videos'])->name('videos.index');
Route::get('search', [StorefrontController::class, 'search'])->name('search');
Route::get('cart', [CartController::class, 'index'])->name('cart.index');
Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('cart/items/{key}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('cart/items/{key}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::middleware('auth')->group(function () {
    Route::post('cart/restore', [CartController::class, 'restore'])->name('cart.restore');
    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout/preview', [CheckoutController::class, 'preview'])->name('checkout.preview');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});
Route::redirect('pages/about', '/#store-information');
Route::redirect('pages/terms', '/#store-information');
Route::redirect('support', '/account/tickets');

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
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
        Route::get('orders/{order}/tickets/create', [AdminTicketController::class, 'createForOrder'])->name('orders.tickets.create');
        Route::post('orders/{order}/tickets', [AdminTicketController::class, 'storeForOrder'])->name('orders.tickets.store');
        Route::get('tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/replies', [AdminTicketController::class, 'reply'])->name('tickets.reply');
        Route::patch('tickets/{ticket}', [AdminTicketController::class, 'update'])->name('tickets.update');
        Route::resource('coupons', AdminCouponController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('settings', [CommerceSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [CommerceSettingsController::class, 'update'])->name('settings.update');

        Route::resource('product-types', ProductTypeController::class)->except('show');
        Route::resource('attributes', AttributeController::class)->except('show');
        Route::get('products/{product}/media', [ProductMediaController::class, 'edit'])->name('products.media.edit');
        Route::post('products/{product}/media/upload', [ProductMediaController::class, 'upload'])->name('products.media.upload');
        Route::post('products/{product}/media', [ProductMediaController::class, 'update'])->name('products.media.update');
        Route::resource('videos', AdminVideoController::class)->except('show');
        Route::resource('shorts', AdminShortController::class)
            ->parameters(['shorts' => 'short'])
            ->except('show');
        Route::post('uploads/chunk', [TemporaryUploadController::class, 'chunk'])->name('uploads.chunk');
        Route::post('uploads/complete', [TemporaryUploadController::class, 'complete'])->name('uploads.complete');

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
            ->where('resource', 'users|creators|inventory|payments|reviews|trades|posts|videos|shorts|comments|reports|moderation|notifications|banners|pages|audit-logs|support')
            ->name('resources.index');
    });
});
