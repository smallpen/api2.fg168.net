<?php

namespace App\Services\Database;

use Carbon\Carbon;
use Exception;

/**
 * 結果轉換器
 * 
 * 負責將資料庫查詢結果轉換為 JSON 格式，並進行資料類型轉換和欄位映射
 */
class ResultTransformer
{
    /**
     * 轉換查詢結果為 JSON 格式
     *
     * @param array $result 資料庫查詢結果
     * @param array|null $responseMapping 回應映射配置（來自 function_responses）
     * @return array 轉換後的結果
     */
    public function transform(array $result, ?array $responseMapping = null): array
    {
        if (empty($result)) {
            return [];
        }
        
        // 如果沒有映射配置，直接轉換資料類型
        if (!$responseMapping) {
            return $this->transformWithoutMapping($result);
        }
        
        // 根據映射配置轉換
        return $this->transformWithMapping($result, $responseMapping);
    }

    /**
     * 不使用映射配置的轉換
     *
     * @param array $result 查詢結果
     * @return array
     */
    protected function transformWithoutMapping(array $result): array
    {
        $transformed = [];
        
        foreach ($result as $row) {
            $transformedRow = [];
            
            foreach ($row as $column => $value) {
                $transformedRow[$column] = $this->convertValue($value);
            }
            
            $transformed[] = $transformedRow;
        }
        
        return $transformed;
    }

    /**
     * 使用映射配置的轉換
     *
     * @param array $result 查詢結果
     * @param array $responseMapping 回應映射配置
     * @return array
     */
    protected function transformWithMapping(array $result, array $responseMapping): array
    {
        $transformed = [];
        
        foreach ($result as $row) {
            $transformedRow = [];
            
            foreach ($responseMapping as $mapping) {
                $fieldName = $mapping['field_name'];
                $spColumnName = $mapping['sp_column_name'] ?? $fieldName;
                $dataType = $mapping['data_type'] ?? 'string';
                $transformRule = $mapping['transform_rule'] ?? null;
                
                // 取得原始值
                $value = $row[$spColumnName] ?? null;
                
                // 應用轉換規則
                if ($transformRule) {
                    $value = $this->applyTransformRule($value, $transformRule);
                }
                
                // 轉換資料類型
                $transformedRow[$fieldName] = $this->convertValueToType($value, $dataType);
            }
            
            $transformed[] = $transformedRow;
        }
        
        return $transformed;
    }

    /**
     * 轉換單一結果（單筆記錄）
     *
     * @param array $row 單筆記錄
     * @param array|null $responseMapping 回應映射配置
     * @return array
     */
    public function transformSingle(array $row, ?array $responseMapping = null): array
    {
        if (empty($row)) {
            return [];
        }
        
        $result = $this->transform([$row], $responseMapping);
        
        return $result[0] ?? [];
    }

    /**
     * 轉換值（自動偵測類型）
     *
     * @param mixed $value 原始值
     * @return mixed 轉換後的值
     */
    protected function convertValue($value)
    {
        if ($value === null) {
            return null;
        }
        
        // 如果是數字字串，轉換為數字
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        
        // 如果是布林值字串
        if (in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }
        
        // 如果是日期時間格式
        if ($this->isDateTime($value)) {
            return $value; // 保持字串格式，或可以轉換為 ISO 8601
        }
        
        // 如果是 JSON 字串
        if ($this->isJson($value)) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * 轉換值到指定類型
     *
     * @param mixed $value 原始值
     * @param string $type 目標類型
     * @return mixed 轉換後的值
     */
    protected function convertValueToType($value, string $type)
    {
        if ($value === null) {
            return null;
        }
        
        try {
            return match($type) {
                'string', 'varchar', 'text' => (string) $value,
                'integer', 'int', 'bigint' => (int) $value,
                'float', 'double', 'decimal' => (float) $value,
                'boolean', 'bool' => $this->toBoolean($value),
                'date' => $this->toDateString($value),
                'datetime', 'timestamp' => $this->toDateTimeString($value),
                'json', 'object' => $this->toJson($value),
                'array' => $this->toArray($value),
                default => $value,
            };
        } catch (Exception $e) {
            // 如果轉換失敗，返回原始值
            return $value;
        }
    }

    /**
     * 轉換為布林值
     *
     * @param mixed $value 原始值
     * @return bool
     */
    protected function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        if (is_string($value)) {
            $lower = strtolower($value);
            return in_array($lower, ['true', '1', 'yes', 'on', 'y']);
        }
        
        return (bool) $value;
    }

    /**
     * 轉換為日期字串
     *
     * @param mixed $value 原始值
     * @return string
     */
    protected function toDateString($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }
        
