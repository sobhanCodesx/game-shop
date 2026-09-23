<?php

use App\Http\Controllers\Api\ContentAgentGraphqlController;
use App\Http\Controllers\Api\ContentAgentMcpController;
use App\Http\Controllers\Api\DeploymentAgentController;
use App\Http\Controllers\Api\NexusAiChatController;
use App\Http\Controllers\Api\NexusAiContextController;
use App\Http\Controllers\Api\NexusAiEventController;
use App\Http\Controllers\Api\NexusAiFeedbackController;
use App\Http\Controllers\Api\NexusAiHealthController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\V1\MobileAccountController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\MobileCatalogController;
use App\Http\Controllers\Api\V1\MobileChannelsController;
use App\Http\Controllers\Api\V1\MobileCommerceController;
use App\Http\Controllers\Api\V1\MobileCommunityController;
use App\Http\Controllers\Api\V1\MobileContentController;
use App\Http\Controllers\Api\V1\MobileDeviceApiController;
use App\Http\Controllers\Api\V1\MobileTicketController;
use App\Http\Controllers\Api\V1\MobileWatchProgressController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware('throttle:90,1')
    ->name('telegram.webhook');

Route::post('/mcp', ContentAgentMcpController::class)
    ->middleware(['content.agent', 'throttle:60,1'])
    ->name('content-agent.mcp');

Route::post('/graphql', ContentAgentGraphqlController::class)
    ->middleware(['content.agent', 'throttle:30,1'])
    ->name('content-agent.graphql');

Route::get('/nexus-ai/health', NexusAiHealthController::class)
    ->middleware('throttle:120,1')
    ->name('nexus-ai.health');

Route::post('/nexus-ai/chat', NexusAiChatController::class)
    ->middleware('throttle:30,1')
    ->name('nexus-ai.chat');

Route::post('/nexus-ai/context', NexusAiContextController::class)
    ->middleware('throttle:60,1')
    ->name('nexus-ai.context');

Route::post('/nexus-ai/feedback', NexusAiFeedbackController::class)
    ->middleware('throttle:30,1')
    ->name('nexus-ai.feedback');

Route::post('/nexus-ai/events', NexusAiEventController::class)
    ->middleware('throttle:90,1')
    ->name('nexus-ai.events');

Route::post('/content-agent/upload/chunk', [ContentAgentMcpController::class, 'uploadChunkFile'])
    ->middleware(['content.agent', 'throttle:1200,1'])
    ->name('content-agent.upload.chunk');

Route::post('/content-agent/android-release/upload/chunk', [ContentAgentMcpController::class, 'uploadAndroidReleaseChunkFile'])
    ->middleware(['content.agent', 'throttle:1200,1'])
    ->name('content-agent.android-release.upload.chunk');

Route::prefix('/deployment-agent')
    ->middleware(['throttle:300,1', 'deployment.agent'])
    ->name('deployment-agent.')
    ->group(function () {
        Route::post('/upload/chunk', [DeploymentAgentController::class, 'chunk'])->name('chunk');
        Route::post('/upload/complete', [DeploymentAgentController::class, 'complete'])->name('complete');
        Route::get('/health', [DeploymentAgentController::class, 'health'])->name('health');
        Route::post('/{deployment}/verify', [DeploymentAgentController::class, 'verify'])->whereUuid('deployment')->name('verify');
        Route::post('/{deployment}/apply', [DeploymentAgentController::class, 'apply'])->whereUuid('deployment')->name('apply');
        Route::get('/{deployment}/status', [DeploymentAgentController::class, 'status'])->whereUuid('deployment')->name('status');
    });

