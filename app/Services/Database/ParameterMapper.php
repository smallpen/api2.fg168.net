<?php

namespace App\Services\Database;

use Carbon\Carbon;
use Exception;

/**
 * 參數映射器
 * 
 * 負責將 API 參數映射到 Stored Procedure 參數格式
 */
class ParameterMapper
{
    /**
     * 映射參數陣列
     *
     * @param array $parameters 原始參數陣列
     * @param array|null $parameterDefinitions 參數定義（來自 function_parameters）
     * @return array 映射後的參數陣列
     * @throws Exception
     */
    public function map(array $parameters, ?array $parameterDefinitions = null): array
    {
        $mapped = [];
        
        if ($parameterDefinitions) {
            // 根據參數定義進行映射
            foreach ($parameterDefinitions as $definition) {
                $apiName = $definition['name'];
                $spName = $definition['sp_parameter_name'] ?? $apiName;
                $dataType = $definition['data_type'];
                $isRequired = $definition['is_required'] ?? false;
                $defaultValue = $definition['default_value'] ?? null;
                
                // 取得參數值
                $value = $parameters[$apiName] ?? $defaultValue;
                
                // 檢查必填參數
                if ($isRequired && $value === null) {
                    throw new Exception("必填參數 '{$apiName}' 缺少值");
                }
                
                // 轉換資料類型
                $convertedValue = $this->convertType($value, $dataType);
                
                $mapped[] = [
                    'name' => $spName,
                    'value' => $convertedValue,
                    'type' => $dataType,
                    'position' => $definition['position'] ?? count($mapped)
                ];
            }
            
            // 按位置排序
            usort($mapped, fn($a, $b) => $a['position'] <=> $b['position']);
            
        } else {
            // 沒有參數定義時，直接映射
            $position = 0;
            foreach ($parameters as $name => $value) {
                $mapped[] = [
                    'name' => $name,
                    'value' => $value,
                    'type' => $this->detectType($value),
                    'position' => $position++
                ];
            }
        }
        
        return $mapped;
    }

    /**
     * 轉換資料類型
     *
     * @param mixed $value 原始值
     * @param string $targetType 目標資料類型
     * @return mixed 轉換後的值
     * @throws Exception
     */
    protected function convertType($value, string $targetType)
    {
        if ($value === null) {
            return null;
        }
        
        try {
            return match($targetType) {
                'string', 'varchar', 'text', 'char' => (string) $value,
                'integer', 'int', 'bigint', 'smallint', 'tinyint' => (int) $value,
                'float', 'double', 'decimal', 'numeric' => (float) $value,
                'boolean', 'bool', 'bit' => $this->convertToBoolean($value),
                'date' => $this->convertToDate($value),
                'datetime', 'timestamp' => $this->convertToDateTime($value),
                'json' => $this->convertToJson($value),
                'array' => $this->convertToArray($value),
                default => $value,
            };
        } catch (Exception $e) {
            throw new Exception("無法將值轉換為類型 '{$targetType}': {$e->getMessage()}");
        }
    }

