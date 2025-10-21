<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | 此選項控制應用程式使用的預設快取連線。
    |
    */

    'default' => env('CACHE_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以定義應用程式的所有快取「儲存」以及它們的驅動程式。
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        // 配置快取（用於快取 API Function 配置）
        'config' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        // 權限快取（用於快取權限檢查結果）
        'permissions' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        // 查詢結果快取（用於快取資料庫查詢結果）
        'query' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | 當使用 APC、Memcached 或 Redis 快取時，可能有其他應用程式使用相同的快取。
    | 因此，我們可以為每個快取鍵指定一個前綴，以避免衝突。
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Settings
    |--------------------------------------------------------------------------
    |
    | 定義不同類型快取的預設過期時間（秒）
    |
    */

    'ttl' => [
        // API Function 配置快取（1 小時）
        'config' => env('CACHE_TTL_CONFIG', 3600),
        
        // 權限快取（30 分鐘）
        'permissions' => env('CACHE_TTL_PERMISSIONS', 1800),
        
        // 查詢結果快取（5 分鐘）
        'query' => env('CACHE_TTL_QUERY', 300),
        
        // 一般快取（1 小時）
        'default' => env('CACHE_TTL_DEFAULT', 3600),
    ],

];
