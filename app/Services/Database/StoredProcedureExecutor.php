<?php

namespace App\Services\Database;

use App\Exceptions\StoredProcedureException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use Exception;
use Throwable;

/**
 * Stored Procedure 執行器
 * 
 * 負責執行資料庫 Stored Procedure，包含參數映射、連線管理和交易處理
 */
class StoredProcedureExecutor
{
    /**
     * @var ParameterMapper 參數映射器
     */
    protected ParameterMapper $parameterMapper;

    /**
     * @var ErrorHandler 錯誤處理器
     */
    protected ErrorHandler $errorHandler;

    /**
     * @var int 查詢逾時時間（秒）
     */
    protected int $queryTimeout;

    /**
     * @var int 最大重試次數
     */
    protected int $maxRetries;

    /**
     * 建構函數
     *
     * @param ParameterMapper $parameterMapper
     * @param ErrorHandler $errorHandler
     */
    public function __construct(ParameterMapper $parameterMapper, ErrorHandler $errorHandler)
    {
        $this->parameterMapper = $parameterMapper;
        $this->errorHandler = $errorHandler;
        $this->queryTimeout = config('database.query_timeout', 30);
        $this->maxRetries = config('database.max_retries', 3);
    }

    /**
     * 執行 Stored Procedure
     *
     * @param string $procedureName Stored Procedure 名稱
     * @param array $parameters 參數陣列
     * @param string|null $connection 資料庫連線名稱
     * @return array 執行結果
     * @throws Exception
     */
    public function execute(string $procedureName, array $parameters = [], ?string $connection = null): array
    {
        $startTime = microtime(true);
        
        try {
            // 映射參數
            $mappedParams = $this->parameterMapper->map($parameters);
            
            // 取得資料庫連線
            $db = $connection ? DB::connection($connection) : DB::connection();
            
            // 設定查詢逾時
            $this->setQueryTimeout($db);
            
            // 執行 Stored Procedure
            $result = $this->executeProcedure($db, $procedureName, $mappedParams);
            
            $executionTime = microtime(true) - $startTime;
            
            Log::info('Stored Procedure 執行成功', [
                'procedure' => $procedureName,
                'execution_time' => $executionTime,
                'params_count' => count($parameters)
            ]);
            
            return [
                'success' => true,
                'data' => $result,
                'execution_time' => $executionTime
            ];
            
        } catch (Throwable $e) {
            $executionTime = microtime(true) - $startTime;
            
            Log::error('Stored Procedure 執行失敗', [
                'procedure' => $procedureName,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime
            ]);
            
            // 建立 StoredProcedureException
            $spException = $this->errorHandler->createStoredProcedureException(
                $e,
                $procedureName,
                $parameters
            );
            
            throw $spException;
        }
    }

