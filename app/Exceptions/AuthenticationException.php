<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authentication Exception
 * 
 * 驗證失敗時拋出的例外
 */
class AuthenticationException extends Exception
{
    /**
     * HTTP 狀態碼
     */
    protected int $statusCode = 401;

    /**
     * 錯誤代碼
     */
    protected string $errorCode = 'AUTHENTICATION_REQUIRED';

    /**
     * 建構函數
     *
     * @param string $message 錯誤訊息
     * @param string|null $errorCode 錯誤代碼
     * @param int|null $statusCode HTTP 狀態碼
     */
    public function __construct(
        string $message = '需要驗證才能存取此資源',
        ?string $errorCode = null,
        ?int $statusCode = null
    ) {
        parent::__construct($message);

        if ($errorCode) {
            $this->errorCode = $errorCode;
        }

        if ($statusCode) {
            $this->statusCode = $statusCode;
        }
    }

    /**
     * 取得 HTTP 狀態碼
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 取得錯誤代碼
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 渲染例外為 HTTP 回應
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ], $this->statusCode);
    }

    /**
     * 建立缺少驗證憑證例外
     *
     * @return self
     */
    public static function missingCredentials(): self
    {
        return new self(
            '請求缺少驗證憑證',
            'AUTHENTICATION_REQUIRED',
            401
        );
    }

    /**
     * 建立無效憑證例外
     *
     * @return self
     */
    public static function invalidCredentials(): self
    {
        return new self(
            '驗證憑證無效',
            'INVALID_CREDENTIALS',
            401
        );
    }

    /**
     * 建立 Token 過期例外
     *
     * @return self
     */
    public static function tokenExpired(): self
    {
        return new self(
            'Token 已過期',
            'TOKEN_EXPIRED',
            401
        );
    }

    /**
     * 建立 Token 無效例外
     *
     * @return self
     */
    public static function invalidToken(): self
    {
        return new self(
            'Token 無效',
            'INVALID_TOKEN',
            401
        );
    }

    /**
     * 建立 API Key 無效例外
     *
     * @return self
     */
    public static function invalidApiKey(): self
    {
        return new self(
            'API Key 無效',
            'INVALID_API_KEY',
            401
        );
    }

    /**
     * 建立客戶端未找到例外
     *
     * @return self
     */
    public static function clientNotFound(): self
    {
        return new self(
            '找不到指定的客戶端',
            'CLIENT_NOT_FOUND',
            401
        );
    }

    /**
     * 建立客戶端已停用例外
     *
     * @return self
     */
    public static function clientDisabled(): self
    {
        return new self(
            '客戶端已停用',
            'CLIENT_DISABLED',
            401
        );
    }

    /**
     * 建立 Token 已撤銷例外
     *
     * @return self
     */
    public static function tokenRevoked(): self
    {
        return new self(
            'Token 已被撤銷',
            'TOKEN_REVOKED',
            401
        );
    }
}
