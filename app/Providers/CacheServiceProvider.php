<?php

namespace App\Providers;

use App\Services\Cache\CacheManager;
use App\Services\Configuration\ConfigurationCache;
use App\Services\Authorization\PermissionCache;
use App\Services\Database\QueryResultCache;
use Illuminate\Support\ServiceProvider;

/**
 * Cache Service Provider
 * 
 * 註冊快取相關服務
 */
class CacheServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        // 註冊 ConfigurationCache 為單例
        $this->app->singleton(ConfigurationCache::class, function ($app) {
            return new ConfigurationCache();
        });

        // 註冊 PermissionCache 為單例
        $this->app->singleton(PermissionCache::class, function ($app) {
            return new PermissionCache();
        });

        // 註冊 QueryResultCache 為單例
        $this->app->singleton(QueryResultCache::class, function ($app) {
            return new QueryResultCache();
        });

        // 註冊 CacheManager 為單例
        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                $app->make(ConfigurationCache::class),
                $app->make(PermissionCache::class),
                $app->make(QueryResultCache::class)
            );
        });
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        // 註冊 Artisan 命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\CacheClearCommand::class,
                \App\Console\Commands\CacheStatsCommand::class,
                \App\Console\Commands\CacheWarmupCommand::class,
            ]);
        }
    }
}
