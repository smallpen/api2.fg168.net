<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Stored Procedure 例外
 * 
 * 當 Stored Procedure 執行失敗時拋出此例外
 */
class StoredProcedureException extends Exception
{
    /**
     * @var string|null Stored Procedure 名稱
     */
    protected ?string $procedureName;

    /**
     * @var array 執行參數
     */
    protected array $parameters;

    /**
     * @var string|null SQL 狀態碼
     */
    protected ?string $sqlState;

    /**
     * @var int|null 錯誤代碼
     */
    protected ?int $errorCode;

    /**
     * 建構函數
     *
     * @param string $message 錯誤訊息
     * @param string|null $procedureName Stored Procedure 名稱
     * @param array $parameters 執行參數
     * @param string|null $sqlState SQL 狀態碼
     * @param int|null $errorCode 錯誤代碼
     * @param Throwable|null $previous 前一個例外
     */
    public function __construct(
        string $message = "",
        ?string $procedureName = null,
        array $parameters = [],
        ?string $sqlState = null,
        ?int $errorCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->procedureName = $procedureName;
        $this->parameters = $parameters;
        $this->sqlState = $sqlState;
        $this->errorCode = $errorCode;
    }

    /**
     * 取得 Stored Procedure 名稱
     *
     * @return string|null
     */
    public function getProcedureName(): ?string
    {
        return $this->procedureName;
    }

    /**
     * 取得執行參數
     *
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * 取得 SQL 狀態碼
     *
     * @return string|null
     */
    public function getSqlState(): ?string
    {
        return $this->sqlState;
    }

    /**
     * 取得錯誤代碼
     *
     * @return int|null
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * 取得例外的上下文資訊
     *
     * @return array
     */
    public function getContext(): array
    {
        return [
            'procedure_name' => $this->procedureName,
            'parameters' => $this->parameters,
            'sql_state' => $this->sqlState,
            'error_code' => $this->errorCode,
        ];
    }

    /**
     * 判斷是否為逾時錯誤
     *
     * @return bool
     */
    public function isTimeout(): bool
    {
        return stripos($this->message, 'timeout') !== false
            || stripos($this->message, 'max_execution_time') !== false;
    }

    /**
     * 判斷是否為死鎖錯誤
     *
     * @return bool
     */
    public function isDeadlock(): bool
    {
        return stripos($this->message, 'deadlock') !== false
            || $this->errorCode === 1213;
    }

    /**
     * 判斷是否為連線錯誤
     *
     * @return bool
     */
    public function isConnectionError(): bool
    {
        $connectionErrors = [
            'connection lost',
            'server has gone away',
            'error while sending',
            'broken pipe',
        ];
        
        foreach ($connectionErrors as $error) {
            if (stripos($this->message, $error) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 判斷錯誤是否可重試
     *
     * @return bool
     */
    public function isRetryable(): bool
    {
        return $this->isDeadlock() 
            || $this->isConnectionError()
            || $this->isTimeout();
    }
}
