<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\CsharpApiService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CsharpApiService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use the actual request host (e.g. https://192.168.x.x) for generated URLs so assets and
        // links work on the LAN when .env still has APP_URL=http://localhost.
        if (! $this->app->runningInConsole() && request()->hasHeader('Host')) {
            URL::forceRootUrl(request()->getSchemeAndHttpHost());
        }
    }
}
