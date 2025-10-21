<?php

namespace App\Providers;

use App\Services\Authentication\AuthenticationManager;
use App\Services\Authentication\TokenManager;
use App\Services\Authentication\Validators\TokenValidator;
use App\Services\Authentication\Validators\ApiKeyValidator;
use App\Services\Authentication\Validators\OAuthProvider;
use Illuminate\Support\ServiceProvider;

/**
 * 驗證服務提供者
 * 
 * 註冊驗證相關的服務到容器中
 */
class AuthenticationServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     *
     * @return void
     */
    public function register(): void
    {
        // 註冊 Token 驗證器
        $this->app->singleton(TokenValidator::class, function ($app) {
            return new TokenValidator();
        });

        // 註冊 API Key 驗證器
        $this->app->singleton(ApiKeyValidator::class, function ($app) {
            return new ApiKeyValidator();
        });

        // 註冊 OAuth 提供者
        $this->app->singleton(OAuthProvider::class, function ($app) {
            return new OAuthProvider();
        });

        // 註冊驗證管理器
        $this->app->singleton(AuthenticationManager::class, function ($app) {
            return new AuthenticationManager(
                $app->make(TokenValidator::class),
                $app->make(ApiKeyValidator::class),
                $app->make(OAuthProvider::class)
            );
        });

        // 註冊 Token 管理器
        $this->app->singleton(TokenManager::class, function ($app) {
            return new TokenManager(
                $app->make(TokenValidator::class)
            );
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
