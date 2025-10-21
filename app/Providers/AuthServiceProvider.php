<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * 應用程式的策略映射
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * 註冊任何驗證/授權服務
     */
    public function boot(): void
    {
        //
    }
}
