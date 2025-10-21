<?php

namespace App\Listeners;

use App\Services\Cache\CacheManager;
use Illuminate\Support\Facades\Log;

/**
 * Function 快取失效監聽器
 * 
 * 當 Function 更新時自動清除相關快取
 */
class InvalidateFunctionCache
{
    /**
     * 快取管理器
     */
    protected CacheManager $cacheManager;

    /**
     * 建構函數
     */
    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    /**
     * 處理事件
     */
    public function handle($event): void
    {
        try {
            // 檢查是否啟用自動清除快取
            if (!config('apicache.invalidation.auto_clear_on_function_update', true)) {
                return;
            }

            // 取得 Function 資訊
            $function = $event->function ?? null;
            
            if (!$function) {
                return;
            }

            // 清除相關快取
            $this->cacheManager->invalidateFunction(
                $function->identifier,
                $function->id
            );

            Log::info('Function 快取已自動失效', [
                'function_id' => $function->id,
                'function_identifier' => $function->identifier,
                'event' => class_basename($event)
            ]);

        } catch (\Exception $e) {
            Log::error('Function 快取自動失效處理失敗', [
                'error' => $e->getMessage(),
                'event' => class_basename($event)
            ]);
        }
    }
}
