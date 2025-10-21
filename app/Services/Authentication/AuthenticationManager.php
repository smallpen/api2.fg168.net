<?php

namespace App\Services\Authentication;

use App\Models\ApiClient;
use App\Services\Authentication\Validators\TokenValidator;
use App\Services\Authentication\Validators\ApiKeyValidator;
use App\Services\Authentication\Validators\OAuthProvider;
use Illuminate\Http\Request;

/**
 * 驗證管理器
 * 
 * 負責協調不同的驗證方式，自動偵測並執行適當的驗證邏輯
 */
class AuthenticationManager
{
    /**
     * Token 驗證器
     */
    protected TokenValidator $tokenValidator;

    /**
     * API Key 驗證器
     */
    protected ApiKeyValidator $apiKeyValidator;

    /**
     * OAuth 提供者
     */
    protected OAuthProvider $oauthProvider;

    /**
     * 建構函數
     */
    public function __construct(
        TokenValidator $tokenValidator,
        ApiKeyValidator $apiKeyValidator,
        OAuthProvider $oauthProvider
    ) {
        $this->tokenValidator = $tokenValidator;
        $this->apiKeyValidator = $apiKeyValidator;
        $this->oauthProvider = $oauthProvider;
    }

    /**
     * 驗證請求
     * 
     * 自動偵測驗證方式並執行驗證
     * 
     * @param Request $request HTTP 請求物件
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function authenticate(Request $request): ApiClient
    {
        // 嘗試從請求中偵測驗證方式
        $authenticationType = $this->detectAuthenticationType($request);

        // 根據驗證方式執行對應的驗證邏輯
        return match ($authenticationType) {
            'bearer' => $this->tokenValidator->validate($request),
            'api_key' => $this->apiKeyValidator->validate($request),
            'oauth' => $this->oauthProvider->validate($request),
            default => throw new AuthenticationException('AUTHENTICATION_REQUIRED', '缺少驗證憑證'),
        };
    }

    /**
     * 偵測驗證類型
     * 
     * 根據請求標頭判斷使用哪種驗證方式
     * 
     * @param Request $request HTTP 請求物件
     * @return string|null 驗證類型
     */
    protected function detectAuthenticationType(Request $request): ?string
    {
        // 檢查 Bearer Token
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return 'bearer';
        }

        // 檢查 API Key
        if ($request->header('X-API-Key')) {
            return 'api_key';
        }

        // 檢查 OAuth
        if ($authHeader && str_starts_with($authHeader, 'OAuth ')) {
            return 'oauth';
        }

        return null;
    }

    /**
     * 驗證 Bearer Token
     * 
     * @param string $token Token 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validateBearerToken(string $token): ApiClient
    {
        return $this->tokenValidator->validateToken($token);
    }

    /**
     * 驗證 API Key
     * 
     * @param string $apiKey API Key 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validateApiKey(string $apiKey): ApiClient
    {
        return $this->apiKeyValidator->validateApiKey($apiKey);
    }

    /**
     * 驗證 OAuth Token
     * 
     * @param string $token OAuth Token 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validateOAuthToken(string $token): ApiClient
    {
        return $this->oauthProvider->validateOAuthToken($token);
    }

    /**
     * 檢查客戶端是否已驗證
     * 
     * @param Request $request HTTP 請求物件
     * @return bool 是否已驗證
     */
    public function check(Request $request): bool
    {
        try {
            $this->authenticate($request);
            return true;
        } catch (AuthenticationException $e) {
            return false;
        }
    }

    /**
     * 取得已驗證的客戶端
     * 
     * @param Request $request HTTP 請求物件
     * @return ApiClient|null 已驗證的客戶端，未驗證則返回 null
     */
    public function getClient(Request $request): ?ApiClient
    {
        try {
            return $this->authenticate($request);
        } catch (AuthenticationException $e) {
            return null;
        }
    }
}
