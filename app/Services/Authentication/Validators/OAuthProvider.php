<?php

namespace App\Services\Authentication\Validators;

use App\Models\ApiClient;
use App\Services\Authentication\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * OAuth 提供者
 * 
 * 支援 OAuth 2.0 驗證
 */
class OAuthProvider
{
    /**
     * OAuth 提供者配置
     */
    protected array $providers = [];

    /**
     * 建構函數
     */
    public function __construct()
    {
        $this->providers = config('oauth.providers', []);
    }

    /**
     * 驗證請求中的 OAuth Token
     * 
     * @param Request $request HTTP 請求物件
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validate(Request $request): ApiClient
    {
        $token = $this->extractToken($request);

        if (!$token) {
            throw new AuthenticationException(
                'AUTHENTICATION_REQUIRED',
                '缺少 OAuth Token'
            );
        }

        return $this->validateOAuthToken($token);
    }

    /**
     * 從請求中提取 OAuth Token
     * 
     * @param Request $request HTTP 請求物件
     * @return string|null Token 字串
     */
    protected function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return null;
        }

        // 支援 "OAuth token" 和 "Bearer token" 格式
        if (str_starts_with($authHeader, 'OAuth ')) {
            return substr($authHeader, 6);
        }

        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return null;
    }

    /**
     * 驗證 OAuth Token
     * 
     * @param string $token OAuth Token 字串
     * @param string|null $provider OAuth 提供者名稱
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validateOAuthToken(string $token, ?string $provider = null): ApiClient
    {
        // 如果沒有指定提供者，嘗試所有已配置的提供者
        if (!$provider) {
            return $this->validateWithAllProviders($token);
        }

        // 驗證指定的提供者
        return $this->validateWithProvider($token, $provider);
    }

    /**
     * 使用所有提供者驗證 Token
     * 
     * @param string $token OAuth Token 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    protected function validateWithAllProviders(string $token): ApiClient
    {
        $lastException = null;

        foreach ($this->providers as $providerName => $config) {
            try {
                return $this->validateWithProvider($token, $providerName);
            } catch (AuthenticationException $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new AuthenticationException(
            'INVALID_CREDENTIALS',
            'OAuth Token 無效'
        );
    }

    /**
     * 使用指定提供者驗證 Token
     * 
     * @param string $token OAuth Token 字串
     * @param string $provider 提供者名稱
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    protected function validateWithProvider(string $token, string $provider): ApiClient
    {
        // 檢查提供者是否存在
        if (!isset($this->providers[$provider])) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                "OAuth 提供者 '{$provider}' 不存在"
            );
        }

        $config = $this->providers[$provider];

        // 驗證 Token
        $userInfo = $this->fetchUserInfo($token, $config);

        if (!$userInfo) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                'OAuth Token 無效或已過期'
            );
        }

        // 根據 OAuth 使用者資訊查找或建立客戶端
        return $this->findOrCreateClient($userInfo, $provider);
    }

    /**
     * 從 OAuth 提供者取得使用者資訊
     * 
     * @param string $token OAuth Token 字串
     * @param array $config 提供者配置
     * @return array|null 使用者資訊
     */
    protected function fetchUserInfo(string $token, array $config): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout($config['timeout'] ?? 10)
                ->get($config['user_info_url']);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 根據 OAuth 使用者資訊查找或建立客戶端
     * 
     * @param array $userInfo OAuth 使用者資訊
     * @param string $provider 提供者名稱
     * @return ApiClient 客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    protected function findOrCreateClient(array $userInfo, string $provider): ApiClient
    {
        // 從使用者資訊中提取唯一識別碼
        $oauthId = $userInfo['id'] ?? $userInfo['sub'] ?? null;

        if (!$oauthId) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                'OAuth 使用者資訊缺少唯一識別碼'
            );
        }

        // 建立唯一的 API Key（基於提供者和 OAuth ID）
        $apiKey = "oauth_{$provider}_{$oauthId}";

        // 查找現有客戶端
        $client = ApiClient::where('api_key', $apiKey)->first();

        if ($client) {
            // 檢查客戶端是否啟用
            if (!$client->isActive()) {
                throw new AuthenticationException(
                    'INVALID_CREDENTIALS',
                    '客戶端已停用'
                );
            }

            return $client;
        }

        // 建立新客戶端
        $client = ApiClient::create([
            'name' => $userInfo['name'] ?? $userInfo['email'] ?? "OAuth User {$oauthId}",
            'client_type' => ApiClient::TYPE_OAUTH,
            'api_key' => $apiKey,
            'secret' => ApiClient::generateSecret(),
            'is_active' => true,
            'rate_limit' => ApiClient::DEFAULT_RATE_LIMIT,
        ]);

        return $client;
    }

    /**
     * 取得授權 URL
     * 
     * @param string $provider 提供者名稱
     * @param string $redirectUri 重定向 URI
     * @param array $scopes 權限範圍
     * @return string 授權 URL
     * @throws AuthenticationException 提供者不存在時拋出
     */
    public function getAuthorizationUrl(string $provider, string $redirectUri, array $scopes = []): string
    {
        if (!isset($this->providers[$provider])) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                "OAuth 提供者 '{$provider}' 不存在"
            );
        }

        $config = $this->providers[$provider];

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes ?: $config['default_scopes'] ?? []),
            'state' => bin2hex(random_bytes(16)),
        ];

        return $config['authorization_url'] . '?' . http_build_query($params);
    }

    /**
     * 交換授權碼為 Access Token
     * 
     * @param string $provider 提供者名稱
     * @param string $code 授權碼
     * @param string $redirectUri 重定向 URI
     * @return array Token 資訊
     * @throws AuthenticationException 交換失敗時拋出
     */
    public function exchangeCodeForToken(string $provider, string $code, string $redirectUri): array
    {
        if (!isset($this->providers[$provider])) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                "OAuth 提供者 '{$provider}' 不存在"
            );
        }

        $config = $this->providers[$provider];

        try {
            $response = Http::asForm()->post($config['token_url'], [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if (!$response->successful()) {
                throw new AuthenticationException(
                    'INVALID_CREDENTIALS',
                    '無法交換授權碼為 Access Token'
                );
            }

            return $response->json();
        } catch (Exception $e) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                '無法交換授權碼為 Access Token: ' . $e->getMessage()
            );
        }
    }
}