Route::prefix('v1')->name('mobile-api.v1.')->group(function (): void {
    Route::get('meta', [MobileCatalogController::class, 'meta'])
        ->middleware('throttle:120,1')
        ->name('meta');

    Route::prefix('auth')->name('auth.')->middleware('throttle:20,1')->group(function (): void {
        Route::post('register', [MobileAuthController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('register');
        Route::post('verify', [MobileAuthController::class, 'verify'])
            ->middleware('throttle:10,1')
            ->name('verify');
        Route::post('verification/resend', [MobileAuthController::class, 'resendVerification'])
            ->middleware('throttle:3,1')
            ->name('verification.resend');
        Route::post('verification/telegram', [MobileAuthController::class, 'requestVerificationTelegram'])
            ->middleware('throttle:3,1')
            ->name('verification.telegram');
        Route::post('verification/telegram/complete', [MobileAuthController::class, 'completeVerificationTelegram'])
            ->middleware('throttle:10,1')
            ->name('verification.telegram.complete');
        Route::post('login', [MobileAuthController::class, 'login'])
            ->middleware('throttle:8,1')
            ->name('login');
        Route::post('passwordless/request', [MobileAuthController::class, 'requestPasswordless'])
            ->middleware('throttle:3,1')
            ->name('passwordless.request');
        Route::post('passwordless/telegram', [MobileAuthController::class, 'requestPasswordlessTelegram'])
            ->middleware('throttle:2,1')
            ->name('passwordless.telegram');
        Route::post('passwordless/verify', [MobileAuthController::class, 'verifyPasswordless'])
            ->middleware('throttle:10,1')
            ->name('passwordless.verify');
        Route::post('password/reset/request', [MobileAuthController::class, 'requestPasswordReset'])
            ->middleware('throttle:3,1')
            ->name('password.reset.request');
        Route::post('password/reset/confirm', [MobileAuthController::class, 'confirmPasswordReset'])
            ->middleware('throttle:8,1')
            ->name('password.reset.confirm');
        Route::post('google', [MobileAuthController::class, 'google'])
            ->middleware('throttle:10,1')
            ->name('google');
        Route::post('google/exchange', [MobileAuthController::class, 'googleExchange'])
            ->middleware('throttle:10,1')
            ->name('google.exchange');
    });

    Route::middleware(['mobile.api:optional', 'throttle:180,1'])->group(function (): void {
        Route::get('home', [MobileCatalogController::class, 'home'])->name('home');

        Route::get('feed', [MobileContentController::class, 'feed'])->name('feed.index');
        Route::get('feed/trending', [MobileContentController::class, 'trending'])->name('feed.trending');
        Route::get('discover', [MobileContentController::class, 'discover'])->name('discover');
        Route::get('videos', [MobileContentController::class, 'videos'])->name('videos.index');
        Route::get('shorts', [MobileContentController::class, 'shorts'])->name('shorts.index');
        Route::get('stories', [MobileContentController::class, 'stories'])->name('stories.index');
        Route::get('contents/{content:slug}', [MobileContentController::class, 'show'])->name('contents.show');
        Route::get('contents/{content:slug}/comments', [MobileContentController::class, 'comments'])->name('contents.comments');
        Route::post('contents/{content:slug}/views', [MobileContentController::class, 'recordView'])
            ->middleware('throttle:90,1')
            ->name('contents.views.store');

        Route::get('products', [MobileCatalogController::class, 'products'])->name('products.index');
        Route::get('offers', [MobileCatalogController::class, 'offers'])->name('offers.index');
        Route::get('exchange-products', [MobileCatalogController::class, 'exchangeProducts'])->name('exchange-products.index');
        Route::get('products/{product:slug}', [MobileCatalogController::class, 'product'])->name('products.show');
        Route::get('categories', [MobileCatalogController::class, 'categories'])->name('categories.index');
        Route::get('categories/{category:slug}', [MobileCatalogController::class, 'category'])->name('categories.show');
        Route::get('search/suggestions', [MobileCatalogController::class, 'suggestions'])
            ->middleware('throttle:120,1')
            ->name('search.suggestions');
        Route::get('search', [MobileCatalogController::class, 'search'])->name('search');
        Route::get('game-radar', [MobileCatalogController::class, 'radar'])->name('game-radar.index');

        Route::get('channels', [MobileChannelsController::class, 'channels'])->name('channels.index');
        Route::get('studios', [MobileChannelsController::class, 'studios'])->name('studios.index');
        Route::get('studios/{studio:slug}', [MobileChannelsController::class, 'studio'])->name('studios.show');
        Route::get('channels/{game:slug}', [MobileChannelsController::class, 'channel'])->name('channels.show');
        Route::get('channels/{game:slug}/playlists/{playlist:slug}', [MobileChannelsController::class, 'playlist'])
            ->name('channels.playlists.show');
        Route::get('collections/{playlist:slug}', [MobileChannelsController::class, 'collection'])
            ->name('collections.show');

        Route::post('cart/resolve', [MobileCommerceController::class, 'cart'])
            ->middleware('throttle:90,1')
            ->name('cart.resolve');
    });

    Route::middleware(['mobile.api', 'throttle:180,1'])->group(function (): void {
        Route::post('auth/logout', [MobileAuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [MobileAuthController::class, 'logoutAll'])->name('auth.logout-all');

        Route::get('me', [MobileAccountController::class, 'me'])->name('account.me');
        Route::patch('me', [MobileAccountController::class, 'updateProfile'])->name('account.profile.update');
        Route::put('me/password', [MobileAccountController::class, 'updatePassword'])->name('account.password.update');

        Route::get('addresses', [MobileAccountController::class, 'addresses'])->name('addresses.index');
        Route::post('addresses', [MobileAccountController::class, 'storeAddress'])->name('addresses.store');
        Route::put('addresses/{address}', [MobileAccountController::class, 'updateAddress'])->name('addresses.update');
        Route::delete('addresses/{address}', [MobileAccountController::class, 'destroyAddress'])->name('addresses.destroy');

        Route::get('notifications', [MobileAccountController::class, 'notifications'])->name('notifications.index');
        Route::patch('notifications/read-all', [MobileAccountController::class, 'readAllNotifications'])->name('notifications.read-all');
        Route::patch('notifications/{notification}', [MobileAccountController::class, 'readNotification'])->name('notifications.read');
        Route::get('notification-preferences', [MobileAccountController::class, 'notificationPreferences'])
            ->name('notification-preferences.show');
        Route::put('notification-preferences', [MobileAccountController::class, 'updateNotificationPreferences'])
            ->name('notification-preferences.update');
        Route::post('me/telegram/connect', [MobileAccountController::class, 'connectTelegram'])
            ->middleware('throttle:5,1')
            ->name('account.telegram.connect');
        Route::post('me/phone/verify-telegram', [MobileAccountController::class, 'verifyPhoneWithTelegram'])
            ->middleware('throttle:4,1')
            ->name('account.phone.telegram');
        Route::delete('me/telegram', [MobileAccountController::class, 'disconnectTelegram'])
            ->middleware('throttle:5,1')
            ->name('account.telegram.disconnect');
        Route::get('saved', [MobileAccountController::class, 'saved'])->name('saved.index');

        Route::post('contents/{content:slug}/reaction', [MobileCommunityController::class, 'react'])
            ->middleware('throttle:60,1')
            ->name('contents.reaction');
        Route::post('contents/{content:slug}/save', [MobileCommunityController::class, 'save'])
            ->middleware('throttle:60,1')
            ->name('contents.save');
        Route::post('contents/{content:slug}/comments', [MobileCommunityController::class, 'comment'])
            ->middleware('throttle:20,1')
            ->name('contents.comments.store');
        Route::post('comments/{comment}/like', [MobileCommunityController::class, 'likeComment'])
            ->middleware('throttle:60,1')
            ->name('comments.like');
        Route::delete('comments/{comment}', [MobileCommunityController::class, 'destroyComment'])
            ->name('comments.destroy');
        Route::post('channels/{game:slug}/subscription', [MobileCommunityController::class, 'subscribe'])
            ->middleware('throttle:30,1')
            ->name('channels.subscription');

        Route::post('checkout/bootstrap', [MobileCommerceController::class, 'checkout'])->name('checkout.bootstrap');
        Route::post('checkout/preview', [MobileCommerceController::class, 'preview'])->name('checkout.preview');
        Route::post('checkout', [MobileCommerceController::class, 'store'])->name('checkout.store');
        Route::get('orders', [MobileAccountController::class, 'orders'])->name('orders.index');
        Route::get('orders/{order}', [MobileCommerceController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/invoice', [MobileCommerceController::class, 'invoice'])->name('orders.invoice');
        Route::patch('orders/{order}/cancel', [MobileCommerceController::class, 'cancel'])->name('orders.cancel');

        Route::get('tickets', [MobileTicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create-context', [MobileTicketController::class, 'createContext'])->name('tickets.create-context');
        Route::post('tickets', [MobileTicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [MobileTicketController::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/replies', [MobileTicketController::class, 'reply'])->name('tickets.reply');
        Route::patch('tickets/{ticket}/exchange-response', [MobileTicketController::class, 'exchangeDecision'])
            ->name('tickets.exchange-response');

        Route::get('session', [MobileDeviceApiController::class, 'session'])->name('session');
        Route::get('devices', [MobileDeviceApiController::class, 'index'])->name('devices.index');
        Route::put('devices', [MobileDeviceApiController::class, 'store'])->name('devices.store');
        Route::delete('devices/{installationId}', [MobileDeviceApiController::class, 'destroy'])
            ->whereUuid('installationId')
            ->name('devices.destroy');

        Route::get('watch-progress', [MobileWatchProgressController::class, 'index'])->name('watch-progress.index');
        Route::put('watch-progress/{content}', [MobileWatchProgressController::class, 'store'])
            ->whereNumber('content')
            ->name('watch-progress.store');
        Route::delete('watch-progress/{content}', [MobileWatchProgressController::class, 'destroy'])
            ->whereNumber('content')
            ->name('watch-progress.destroy');
    });
});
