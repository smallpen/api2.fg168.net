<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Configuration
    |--------------------------------------------------------------------------
    |
    | 此配置檔案定義了 API 速率限制的相關設定
    |
    */

    /**
     * 預設速率限制（每分鐘請求次數）
     */
    'default' => [
        'max_attempts' => env('RATE_LIMIT_DEFAULT', 60),
        'decay_seconds' => 60,
    ],

    /**
     * 不同客戶端類型的速率限制
     */
    'limits' => [
        'default' => '60/minute',
        'premium' => '1000/minute',
        'enterprise' => '10000/minute',
    ],

    /**
     * Redis 連線設定
     */
    'redis' => [
        'connection' => env('RATE_LIMIT_REDIS_CONNECTION', 'default'),
    ],

    /**
     * 快取鍵前綴
     */
    'prefix' => env('RATE_LIMIT_PREFIX', 'rate_limit:'),

    /**
     * 是否在回應標頭中包含速率限制資訊
     */
    'headers' => [
        'enabled' => true,
        'limit' => 'X-RateLimit-Limit',
        'remaining' => 'X-RateLimit-Remaining',
        'reset' => 'X-RateLimit-Reset',
        'retry_after' => 'Retry-After',
    ],

];
