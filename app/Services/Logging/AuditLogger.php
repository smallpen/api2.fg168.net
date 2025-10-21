<?php

namespace App\Services\Logging;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * 審計日誌記錄器
 * 
 * 負責記錄系統配置變更和重要操作
 */
class AuditLogger
{
    /**
     * 操作類型常數
     */
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_ENABLE = 'enable';
    public const ACTION_DISABLE = 'disable';
    public const ACTION_REVOKE = 'revoke';

    /**
     * 資源類型常數
     */
    public const RESOURCE_API_FUNCTION = 'api_function';
    public const RESOURCE_API_CLIENT = 'api_client';
    public const RESOURCE_API_TOKEN = 'api_token';
    public const RESOURCE_PERMISSION = 'permission';
    public const RESOURCE_ROLE = 'role';
    public const RESOURCE_PARAMETER = 'parameter';
    public const RESOURCE_RESPONSE = 'response';

    /**
     * 記錄審計事件
     *
     * @param int|null $userId 操作者 ID
     * @param string $action 操作類型
     * @param string $resourceType 資源類型
     * @param int|string|null $resourceId 資源 ID
     * @param array|null $oldValue 舊值
     * @param array|null $newValue 新值
     * @return void
     */
    public function log(
        ?int $userId,
        string $action,
        string $resourceType,
        $resourceId = null,
        ?array $oldValue = null,
        ?array $newValue = null
    ): void {
        try {
            AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);

            // 記錄到系統日誌以便追蹤
            Log::info('Audit log created', [
                'user_id' => $userId,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
            ]);
        } catch (\Exception $e) {
            // 如果日誌記錄失敗，記錄到系統日誌但不影響主流程
            Log::error('Failed to create audit log', [
                'error' => $e->getMessage(),
                'action' => $action,
                'resource_type' => $resourceType,
            ]);
        }
    }

    /**
     * 記錄創建操作
     *
     * @param int|null $userId 操作者 ID
     * @param string $resourceType 資源類型
     * @param int|string $resourceId 資源 ID
     * @param array $data 創建的資料
     * @return void
     */
    public function logCreate(?int $userId, string $resourceType, $resourceId, array $data): void
    {
        $this->log($userId, self::ACTION_CREATE, $resourceType, $resourceId, null, $data);
    }

    /**
     * 記錄更新操作
     *
     * @param int|null $userId 操作者 ID
     * @param string $resourceType 資源類型
     * @param int|string $resourceId 資源 ID
     * @param array $oldData 舊資料
     * @param array $newData 新資料
     * @return void
     */
    public function logUpdate(?int $userId, string $resourceType, $resourceId, array $oldData, array $newData): void
    {
        $this->log($userId, self::ACTION_UPDATE, $resourceType, $resourceId, $oldData, $newData);
    }

    /**
     * 記錄刪除操作
     *
     * @param int|null $userId 操作者 ID
     * @param string $resourceType 資源類型
     * @param int|string $resourceId 資源 ID
     * @param array $data 被刪除的資料
     * @return void
     */
    public function logDelete(?int $userId, string $resourceType, $resourceId, array $data): void
    {
        $this->log($userId, self::ACTION_DELETE, $resourceType, $resourceId, $data, null);
    }

    /**
     * 記錄啟用操作
     *
     * @param int|null $userId 操作者 ID
     * @param string $resourceType 資源類型
     * @param int|string $resourceId 資源 ID
     * @return void
     */
    public function logEnable(?int $userId, string $resourceType, $resourceId): void
    {
        $this->log($userId, self::ACTION_ENABLE, $resourceType, $resourceId, ['is_active' => false], ['is_active' => true]);
    }

    /**
     * 記錄停用操作
     *
     * @param int|null $userId 操作者 ID
     * @param string $resourceType 資源類型
     * @param int|string $resourceId 資源 ID
     * @return void
     */
    public function logDisable(?int $userId, string $resourceType, $resourceId): void
    {
        $this->log($userId, self::ACTION_DISABLE, $resourceType, $resourceId, ['is_active' => true], ['is_active' => false]);
    }

    /**
     * 記錄撤銷操作
     *
     * @param int|null $userId 操作者 ID
     * @param string $resourceType 資源類型
     * @param int|string $resourceId 資源 ID
     * @param array $details 詳細資訊
     * @return void
     */
    public function logRevoke(?int $userId, string $resourceType, $resourceId, array $details = []): void
    {
        $this->log($userId, self::ACTION_REVOKE, $resourceType, $resourceId, null, $details);
    }

    /**
     * 查詢審計日誌
     *
     * @param array $filters 篩選條件
     * @param int $perPage 每頁筆數
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function queryLogs(array $filters = [], int $perPage = 50)
    {
        $query = AuditLog::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        // 按操作者篩選
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // 按操作類型篩選
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // 按資源類型篩選
        if (!empty($filters['resource_type'])) {
            $query->where('resource_type', $filters['resource_type']);
        }

        // 按資源 ID 篩選
        if (!empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        // 按時間範圍篩選
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->paginate($perPage);
    }
}
