<?php

namespace App\Services\Database;

use App\Exceptions\StoredProcedureException;
use App\Models\FunctionErrorMapping;
use Illuminate\Support\Facades\Log;
use PDOException;
use Exception;
use Throwable;

/**
 * 資料庫錯誤處理器
 * 
 * 負責處理 Stored Procedure 執行錯誤，並映射到 HTTP 狀態碼
 */
class ErrorHandler
{
    /**
     * 處理 Stored Procedure 錯誤
     *
     * @param Throwable $exception 例外物件
     * @param string|null $procedureName Stored Procedure 名稱
     * @param array $parameters 執行參數
     * @param int|null $functionId API Function ID（用於錯誤映射）
     * @return array 錯誤回應資料
     */
    public function handle(
        Throwable $exception,
        ?string $procedureName = null,
        array $parameters = [],
        ?int $functionId = null
    ): array {
        // 記錄錯誤
        $this->logError($exception, $procedureName, $parameters);
        
        // 解析錯誤資訊
        $errorInfo = $this->parseError($exception);
        
        // 如果有 Function ID，嘗試從錯誤映射中取得自訂錯誤訊息
        if ($functionId) {
            $customError = $this->getCustomErrorMapping($functionId, $errorInfo['code']);
            if ($customError) {
                return $customError;
            }
        }
        
        // 返回預設錯誤回應
        return [
            'http_status' => $errorInfo['http_status'],
            'error_code' => $errorInfo['code'],
            'error_message' => $errorInfo['message'],
            'details' => $errorInfo['details'],
        ];
    }

    /**
     * 解析錯誤資訊
     *
     * @param Throwable $exception 例外物件
     * @return array 錯誤資訊
     */
    protected function parseError(Throwable $exception): array
    {
        if ($exception instanceof StoredProcedureException) {
            return $this->parseStoredProcedureException($exception);
        }
        
        if ($exception instanceof PDOException) {
            return $this->parsePDOException($exception);
        }
        
        // 一般例外
        return [
            'code' => 'INTERNAL_ERROR',
            'message' => '內部伺服器錯誤',
            'details' => config('app.debug') ? $exception->getMessage() : null,
            'http_status' => 500,
        ];
    }

    /**
     * 解析 StoredProcedureException
     *
     * @param StoredProcedureException $exception
     * @return array
     */
    protected function parseStoredProcedureException(StoredProcedureException $exception): array
    {
        $code = 'STORED_PROCEDURE_ERROR';
        $httpStatus = 500;
        
        if ($exception->isTimeout()) {
            $code = 'QUERY_TIMEOUT';
            $httpStatus = 504;
        } elseif ($exception->isDeadlock()) {
            $code = 'DEADLOCK_ERROR';
            $httpStatus = 409;
        } elseif ($exception->isConnectionError()) {
            $code = 'DATABASE_CONNECTION_ERROR';
            $httpStatus = 503;
        }
        
        return [
            'code' => $code,
            'message' => $this->getErrorMessage($code),
            'details' => config('app.debug') ? [
                'procedure' => $exception->getProcedureName(),
                'sql_state' => $exception->getSqlState(),
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ] : null,
            'http_status' => $httpStatus,
        ];
    }

    /**
     * 解析 PDOException
     *
     * @param PDOException $exception
     * @return array
     */
    protected function parsePDOException(PDOException $exception): array
    {
        $errorInfo = $exception->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? null;
        $errorCode = $errorInfo[1] ?? null;
        $errorMessage = $errorInfo[2] ?? $exception->getMessage();
        
        // 根據 SQL 狀態碼判斷錯誤類型
        $code = $this->mapSqlStateToErrorCode($sqlState, $errorCode);
        $httpStatus = $this->mapErrorCodeToHttpStatus($code);
        
        return [
            'code' => $code,
            'message' => $this->getErrorMessage($code),
            'details' => config('app.debug') ? [
                'sql_state' => $sqlState,
                'error_code' => $errorCode,
                'message' => $errorMessage,
            ] : null,
            'http_status' => $httpStatus,
        ];
    }

    /**
     * 映射 SQL 狀態碼到錯誤代碼
     *
     * @param string|null $sqlState SQL 狀態碼
     * @param int|null $errorCode 錯誤代碼
     * @return string
     */
    protected function mapSqlStateToErrorCode(?string $sqlState, ?int $errorCode): string
    {
        // MySQL 錯誤代碼映射
        $errorCodeMap = [
            1213 => 'DEADLOCK_ERROR',           // Deadlock found
            1205 => 'LOCK_TIMEOUT',              // Lock wait timeout
            2006 => 'DATABASE_CONNECTION_ERROR', // MySQL server has gone away
            2013 => 'DATABASE_CONNECTION_ERROR', // Lost connection to MySQL server
            1062 => 'DUPLICATE_ENTRY',           // Duplicate entry
            1452 => 'FOREIGN_KEY_CONSTRAINT',    // Foreign key constraint fails
            1406 => 'DATA_TOO_LONG',             // Data too long for column
        ];
        
        if ($errorCode && isset($errorCodeMap[$errorCode])) {
            return $errorCodeMap[$errorCode];
        }
        
        // SQL 狀態碼映射
        if ($sqlState) {
            $statePrefix = substr($sqlState, 0, 2);
            
            return match($statePrefix) {
                '08' => 'DATABASE_CONNECTION_ERROR',
                '23' => 'CONSTRAINT_VIOLATION',
                '40' => 'TRANSACTION_ERROR',
                '42' => 'SYNTAX_ERROR',
                'HY' => 'GENERAL_ERROR',
                default => 'DATABASE_ERROR',
            };
        }
        
        return 'DATABASE_ERROR';
    }

