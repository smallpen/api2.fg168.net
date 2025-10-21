<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API 快取設定
    |--------------------------------------------------------------------------
    |
    | 此檔案包含 API Manager 系統的所有快取相關設定
    |
    */

    /*
    |--------------------------------------------------------------------------
    | 配置快取設定
    |--------------------------------------------------------------------------
    */
    'configuration' => [
        // 是否啟用配置快取
        'enabled' => env('CACHE_CONFIGURATION_ENABLED', true),
        
        // 快取時間（秒）- 預設 1 小時
        'ttl' => env('CACHE_CONFIGURATION_TTL', 3600),
        
        // 快取鍵前綴
        'prefix' => 'api_config:',
        
        // 快取標籤
        'tag' => 'api_configurations',
    ],

    /*
    |--------------------------------------------------------------------------
    | 權限快取設定
    |--------------------------------------------------------------------------
    */
    'permission' => [
        // 是否啟用權限快取
        'enabled' => env('CACHE_PERMISSION_ENABLED', true),
        
        // 快取時間（秒）- 預設 30 分鐘
        'ttl' => env('CACHE_PERMISSION_TTL', 1800),
        
        // 客戶端權限快取前綴
        'client_prefix' => 'client_perm:',
        
        // 角色權限快取前綴
        'role_prefix' => 'role_perm:',
        
        // Function 權限快取前綴
        'function_prefix' => 'func_perm:',
    ],

    /*
    |--------------------------------------------------------------------------
    | 查詢結果快取設定
    |--------------------------------------------------------------------------
    */
    'query_result' => [
        // 是否啟用查詢結果快取
        'enabled' => env('CACHE_QUERY_RESULT_ENABLED', true),
        
        // 快取時間（秒）- 預設 5 分鐘
        'ttl' => env('CACHE_QUERY_RESULT_TTL', 300),
        
        // 快取鍵前綴
        'prefix' => 'query_result:',
        
        // 可快取的 Function 識別碼列表（空陣列表示全部可快取）
        'cacheable_functions' => env('CACHE_QUERY_CACHEABLE_FUNCTIONS', []),
        
        // 不可快取的 Function 識別碼列表
        'non_cacheable_functions' => env('CACHE_QUERY_NON_CACHEABLE_FUNCTIONS', []),
        
        // 根據參數決定是否快取（例如：某些參數值不應快取）
        'cache_conditions' => [
            // 範例：'user_id' => ['!=', 'admin']
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 快取失效策略
    |--------------------------------------------------------------------------
    */
    'invalidation' => [
        // 當 Function 更新時，是否自動清除相關快取
        'auto_clear_on_function_update' => env('CACHE_AUTO_CLEAR_ON_UPDATE', true),
        
        // 當權限變更時，是否自動清除相關快取
        'auto_clear_on_permission_change' => env('CACHE_AUTO_CLEAR_ON_PERMISSION', true),
        
        // 當客戶端角色變更時，是否自動清除相關快取
        'auto_clear_on_role_change' => env('CACHE_AUTO_CLEAR_ON_ROLE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 快取預熱設定
    |--------------------------------------------------------------------------
    */
    'warmup' => [
        // 是否在系統啟動時預熱快取
        'enabled' => env('CACHE_WARMUP_ENABLED', false),
        
        // 預熱的 Function 識別碼列表
        'functions' => env('CACHE_WARMUP_FUNCTIONS', []),
        
        // 預熱排程（Cron 表達式）
        'schedule' => env('CACHE_WARMUP_SCHEDULE', '0 */6 * * *'), // 每 6 小時
    ],

    /*
    |--------------------------------------------------------------------------
    | 快取監控設定
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        // 是否記錄快取命中率
        'log_hit_rate' => env('CACHE_LOG_HIT_RATE', false),
        
        // 是否記錄快取操作
        'log_operations' => env('CACHE_LOG_OPERATIONS', false),
        
        // 快取統計資訊更新間隔（秒）
        'stats_interval' => env('CACHE_STATS_INTERVAL', 300),
    ],

];
