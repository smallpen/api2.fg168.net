<?php

use App\Helpers\AuthHelper;
use App\Models\ApiClient;
use Illuminate\Http\Request;

if (!function_exists('auth_client')) {
    /**
     * 取得已驗證的 API 客戶端
     * 
     * @param Request|null $request HTTP 請求物件
     * @return ApiClient|null 已驗證的客戶端
     */
    function auth_client(?Request $request = null): ?ApiClient
    {
        return AuthHelper::getAuthenticatedClient($request);
    }
}

if (!function_exists('auth_client_id')) {
    /**
     * 取得已驗證客戶端的 ID
     * 
     * @param Request|null $request HTTP 請求物件
     * @return int|null 客戶端 ID
     */
    function auth_client_id(?Request $request = null): ?int
    {
        return AuthHelper::getClientId($request);
    }
}

if (!function_exists('is_authenticated')) {
    /**
     * 檢查請求是否已驗證
     * 
     * @param Request|null $request HTTP 請求物件
     * @return bool 是否已驗證
     */
    function is_authenticated(?Request $request = null): bool
    {
        return AuthHelper::isAuthenticated($request);
    }
}
