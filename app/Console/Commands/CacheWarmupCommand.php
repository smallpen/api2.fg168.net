<?php

namespace App\Console\Commands;

use App\Services\Cache\CacheManager;
use App\Services\Configuration\ConfigurationManager;
use Illuminate\Console\Command;

/**
 * 預熱快取命令
 */
class CacheWarmupCommand extends Command
{
    /**
     * 命令名稱和簽名
     */
    protected $signature = 'api:cache-warmup 
                            {--functions=* : 要預熱的 Function 識別碼}';

    /**
     * 命令描述
     */
    protected $description = '預熱 API 系統快取';

    /**
     * 快取管理器
     */
    protected CacheManager $cacheManager;

    /**
     * 配置管理器
     */
    protected ConfigurationManager $configurationManager;

    /**
     * 建構函數
     */
    public function __construct(
        CacheManager $cacheManager,
        ConfigurationManager $configurationManager
    ) {
        parent::__construct();
        $this->cacheManager = $cacheManager;
        $this->configurationManager = $configurationManager;
    }

    /**
     * 執行命令
     */
    public function handle(): int
    {
        $this->info('開始預熱快取...');
        
        try {
            $functions = $this->option('functions');
            
            if (empty($functions)) {
                // 從配置檔案讀取要預熱的 Function
                $functions = config('apicache.warmup.functions', []);
            }
            
            if (empty($functions)) {
                // 如果沒有指定，預熱所有啟用的 Function
                $this->line('預熱所有啟用的 Function...');
                $allConfigs = $this->configurationManager->getAllActiveConfigurations();
                $count = count($allConfigs);
                $this->info("✓ 已預熱 {$count} 個 Function 配置");
            } else {
                // 預熱指定的 Function
                $this->line('預熱指定的 Function...');
                $bar = $this->output->createProgressBar(count($functions));
                $bar->start();
                
                $success = 0;
                $failed = 0;
                
                foreach ($functions as $identifier) {
                    try {
                        $this->configurationManager->loadConfiguration($identifier);
                        $success++;
                    } catch (\Exception $e) {
                        $failed++;
                        $this->newLine();
                        $this->warn("預熱失敗: {$identifier} - " . $e->getMessage());
                    }
                    $bar->advance();
                }
                
                $bar->finish();
                $this->newLine(2);
                
                $this->info("✓ 預熱完成: 成功 {$success} 個，失敗 {$failed} 個");
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('✗ 快取預熱失敗: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
