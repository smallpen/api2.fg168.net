<?php

namespace App\Services\Authentication\Validators;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Services\Authentication\AuthenticationException;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * Token 驗證器
 * 
 * 支援 JWT 和自訂 Token 的驗證
 */
class TokenValidator
{
    /**
     * JWT 密鑰
     */
    protected string $jwtSecret;

    /**
     * JWT 演算法
     */
    protected string $jwtAlgorithm = 'HS256';

    /**
     * 建構函數
     */
    public function __construct()
    {
        $this->jwtSecret = config('app.key');
    }

    /**
     * 驗證請求中的 Token
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
                '缺少 Bearer Token'
            );
        }

        return $this->validateToken($token);
    }

    /**
     * 從請求中提取 Token
     * 
     * @param Request $request HTTP 請求物件
     * @return string|null Token 字串
     */
    protected function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * 驗證 Token
     * 
     * 支援 JWT 和資料庫 Token 兩種方式
     * 
     * @param string $token Token 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    public function validateToken(string $token): ApiClient
    {
        // 首先嘗試作為 JWT 驗證
        try {
            return $this->validateJWT($token);
        } catch (Exception $e) {
            // JWT 驗證失敗，嘗試作為資料庫 Token 驗證
            return $this->validateDatabaseToken($token);
        }
    }

    /**
     * 驗證 JWT Token
     * 
     * @param string $token JWT Token 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    protected function validateJWT(string $token): ApiClient
    {
        try {
            // 解碼 JWT
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));

            // 檢查必要的欄位
            if (!isset($decoded->client_id)) {
                throw new AuthenticationException(
                    'INVALID_CREDENTIALS',
                    'JWT Token 格式不正確'
                );
            }

            // 查找客戶端
            $client = ApiClient::find($decoded->client_id);

            if (!$client) {
                throw new AuthenticationException(
                    'INVALID_CREDENTIALS',
                    '客戶端不存在'
                );
            }

            // 檢查客戶端是否啟用
            if (!$client->isActive()) {
                throw new AuthenticationException(
                    'INVALID_CREDENTIALS',
                    '客戶端已停用'
                );
            }

            return $client;

        } catch (Exception $e) {
            if ($e instanceof AuthenticationException) {
                throw $e;
            }

            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                'JWT Token 無效或已過期'
            );
        }
    }

    /**
     * 驗證資料庫 Token
     * 
     * @param string $token Token 字串
     * @return ApiClient 驗證成功的客戶端
     * @throws AuthenticationException 驗證失敗時拋出
     */
    protected function validateDatabaseToken(string $token): ApiClient
    {
        // 查找 Token
        $apiToken = ApiToken::findValidToken($token);

        if (!$apiToken) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                'Token 無效或已過期'
            );
        }

        // 更新最後使用時間
        $apiToken->updateLastUsed();

        // 取得客戶端
        $client = $apiToken->client;

        if (!$client) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                '客戶端不存在'
            );
        }

        // 檢查客戶端是否啟用
        if (!$client->isActive()) {
            throw new AuthenticationException(
                'INVALID_CREDENTIALS',
                '客戶端已停用'
            );
        }

        return $client;
    }

    /**
     * 生成 JWT Token
     * 
     * @param ApiClient $client 客戶端
     * @param int $expiresInHours Token 有效期（小時）
     * @return string JWT Token
     */
    public function generateJWT(ApiClient $client, int $expiresInHours = 24): string
    {
        $payload = [
            'iss' => config('app.url'),
            'iat' => time(),
            'exp' => time() + ($expiresInHours * 3600),
            'client_id' => $client->id,
            'client_name' => $client->name,
            'client_type' => $client->client_type,
        ];

        return JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);
    }

    /**
     * 解碼 JWT Token（不驗證）
     * 
     * @param string $token JWT Token 字串
     * @return object|null 解碼後的資料
     */
    public function decodeJWT(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgorithm));
        } catch (Exception $e) {
            return null;
        }
    }
}
