<?php

namespace App\Services\Gateway;

use App\Models\ApiFunction;
use App\Services\Database\StoredProcedureExecutor;
use App\Exceptions\StoredProcedureException;
use Illuminate\Support\Facades\Log;

/**
 * Function 執行器
 * 
 * 負責編排和執行 API Function 的完整流程
 */
class FunctionExecutor
{
    /**
     * Stored Procedure 執行器
     */
    protected StoredProcedureExecutor $spExecutor;

    /**
     * 建構函數
     */
    public function __construct(StoredProcedureExecutor $spExecutor)
    {
        $this->spExecutor = $spExecutor;
    }

    /**
     * 執行 API Function
     * 
     * 編排完整的執行流程：參數映射 → 執行 SP → 處理結果
     * 
     * @param ApiFunction $function API Function 物件
     * @param array $validatedParams 已驗證的參數
     * @return array 執行結果
     * @throws StoredProcedureException 執行失敗時拋出
     */
    public function execute(ApiFunction $function, array $validatedParams): array
    {
        $startTime = microtime(true);

        Log::info('開始執行 API Function', [
            'function' => $function->identifier,
            'stored_procedure' => $function->stored_procedure,
            'params_count' => count($validatedParams),
        ]);

        try {
            // 1. 映射參數到 Stored Procedure 參數
            $spParams = $this->mapParametersToStoredProcedure($function, $validatedParams);

            Log::debug('參數映射完成', [
                'function' => $function->identifier,
                'sp_params' => $spParams,
            ]);

            // 2. 執行 Stored Procedure
            $spResult = $this->executeStoredProcedure($function, $spParams);

            Log::debug('Stored Procedure 執行完成', [
                'function' => $function->identifier,
                'execution_time' => $spResult['execution_time'] ?? 0,
            ]);

            // 3. 處理執行結果
            $processedResult = $this->processResult($function, $spResult);

            $totalExecutionTime = microtime(true) - $startTime;

            Log::info('API Function 執行成功', [
                'function' => $function->identifier,
                'total_execution_time' => $totalExecutionTime,
            ]);

            return [
                'success' => true,
                'data' => $processedResult,
                'execution_time' => $totalExecutionTime,
            ];

        } catch (StoredProcedureException $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('API Function 執行失敗', [
                'function' => $function->identifier,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'execution_time' => $executionTime,
            ]);

            throw $e;

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('API Function 執行發生未預期錯誤', [
                'function' => $function->identifier,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new StoredProcedureException(
                "執行 API Function 時發生錯誤: {$e->getMessage()}",
                500,
                $e
            );
        }
    }

    /**
     * 映射參數到 Stored Procedure 參數
     * 
     * 根據 Function 的參數配置，將 API 參數映射到 SP 參數
     * 
     * @param ApiFunction $function API Function 物件
     * @param array $validatedParams 已驗證的參數
     * @return array SP 參數陣列（按位置排序）
     */
    protected function mapParametersToStoredProcedure(ApiFunction $function, array $validatedParams): array
    {
        $spParams = [];

        // 取得所有參數配置，按位置排序
        $parameters = $function->parameters()->orderBy('position')->get();

        foreach ($parameters as $parameter) {
            $apiParamName = $parameter->name;
            $spParamName = $parameter->sp_parameter_name ?? $apiParamName;

            // 取得參數值
            $value = $validatedParams[$apiParamName] ?? $parameter->default_value;

            // 轉換資料類型
            $convertedValue = $this->convertDataType($value, $parameter->data_type);

            $spParams[] = $convertedValue;

            Log::debug('參數映射', [
                'api_param' => $apiParamName,
                'sp_param' => $spParamName,
                'position' => $parameter->position,
                'value' => $convertedValue,
                'type' => $parameter->data_type,
            ]);
        }

        return $spParams;
    }

    /**
     * 轉換資料類型
     * 
     * 將參數值轉換為 Stored Procedure 所需的資料類型
     * 
     * @param mixed $value 原始值
     * @param string $dataType 目標資料類型
     * @return mixed 轉換後的值
     */
    protected function convertDataType($value, string $dataType)
    {
        if ($value === null) {
            return null;
        }

        return match($dataType) {
            'integer', 'int' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'date' => $this->formatDate($value),
            'datetime' => $this->formatDateTime($value),
            'json' => is_string($value) ? $value : json_encode($value),
            'array' => is_array($value) ? json_encode($value) : $value,
            default => $value,
        };
    }

    /**
     * 格式化日期
     * 
     * @param mixed $value 日期值
     * @return string 格式化後的日期字串
     */
    protected function formatDate($value): string
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            try {
                $date = new \DateTime($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return (string) $value;
    }

    /**
     * 格式化日期時間
     * 
     * @param mixed $value 日期時間值
     * @return string 格式化後的日期時間字串
     */
    protected function formatDateTime($value): string
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            try {
                $date = new \DateTime($value);
                return $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return (string) $value;
    }

    /**
     * 執行 Stored Procedure
     * 
     * @param ApiFunction $function API Function 物件
     * @param array $spParams SP 參數陣列
     * @return array 執行結果
     * @throws StoredProcedureException 執行失敗時拋出
     */
    protected function executeStoredProcedure(ApiFunction $function, array $spParams): array
    {
        $procedureName = $function->stored_procedure;

        try {
            // 執行 Stored Procedure
            $result = $this->spExecutor->execute($procedureName, $spParams);

            return $result;

        } catch (StoredProcedureException $e) {
            // 檢查是否有錯誤映射配置
            $mappedError = $this->mapStoredProcedureError($function, $e);

            if ($mappedError) {
                throw new StoredProcedureException(
                    $mappedError['message'],
                    $mappedError['http_status'],
                    $e
                );
            }

            // 沒有映射配置，直接拋出原始錯誤
            throw $e;
        }
    }

    /**
     * 映射 Stored Procedure 錯誤
     * 
     * 根據 Function 的錯誤映射配置，將 SP 錯誤轉換為 HTTP 錯誤
     * 
     * @param ApiFunction $function API Function 物件
     * @param StoredProcedureException $exception SP 例外
     * @return array|null 映射後的錯誤資訊，沒有映射則返回 null
     */
    protected function mapStoredProcedureError(ApiFunction $function, StoredProcedureException $exception): ?array
    {
        $errorCode = $exception->getCode();

        // 查找錯誤映射
        $errorMapping = $function->errorMappings()
            ->where('error_code', $errorCode)
            ->first();

        if ($errorMapping) {
            return [
                'http_status' => $errorMapping->http_status,
                'message' => $errorMapping->error_message,
            ];
        }

        return null;
    }

    /**
     * 處理執行結果
     * 
     * 處理 Stored Procedure 的執行結果
     * 
     * @param ApiFunction $function API Function 物件
     * @param array $spResult SP 執行結果
     * @return mixed 處理後的結果
     */
    protected function processResult(ApiFunction $function, array $spResult)
    {
        // 取得 SP 返回的資料
        $data = $spResult['data'] ?? [];

        // 如果沒有回應映射配置，直接返回原始資料
        if ($function->responses->isEmpty()) {
            return $data;
        }

        // 應用回應映射（將在後續的 ResponseFormatter 中實作）
        // 這裡先返回原始資料
        return $data;
    }

    /**
     * 在交易中執行 API Function
     * 
     * @param ApiFunction $function API Function 物件
     * @param array $validatedParams 已驗證的參數
     * @return array 執行結果
     * @throws StoredProcedureException 執行失敗時拋出
     */
    public function executeInTransaction(ApiFunction $function, array $validatedParams): array
    {
        $startTime = microtime(true);

        Log::info('在交易中執行 API Function', [
            'function' => $function->identifier,
            'stored_procedure' => $function->stored_procedure,
        ]);

        try {
            // 映射參數
            $spParams = $this->mapParametersToStoredProcedure($function, $validatedParams);

            // 在交易中執行 Stored Procedure
            $spResult = $this->spExecutor->executeInTransaction(
                $function->stored_procedure,
                $spParams
            );

            // 處理結果
            $processedResult = $this->processResult($function, $spResult);

            $totalExecutionTime = microtime(true) - $startTime;

            return [
                'success' => true,
                'data' => $processedResult,
                'execution_time' => $totalExecutionTime,
            ];

        } catch (\Exception $e) {
            Log::error('交易中執行 API Function 失敗', [
                'function' => $function->identifier,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
