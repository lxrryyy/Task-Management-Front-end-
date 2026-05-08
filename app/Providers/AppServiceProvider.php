<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\AccountApiService;
use App\Services\AuthApiService;
use App\Services\CommentApiService;
use App\Services\CsharpApiService;
use App\Services\DashboardApiService;
use App\Services\NotificationApiService;
use App\Services\ProjectApiService;
use App\Services\StickyNoteApiService;
use App\Services\TaskApiService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CsharpApiService::class);
        $this->app->singleton(TaskApiService::class);
        $this->app->singleton(CommentApiService::class);
        $this->app->singleton(NotificationApiService::class);
        $this->app->singleton(ProjectApiService::class);
        $this->app->singleton(AccountApiService::class);
        $this->app->singleton(AuthApiService::class);
        $this->app->singleton(DashboardApiService::class);
        $this->app->singleton(StickyNoteApiService::class);
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