        if (is_string($value) || is_numeric($value)) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Exception $e) {
                return (string) $value;
            }
        }
        
        return (string) $value;
    }

    /**
     * 轉換為日期時間字串
     *
     * @param mixed $value 原始值
     * @return string
     */
    protected function toDateTimeString($value): string
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }
        
        if (is_string($value) || is_numeric($value)) {
            try {
                return Carbon::parse($value)->toIso8601String();
            } catch (Exception $e) {
                return (string) $value;
            }
        }
        
        return (string) $value;
    }

    /**
     * 轉換為 JSON
     *
     * @param mixed $value 原始值
     * @return mixed
     */
    protected function toJson($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        
        return $value;
    }

    /**
     * 轉換為陣列
     *
     * @param mixed $value 原始值
     * @return array
     */
    protected function toArray($value): array
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
     * 應用轉換規則
     *
     * @param mixed $value 原始值
     * @param string|array $rule 轉換規則
     * @return mixed 轉換後的值
     */
    protected function applyTransformRule($value, $rule)
    {
        if (is_string($rule)) {
            return $this->applyStringRule($value, $rule);
        }
        
        if (is_array($rule)) {
            return $this->applyArrayRule($value, $rule);
        }
        
        return $value;
    }

    /**
     * 應用字串規則
     *
     * @param mixed $value 原始值
     * @param string $rule 規則字串
     * @return mixed
     */
    protected function applyStringRule($value, string $rule)
    {
        return match($rule) {
            'uppercase' => strtoupper($value),
            'lowercase' => strtolower($value),
            'trim' => trim($value),
            'strip_tags' => strip_tags($value),
            'url_encode' => urlencode($value),
            'url_decode' => urldecode($value),
            'base64_encode' => base64_encode($value),
            'base64_decode' => base64_decode($value),
            'md5' => md5($value),
            'sha1' => sha1($value),
            default => $value,
        };
    }

    /**
     * 應用陣列規則（複雜規則）
     *
     * @param mixed $value 原始值
     * @param array $rule 規則陣列
     * @return mixed
     */
    protected function applyArrayRule($value, array $rule)
    {
        $type = $rule['type'] ?? null;
        
        return match($type) {
            'replace' => $this->applyReplaceRule($value, $rule),
            'format' => $this->applyFormatRule($value, $rule),
            'concat' => $this->applyConcatRule($value, $rule),
            'split' => $this->applySplitRule($value, $rule),
            'map' => $this->applyMapRule($value, $rule),
            default => $value,
        };
    }

    /**
     * 應用替換規則
     */
    protected function applyReplaceRule($value, array $rule)
    {
        $search = $rule['search'] ?? '';
        $replace = $rule['replace'] ?? '';
        
        return str_replace($search, $replace, $value);
    }

    /**
     * 應用格式化規則
     */
    protected function applyFormatRule($value, array $rule)
    {
        $format = $rule['format'] ?? '%s';
        
        return sprintf($format, $value);
    }

    /**
     * 應用串接規則
     */
    protected function applyConcatRule($value, array $rule)
    {
        $prefix = $rule['prefix'] ?? '';
        $suffix = $rule['suffix'] ?? '';
        
        return $prefix . $value . $suffix;
    }

    /**
     * 應用分割規則
     */
    protected function applySplitRule($value, array $rule)
    {
        $delimiter = $rule['delimiter'] ?? ',';
        
        return explode($delimiter, $value);
    }

    /**
     * 應用映射規則
     */
    protected function applyMapRule($value, array $rule)
    {
        $mapping = $rule['mapping'] ?? [];
        
        return $mapping[$value] ?? $value;
    }

    /**
     * 檢查字串是否為日期時間格式
     *
     * @param mixed $value 值
     * @return bool
     */
    protected function isDateTime($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        // 常見的日期時間格式
        $patterns = [
            '/^\d{4}-\d{2}-\d{2}$/',                          // Y-m-d
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',       // Y-m-d H:i:s
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',        // ISO 8601
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 檢查字串是否為 JSON 格式
     *
     * @param mixed $value 值
     * @return bool
     */
    protected function isJson($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 轉換分頁結果
     *
     * @param array $result 查詢結果
     * @param int $total 總記錄數
     * @param int $page 當前頁碼
     * @param int $perPage 每頁記錄數
     * @param array|null $responseMapping 回應映射配置
     * @return array
     */
    public function transformPaginated(
        array $result,
        int $total,
        int $page,
        int $perPage,
        ?array $responseMapping = null
    ): array {
        $data = $this->transform($result, $responseMapping);
        
        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'count' => count($data),
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * 轉換巢狀結果（包含關聯資料）
     *
     * @param array $result 查詢結果
     * @param array $nestedMappings 巢狀映射配置
     * @return array
     */
    public function transformNested(array $result, array $nestedMappings): array
    {
        $transformed = [];
        
        foreach ($result as $row) {
            $transformedRow = [];
            
            foreach ($nestedMappings as $key => $mapping) {
                if (is_array($mapping) && isset($mapping['nested'])) {
                    // 處理巢狀資料
                    $nestedData = $row[$key] ?? [];
                    $transformedRow[$key] = $this->transform($nestedData, $mapping['fields'] ?? null);
                } else {
                    // 處理一般欄位
                    $transformedRow[$key] = $this->convertValue($row[$key] ?? null);
                }
            }
            
            $transformed[] = $transformedRow;
        }
        
        return $transformed;
    }
}
