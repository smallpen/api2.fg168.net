<?php

namespace App\Providers;

use App\Services\Database\ConnectionPoolManager;
use App\Services\Database\ErrorHandler;
use App\Services\Database\ParameterMapper;
use App\Services\Database\ResultTransformer;
use App\Services\Database\RetryHandler;
use App\Services\Database\StoredProcedureExecutor;
use App\Services\Database\TransactionManager;
use Illuminate\Support\ServiceProvider;

/**
 * 資料庫服務提供者
 * 
 * 註冊資料庫相關服務
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     *
     * @return void
     */
    public function register(): void
    {
        // 註冊 ParameterMapper 為單例
        $this->app->singleton(ParameterMapper::class, function ($app) {
            return new ParameterMapper();
        });

        // 註冊 ErrorHandler 為單例
        $this->app->singleton(ErrorHandler::class, function ($app) {
            return new ErrorHandler();
        });

        // 註冊 RetryHandler 為單例
        $this->app->singleton(RetryHandler::class, function ($app) {
            return new RetryHandler(
                $app->make(ErrorHandler::class)
            );
        });

        // 註冊 StoredProcedureExecutor 為單例
        $this->app->singleton(StoredProcedureExecutor::class, function ($app) {
            return new StoredProcedureExecutor(
                $app->make(ParameterMapper::class),
                $app->make(ErrorHandler::class)
            );
        });

        // 註冊 ConnectionPoolManager 為單例
        $this->app->singleton(ConnectionPoolManager::class, function ($app) {
            return new ConnectionPoolManager();
        });

        // 註冊 TransactionManager 為單例
        $this->app->singleton(TransactionManager::class, function ($app) {
            return new TransactionManager();
        });

        // 註冊 ResultTransformer 為單例
        $this->app->singleton(ResultTransformer::class, function ($app) {
            return new ResultTransformer();
        });
    }

    /**
     * 啟動服務
     *
     * @return void
     */
    public function boot(): void
    {
        // 註冊定期清理閒置連線的排程
        if ($this->app->runningInConsole()) {
            $this->commands([
                // 可以在這裡註冊相關的 Console 命令
            ]);
        }
    }

    /**
     * 取得提供者提供的服務
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            ParameterMapper::class,
            ErrorHandler::class,
            RetryHandler::class,
            StoredProcedureExecutor::class,
            ConnectionPoolManager::class,
            TransactionManager::class,
            ResultTransformer::class,
        ];
    }
}
