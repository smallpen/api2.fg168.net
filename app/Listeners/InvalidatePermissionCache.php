<?php

namespace App\Listeners;

use App\Services\Cache\CacheManager;
use Illuminate\Support\Facades\Log;

/**
 * 權限快取失效監聽器
 * 
 * 當權限或角色變更時自動清除相關快取
 */
class InvalidatePermissionCache
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
            if (!config('apicache.invalidation.auto_clear_on_permission_change', true)) {
                return;
            }

            // 根據事件類型處理不同的快取失效邏輯
            if (isset($event->client)) {
                // 客戶端相關事件
                $this->cacheManager->invalidateClient($event->client->id);
                
                Log::info('客戶端權限快取已自動失效', [
                    'client_id' => $event->client->id,
                    'event' => class_basename($event)
                ]);
            }

            if (isset($event->role)) {
                // 角色相關事件
                $this->cacheManager->invalidateRole($event->role->id);
                
                Log::info('角色權限快取已自動失效', [
                    'role_id' => $event->role->id,
                    'event' => class_basename($event)
                ]);
            }

            if (isset($event->permission)) {
                // 權限相關事件 - 可能需要清除多個快取
                Log::info('權限快取已自動失效', [
                    'permission_id' => $event->permission->id,
                    'event' => class_basename($event)
                ]);
            }

        } catch (\Exception $e) {
            Log::error('權限快取自動失效處理失敗', [
                'error' => $e->getMessage(),
                'event' => class_basename($event)
            ]);
        }
    }
}
