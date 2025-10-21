<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Function Not Found Exception
 * 
 * 當找不到指定的 API Function 時拋出此例外
 */
class FunctionNotFoundException extends Exception
{
    /**
     * HTTP 狀態碼
     */
    protected int $statusCode = 404;

    /**
     * 錯誤代碼
     */
    protected string $errorCode = 'FUNCTION_NOT_FOUND';

    /**
     * Function 識別碼
     */
    protected ?string $functionIdentifier;

    /**
     * 建構函數
     *
     * @param string $message 錯誤訊息
     * @param string|null $functionIdentifier Function 識別碼
     * @param string|null $errorCode 錯誤代碼
     */
    public function __construct(
        string $message = "找不到指定的 API Function",
        ?string $functionIdentifier = null,
        ?string $errorCode = null
    ) {
        parent::__construct($message);
        
        $this->functionIdentifier = $functionIdentifier;

        if ($errorCode) {
            $this->errorCode = $errorCode;
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
     * 取得 Function 識別碼
     *
     * @return string|null
     */
    public function getFunctionIdentifier(): ?string
    {
        return $this->functionIdentifier;
    }

    /**
     * 渲染例外為 HTTP 回應
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function render(Request $request): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        // 如果有 Function 識別碼，加入回應中
        if ($this->functionIdentifier) {
            $response['error']['function'] = $this->functionIdentifier;
        }

        return response()->json($response, $this->statusCode);
    }

    /**
     * 建立 Function 不存在例外
     *
     * @param string $identifier Function 識別碼
     * @return self
     */
    public static function withIdentifier(string $identifier): self
    {
        return new self(
            "找不到 API Function: {$identifier}",
            $identifier,
            'FUNCTION_NOT_FOUND'
        );
    }

    /**
     * 回傳錯誤回應資料（向後相容）
     *
     * @return array
     */
    public function toResponse(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ];
    }
}
