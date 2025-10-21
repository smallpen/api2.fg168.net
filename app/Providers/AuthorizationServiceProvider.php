<?php

namespace App\Providers;

use App\Services\Authorization\AuthorizationManager;
use App\Services\Authorization\PermissionChecker;
use App\Services\Authorization\RoleManager;
use Illuminate\Support\ServiceProvider;

/**
 * Authorization Service Provider
 * 
 * 註冊授權相關服務
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        // 註冊 PermissionChecker 為單例
        $this->app->singleton(PermissionChecker::class, function ($app) {
            return new PermissionChecker();
        });

        // 註冊 RoleManager 為單例
        $this->app->singleton(RoleManager::class, function ($app) {
            return new RoleManager();
        });

        // 註冊 AuthorizationManager 為單例
        $this->app->singleton(AuthorizationManager::class, function ($app) {
            return new AuthorizationManager(
                $app->make(PermissionChecker::class),
                $app->make(RoleManager::class)
            );
        });
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        //
    }
}
