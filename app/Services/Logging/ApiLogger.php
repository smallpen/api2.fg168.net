<?php

namespace App\Services\Logging;

use App\Models\ApiRequestLog;
use Illuminate\Support\Facades\Log;

/**
 * API 請求日誌記錄器
 * 
 * 負責記錄所有通過 API Gateway 的請求資訊
 */
class ApiLogger
{
    /**
     * 記錄 API 請求
     *
     * @param array $data 請求資料
     * @return void
     */
    public function logRequest(array $data): void
    {
        try {
            ApiRequestLog::create([
                'client_id' => $data['client_id'] ?? null,
                'function_id' => $data['function_id'] ?? null,
                'request_data' => $data['request_data'] ?? [],
                'response_data' => $data['response_data'] ?? [],
                'http_status' => $data['http_status'] ?? 200,
                'execution_time' => $data['execution_time'] ?? 0,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);
        } catch (\Exception $e) {
            // 如果日誌記錄失敗，記錄到系統日誌但不影響主流程
            Log::error('Failed to log API request', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * 記錄成功的 API 請求
     *
     * @param int|null $clientId 客戶端 ID
     * @param int|null $functionId Function ID
     * @param array $requestData 請求資料
     * @param array $responseData 回應資料
     * @param float $executionTime 執行時間（秒）
     * @param string|null $ipAddress IP 位址
     * @param string|null $userAgent User Agent
     * @return void
     */
    public function logSuccess(
        ?int $clientId,
        ?int $functionId,
        array $requestData,
        array $responseData,
        float $executionTime,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $this->logRequest([
            'client_id' => $clientId,
            'function_id' => $functionId,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'http_status' => 200,
            'execution_time' => $executionTime,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * 記錄失敗的 API 請求
     *
     * @param int|null $clientId 客戶端 ID
     * @param int|null $functionId Function ID
     * @param array $requestData 請求資料
     * @param array $errorData 錯誤資料
     * @param int $httpStatus HTTP 狀態碼
     * @param float $executionTime 執行時間（秒）
     * @param string|null $ipAddress IP 位址
     * @param string|null $userAgent User Agent
     * @return void
     */
    public function logFailure(
        ?int $clientId,
        ?int $functionId,
        array $requestData,
        array $errorData,
        int $httpStatus,
        float $executionTime,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $this->logRequest([
            'client_id' => $clientId,
            'function_id' => $functionId,
            'request_data' => $requestData,
            'response_data' => $errorData,
            'http_status' => $httpStatus,
            'execution_time' => $executionTime,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * 查詢 API 請求日誌
     *
     * @param array $filters 篩選條件
     * @param int $perPage 每頁筆數
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function queryLogs(array $filters = [], int $perPage = 50)
    {
        $query = ApiRequestLog::query()
            ->with(['client', 'function'])
            ->orderBy('created_at', 'desc');

        // 按客戶端篩選
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        // 按 Function 篩選
        if (!empty($filters['function_id'])) {
            $query->where('function_id', $filters['function_id']);
        }

        // 按 HTTP 狀態碼篩選
        if (!empty($filters['http_status'])) {
            $query->where('http_status', $filters['http_status']);
        }

        // 按時間範圍篩選
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        // 按 IP 位址篩選
        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        return $query->paginate($perPage);
    }
}
