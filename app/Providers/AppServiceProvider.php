<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 註冊任何應用程式服務
     */
    public function register(): void
    {
        // 註冊日誌服務
        $this->app->singleton(\App\Services\Logging\ApiLogger::class);
        $this->app->singleton(\App\Services\Logging\SecurityLogger::class);
        $this->app->singleton(\App\Services\Logging\AuditLogger::class);
        $this->app->singleton(\App\Services\Logging\LoggingService::class);
    }

    /**
     * 啟動任何應用程式服務
     */
    public function boot(): void
    {
        //
    }
}
