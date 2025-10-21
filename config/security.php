<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | 這裡定義應用程式的安全相關設定
    |
    */

    /*
    |--------------------------------------------------------------------------
    | IP Whitelists
    |--------------------------------------------------------------------------
    |
    | 定義不同用途的 IP 白名單
    | 支援單一 IP 和 CIDR 表示法（例如：192.168.1.0/24）
    |
    */

    'ip_whitelists' => [
        // 預設白名單（空陣列表示允許所有 IP）
        'default' => explode(',', env('IP_WHITELIST_DEFAULT', '')),

        // Admin UI 白名單
        'admin' => explode(',', env('IP_WHITELIST_ADMIN', '')),

        // API 白名單
        'api' => explode(',', env('IP_WHITELIST_API', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | 速率限制設定
    |
    */

    'rate_limiting' => [
        // 預設速率限制（每分鐘請求數）
        'default' => env('RATE_LIMIT_DEFAULT', 60),

        // 登入嘗試限制
        'login_attempts' => env('RATE_LIMIT_LOGIN', 5),

        // API 請求限制
        'api' => [
            'default' => env('API_RATE_LIMIT_DEFAULT', 60),
            'premium' => env('API_RATE_LIMIT_PREMIUM', 1000),
            'enterprise' => env('API_RATE_LIMIT_ENTERPRISE', 10000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | 密碼政策設定
    |
    */

    'password' => [
        // 最小長度
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),

        // 需要大寫字母
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),

        // 需要小寫字母
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),

        // 需要數字
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),

        // 需要特殊字元
        'require_special_chars' => env('PASSWORD_REQUIRE_SPECIAL', true),

        // 密碼過期天數（0 表示不過期）
        'expires_days' => env('PASSWORD_EXPIRES_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Configuration
    |--------------------------------------------------------------------------
    |
    | Token 相關設定
    |
    */

    'token' => [
        // Token 長度（字元數）
        'length' => env('TOKEN_LENGTH', 64),

        // Token 過期時間（分鐘）
        'ttl' => env('TOKEN_TTL', 60),

        // Refresh Token 過期時間（分鐘）
        'refresh_ttl' => env('TOKEN_REFRESH_TTL', 20160), // 14 天
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Injection Protection
    |--------------------------------------------------------------------------
    |
    | SQL Injection 防護設定
    |
    */

    'sql_injection' => [
        // 啟用 SQL Injection 檢測
        'enabled' => env('SQL_INJECTION_PROTECTION', true),

        // 可疑的 SQL 關鍵字
        'suspicious_keywords' => [
            'UNION', 'SELECT', 'DROP', 'TABLE', 'INSERT', 'INTO',
            'DELETE', 'FROM', 'UPDATE', 'SET', 'EXEC', 'EXECUTE',
            'SCRIPT', '--', '#', '/*', '*/',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | XSS Protection
    |--------------------------------------------------------------------------
    |
    | XSS 防護設定
    |
    */

    'xss' => [
        // 啟用 XSS 防護
        'enabled' => env('XSS_PROTECTION', true),

        // 允許的 HTML 標籤
        'allowed_tags' => ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li'],
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | CORS 相關設定
    |
    */

    'cors' => [
        // 允許的來源
        'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

        // 允許的方法
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

        // 允許的標頭
        'allowed_headers' => ['*'],

        // 是否允許憑證
        'supports_credentials' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | 安全標頭設定
    |
    */

    'headers' => [
        // 啟用安全標頭
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),

        // X-Frame-Options
        'x_frame_options' => 'SAMEORIGIN',

        // X-Content-Type-Options
        'x_content_type_options' => 'nosniff',

        // X-XSS-Protection
        'x_xss_protection' => '1; mode=block',

        // Referrer-Policy
        'referrer_policy' => 'strict-origin-when-cross-origin',

        // HSTS (Strict-Transport-Security)
        'hsts' => [
            'enabled' => env('HSTS_ENABLED', true),
            'max_age' => 31536000, // 1 年
            'include_subdomains' => true,
            'preload' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | 審計日誌設定
    |
    */

    'audit' => [
        // 啟用審計日誌
        'enabled' => env('AUDIT_LOGGING_ENABLED', true),

        // 需要記錄的事件
        'events' => [
            'authentication_failed',
            'authorization_failed',
            'validation_failed',
            'ip_blocked',
            'rate_limit_exceeded',
            'suspicious_activity',
        ],
    ],

];
