<?php

namespace App\Services\Authentication\Validators;

use App\Models\ApiClient;
use App\Services\Authentication\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * API Key 驗證器
 * 
 * 支援 API Key 的驗證
 */
class ApiKeyValidator
{
    /**
     * 驗證請求中的 API Key
     * 
     * @param Request $request HTTP 請求物件
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validate(Request $request): ApiClient
    {
        $apiKey = $this->extractApiKey($request);

        if (!$apiKey) {
            throw new AuthenticationException(
                'AUTHENTICATION_REQUIRED',
                '缺少 API Key'
            );
        }

        return $this->validateApiKey($apiKey);
    }

    /**
     * 從請求中提取 API Key
     * 
     * 支援從標頭或查詢參數中提取
     * 
     * @param Request $request HTTP 請求物件
     * @return string|null API Key 字串
     */
    protected function extractApiKey(Request $request): ?string
    {
        // 優先從標頭中取得
        $apiKey = $request->header('X-API-Key');

        // 如果標頭中沒有，嘗試從查詢參數中取得
        if (!$apiKey) {
            $apiKey = $request->query('api_key');
        }

        return $apiKey;
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
        // 查找客戶端
        $client = ApiClient::findByApiKey($apiKey);

        if (!$client) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                'API Key 無效'
            );
        }

        // 檢查客戶端是否啟用
        if (!$client->isActive()) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                '客戶端已停用'
            );
        }

        // 檢查客戶端類型
        if ($client->client_type !== ApiClient::TYPE_API_KEY) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                '此客戶端不支援 API Key 驗證'
            );
        }

        return $client;
    }

    /**
     * 驗證 API Key 和 Secret
     * 
     * 用於需要雙重驗證的場景
     * 
     * @param string $apiKey API Key 字串
     * @param string $secret Secret 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validateApiKeyWithSecret(string $apiKey, string $secret): ApiClient
    {
        // 先驗證 API Key
        $client = $this->validateApiKey($apiKey);

        // 驗證 Secret
        if (!Hash::check($secret, $client->secret)) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                'Secret 不正確'
            );
        }

        return $client;
    }

    /**
     * 檢查 API Key 格式是否正確
     * 
     * @param string $apiKey API Key 字串
     * @return bool 格式是否正確
     */
    public function isValidFormat(string $apiKey): bool
    {
        // API Key 應該以 'ak_' 開頭，後面跟著 32 個字元
        return preg_match('/^ak_[a-zA-Z0-9]{32}$/', $apiKey) === 1;
    }

    /**
     * 生成新的 API Key
     * 
     * @return string 新的 API Key
     */
    public function generateApiKey(): string
    {
        return ApiClient::generateApiKey();
    }
}
