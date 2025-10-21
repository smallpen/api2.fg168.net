<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuration Cache Settings
    |--------------------------------------------------------------------------
    |
    | 這些設定控制 API Function 配置的快取行為
    |
    */

    'cache' => [
        // 快取驅動（使用 config/cache.php 中定義的驅動）
        'driver' => env('CONFIGURATION_CACHE_DRIVER', env('CACHE_DRIVER', 'redis')),

        // 快取鍵前綴
        'prefix' => env('CONFIGURATION_CACHE_PREFIX', 'api_config:'),

        // 快取標籤
        'tag' => env('CONFIGURATION_CACHE_TAG', 'api_configurations'),

        // 預設快取時間（秒）
        'ttl' => env('CONFIGURATION_CACHE_TTL', 3600), // 1 小時

        // 是否啟用快取
        'enabled' => env('CONFIGURATION_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Validation Settings
    |--------------------------------------------------------------------------
    |
    | 這些設定控制配置驗證的行為
    |
    */

    'validation' => [
        // 是否在載入時自動驗證配置
        'auto_validate' => env('CONFIGURATION_AUTO_VALIDATE', true),

        // 驗證失敗時是否拋出例外
        'throw_on_error' => env('CONFIGURATION_THROW_ON_ERROR', true),

        // 是否記錄驗證錯誤
        'log_errors' => env('CONFIGURATION_LOG_ERRORS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Cache Clear Settings
    |--------------------------------------------------------------------------
    |
    | 這些設定控制自動清除快取的行為
    |
    */

    'auto_clear_cache' => [
        // 是否在配置更新時自動清除快取
        'enabled' => env('CONFIGURATION_AUTO_CLEAR_CACHE', true),

        // 是否記錄快取清除操作
        'log_operations' => env('CONFIGURATION_LOG_CACHE_OPERATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Data Types
    |--------------------------------------------------------------------------
    |
    | API Function 參數和回應支援的資料類型
    |
    */

    'data_types' => [
        'string',
        'integer',
        'float',
        'boolean',
        'date',
        'datetime',
        'json',
        'array',
    ],

];