    /**
     * 在交易中執行 Stored Procedure
     *
     * @param string $procedureName Stored Procedure 名稱
     * @param array $parameters 參數陣列
     * @param string|null $connection 資料庫連線名稱
     * @return array 執行結果
     * @throws Exception
     */
    public function executeInTransaction(string $procedureName, array $parameters = [], ?string $connection = null): array
    {
        $db = $connection ? DB::connection($connection) : DB::connection();
        
        try {
            $db->beginTransaction();
            
            $result = $this->execute($procedureName, $parameters, $connection);
            
            $db->commit();
            
            return $result;
            
        } catch (Exception $e) {
            $db->rollBack();
            
            Log::error('交易回滾', [
                'procedure' => $procedureName,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * 批次執行多個 Stored Procedure（在同一交易中）
     *
     * @param array $procedures 程序陣列，格式：[['name' => 'sp_name', 'params' => [...]]]
     * @param string|null $connection 資料庫連線名稱
     * @return array 執行結果陣列
     * @throws Exception
     */
    public function executeBatch(array $procedures, ?string $connection = null): array
    {
        $db = $connection ? DB::connection($connection) : DB::connection();
        $results = [];
        
        try {
            $db->beginTransaction();
            
            foreach ($procedures as $index => $procedure) {
                $procedureName = $procedure['name'] ?? null;
                $parameters = $procedure['params'] ?? [];
                
                if (!$procedureName) {
                    throw new Exception("批次執行中第 {$index} 個程序缺少名稱");
                }
                
                $result = $this->execute($procedureName, $parameters, $connection);
                $results[] = $result;
            }
            
            $db->commit();
            
            return [
                'success' => true,
                'results' => $results,
                'count' => count($results)
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            
            Log::error('批次執行失敗', [
                'error' => $e->getMessage(),
                'completed' => count($results)
            ]);
            
            throw $e;
        }
    }

    /**
     * 執行 Stored Procedure 的核心邏輯
     *
     * @param \Illuminate\Database\Connection $db 資料庫連線
     * @param string $procedureName Stored Procedure 名稱
     * @param array $parameters 映射後的參數
     * @return array 執行結果
     */
    protected function executeProcedure($db, string $procedureName, array $parameters): array
    {
        // 建立參數佔位符
        $placeholders = $this->buildPlaceholders($parameters);
        
        // 建立 CALL 語句
        $sql = "CALL {$procedureName}({$placeholders})";
        
        // 執行查詢
        $pdo = $db->getPdo();
        $statement = $pdo->prepare($sql);
        
        // 綁定參數
        $this->bindParameters($statement, $parameters);
        
        // 執行
        $statement->execute();
        
        // 取得結果
        $results = [];
        do {
            $rowset = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($rowset) {
                $results[] = $rowset;
            }
        } while ($statement->nextRowset());
        
        // 如果只有一個結果集，直接返回該結果集
        if (count($results) === 1) {
            return $results[0];
        }
        
        return $results;
    }

    /**
     * 建立參數佔位符字串
     *
     * @param array $parameters 參數陣列
     * @return string 佔位符字串，例如：?, ?, ?
     */
    protected function buildPlaceholders(array $parameters): string
    {
        return implode(', ', array_fill(0, count($parameters), '?'));
    }

    /**
     * 綁定參數到 PDO Statement
     *
     * @param \PDOStatement $statement PDO Statement
     * @param array $parameters 參數陣列
     */
    protected function bindParameters($statement, array $parameters): void
    {
        foreach ($parameters as $index => $param) {
            $position = $index + 1; // PDO 參數位置從 1 開始
            $value = $param['value'];
            $type = $this->getPdoType($param['type']);
            
            $statement->bindValue($position, $value, $type);
        }
    }

    /**
     * 取得 PDO 參數類型
     *
     * @param string $type 資料類型
     * @return int PDO 參數類型常數
     */
    protected function getPdoType(string $type): int
    {
        return match($type) {
            'integer', 'int' => PDO::PARAM_INT,
            'boolean', 'bool' => PDO::PARAM_BOOL,
            'null' => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * 設定查詢逾時時間
     *
     * @param \Illuminate\Database\Connection $db 資料庫連線
     */
    protected function setQueryTimeout($db): void
    {
        try {
            $db->statement("SET SESSION max_execution_time = {$this->queryTimeout}000");
        } catch (Exception $e) {
            Log::warning('無法設定查詢逾時時間', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 檢查 Stored Procedure 是否存在
     *
     * @param string $procedureName Stored Procedure 名稱
     * @param string|null $connection 資料庫連線名稱
     * @return bool
     */
    public function procedureExists(string $procedureName, ?string $connection = null): bool
    {
        $db = $connection ? DB::connection($connection) : DB::connection();
        $database = $db->getDatabaseName();
        
        $result = $db->select(
            "SELECT COUNT(*) as count FROM information_schema.ROUTINES 
             WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = ? AND ROUTINE_TYPE = 'PROCEDURE'",
            [$database, $procedureName]
        );
        
        return $result[0]->count > 0;
    }

    /**
     * 取得 Stored Procedure 的參數資訊
     *
     * @param string $procedureName Stored Procedure 名稱
     * @param string|null $connection 資料庫連線名稱
     * @return array 參數資訊陣列
     */
    public function getProcedureParameters(string $procedureName, ?string $connection = null): array
    {
        $db = $connection ? DB::connection($connection) : DB::connection();
        $database = $db->getDatabaseName();
        
        $parameters = $db->select(
            "SELECT PARAMETER_NAME, DATA_TYPE, PARAMETER_MODE, ORDINAL_POSITION
             FROM information_schema.PARAMETERS
             WHERE SPECIFIC_SCHEMA = ? AND SPECIFIC_NAME = ?
             ORDER BY ORDINAL_POSITION",
            [$database, $procedureName]
        );
        
        return array_map(function($param) {
            return [
                'name' => $param->PARAMETER_NAME,
                'type' => $param->DATA_TYPE,
                'mode' => $param->PARAMETER_MODE,
                'position' => $param->ORDINAL_POSITION
            ];
        }, $parameters);
    }
}
