<?php

namespace App\Console\Commands;

use App\Services\Cache\CacheManager;
use Illuminate\Console\Command;

/**
 * 顯示快取統計資訊命令
 */
class CacheStatsCommand extends Command
{
    /**
     * 命令名稱和簽名
     */
    protected $signature = 'api:cache-stats';

    /**
     * 命令描述
     */
    protected $description = '顯示 API 系統快取統計資訊';

    /**
     * 快取管理器
     */
    protected CacheManager $cacheManager;

    /**
     * 建構函數
     */
    public function __construct(CacheManager $cacheManager)
    {
        parent::__construct();
        $this->cacheManager = $cacheManager;
    }

    /**
     * 執行命令
     */
    public function handle(): int
    {
        try {
            $stats = $this->cacheManager->getStats();
            
            $this->info('=== API 快取統計資訊 ===');
            $this->newLine();
            
            // 配置快取統計
            $this->line('【配置快取】');
            $configStats = $stats['configuration'] ?? [];
            $this->table(
                ['項目', '數值'],
                [
                    ['快取項目數', $configStats['total_cached'] ?? 0],
                    ['快取前綴', $configStats['cache_prefix'] ?? 'N/A'],
                    ['預設 TTL', ($configStats['default_ttl'] ?? 0) . ' 秒'],
                ]
            );
            $this->newLine();
            
            // 權限快取統計
            $this->line('【權限快取】');
            $permStats = $stats['permission'] ?? [];
            $this->table(
                ['項目', '數值'],
                [
                    ['客戶端權限快取', $permStats['client_permissions_cached'] ?? 0],
                    ['角色權限快取', $permStats['role_permissions_cached'] ?? 0],
                    ['Function 權限快取', $permStats['function_permissions_cached'] ?? 0],
                    ['總計', $permStats['total_cached'] ?? 0],
                    ['預設 TTL', ($permStats['default_ttl'] ?? 0) . ' 秒'],
                ]
            );
            $this->newLine();
            
            // 查詢結果快取統計
            $this->line('【查詢結果快取】');
            $queryStats = $stats['query_result'] ?? [];
            $this->table(
                ['項目', '數值'],
                [
                    ['快取項目數', $queryStats['total_cached'] ?? 0],
                    ['快取前綴', $queryStats['cache_prefix'] ?? 'N/A'],
                    ['預設 TTL', ($queryStats['default_ttl'] ?? 0) . ' 秒'],
                ]
            );
            $this->newLine();
            
            $this->info('統計時間: ' . ($stats['timestamp'] ?? now()->toDateTimeString()));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('✗ 取得快取統計失敗: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