    /**
     * 映射錯誤代碼到 HTTP 狀態碼
     *
     * @param string $errorCode 錯誤代碼
     * @return int HTTP 狀態碼
     */
    protected function mapErrorCodeToHttpStatus(string $errorCode): int
    {
        return match($errorCode) {
            'QUERY_TIMEOUT' => 504,
            'DEADLOCK_ERROR' => 409,
            'LOCK_TIMEOUT' => 409,
            'DATABASE_CONNECTION_ERROR' => 503,
            'DUPLICATE_ENTRY' => 409,
            'FOREIGN_KEY_CONSTRAINT' => 400,
            'CONSTRAINT_VIOLATION' => 400,
            'DATA_TOO_LONG' => 400,
            'SYNTAX_ERROR' => 400,
            'TRANSACTION_ERROR' => 500,
            default => 500,
        };
    }

    /**
     * 取得錯誤訊息
     *
     * @param string $errorCode 錯誤代碼
     * @return string
     */
    protected function getErrorMessage(string $errorCode): string
    {
        return match($errorCode) {
            'STORED_PROCEDURE_ERROR' => 'Stored Procedure 執行失敗',
            'QUERY_TIMEOUT' => '查詢執行逾時',
            'DEADLOCK_ERROR' => '資料庫死鎖，請稍後重試',
            'LOCK_TIMEOUT' => '資料庫鎖定逾時',
            'DATABASE_CONNECTION_ERROR' => '資料庫連線錯誤',
            'DUPLICATE_ENTRY' => '資料重複',
            'FOREIGN_KEY_CONSTRAINT' => '外鍵約束違反',
            'CONSTRAINT_VIOLATION' => '資料約束違反',
            'DATA_TOO_LONG' => '資料長度超過限制',
            'SYNTAX_ERROR' => 'SQL 語法錯誤',
            'TRANSACTION_ERROR' => '交易執行錯誤',
            'DATABASE_ERROR' => '資料庫錯誤',
            'GENERAL_ERROR' => '一般錯誤',
            'INTERNAL_ERROR' => '內部伺服器錯誤',
            default => '未知錯誤',
        };
    }

    /**
     * 從資料庫取得自訂錯誤映射
     *
     * @param int $functionId API Function ID
     * @param string $errorCode 錯誤代碼
     * @return array|null
     */
    protected function getCustomErrorMapping(int $functionId, string $errorCode): ?array
    {
        try {
            $mapping = FunctionErrorMapping::where('function_id', $functionId)
                ->where('error_code', $errorCode)
                ->first();
            
            if ($mapping) {
                return [
                    'http_status' => $mapping->http_status,
                    'error_code' => $errorCode,
                    'error_message' => $mapping->error_message,
                    'details' => null,
                ];
            }
        } catch (Exception $e) {
            Log::warning('無法載入錯誤映射', [
                'function_id' => $functionId,
                'error_code' => $errorCode,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }

    /**
     * 記錄錯誤
     *
     * @param Throwable $exception 例外物件
     * @param string|null $procedureName Stored Procedure 名稱
     * @param array $parameters 執行參數
     */
    protected function logError(Throwable $exception, ?string $procedureName, array $parameters): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'procedure' => $procedureName,
            'parameters' => $parameters,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
        
        if ($exception instanceof StoredProcedureException) {
            $context = array_merge($context, $exception->getContext());
        }
        
        Log::error('Stored Procedure 執行錯誤', $context);
    }

    /**
     * 判斷錯誤是否可重試
     *
     * @param Throwable $exception 例外物件
     * @return bool
     */
    public function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof StoredProcedureException) {
            return $exception->isRetryable();
        }
        
        if ($exception instanceof PDOException) {
            $errorInfo = $exception->errorInfo ?? [];
            $errorCode = $errorInfo[1] ?? null;
            
            // 可重試的錯誤代碼
            $retryableErrors = [1213, 1205, 2006, 2013];
            
            return in_array($errorCode, $retryableErrors);
        }
        
        return false;
    }

    /**
     * 建立 StoredProcedureException
     *
     * @param Throwable $exception 原始例外
     * @param string $procedureName Stored Procedure 名稱
     * @param array $parameters 執行參數
     * @return StoredProcedureException
     */
    public function createStoredProcedureException(
        Throwable $exception,
        string $procedureName,
        array $parameters
    ): StoredProcedureException {
        $sqlState = null;
        $errorCode = null;
        
        if ($exception instanceof PDOException) {
            $errorInfo = $exception->errorInfo ?? [];
            $sqlState = $errorInfo[0] ?? null;
            $errorCode = $errorInfo[1] ?? null;
        }
        
        return new StoredProcedureException(
            $exception->getMessage(),
            $procedureName,
            $parameters,
            $sqlState,
            $errorCode,
            $exception
        );
    }
}
