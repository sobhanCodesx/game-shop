<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminResourceController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\CommerceSettingsController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DeploymentController;
use App\Http\Controllers\Admin\FeedPostController as AdminFeedPostController;
use App\Http\Controllers\Admin\HomeSettingsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductMediaController;
use App\Http\Controllers\Admin\ProductTypeController;
use App\Http\Controllers\Admin\ShortController as AdminShortController;
use App\Http\Controllers\Admin\SmsPatternController;
use App\Http\Controllers\Admin\SmsTestController;
use App\Http\Controllers\Admin\StudioController as AdminStudioController;
use App\Http\Controllers\Admin\SystemMaintenanceController;
use App\Http\Controllers\Admin\TemporaryUploadController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Admin\VideoPlaylistController as AdminVideoPlaylistController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaStreamController;
use App\Http\Controllers\MobileDeviceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialContentController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VideoCommunityController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('feed', [FeedController::class, 'index'])->name('feed.index');
Route::get('feed/trending', [FeedController::class, 'trending'])->name('feed.trending');
Route::get('feed/{content:slug}', [FeedController::class, 'show'])->name('feed.show');
Route::get('feed/{content:slug}/comments', [FeedController::class, 'comments'])->name('feed.comments');
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('sitemaps/{type}.xml', [SitemapController::class, 'show'])
    ->whereIn('type', ['static', 'products', 'categories', 'feed', 'content', 'channels', 'studios', 'playlists'])
    ->name('sitemap.show');
