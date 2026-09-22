<?php

namespace App\Providers;

use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\SocialContentMedia;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Observers\GameObserver;
use App\Observers\ProductObserver;
use App\Observers\SocialContentMediaObserver;
use App\Observers\SocialContentObserver;
use App\Observers\StudioObserver;
use App\Observers\VideoPlaylistObserver;
use App\Services\Sms\PayamakPanelSmsService;
use App\Services\Sms\SmsIrSmsService;
use App\Services\Sms\SmsProvider;
use App\Services\Sms\SmsProviderSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsProviderSettings::class);

        $this->app->bind(SmsProvider::class, function ($app): SmsProvider {
            /** @var SmsProviderSettings $settings */
            $settings = $app->make(SmsProviderSettings::class);
            $settings->applyRuntimeConfig();

            return match ($settings->activeProvider()) {
                SmsProviderSettings::SMS_IR => $app->make(SmsIrSmsService::class),
                default => $app->make(PayamakPanelSmsService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Game::observe(GameObserver::class);
        Product::observe(ProductObserver::class);
        SocialContent::observe(SocialContentObserver::class);
        SocialContentMedia::observe(SocialContentMediaObserver::class);
        Studio::observe(StudioObserver::class);
        VideoPlaylist::observe(VideoPlaylistObserver::class);
    }
}
