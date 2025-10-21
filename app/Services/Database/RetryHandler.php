<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\Log;
use Throwable;
use Closure;

/**
 * 重試處理器
 * 
 * 提供自動重試機制，用於處理暫時性錯誤
 */
class RetryHandler
{
    /**
     * @var ErrorHandler 錯誤處理器
     */
    protected ErrorHandler $errorHandler;

    /**
     * @var int 最大重試次數
     */
    protected int $maxRetries;

    /**
     * @var int 基礎延遲時間（毫秒）
     */
    protected int $baseDelay;

    /**
     * @var float 延遲倍數（指數退避）
     */
    protected float $delayMultiplier;

    /**
     * @var int 最大延遲時間（毫秒）
     */
    protected int $maxDelay;

    /**
     * 建構函數
     *
     * @param ErrorHandler $errorHandler
     */
    public function __construct(ErrorHandler $errorHandler)
    {
        $this->errorHandler = $errorHandler;
        $this->maxRetries = config('database.max_retries', 3);
        $this->baseDelay = config('database.retry_base_delay', 100);
        $this->delayMultiplier = config('database.retry_delay_multiplier', 2.0);
        $this->maxDelay = config('database.retry_max_delay', 5000);
    }

    /**
     * 執行帶重試的操作
     *
     * @param Closure $callback 要執行的回呼函數
     * @param int|null $maxRetries 最大重試次數（null 使用預設值）
     * @param string|null $operationName 操作名稱（用於日誌）
     * @return mixed 回呼函數的返回值
     * @throws Throwable
     */
    public function retry(Closure $callback, ?int $maxRetries = null, ?string $operationName = null)
    {
        $maxRetries = $maxRetries ?? $this->maxRetries;
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                return $callback();
            } catch (Throwable $exception) {
                $lastException = $exception;
                
                // 判斷是否應該重試
                if (!$this->shouldRetry($exception, $attempt, $maxRetries)) {
                    throw $exception;
                }
                
                // 計算延遲時間
                $delay = $this->calculateDelay($attempt);
                
                // 記錄重試資訊
                $this->logRetry($exception, $attempt, $maxRetries, $delay, $operationName);
                
                // 延遲後重試
                $this->sleep($delay);
            }
        }
        
        // 所有重試都失敗，拋出最後一個例外
        throw $lastException;
    }

    /**
     * 判斷是否應該重試
     *
     * @param Throwable $exception 例外物件
     * @param int $attempt 當前嘗試次數
     * @param int $maxRetries 最大重試次數
     * @return bool
     */
    protected function shouldRetry(Throwable $exception, int $attempt, int $maxRetries): bool
    {
        // 如果已達最大重試次數，不再重試
        if ($attempt >= $maxRetries) {
            return false;
        }
        
        // 使用 ErrorHandler 判斷錯誤是否可重試
        return $this->errorHandler->isRetryable($exception);
    }

    /**
     * 計算延遲時間（指數退避）
     *
     * @param int $attempt 當前嘗試次數
     * @return int 延遲時間（毫秒）
     */
    protected function calculateDelay(int $attempt): int
    {
        // 指數退避：baseDelay * (multiplier ^ (attempt - 1))
        $delay = $this->baseDelay * pow($this->delayMultiplier, $attempt - 1);
        
        // 加入隨機抖動（jitter）以避免雷鳴群效應
        $jitter = rand(0, (int)($delay * 0.1));
        $delay += $jitter;
        
        // 限制最大延遲時間
        return min((int)$delay, $this->maxDelay);
    }

    /**
     * 延遲執行
     *
     * @param int $milliseconds 延遲時間（毫秒）
     */
    protected function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    /**
     * 記錄重試資訊
     *
     * @param Throwable $exception 例外物件
     * @param int $attempt 當前嘗試次數
     * @param int $maxRetries 最大重試次數
     * @param int $delay 延遲時間
     * @param string|null $operationName 操作名稱
     */
    protected function logRetry(
        Throwable $exception,
        int $attempt,
        int $maxRetries,
        int $delay,
        ?string $operationName
    ): void {
        Log::warning('操作失敗，準備重試', [
            'operation' => $operationName ?? 'unknown',
            'attempt' => $attempt,
            'max_retries' => $maxRetries,
            'delay_ms' => $delay,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * 執行帶重試的 Stored Procedure
     *
     * @param StoredProcedureExecutor $executor Stored Procedure 執行器
     * @param string $procedureName Stored Procedure 名稱
     * @param array $parameters 參數陣列
     * @param string|null $connection 資料庫連線名稱
     * @param int|null $maxRetries 最大重試次數
     * @return array 執行結果
     * @throws Throwable
     */
    public function retryStoredProcedure(
        StoredProcedureExecutor $executor,
        string $procedureName,
        array $parameters = [],
        ?string $connection = null,
        ?int $maxRetries = null
    ): array {
        return $this->retry(
            fn() => $executor->execute($procedureName, $parameters, $connection),
            $maxRetries,
            "Stored Procedure: {$procedureName}"
        );
    }

    /**
     * 執行帶重試的交易
     *
     * @param TransactionManager $transactionManager 交易管理器
     * @param Closure $callback 要在交易中執行的回呼函數
     * @param string|null $connection 資料庫連線名稱
     * @param int|null $maxRetries 最大重試次數
     * @return mixed 回呼函數的返回值
     * @throws Throwable
     */
    public function retryTransaction(
        TransactionManager $transactionManager,
        Closure $callback,
        ?string $connection = null,
        ?int $maxRetries = null
    ) {
        return $this->retry(
            fn() => $transactionManager->transaction($callback, $connection, 1),
            $maxRetries,
            'Transaction'
        );
    }

    /**
     * 設定最大重試次數
     *
     * @param int $maxRetries
     * @return self
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * 設定基礎延遲時間
     *
     * @param int $baseDelay 延遲時間（毫秒）
     * @return self
     */
    public function setBaseDelay(int $baseDelay): self
    {
        $this->baseDelay = $baseDelay;
        return $this;
    }

    /**
     * 設定延遲倍數
     *
     * @param float $multiplier
     * @return self
     */
    public function setDelayMultiplier(float $multiplier): self
    {
        $this->delayMultiplier = $multiplier;
        return $this;
    }

    /**
     * 設定最大延遲時間
     *
     * @param int $maxDelay 延遲時間（毫秒）
     * @return self
     */
    public function setMaxDelay(int $maxDelay): self
    {
        $this->maxDelay = $maxDelay;
        return $this;
    }

    /**
     * 取得當前配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'max_retries' => $this->maxRetries,
            'base_delay' => $this->baseDelay,
            'delay_multiplier' => $this->delayMultiplier,
            'max_delay' => $this->maxDelay,
        ];
    }
}
