<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authorization Exception
 * 
 * 授權失敗時拋出的例外
 */
class AuthorizationException extends Exception
{
    /**
     * HTTP 狀態碼
     */
    protected int $statusCode = 403;

    /**
     * 錯誤代碼
     */
    protected string $errorCode = 'PERMISSION_DENIED';

    /**
     * 建構函數
     */
    public function __construct(
        string $message = '權限不足',
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
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 取得錯誤代碼
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 渲染例外為 HTTP 回應
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
     * 建立 Function 未啟用例外
     */
    public static function functionDisabled(string $functionName): self
    {
        return new self(
            "API Function '{$functionName}' 已停用",
            'FUNCTION_DISABLED',
            403
        );
    }

    /**
     * 建立客戶端未啟用例外
     */
    public static function clientDisabled(): self
    {
        return new self(
            '客戶端已停用',
            'CLIENT_DISABLED',
            403
        );
    }

    /**
     * 建立無權限存取 Function 例外
     */
    public static function noFunctionAccess(string $functionName): self
    {
        return new self(
            "無權限存取 API Function '{$functionName}'",
            'PERMISSION_DENIED',
            403
        );
    }

    /**
     * 建立無權限執行動作例外
     */
    public static function noActionPermission(string $action, string $resource): self
    {
        return new self(
            "無權限執行 '{$action}' 動作於 '{$resource}'",
            'PERMISSION_DENIED',
            403
        );
    }
}
