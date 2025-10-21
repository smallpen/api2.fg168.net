<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth 提供者配置
    |--------------------------------------------------------------------------
    |
    | 這裡配置支援的 OAuth 2.0 提供者
    |
    */

    'providers' => [
        // Google OAuth 2.0
        'google' => [
            'client_id' => env('OAUTH_GOOGLE_CLIENT_ID'),
            'client_secret' => env('OAUTH_GOOGLE_CLIENT_SECRET'),
            'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'user_info_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
            'default_scopes' => ['openid', 'profile', 'email'],
            'timeout' => 10,
        ],

        // GitHub OAuth 2.0
        'github' => [
            'client_id' => env('OAUTH_GITHUB_CLIENT_ID'),
            'client_secret' => env('OAUTH_GITHUB_CLIENT_SECRET'),
            'authorization_url' => 'https://github.com/login/oauth/authorize',
            'token_url' => 'https://github.com/login/oauth/access_token',
            'user_info_url' => 'https://api.github.com/user',
            'default_scopes' => ['user:email'],
            'timeout' => 10,
        ],

        // Microsoft OAuth 2.0
        'microsoft' => [
            'client_id' => env('OAUTH_MICROSOFT_CLIENT_ID'),
            'client_secret' => env('OAUTH_MICROSOFT_CLIENT_SECRET'),
            'authorization_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'user_info_url' => 'https://graph.microsoft.com/v1.0/me',
            'default_scopes' => ['openid', 'profile', 'email'],
            'timeout' => 10,
        ],
    ],
];
