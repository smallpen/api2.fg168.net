<?php

namespace App\Services\RateLimit;

use Exception;

/**
 * Rate Limit 例外類別
 * 
 * 當客戶端超過速率限制時拋出此例外
 */
class RateLimitException extends Exception
{
    /**
     * 錯誤代碼
     */
    protected string $errorCode;

    /**
     * 剩餘請求次數
     */
    protected int $remaining;

    /**
     * 重試時間（秒）
     */
    protected int $retryAfter;

    /**
     * 建構函數
     * 
     * @param string $message 錯誤訊息
     * @param int $remaining 剩餘請求次數
     * @param int $retryAfter 重試時間（秒）
     * @param string $errorCode 錯誤代碼
     * @param int $code HTTP 狀態碼
     */
    public function __construct(
        string $message = '超過請求頻率限制',
        int $remaining = 0,
        int $retryAfter = 60,
        string $errorCode = 'RATE_LIMIT_EXCEEDED',
        int $code = 429
    ) {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
        $this->remaining = $remaining;
        $this->retryAfter = $retryAfter;
    }

    /**
     * 獲取錯誤代碼
     * 
     * @return string 錯誤代碼
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 獲取剩餘請求次數
     * 
     * @return int 剩餘請求次數
     */
    public function getRemaining(): int
    {
        return $this->remaining;
    }

    /**
     * 獲取重試時間
     * 
     * @return int 重試時間（秒）
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
