<?php

namespace App\Services\Logging;

use App\Models\SecurityLog;
use Illuminate\Support\Facades\Log;

/**
 * 安全日誌記錄器
 * 
 * 負責記錄系統安全相關事件，如驗證失敗、權限拒絕等
 */
class SecurityLogger
{
    /**
     * 安全事件類型常數
     */
    public const EVENT_AUTH_FAILED = 'auth_failed';
    public const EVENT_AUTH_SUCCESS = 'auth_success';
    public const EVENT_PERMISSION_DENIED = 'permission_denied';
    public const EVENT_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    public const EVENT_INVALID_TOKEN = 'invalid_token';
    public const EVENT_TOKEN_EXPIRED = 'token_expired';
    public const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    /**
     * 記錄安全事件
     *
     * @param string $eventType 事件類型
     * @param int|null $clientId 客戶端 ID
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊
     * @return void
     */
    public function log(
        string $eventType,
        ?int $clientId = null,
        ?string $ipAddress = null,
        array $details = []
    ): void {
        try {
            SecurityLog::create([
                'event_type' => $eventType,
                'client_id' => $clientId,
                'ip_address' => $ipAddress,
                'details' => $details,
            ]);

            // 對於嚴重的安全事件，同時記錄到系統日誌
            if ($this->isCriticalEvent($eventType)) {
                Log::warning('Security event detected', [
                    'event_type' => $eventType,
                    'client_id' => $clientId,
                    'ip_address' => $ipAddress,
                    'details' => $details,
                ]);
            }
        } catch (\Exception $e) {
            // 如果日誌記錄失敗，記錄到系統日誌但不影響主流程
            Log::error('Failed to log security event', [
                'error' => $e->getMessage(),
                'event_type' => $eventType,
            ]);
        }
    }

    /**
     * 記錄驗證失敗事件
     *
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊
     * @return void
     */
    public function logAuthenticationFailed(?string $ipAddress = null, array $details = []): void
    {
        $this->log(self::EVENT_AUTH_FAILED, null, $ipAddress, $details);
    }

    /**
     * 記錄驗證成功事件
     *
     * @param int $clientId 客戶端 ID
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊
     * @return void
     */
    public function logAuthenticationSuccess(int $clientId, ?string $ipAddress = null, array $details = []): void
    {
        $this->log(self::EVENT_AUTH_SUCCESS, $clientId, $ipAddress, $details);
    }

    /**
     * 記錄權限拒絕事件
     *
     * @param int $clientId 客戶端 ID
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊（如嘗試存取的資源）
     * @return void
     */
    public function logPermissionDenied(int $clientId, ?string $ipAddress = null, array $details = []): void
    {
        $this->log(self::EVENT_PERMISSION_DENIED, $clientId, $ipAddress, $details);
    }

    /**
     * 記錄速率限制超過事件
     *
     * @param int $clientId 客戶端 ID
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊
     * @return void
     */
    public function logRateLimitExceeded(int $clientId, ?string $ipAddress = null, array $details = []): void
    {
        $this->log(self::EVENT_RATE_LIMIT_EXCEEDED, $clientId, $ipAddress, $details);
    }

    /**
     * 記錄無效 Token 事件
     *
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊
     * @return void
     */
    public function logInvalidToken(?string $ipAddress = null, array $details = []): void
    {
        $this->log(self::EVENT_INVALID_TOKEN, null, $ipAddress, $details);
    }

    /**
     * 記錄 Token 過期事件
     *
     * @param int|null $clientId 客戶端 ID
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊
     * @return void
     */
    public function logTokenExpired(?int $clientId = null, ?string $ipAddress = null, array $details = []): void
    {
        $this->log(self::EVENT_TOKEN_EXPIRED, $clientId, $ipAddress, $details);
    }

    /**
     * 記錄可疑活動
     *
     * @param int|null $clientId 客戶端 ID
     * @param string|null $ipAddress IP 位址
     * @param array $details 詳細資訊
     * @return void
     */
    public function logSuspiciousActivity(?int $clientId = null, ?string $ipAddress = null, array $details = []): void
    {
        $this->log(self::EVENT_SUSPICIOUS_ACTIVITY, $clientId, $ipAddress, $details);
    }

    /**
     * 判斷是否為嚴重的安全事件
     *
     * @param string $eventType 事件類型
     * @return bool
     */
    private function isCriticalEvent(string $eventType): bool
    {
        return in_array($eventType, [
            self::EVENT_SUSPICIOUS_ACTIVITY,
            self::EVENT_PERMISSION_DENIED,
        ]);
    }

    /**
     * 查詢安全日誌
     *
     * @param array $filters 篩選條件
     * @param int $perPage 每頁筆數
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function queryLogs(array $filters = [], int $perPage = 50)
    {
        $query = SecurityLog::query()
            ->with('client')
            ->orderBy('created_at', 'desc');

        // 按事件類型篩選
        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        // 按客戶端篩選
        if (!empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        // 按 IP 位址篩選
        if (!empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
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
