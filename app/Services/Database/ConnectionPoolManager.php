<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 連線池管理器
 * 
 * 管理資料庫連線池，優化連線使用效率
 */
class ConnectionPoolManager
{
    /**
     * @var array 連線池統計資訊
     */
    protected array $stats = [];

    /**
     * @var int 最大連線數
     */
    protected int $maxConnections;

    /**
     * @var int 最小連線數
     */
    protected int $minConnections;

    /**
     * @var int 連線閒置逾時（秒）
     */
    protected int $idleTimeout;

    /**
     * 建構函數
     */
    public function __construct()
    {
        $this->maxConnections = config('database.pool.max_connections', 100);
        $this->minConnections = config('database.pool.min_connections', 10);
        $this->idleTimeout = config('database.pool.idle_timeout', 300);
        
        $this->initializeStats();
    }

    /**
     * 初始化統計資訊
     */
    protected function initializeStats(): void
    {
        $connections = config('database.connections', []);
        
        foreach (array_keys($connections) as $name) {
            $this->stats[$name] = [
                'active' => 0,
                'idle' => 0,
                'total_created' => 0,
                'total_closed' => 0,
                'last_activity' => null,
            ];
        }
    }

    /**
     * 取得資料庫連線
     *
     * @param string|null $name 連線名稱
     * @return \Illuminate\Database\Connection
     */
    public function getConnection(?string $name = null)
    {
        $connectionName = $name ?? config('database.default');
        
        // 記錄連線使用
        $this->recordConnectionAcquired($connectionName);
        
        // 取得連線
        $connection = DB::connection($connectionName);
        
        // 檢查連線是否有效
        $this->ensureConnectionAlive($connection);
        
        return $connection;
    }

    /**
     * 釋放連線
     *
     * @param string|null $name 連線名稱
     */
    public function releaseConnection(?string $name = null): void
    {
        $connectionName = $name ?? config('database.default');
        
        // 記錄連線釋放
        $this->recordConnectionReleased($connectionName);
    }

    /**
     * 確保連線存活
     *
     * @param \Illuminate\Database\Connection $connection
     */
    protected function ensureConnectionAlive($connection): void
    {
        try {
            // 執行簡單查詢測試連線
            $connection->select('SELECT 1');
        } catch (\Exception $e) {
            Log::warning('資料庫連線已斷開，嘗試重新連線', [
                'error' => $e->getMessage()
            ]);
            
            // 重新連線
            $connection->reconnect();
        }
    }

    /**
     * 記錄連線取得
     *
     * @param string $connectionName 連線名稱
     */
    protected function recordConnectionAcquired(string $connectionName): void
    {
        if (!isset($this->stats[$connectionName])) {
            $this->stats[$connectionName] = [
                'active' => 0,
                'idle' => 0,
                'total_created' => 0,
                'total_closed' => 0,
                'last_activity' => null,
            ];
        }
        
        $this->stats[$connectionName]['active']++;
        $this->stats[$connectionName]['total_created']++;
        $this->stats[$connectionName]['last_activity'] = now();
    }

    /**
     * 記錄連線釋放
     *
     * @param string $connectionName 連線名稱
     */
    protected function recordConnectionReleased(string $connectionName): void
    {
        if (isset($this->stats[$connectionName])) {
            $this->stats[$connectionName]['active']--;
            $this->stats[$connectionName]['idle']++;
            $this->stats[$connectionName]['last_activity'] = now();
        }
    }

    /**
     * 取得連線池統計資訊
     *
     * @param string|null $connectionName 連線名稱，null 表示所有連線
     * @return array
     */
    public function getStats(?string $connectionName = null): array
    {
        if ($connectionName) {
            return $this->stats[$connectionName] ?? [];
        }
        
        return $this->stats;
    }

    /**
     * 清理閒置連線
     *
     * @return int 清理的連線數
     */
    public function cleanupIdleConnections(): int
    {
        $cleaned = 0;
        $now = now();
        
        foreach ($this->stats as $name => $stat) {
            if ($stat['idle'] > $this->minConnections) {
                $lastActivity = $stat['last_activity'];
                
                if ($lastActivity && $now->diffInSeconds($lastActivity) > $this->idleTimeout) {
                    try {
                        DB::connection($name)->disconnect();
                        $this->stats[$name]['idle']--;
                        $this->stats[$name]['total_closed']++;
                        $cleaned++;
                        
                        Log::info('清理閒置連線', [
                            'connection' => $name,
                            'idle_time' => $now->diffInSeconds($lastActivity)
                        ]);
                    } catch (\Exception $e) {
                        Log::error('清理連線失敗', [
                            'connection' => $name,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * 檢查連線池健康狀態
     *
     * @return array 健康狀態資訊
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'connections' => [],
            'warnings' => [],
        ];
        
        foreach ($this->stats as $name => $stat) {
            $totalConnections = $stat['active'] + $stat['idle'];
            
            $health['connections'][$name] = [
                'active' => $stat['active'],
                'idle' => $stat['idle'],
                'total' => $totalConnections,
                'utilization' => $totalConnections > 0 
                    ? round(($stat['active'] / $totalConnections) * 100, 2) 
                    : 0,
            ];
            
            // 檢查是否接近最大連線數
            if ($totalConnections >= $this->maxConnections * 0.9) {
                $health['warnings'][] = "連線 '{$name}' 使用率過高：{$totalConnections}/{$this->maxConnections}";
                $health['status'] = 'warning';
            }
            
            // 檢查是否有連線洩漏
            if ($stat['active'] > $this->maxConnections * 0.8) {
                $health['warnings'][] = "連線 '{$name}' 可能存在連線洩漏：{$stat['active']} 個活動連線";
                $health['status'] = 'warning';
            }
        }
        
        return $health;
    }

    /**
     * 重置統計資訊
     */
    public function resetStats(): void
    {
        foreach ($this->stats as $name => $stat) {
            $this->stats[$name] = [
                'active' => 0,
                'idle' => 0,
                'total_created' => 0,
                'total_closed' => 0,
                'last_activity' => null,
            ];
        }
    }

    /**
     * 關閉所有連線
     */
    public function closeAllConnections(): void
    {
        foreach (array_keys($this->stats) as $name) {
            try {
                DB::connection($name)->disconnect();
                $this->stats[$name]['active'] = 0;
                $this->stats[$name]['idle'] = 0;
                
                Log::info('關閉資料庫連線', ['connection' => $name]);
            } catch (\Exception $e) {
                Log::error('關閉連線失敗', [
                    'connection' => $name,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
