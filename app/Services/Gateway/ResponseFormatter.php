<?php

namespace App\Services\Gateway;

use App\Models\ApiFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * 回應格式化器
 * 
 * 負責格式化 API 回應，包含成功回應、錯誤回應和 Meta 資訊
 */
class ResponseFormatter
{
    /**
     * 格式化成功回應
     * 
     * @param mixed $data 回應資料
     * @param ApiFunction|null $function API Function 物件
     * @param array $meta 額外的 Meta 資訊
     * @return JsonResponse JSON 回應
     */
    public function success($data, ?ApiFunction $function = null, array $meta = []): JsonResponse
    {
        // 應用回應映射（如果有配置）
        if ($function && !$function->responses->isEmpty()) {
            $data = $this->applyResponseMapping($data, $function);
        }

        // 建立基本回應結構
        $response = [
            'success' => true,
            'data' => $data,
        ];

        // 加入 Meta 資訊
        $response['meta'] = $this->buildMetaInformation($meta);

        Log::debug('成功回應已格式化', [
            'function' => $function?->identifier,
            'has_data' => !empty($data),
        ]);

        return response()->json($response, 200);
    }

    /**
     * 格式化錯誤回應
     * 
     * @param string $code 錯誤代碼
     * @param string $message 錯誤訊息
     * @param int $httpStatus HTTP 狀態碼
     * @param array|null $details 錯誤詳情
     * @param array $meta 額外的 Meta 資訊
     * @return JsonResponse JSON 回應
     */
    public function error(
        string $code,
        string $message,
        int $httpStatus = 400,
        ?array $details = null,
        array $meta = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        // 加入錯誤詳情（如果有）
        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        // 加入 Meta 資訊
        $response['meta'] = $this->buildMetaInformation($meta);

        Log::debug('錯誤回應已格式化', [
            'code' => $code,
            'http_status' => $httpStatus,
        ]);

        return response()->json($response, $httpStatus);
    }

    /**
     * 格式化驗證錯誤回應
     * 
     * @param array $errors 驗證錯誤陣列
     * @param array $meta 額外的 Meta 資訊
     * @return JsonResponse JSON 回應
     */
    public function validationError(array $errors, array $meta = []): JsonResponse
    {
        return $this->error(
            'VALIDATION_ERROR',
            '參數驗證失敗',
            400,
            $errors,
            $meta
        );
    }

    /**
     * 格式化驗證例外回應
     * 
     * @param \Illuminate\Validation\ValidationException $exception 驗證例外
     * @param array $meta 額外的 Meta 資訊
     * @return JsonResponse JSON 回應
     */
    public function validationException(\Illuminate\Validation\ValidationException $exception, array $meta = []): JsonResponse
    {
        return $this->validationError(
            $exception->errors(),
            $meta
        );
    }

    /**
     * 應用回應映射
     * 
     * 根據 Function 的回應映射配置轉換資料結構
     * 
     * @param mixed $data 原始資料
     * @param ApiFunction $function API Function 物件
     * @return mixed 映射後的資料
     */
    protected function applyResponseMapping($data, ApiFunction $function)
    {
        // 如果資料是陣列（多筆記錄）
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            return array_map(function ($row) use ($function) {
                return $this->mapSingleRow($row, $function);
            }, $data);
        }

        // 如果資料是單一記錄
        if (is_array($data)) {
            return $this->mapSingleRow($data, $function);
        }