    /**
     * 轉換為布林值
     *
     * @param mixed $value 原始值
     * @return bool
     */
    protected function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower($value);
            return in_array($lower, ['true', '1', 'yes', 'on']);
        }
        
        return (bool) $value;
    }

    /**
     * 轉換為日期格式
     *
     * @param mixed $value 原始值
     * @return string 日期字串 (Y-m-d)
     * @throws Exception
     */
    protected function convertToDate($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }
        
        if (is_string($value) || is_numeric($value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Exception $e) {
                throw new Exception("無效的日期格式: {$value}");
            }
        }
        
        throw new Exception("無法轉換為日期: " . gettype($value));
    }

    /**
     * 轉換為日期時間格式
     *
     * @param mixed $value 原始值
     * @return string 日期時間字串 (Y-m-d H:i:s)
     * @throws Exception
     */
    protected function convertToDateTime($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }
        
        if (is_string($value) || is_numeric($value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                throw new Exception("無效的日期時間格式: {$value}");
            }
        }
        
        throw new Exception("無法轉換為日期時間: " . gettype($value));
    }

    /**
     * 轉換為 JSON 字串
     *
     * @param mixed $value 原始值
     * @return string JSON 字串
     * @throws Exception
     */
    protected function convertToJson($value): string
    {
        if (is_string($value)) {
            // 驗證是否為有效的 JSON
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }
        
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                throw new Exception("無法編碼為 JSON: " . json_last_error_msg());
            }
            return $json;
        }
        
        throw new Exception("無法轉換為 JSON: " . gettype($value));
    }

    /**
     * 轉換為陣列
     *
     * @param mixed $value 原始值
     * @return array
     * @throws Exception
     */
    protected function convertToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            // 嘗試解析 JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            
            // 嘗試以逗號分隔
            return array_map('trim', explode(',', $value));
        }
        
        if (is_object($value)) {
            return (array) $value;
        }
        
        return [$value];
    }

    /**
     * 自動偵測值的資料類型
     *
     * @param mixed $value 值
     * @return string 資料類型
     */
    protected function detectType($value): string
    {
        if ($value === null) {
            return 'null';
        }
        
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_int($value)) {
            return 'integer';
        }
        
        if (is_float($value)) {
            return 'float';
        }
        
        if (is_array($value)) {
            return 'array';
        }
        
        if (is_string($value)) {
            // 嘗試偵測特殊格式
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return 'date';
            }
            
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                return 'datetime';
            }
            
            // 檢查是否為 JSON
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return 'json';
            }
            
            return 'string';
        }
        
        return 'string';
    }

    /**
     * 驗證參數值
     *
     * @param mixed $value 參數值
     * @param array $definition 參數定義
     * @return bool
     * @throws Exception
     */
    public function validate($value, array $definition): bool
    {
        $isRequired = $definition['is_required'] ?? false;
        $dataType = $definition['data_type'] ?? 'string';
        $validationRules = $definition['validation_rules'] ?? [];
        
        // 檢查必填
        if ($isRequired && ($value === null || $value === '')) {
            throw new Exception("參數 '{$definition['name']}' 為必填");
        }
        
        // 如果值為 null 且非必填，則通過驗證
        if ($value === null && !$isRequired) {
            return true;
        }
        
        // 檢查資料類型
        $this->validateType($value, $dataType);
        
        // 執行額外的驗證規則
        if (!empty($validationRules)) {
            $this->validateRules($value, $validationRules, $definition['name']);
        }
        
        return true;
    }

    /**
     * 驗證資料類型
     *
     * @param mixed $value 值
     * @param string $expectedType 預期類型
     * @throws Exception
     */
    protected function validateType($value, string $expectedType): void
    {
        $actualType = $this->detectType($value);
        
        // 類型別名映射
        $typeAliases = [
            'int' => 'integer',
            'bool' => 'boolean',
            'varchar' => 'string',
            'text' => 'string',
            'char' => 'string',
        ];
        
        $normalizedExpected = $typeAliases[$expectedType] ?? $expectedType;
        $normalizedActual = $typeAliases[$actualType] ?? $actualType;
        
        if ($normalizedExpected !== $normalizedActual && $normalizedActual !== 'null') {
            // 嘗試轉換
            try {
                $this->convertType($value, $expectedType);
            } catch (Exception $e) {
                throw new Exception("資料類型不符：預期 {$expectedType}，實際 {$actualType}");
            }
        }
    }

    /**
     * 驗證規則
     *
     * @param mixed $value 值
     * @param array $rules 驗證規則陣列
     * @param string $fieldName 欄位名稱
     * @throws Exception
     */
    protected function validateRules($value, array $rules, string $fieldName): void
    {
        foreach ($rules as $rule => $ruleValue) {
            match($rule) {
                'min' => $this->validateMin($value, $ruleValue, $fieldName),
                'max' => $this->validateMax($value, $ruleValue, $fieldName),
                'regex' => $this->validateRegex($value, $ruleValue, $fieldName),
                'in' => $this->validateIn($value, $ruleValue, $fieldName),
                'email' => $this->validateEmail($value, $fieldName),
                'url' => $this->validateUrl($value, $fieldName),
                default => null,
            };
        }
    }

    /**
     * 驗證最小值/長度
     */
    protected function validateMin($value, $min, string $fieldName): void
    {
        if (is_numeric($value) && $value < $min) {
            throw new Exception("{$fieldName} 必須大於或等於 {$min}");
        }
        
        if (is_string($value) && mb_strlen($value) < $min) {
            throw new Exception("{$fieldName} 長度必須至少 {$min} 個字元");
        }
    }

    /**
     * 驗證最大值/長度
     */
    protected function validateMax($value, $max, string $fieldName): void
    {
        if (is_numeric($value) && $value > $max) {
            throw new Exception("{$fieldName} 必須小於或等於 {$max}");
        }
        
        if (is_string($value) && mb_strlen($value) > $max) {
            throw new Exception("{$fieldName} 長度不能超過 {$max} 個字元");
        }
    }

    /**
     * 驗證正規表達式
     */
    protected function validateRegex($value, string $pattern, string $fieldName): void
    {
        if (!preg_match($pattern, $value)) {
            throw new Exception("{$fieldName} 格式不正確");
        }
    }

    /**
     * 驗證值是否在允許的列表中
     */
    protected function validateIn($value, array $allowed, string $fieldName): void
    {
        if (!in_array($value, $allowed, true)) {
            $allowedStr = implode(', ', $allowed);
            throw new Exception("{$fieldName} 必須是以下值之一：{$allowedStr}");
        }
    }

    /**
     * 驗證電子郵件格式
     */
    protected function validateEmail($value, string $fieldName): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("{$fieldName} 必須是有效的電子郵件地址");
        }
    }

    /**
     * 驗證 URL 格式
     */
    protected function validateUrl($value, string $fieldName): void
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new Exception("{$fieldName} 必須是有效的 URL");
        }
    }
}
