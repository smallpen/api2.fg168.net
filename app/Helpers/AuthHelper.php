<?php

namespace App\Helpers;

use App\Models\ApiClient;
use Illuminate\Http\Request;

/**
 * 驗證輔助函數
 */
class AuthHelper
{
    /**
     * 從請求中取得已驗證的客戶端
     * 
     * @param Request|null $request HTTP 請求物件
     * @return ApiClient|null 已驗證的客戶端
     */
    public static function getAuthenticatedClient(?Request $request = null): ?ApiClient
    {
        // 如果沒有提供請求物件，嘗試從容器中取得
        if ($request === null) {
            // 檢查應用程式是否已啟動
            if (!app()->bound('request')) {
                return null;
            }
            $request = request();
        }

        // 從請求屬性中取得
        $client = $request->attributes->get('api_client');

        if ($client instanceof ApiClient) {
            return $client;
        }

        // 從請求資料中取得
        $client = $request->get('authenticated_client');

        if ($client instanceof ApiClient) {
            return $client;
        }

        return null;
    }

    /**
     * 檢查請求是否已驗證
     * 
     * @param Request|null $request HTTP 請求物件
     * @return bool 是否已驗證
     */
    public static function isAuthenticated(?Request $request = null): bool
    {
        return self::getAuthenticatedClient($request) !== null;
    }

    /**
     * 取得已驗證客戶端的 ID
     * 
     * @param Request|null $request HTTP 請求物件
     * @return int|null 客戶端 ID
     */
    public static function getClientId(?Request $request = null): ?int
    {
        $client = self::getAuthenticatedClient($request);

        return $client?->id;
    }

    /**
     * 取得已驗證客戶端的名稱
     * 
     * @param Request|null $request HTTP 請求物件
     * @return string|null 客戶端名稱
     */
    public static function getClientName(?Request $request = null): ?string
    {
        $client = self::getAuthenticatedClient($request);

        return $client?->name;
    }

    /**
     * 檢查已驗證客戶端是否有指定角色
     * 
     * @param string $roleName 角色名稱
     * @param Request|null $request HTTP 請求物件
     * @return bool 是否有該角色
     */
    public static function hasRole(string $roleName, ?Request $request = null): bool
    {
        $client = self::getAuthenticatedClient($request);

        return $client?->hasRole($roleName) ?? false;
    }

    /**
     * 檢查已驗證客戶端是否可以存取指定的 Function
     * 
     * @param int $functionId Function ID
     * @param Request|null $request HTTP 請求物件
     * @return bool 是否可以存取
     */
    public static function canAccessFunction(int $functionId, ?Request $request = null): bool
    {
        $client = self::getAuthenticatedClient($request);

        return $client?->canAccessFunction($functionId) ?? false;
    }
}