        // 其他情況直接返回
        return $data;
    }

    /**
     * 映射單一資料列
     * 
     * @param array $row 資料列
     * @param ApiFunction $function API Function 物件
     * @return array 映射後的資料列
     */
    protected function mapSingleRow(array $row, ApiFunction $function): array
    {
        $mapped = [];

        foreach ($function->responses as $responseMapping) {
            $fieldName = $responseMapping->field_name;
            $spColumnName = $responseMapping->sp_column_name ?? $fieldName;

            // 取得原始值
            $value = $row[$spColumnName] ?? null;

            // 應用轉換規則（如果有）
            if ($responseMapping->transform_rule) {
                $value = $this->applyTransformRule($value, $responseMapping->transform_rule);
            }

            // 轉換資料類型
            $value = $this->convertResponseDataType($value, $responseMapping->data_type);

            $mapped[$fieldName] = $value;
        }

        // 如果沒有映射到任何欄位，返回原始資料
        if (empty($mapped)) {
            return $row;
        }

        return $mapped;
    }

    /**
     * 應用轉換規則
     * 
     * @param mixed $value 原始值
     * @param string $transformRule 轉換規則
     * @return mixed 轉換後的值
     */
    protected function applyTransformRule($value, string $transformRule)
    {
        // 支援的轉換規則：
        // - uppercase: 轉換為大寫
        // - lowercase: 轉換為小寫
        // - trim: 去除空白
        // - date:format: 格式化日期
        // - number:decimals: 格式化數字

        $parts = explode(':', $transformRule);
        $rule = $parts[0];
        $param = $parts[1] ?? null;

        return match($rule) {
            'uppercase' => is_string($value) ? strtoupper($value) : $value,
            'lowercase' => is_string($value) ? strtolower($value) : $value,
            'trim' => is_string($value) ? trim($value) : $value,
            'date' => $this->formatDateWithRule($value, $param),
            'number' => $this->formatNumberWithRule($value, $param),
            default => $value,
        };
    }

    /**
     * 使用規則格式化日期
     * 
     * @param mixed $value 日期值
     * @param string|null $format 日期格式
     * @return string|null 格式化後的日期
     */
    protected function formatDateWithRule($value, ?string $format = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $format = $format ?? 'Y-m-d H:i:s';

        try {
            if ($value instanceof \DateTime) {
                return $value->format($format);
            }

            if (is_string($value)) {
                $date = new \DateTime($value);
                return $date->format($format);
            }
        } catch (\Exception $e) {
            Log::warning('日期格式化失敗', [
                'value' => $value,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);
        }

        return $value;
    }

    /**
     * 使用規則格式化數字
     * 
     * @param mixed $value 數字值
     * @param string|null $decimals 小數位數
     * @return string|null 格式化後的數字
     */
    protected function formatNumberWithRule($value, ?string $decimals = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $decimals = $decimals !== null ? (int) $decimals : 2;

        if (is_numeric($value)) {
            return number_format((float) $value, $decimals, '.', '');
        }

        return $value;
    }

    /**
     * 轉換回應資料類型
     * 
     * @param mixed $value 原始值
     * @param string|null $dataType 目標資料類型
     * @return mixed 轉換後的值
     */
    protected function convertResponseDataType($value, ?string $dataType)
    {
        if ($value === null || $dataType === null) {
            return $value;
        }

        return match($dataType) {
            'integer', 'int' => is_numeric($value) ? (int) $value : $value,
            'float', 'double', 'decimal' => is_numeric($value) ? (float) $value : $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'array' => is_string($value) ? json_decode($value, true) : $value,
            'json' => is_array($value) ? json_encode($value) : $value,
            default => $value,
        };
    }

    /**
     * 建立 Meta 資訊
     * 
     * @param array $additionalMeta 額外的 Meta 資訊
     * @return array Meta 資訊陣列
     */
    protected function buildMetaInformation(array $additionalMeta = []): array
    {
        $meta = [
            'timestamp' => now()->toIso8601String(),
        ];

        // 加入請求 ID（如果有）
        if (isset($additionalMeta['request_id'])) {
            $meta['request_id'] = $additionalMeta['request_id'];
        }

        // 加入執行時間（如果有）
        if (isset($additionalMeta['execution_time'])) {
            $meta['execution_time'] = round($additionalMeta['execution_time'], 4);
        }

        // 加入其他自訂 Meta 資訊
        foreach ($additionalMeta as $key => $value) {
            if (!isset($meta[$key])) {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    /**
     * 格式化分頁回應
     * 
     * @param mixed $data 分頁資料
     * @param ApiFunction|null $function API Function 物件
     * @param array $meta 額外的 Meta 資訊
     * @return JsonResponse JSON 回應
     */
    public function paginated($data, ?ApiFunction $function = null, array $meta = []): JsonResponse
    {
        // 如果是 Laravel Paginator
        if ($data instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $items = $data->items();

            // 應用回應映射
            if ($function && !$function->responses->isEmpty()) {
                $items = array_map(function ($item) use ($function) {
                    return $this->mapSingleRow(
                        is_array($item) ? $item : $item->toArray(),
                        $function
                    );
                }, $items);
            }

            $response = [
                'success' => true,
                'data' => $items,
                'pagination' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'from' => $data->firstItem(),
                    'to' => $data->lastItem(),
                ],
                'meta' => $this->buildMetaInformation($meta),
            ];

            return response()->json($response, 200);
        }

        // 不是分頁資料，使用一般成功回應
        return $this->success($data, $function, $meta);
    }
}
