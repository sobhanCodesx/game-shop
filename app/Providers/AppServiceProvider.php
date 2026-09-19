<?php

namespace App\Providers;

use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Observers\GameObserver;
use App\Observers\ProductObserver;
use App\Observers\SocialContentObserver;
use App\Services\Sms\PayamakPanelSmsService;
use App\Services\Sms\SmsProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, PayamakPanelSmsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Game::observe(GameObserver::class);
        Product::observe(ProductObserver::class);
        SocialContent::observe(SocialContentObserver::class);
    }
}
