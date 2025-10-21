<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Function Error Mapping Model
 * 
 * 代表 API Function 的錯誤映射規則，定義如何將 Stored Procedure 錯誤轉換為 HTTP 回應
 */
class FunctionErrorMapping extends Model
{
    use HasFactory;

    protected $table = 'function_error_mappings';

    protected $fillable = [
        'function_id',
        'error_code',
        'http_status',
        'error_message',
    ];

    protected $casts = [
        'http_status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 常用的 HTTP 狀態碼
     */
    public const HTTP_STATUS_CODES = [
        200 => 'OK',
        201 => 'Created',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /**
     * 取得此錯誤映射所屬的 API Function
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(ApiFunction::class, 'function_id');
    }

    /**
     * 取得 HTTP 狀態碼的描述
     */
    public function getHttpStatusDescription(): string
    {
        return self::HTTP_STATUS_CODES[$this->http_status] ?? 'Unknown Status';
    }

    /**
     * 檢查是否為成功狀態碼 (2xx)
     */
    public function isSuccessStatus(): bool
    {
        return $this->http_status >= 200 && $this->http_status < 300;
    }

    /**
     * 檢查是否為客戶端錯誤 (4xx)
     */
    public function isClientError(): bool
    {
        return $this->http_status >= 400 && $this->http_status < 500;
    }

    /**
     * 檢查是否為伺服器錯誤 (5xx)
     */
    public function isServerError(): bool
    {
        return $this->http_status >= 500 && $this->http_status < 600;
    }

    /**
     * 建立錯誤回應陣列
     */
    public function toErrorResponse(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->error_code,
                'message' => $this->error_message,
                'http_status' => $this->http_status,
            ],
        ];
    }

    /**
     * 驗證 HTTP 狀態碼是否有效
     */
    public static function isValidHttpStatus(int $status): bool
    {
        return $status >= 100 && $status < 600;
    }

    /**
     * 根據錯誤碼查找映射
     */
    public static function findByErrorCode(int $functionId, string $errorCode): ?self
    {
        return self::where('function_id', $functionId)
            ->where('error_code', $errorCode)
            ->first();
    }
}
