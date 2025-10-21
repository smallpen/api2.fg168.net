<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * 交易管理器
 * 
 * 提供進階的交易管理功能，包含巢狀交易、Savepoint 和自動重試
 */
class TransactionManager
{
    /**
     * @var array 交易堆疊
     */
    protected array $transactionStack = [];

    /**
     * @var int 最大重試次數
     */
    protected int $maxRetries;

    /**
     * @var int 重試延遲（毫秒）
     */
    protected int $retryDelay;

    /**
     * 建構函數
     */
    public function __construct()
    {
        $this->maxRetries = config('database.transaction.max_retries', 3);
        $this->retryDelay = config('database.transaction.retry_delay', 100);
    }

    /**
     * 在交易中執行回呼函數
     *
     * @param callable $callback 要執行的回呼函數
     * @param string|null $connection 資料庫連線名稱
     * @param int $attempts 嘗試次數
     * @return mixed 回呼函數的返回值
     * @throws Exception
     */
    public function transaction(callable $callback, ?string $connection = null, int $attempts = null)
    {
        $attempts = $attempts ?? $this->maxRetries;
        $connectionName = $connection ?? config('database.default');
        $db = DB::connection($connectionName);
        
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            try {
                return $db->transaction($callback);
            } catch (Exception $e) {
                $isLastAttempt = $currentAttempt === $attempts;
                
                // 判斷是否應該重試
                if (!$isLastAttempt && $this->shouldRetry($e)) {
                    Log::warning('交易失敗，準備重試', [
                        'attempt' => $currentAttempt,
                        'max_attempts' => $attempts,
                        'error' => $e->getMessage(),
                        'connection' => $connectionName
                    ]);
                    
                    // 延遲後重試
                    usleep($this->retryDelay * 1000 * $currentAttempt);
                    continue;
                }
                
                // 記錄最終失敗
                Log::error('交易執行失敗', [
                    'attempts' => $currentAttempt,
                    'error' => $e->getMessage(),
                    'connection' => $connectionName,
                    'trace' => $e->getTraceAsString()
                ]);
                
                throw $e;
            }
        }
    }

    /**
     * 開始交易
     *
     * @param string|null $connection 資料庫連線名稱
     * @return void
     */
    public function begin(?string $connection = null): void
    {
        $connectionName = $connection ?? config('database.default');
        $db = DB::connection($connectionName);
        
        $transactionLevel = $db->transactionLevel();
        
        if ($transactionLevel === 0) {
            $db->beginTransaction();
            Log::debug('開始交易', ['connection' => $connectionName]);
        } else {
            // 使用 Savepoint 處理巢狀交易
            $savepointName = "savepoint_level_{$transactionLevel}";
            $db->statement("SAVEPOINT {$savepointName}");
            Log::debug('建立 Savepoint', [
                'connection' => $connectionName,
                'savepoint' => $savepointName,
                'level' => $transactionLevel
            ]);
        }
        
        $this->pushTransaction($connectionName, $transactionLevel);
    }

    /**
     * 提交交易
     *
     * @param string|null $connection 資料庫連線名稱
     * @return void
     */
    public function commit(?string $connection = null): void
    {
        $connectionName = $connection ?? config('database.default');
        $db = DB::connection($connectionName);
        
        $transactionLevel = $db->transactionLevel();
        
        if ($transactionLevel === 1) {
            $db->commit();
            Log::debug('提交交易', ['connection' => $connectionName]);
        } elseif ($transactionLevel > 1) {
            // 釋放 Savepoint
            $savepointName = "savepoint_level_" . ($transactionLevel - 1);
            $db->statement("RELEASE SAVEPOINT {$savepointName}");
            Log::debug('釋放 Savepoint', [
                'connection' => $connectionName,
                'savepoint' => $savepointName,
                'level' => $transactionLevel
            ]);
        }
        
        $this->popTransaction($connectionName);
    }

    /**
     * 回滾交易
     *
     * @param string|null $connection 資料庫連線名稱
     * @return void
     */
    public function rollback(?string $connection = null): void
    {
        $connectionName = $connection ?? config('database.default');
        $db = DB::connection($connectionName);
        
        $transactionLevel = $db->transactionLevel();
        
        if ($transactionLevel === 1) {
            $db->rollBack();
            Log::debug('回滾交易', ['connection' => $connectionName]);
        } elseif ($transactionLevel > 1) {
            // 回滾到 Savepoint
            $savepointName = "savepoint_level_" . ($transactionLevel - 1);
            $db->statement("ROLLBACK TO SAVEPOINT {$savepointName}");
            Log::debug('回滾到 Savepoint', [
                'connection' => $connectionName,
                'savepoint' => $savepointName,
                'level' => $transactionLevel
            ]);
        }
        
        $this->popTransaction($connectionName);
    }

    /**
     * 建立 Savepoint
     *
     * @param string $name Savepoint 名稱
     * @param string|null $connection 資料庫連線名稱
     * @return void
     */
    public function savepoint(string $name, ?string $connection = null): void
    {
        $connectionName = $connection ?? config('database.default');
        $db = DB::connection($connectionName);
        
        $db->statement("SAVEPOINT {$name}");
        
        Log::debug('建立 Savepoint', [
            'connection' => $connectionName,
            'savepoint' => $name
        ]);
    }

    /**
     * 回滾到指定的 Savepoint
     *
     * @param string $name Savepoint 名稱
     * @param string|null $connection 資料庫連線名稱
     * @return void
     */
    public function rollbackToSavepoint(string $name, ?string $connection = null): void
    {
        $connectionName = $connection ?? config('database.default');
        $db = DB::connection($connectionName);
        
        $db->statement("ROLLBACK TO SAVEPOINT {$name}");
        
        Log::debug('回滾到 Savepoint', [
            'connection' => $connectionName,
            'savepoint' => $name
        ]);
    }

    /**
     * 釋放 Savepoint
     *
     * @param string $name Savepoint 名稱
     * @param string|null $connection 資料庫連線名稱
     * @return void
     */
    public function releaseSavepoint(string $name, ?string $connection = null): void
    {
        $connectionName = $connection ?? config('database.default');
        $db = DB::connection($connectionName);
        
        $db->statement("RELEASE SAVEPOINT {$name}");
        
        Log::debug('釋放 Savepoint', [
            'connection' => $connectionName,
            'savepoint' => $name
        ]);
    }

    /**
     * 取得當前交易層級
     *
     * @param string|null $connection 資料庫連線名稱
     * @return int
     */
    public function getTransactionLevel(?string $connection = null): int
    {
        $connectionName = $connection ?? config('database.default');
        return DB::connection($connectionName)->transactionLevel();
    }

    /**
     * 檢查是否在交易中
     *
     * @param string|null $connection 資料庫連線名稱
     * @return bool
     */
    public function inTransaction(?string $connection = null): bool
    {
        return $this->getTransactionLevel($connection) > 0;
    }

    /**
     * 判斷錯誤是否應該重試
     *
     * @param Exception $exception 例外物件
     * @return bool
     */
    protected function shouldRetry(Exception $exception): bool
    {
        $message = $exception->getMessage();
        
        // 可重試的錯誤類型
        $retryableErrors = [
            'Deadlock found',
            'Lock wait timeout exceeded',
            'Connection lost',
            'MySQL server has gone away',
            'Error while sending QUERY packet',
        ];
        
        foreach ($retryableErrors as $error) {
            if (stripos($message, $error) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 將交易推入堆疊
     *
     * @param string $connection 連線名稱
     * @param int $level 交易層級
     */
    protected function pushTransaction(string $connection, int $level): void
    {
        if (!isset($this->transactionStack[$connection])) {
            $this->transactionStack[$connection] = [];
        }
        
        $this->transactionStack[$connection][] = [
            'level' => $level,
            'started_at' => microtime(true),
        ];
    }

    /**
     * 從堆疊中彈出交易
     *
     * @param string $connection 連線名稱
     */
    protected function popTransaction(string $connection): void
    {
        if (isset($this->transactionStack[$connection]) && !empty($this->transactionStack[$connection])) {
            $transaction = array_pop($this->transactionStack[$connection]);
            
            $duration = microtime(true) - $transaction['started_at'];
            
            Log::debug('交易完成', [
                'connection' => $connection,
                'level' => $transaction['level'],
                'duration' => round($duration, 4)
            ]);
        }
    }

    /**
     * 取得交易堆疊資訊
     *
     * @param string|null $connection 連線名稱
     * @return array
     */
    public function getTransactionStack(?string $connection = null): array
    {
        if ($connection) {
            return $this->transactionStack[$connection] ?? [];
        }
        
        return $this->transactionStack;
    }

    /**
     * 清空交易堆疊
     */
    public function clearTransactionStack(): void
    {
        $this->transactionStack = [];
    }
}
