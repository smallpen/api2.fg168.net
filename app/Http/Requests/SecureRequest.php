<?php

namespace App\Http\Requests;

use App\Helpers\SecurityHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 安全請求基礎類別
 * 
 * 提供自動的輸入清理和安全驗證
 */
abstract class SecureRequest extends FormRequest
{
    /**
     * 是否自動清理輸入
     *
     * @var bool
     */
    protected $sanitizeInput = true;

    /**
     * 需要清理的欄位（空陣列表示清理所有欄位）
     *
     * @var array
     */
    protected $sanitizeFields = [];

    /**
     * 準備驗證資料
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->sanitizeInput) {
            $this->sanitizeRequestData();
        }
    }

    /**
     * 清理請求資料
     *
     * @return void
     */
    protected function sanitizeRequestData(): void
    {
        $data = $this->all();
        $sanitized = [];

        foreach ($data as $key => $value) {
            // 如果指定了要清理的欄位，只清理這些欄位
            if (!empty($this->sanitizeFields) && !in_array($key, $this->sanitizeFields)) {
                $sanitized[$key] = $value;
                continue;
            }

            // 清理字串值
            if (is_string($value)) {
                $sanitized[$key] = SecurityHelper::sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        $this->replace($sanitized);
    }

    /**
     * 遞迴清理陣列
     *
     * @param  array  $array
     * @return array
     */
    protected function sanitizeArray(array $array): array
    {
        $sanitized = [];

        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = SecurityHelper::sanitizeInput($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * 處理驗證失敗
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        // 記錄驗證失敗事件
        SecurityHelper::logSecurityEvent('validation_failed', [
            'url' => $this->fullUrl(),
            'method' => $this->method(),
            'errors' => $validator->errors()->toArray(),
            'ip_address' => $this->ip(),
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '參數驗證失敗',
                    'details' => $validator->errors(),
                ],
                'meta' => [
                    'request_id' => request()->header('X-Request-ID'),
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400)
        );
    }

    /**
     * 處理授權失敗
     *
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        // 記錄授權失敗事件
        SecurityHelper::logSecurityEvent('authorization_failed', [
            'url' => $this->fullUrl(),
            'method' => $this->method(),
            'ip_address' => $this->ip(),
        ]);

        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PERMISSION_DENIED',
                    'message' => '權限不足',
                ],
                'meta' => [
                    'request_id' => request()->header('X-Request-ID'),
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403)
        );
    }
}
