<?php

namespace App\Providers;

use App\Services\RateLimit\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate Limit Service Provider
 * 
 * 註冊 Rate Limiting 相關服務
 */
class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(RateLimiter::class, function ($app) {
            return new RateLimiter();
        });
    }

    /**
     * 啟動服務
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
