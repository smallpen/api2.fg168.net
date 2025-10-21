<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 資料庫查詢優化服務提供者
 * 
 * 提供資料庫查詢效能監控和優化功能
 */
class DatabaseOptimizationServiceProvider extends ServiceProvider
{
    /**
     * 註冊服務
     */
    public function register(): void
    {
        //
    }

    /**
     * 啟動服務
     */
    public function boot(): void
    {
        // 只在非 Production 環境或啟用除錯模式時記錄慢查詢
        if (config('app.debug') || config('app.env') !== 'production') {
            $this->logSlowQueries();
        }

        // 啟用查詢結果快取
        $this->enableQueryCache();

        // 優化資料庫連線
        $this->optimizeDatabaseConnection();
    }

    /**
     * 記錄慢查詢
     */
    protected function logSlowQueries(): void
    {
        DB::listen(function ($query) {
            // 記錄執行時間超過 1000ms 的查詢
            if ($query->time > 1000) {
                Log::warning('慢查詢偵測', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }

    /**
     * 啟用查詢結果快取
     */
    protected function enableQueryCache(): void
    {
        // Laravel 的查詢快取已內建在 Query Builder 中
        // 可以使用 ->remember() 方法來快取查詢結果
        // 這裡設定預設的快取時間
        config(['cache.query_ttl' => 3600]); // 1 小時
    }

    /**
     * 優化資料庫連線
     */
    protected function optimizeDatabaseConnection(): void
    {
        // 設定 PDO 屬性以優化效能
        DB::connection()->getPdo()->setAttribute(
            \PDO::ATTR_EMULATE_PREPARES,
            false
        );

        // 啟用持久連線（Production 環境）
        if (config('app.env') === 'production') {
            config(['database.connections.mysql.options' => [
                \PDO::ATTR_PERSISTENT => true,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ]]);
        }
    }
}
