<?php

namespace App\Console\Commands;

use App\Services\Cache\CacheManager;
use Illuminate\Console\Command;

/**
 * 清除 API 快取命令
 */
class CacheClearCommand extends Command
{
    /**
     * 命令名稱和簽名
     */
    protected $signature = 'api:cache-clear 
                            {type? : 快取類型 (configuration|permission|query|all)}
                            {--function= : Function 識別碼}
                            {--client= : 客戶端 ID}
                            {--role= : 角色 ID}';

    /**
     * 命令描述
     */
    protected $description = '清除 API 系統快取';

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
        $type = $this->argument('type') ?? 'all';
        
        $this->info("正在清除 {$type} 快取...");
        
        try {
            switch ($type) {
                case 'configuration':
                case 'config':
                    $this->clearConfigurationCache();
                    break;
                    
                case 'permission':
                case 'perm':
                    $this->clearPermissionCache();
                    break;
                    
                case 'query':
                case 'result':
                    $this->clearQueryCache();
                    break;
                    
                case 'all':
                default:
                    $this->clearAllCache();
                    break;
            }
            
            $this->info('✓ 快取清除完成');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('✗ 快取清除失敗: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 清除配置快取
     */
    protected function clearConfigurationCache(): void
    {
        $functionIdentifier = $this->option('function');
        
        if ($functionIdentifier) {
            $this->cacheManager->getConfigurationCache()->forget($functionIdentifier);
            $this->line("已清除 Function '{$functionIdentifier}' 的配置快取");
        } else {
            $this->cacheManager->getConfigurationCache()->flush();
            $this->line('已清除所有配置快取');
        }
    }

    /**
     * 清除權限快取
     */
    protected function clearPermissionCache(): void
    {
        $clientId = $this->option('client');
        $roleId = $this->option('role');
        
        if ($clientId) {
            $this->cacheManager->invalidateClient((int) $clientId);
            $this->line("已清除客戶端 {$clientId} 的權限快取");
        } elseif ($roleId) {
            $this->cacheManager->invalidateRole((int) $roleId);
            $this->line("已清除角色 {$roleId} 的權限快取");
        } else {
            $this->cacheManager->getPermissionCache()->flush();
            $this->line('已清除所有權限快取');
        }
    }

    /**
     * 清除查詢結果快取
     */
    protected function clearQueryCache(): void
    {
        $functionIdentifier = $this->option('function');
        
        if ($functionIdentifier) {
            $this->cacheManager->getQueryResultCache()->forgetByFunction($functionIdentifier);
            $this->line("已清除 Function '{$functionIdentifier}' 的查詢快取");
        } else {
            $this->cacheManager->getQueryResultCache()->flush();
            $this->line('已清除所有查詢結果快取');
        }
    }

    /**
     * 清除所有快取
     */
    protected function clearAllCache(): void
    {
        $this->cacheManager->flushAll();
        $this->line('已清除所有系統快取');
    }
}
