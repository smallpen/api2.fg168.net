<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * 路由服務提供者
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * 應用程式的路由命名空間
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * 定義路由模型綁定、模式過濾器等
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // API 路由
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Web 路由
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Admin 路由
            Route::middleware('web')
                ->prefix('admin')
                ->group(base_path('routes/admin.php'));
        });
    }

    /**
     * 配置應用程式的速率限制
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
