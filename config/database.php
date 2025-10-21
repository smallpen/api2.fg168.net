<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | 在這裡您可以指定要用作預設連線的資料庫連線。
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | 以下是為您的應用程式設定的每個資料庫連線。
    |
    */

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | 此表格會追蹤所有已經為您的應用程式執行的遷移。
    |
    */

    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis 是一個開源、快速且進階的鍵值儲存系統。
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Database Proxy Configuration
    |--------------------------------------------------------------------------
    |
    | 資料庫代理和 Stored Procedure 執行器的配置選項
    |
    */

    'query_timeout' => env('DB_QUERY_TIMEOUT', 30),
    'max_retries' => env('DB_MAX_RETRIES', 3),
    'retry_base_delay' => env('DB_RETRY_BASE_DELAY', 100),
    'retry_delay_multiplier' => env('DB_RETRY_DELAY_MULTIPLIER', 2.0),
    'retry_max_delay' => env('DB_RETRY_MAX_DELAY', 5000),

    'pool' => [
        'max_connections' => env('DB_POOL_MAX_CONNECTIONS', 100),
        'min_connections' => env('DB_POOL_MIN_CONNECTIONS', 10),
        'idle_timeout' => env('DB_POOL_IDLE_TIMEOUT', 300),
    ],

    'transaction' => [
        'max_retries' => env('DB_TRANSACTION_MAX_RETRIES', 3),
        'retry_delay' => env('DB_TRANSACTION_RETRY_DELAY', 100),
    ],

];