Route::get('media/{path}', MediaStreamController::class)->where('path', '.*')->name('media.stream');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::post('login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('auth.google.callback');
    Route::post('login/otp', [AuthController::class, 'sendPasswordlessCode'])->middleware('throttle:3,1')->name('login.otp.send');
    Route::get('login/otp', [AuthController::class, 'passwordlessNotice'])->name('login.otp.notice');
    Route::post('login/otp/verify', [AuthController::class, 'confirmPasswordlessLogin'])->middleware('throttle:8,1')->name('login.otp.verify');
    Route::post('login/otp/resend', [AuthController::class, 'resendPasswordless'])->middleware('throttle:2,1')->name('login.otp.resend');
    Route::get('register', [AuthController::class, 'register'])->name('register');
    Route::post('register', [AuthController::class, 'storeRegistration'])->middleware('throttle:3,1')->name('register.store');
    Route::get('verify-email', [AuthController::class, 'verifyAccount'])->name('verification.notice');
    Route::post('verify-email', [AuthController::class, 'confirmAccount'])->middleware('throttle:8,1')->name('verification.verify');
    Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:2,1')->name('verification.resend');
    Route::get('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetCode'])->middleware('throttle:3,1')->name('password.email');
    Route::get('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'updatePassword'])->middleware('throttle:8,1')->name('password.update');
});
Route::post('logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::post('impersonation/stop', [AdminUserController::class, 'stopImpersonating'])->middleware('auth')->name('impersonation.stop');
Route::middleware('auth')->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('dashboard');
    Route::patch('profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::put('password', [AccountController::class, 'updatePassword'])->name('password.update');
    Route::put('content-notifications', [AccountController::class, 'updateContentNotificationPreferences'])->name('content-notifications.update');
    Route::post('addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
    Route::put('addresses/{address}', [AccountController::class, 'updateAddress'])->name('addresses.update');
    Route::delete('addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('tickets/{ticket}/replies', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::patch('tickets/{ticket}/exchange-response', [TicketController::class, 'respondToExchange'])->name('tickets.exchange-response');
    Route::get('notifications/{notification}', [AccountController::class, 'readNotification'])->name('notifications.read');
    Route::patch('notifications/{notification}', [AccountController::class, 'readNotification'])->name('notifications.mark-read');
    Route::patch('notifications', [AccountController::class, 'readAllNotifications'])->name('notifications.read-all');
});
Route::middleware(['auth', 'throttle:60,1'])->prefix('mobile')->name('mobile.')->group(function () {
    Route::get('session', [MobileDeviceController::class, 'status'])->name('session');
    Route::put('devices', [MobileDeviceController::class, 'store'])->name('devices.store');
    Route::delete('devices/{installationId}', [MobileDeviceController::class, 'destroy'])->whereUuid('installationId')->name('devices.destroy');
});

// Keep the public navigation useful until dedicated listing and information
// pages are introduced. These destinations point to real sections on home
// instead of sending visitors to a 404 page.
Route::get('shop', [StorefrontController::class, 'shop'])->name('shop.index');
Route::get('exchange-products', [StorefrontController::class, 'exchangeProducts'])->name('exchange-products.index');
Route::get('products', [StorefrontController::class, 'shop'])->name('products.index');
Route::get('discover', [StorefrontController::class, 'discover'])->name('discover');
Route::get('categories', [StorefrontController::class, 'shop'])->name('categories.index');
Route::get('categories/{category:slug}', [StorefrontController::class, 'category'])->name('categories.show');
Route::get('games', [StorefrontController::class, 'shop'])->name('games.index');
Route::get('offers', [StorefrontController::class, 'shop'])->defaults('sort', 'latest')->name('offers.index');
Route::get('videos', [StorefrontController::class, 'videos'])->name('videos.index');
Route::get('studios', [StudioController::class, 'index'])->name('studios.index');
Route::get('studios/{studio:slug}', [StudioController::class, 'show'])->name('studios.show');
Route::get('channels/{game:slug}', [ChannelController::class, 'show'])->name('channels.show');
Route::get('channels/{game:slug}/playlists/{playlist:slug}', [ChannelController::class, 'playlist'])->name('channels.playlists.show');
Route::get('collections/{playlist:slug}', [ChannelController::class, 'collection'])->name('collections.show');
Route::get('search/suggestions', [StorefrontController::class, 'searchSuggestions'])->middleware('throttle:120,1')->name('search.suggestions');
Route::get('search', [StorefrontController::class, 'search'])->name('search');
Route::get('cart', [CartController::class, 'index'])->name('cart.index');
Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('cart/items/{key}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('cart/items/{key}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::middleware('auth')->group(function () {
    Route::post('feed/{content:slug}/reaction', [FeedController::class, 'react'])->middleware('throttle:60,1')->name('feed.reaction');
    Route::post('feed/{content:slug}/save', [FeedController::class, 'save'])->middleware('throttle:60,1')->name('feed.save');
    Route::post('feed/{content:slug}/comments', [FeedController::class, 'storeComment'])->middleware('throttle:20,1')->name('feed.comments.store');
    Route::post('videos/{content:slug}/reaction', [VideoCommunityController::class, 'react'])->middleware('throttle:60,1')->name('videos.reaction');
    Route::post('videos/{content:slug}/comments', [VideoCommunityController::class, 'comment'])->middleware('throttle:20,1')->name('videos.comments.store');
    Route::post('comments/{comment}/like', [VideoCommunityController::class, 'likeComment'])->middleware('throttle:60,1')->name('comments.like');
    Route::delete('comments/{comment}', [VideoCommunityController::class, 'destroyComment'])->name('comments.destroy');
    Route::post('channels/{game:slug}/subscription', [VideoCommunityController::class, 'subscribe'])->middleware('throttle:30,1')->name('channels.subscription');
    Route::post('cart/restore', [CartController::class, 'restore'])->name('cart.restore');
    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('checkout/preview', [CheckoutController::class, 'preview'])->name('checkout.preview');
    Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
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
        Route::patch('tickets/{ticket}/exchange-offer', [AdminTicketController::class, 'offer'])->name('tickets.exchange-offer');
        Route::patch('tickets/{ticket}/exchange-cancel', [AdminTicketController::class, 'cancelExchange'])->name('tickets.exchange-cancel');
        Route::patch('tickets/{ticket}/exchange-complete', [AdminTicketController::class, 'completeExchange'])->name('tickets.exchange-complete');
        Route::delete('tickets/{ticket}/attachments', [AdminTicketController::class, 'destroyAttachments'])->name('tickets.attachments.destroy');
        Route::resource('coupons', AdminCouponController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('settings', [CommerceSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [CommerceSettingsController::class, 'update'])->name('settings.update');
        Route::get('sms-patterns', [SmsPatternController::class, 'index'])->name('sms-patterns.index');
        Route::put('sms-patterns', [SmsPatternController::class, 'update'])->name('sms-patterns.update');
        Route::get('sms-test', [SmsTestController::class, 'index'])->name('sms-test.index');
        Route::post('sms-test', [SmsTestController::class, 'store'])->middleware('throttle:5,1')->name('sms-test.store');
        Route::get('system-maintenance', [SystemMaintenanceController::class, 'index'])->name('system-maintenance.index');
        Route::post('system-maintenance/run', [SystemMaintenanceController::class, 'run'])->middleware('throttle:6,1')->name('system-maintenance.run');
        Route::prefix('deployments')->name('deployments.')->middleware('deployment.guard')->group(function () {
            Route::get('/', [DeploymentController::class, 'index'])->name('index');
            Route::post('export', [DeploymentController::class, 'export'])->name('export');
            Route::post('upload/chunk', [DeploymentController::class, 'chunk'])->name('chunk');
            Route::post('upload/complete', [DeploymentController::class, 'complete'])->name('complete');
            Route::post('{deployment}/verify', [DeploymentController::class, 'verify'])->name('verify');
            Route::get('{deployment}/status', [DeploymentController::class, 'status'])->name('status');
            Route::post('{deployment}/apply', [DeploymentController::class, 'apply'])->name('apply');
            Route::post('{deployment}/rollback', [DeploymentController::class, 'rollback'])->name('rollback');
            Route::get('{deployment}/report', [DeploymentController::class, 'report'])->name('report');
            Route::delete('cleanup/expired', [DeploymentController::class, 'cleanup'])->name('cleanup');
        });

        Route::resource('product-types', ProductTypeController::class)->except('show');
        Route::resource('attributes', AttributeController::class)->except('show');
        Route::get('products/{product}/media', [ProductMediaController::class, 'edit'])->name('products.media.edit');
        Route::post('products/{product}/media/upload', [ProductMediaController::class, 'upload'])->name('products.media.upload');
        Route::post('products/{product}/media', [ProductMediaController::class, 'update'])->name('products.media.update');
        Route::patch('products/{product}/exchange', [CatalogController::class, 'toggleExchange'])->name('products.exchange.toggle');
        Route::resource('videos', AdminVideoController::class)->except('show');
        Route::resource('feed', AdminFeedPostController::class)
            ->parameters(['feed' => 'post'])
            ->except('show');
        Route::resource('studios', AdminStudioController::class)->except('show');
        Route::resource('video-playlists', AdminVideoPlaylistController::class)
            ->parameters(['video-playlists' => 'playlist'])
            ->except('show');
        Route::resource('shorts', AdminShortController::class)
            ->parameters(['shorts' => 'short'])
            ->except('show');
        Route::post('uploads/chunk', [TemporaryUploadController::class, 'chunk'])->name('uploads.chunk');
        Route::post('uploads/complete', [TemporaryUploadController::class, 'complete'])->name('uploads.complete');
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/impersonate', [AdminUserController::class, 'impersonate'])->name('users.impersonate');

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
            ->where('resource', 'creators|inventory|payments|reviews|trades|posts|videos|shorts|comments|reports|moderation|notifications|banners|pages|audit-logs|support')
            ->name('resources.index');
    });
});
