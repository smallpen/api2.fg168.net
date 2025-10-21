<?php

namespace App\Services\Authentication;

use Exception;

/**
 * 驗證例外
 * 
 * 當驗證失敗時拋出此例外
 */
class AuthenticationException extends Exception
{
    /**
     * 錯誤代碼
     */
    protected string $errorCode;

    /**
     * 建構函數
     * 
     * @param string $errorCode 錯誤代碼
     * @param string $message 錯誤訊息
     * @param int $code HTTP 狀態碼
     */
    public function __construct(string $errorCode, string $message, int $code = 401)
    {
        parent::__construct($message, $code);
        $this->errorCode = $errorCode;
    }

    /**
     * 取得錯誤代碼
     * 
     * @return string 錯誤代碼
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 轉換為陣列格式
     * 
     * @return array 錯誤資訊陣列
     */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'http_status' => $this->getCode(),
        ];
    }
}
