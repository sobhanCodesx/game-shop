<?php

namespace App\Providers;

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
        //
    }
}
