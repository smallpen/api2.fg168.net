<?php

namespace App\Services\Logging;

/**
 * 日誌服務
 * 
 * 統一管理所有日誌記錄器，提供便捷的日誌記錄介面
 */
class LoggingService
{
    /**
     * API 請求日誌記錄器
     *
     * @var ApiLogger
     */
    protected ApiLogger $apiLogger;

    /**
     * 安全日誌記錄器
     *
     * @var SecurityLogger
     */
    protected SecurityLogger $securityLogger;

    /**
     * 審計日誌記錄器
     *
     * @var AuditLogger
     */
    protected AuditLogger $auditLogger;

    /**
     * 建構函式
     *
     * @param ApiLogger $apiLogger
     * @param SecurityLogger $securityLogger
     * @param AuditLogger $auditLogger
     */
    public function __construct(
        ApiLogger $apiLogger,
        SecurityLogger $securityLogger,
        AuditLogger $auditLogger
    ) {
        $this->apiLogger = $apiLogger;
        $this->securityLogger = $securityLogger;
        $this->auditLogger = $auditLogger;
    }

    /**
     * 取得 API 日誌記錄器
     *
     * @return ApiLogger
     */
    public function api(): ApiLogger
    {
        return $this->apiLogger;
    }

    /**
     * 取得安全日誌記錄器
     *
     * @return SecurityLogger
     */
    public function security(): SecurityLogger
    {
        return $this->securityLogger;
    }

    /**
     * 取得審計日誌記錄器
     *
     * @return AuditLogger
     */
    public function audit(): AuditLogger
    {
        return $this->auditLogger;
    }
}
