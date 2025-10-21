<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Validation Exception
 * 
 * 參數驗證失敗時拋出的例外
 */
class ValidationException extends Exception
{
    /**
     * HTTP 狀態碼
     */
    protected int $statusCode = 400;

    /**
     * 錯誤代碼
     */
    protected string $errorCode = 'VALIDATION_ERROR';

    /**
     * 驗證錯誤詳情
     *
     * @var array
     */
    protected array $errors = [];

    /**
     * 建構函數
     *
     * @param string $message 錯誤訊息
     * @param array $errors 驗證錯誤詳情
     * @param string|null $errorCode 錯誤代碼
     * @param int|null $statusCode HTTP 狀態碼
     */
    public function __construct(
        string $message = '參數驗證失敗',
        array $errors = [],
        ?string $errorCode = null,
        ?int $statusCode = null
    ) {
        parent::__construct($message);

        $this->errors = $errors;

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
     * 取得驗證錯誤詳情
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
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

        // 如果有驗證錯誤詳情，加入回應中
        if (!empty($this->errors)) {
            $response['error']['details'] = $this->errors;
        }

        return response()->json($response, $this->statusCode);
    }

    /**
     * 建立缺少必填參數例外
     *
     * @param string $parameter 參數名稱
     * @return self
     */
    public static function missingParameter(string $parameter): self
    {
        return new self(
            '缺少必填參數',
            [$parameter => ['此參數為必填']],
            'MISSING_PARAMETER',
            400
        );
    }

    /**
     * 建立參數類型錯誤例外
     *
     * @param string $parameter 參數名稱
     * @param string $expectedType 預期類型
     * @param string $actualType 實際類型
     * @return self
     */
    public static function invalidType(string $parameter, string $expectedType, string $actualType): self
    {
        return new self(
            '參數類型不正確',
            [$parameter => ["預期類型為 {$expectedType}，實際為 {$actualType}"]],
            'INVALID_TYPE',
            400
        );
    }

    /**
     * 建立參數格式錯誤例外
     *
     * @param string $parameter 參數名稱
     * @param string $format 預期格式
     * @return self
     */
    public static function invalidFormat(string $parameter, string $format): self
    {
        return new self(
            '參數格式不正確',
            [$parameter => ["參數格式應為 {$format}"]],
            'INVALID_FORMAT',
            400
        );
    }

    /**
     * 建立參數值超出範圍例外
     *
     * @param string $parameter 參數名稱
     * @param mixed $min 最小值
     * @param mixed $max 最大值
     * @return self
     */
    public static function outOfRange(string $parameter, $min = null, $max = null): self
    {
        $message = '參數值超出允許範圍';
        $errorMessage = [];

        if ($min !== null && $max !== null) {
            $errorMessage[] = "值必須介於 {$min} 和 {$max} 之間";
        } elseif ($min !== null) {
            $errorMessage[] = "值必須大於或等於 {$min}";
        } elseif ($max !== null) {
            $errorMessage[] = "值必須小於或等於 {$max}";
        }

        return new self(
            $message,
            [$parameter => $errorMessage],
            'OUT_OF_RANGE',
            400
        );
    }

    /**
     * 建立參數值不在允許列表中例外
     *
     * @param string $parameter 參數名稱
     * @param array $allowedValues 允許的值列表
     * @return self
     */
    public static function notInList(string $parameter, array $allowedValues): self
    {
        $valuesList = implode(', ', $allowedValues);
        
        return new self(
            '參數值不在允許列表中',
            [$parameter => ["值必須為以下其中之一: {$valuesList}"]],
            'NOT_IN_LIST',
            400
        );
    }

    /**
     * 建立多個驗證錯誤例外
     *
     * @param array $errors 錯誤詳情陣列
     * @return self
     */
    public static function withErrors(array $errors): self
    {
        return new self(
            '參數驗證失敗',
            $errors,
            'VALIDATION_ERROR',
            400
        );
    }

    /**
     * 建立 JSON 格式錯誤例外
     *
     * @param string $parameter 參數名稱
     * @return self
     */
    public static function invalidJson(string $parameter): self
    {
        return new self(
            'JSON 格式不正確',
            [$parameter => ['參數必須為有效的 JSON 格式']],
            'INVALID_JSON',
            400
        );
    }

    /**
     * 建立參數長度錯誤例外
     *
     * @param string $parameter 參數名稱
     * @param int|null $minLength 最小長度
     * @param int|null $maxLength 最大長度
     * @return self
     */
    public static function invalidLength(string $parameter, ?int $minLength = null, ?int $maxLength = null): self
    {
        $errorMessage = [];

        if ($minLength !== null && $maxLength !== null) {
            $errorMessage[] = "長度必須介於 {$minLength} 和 {$maxLength} 之間";
        } elseif ($minLength !== null) {
            $errorMessage[] = "長度必須至少為 {$minLength}";
        } elseif ($maxLength !== null) {
            $errorMessage[] = "長度不能超過 {$maxLength}";
        }

        return new self(
            '參數長度不正確',
            [$parameter => $errorMessage],
            'INVALID_LENGTH',
            400
        );
    }
}
