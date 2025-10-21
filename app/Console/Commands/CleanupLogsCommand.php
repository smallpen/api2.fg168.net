<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Models\ErrorLog;
use App\Models\SecurityLog;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 清理舊日誌資料命令
 * 
 * 定期清理超過保留期限的日誌資料，避免資料庫過度膨脹
 */
class CleanupLogsCommand extends Command
{
    /**
     * 命令名稱和簽名
     *
     * @var string
     */
    protected $signature = 'logs:cleanup 
                            {--days=30 : 保留天數，預設 30 天}
                            {--type=all : 日誌類型 (all, api, error, security, audit)}
                            {--dry-run : 僅顯示將要刪除的記錄數，不實際刪除}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '清理超過保留期限的日誌資料';

    /**
     * 執行命令
     *
     * @return int
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $type = $this->option('type');
        $dryRun = $this->option('dry-run');

        if ($days < 1) {
            $this->error('保留天數必須大於 0');
            return 1;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("開始清理 {$days} 天前的日誌資料...");
        $this->info("截止日期: {$cutoffDate->toDateTimeString()}");

        if ($dryRun) {
            $this->warn('*** 模擬模式 - 不會實際刪除資料 ***');
        }

        $totalDeleted = 0;

        try {
            DB::beginTransaction();

            // 清理 API 請求日誌
            if ($type === 'all' || $type === 'api') {
                $count = $this->cleanupApiRequestLogs($cutoffDate, $dryRun);
                $totalDeleted += $count;
                $this->info("API 請求日誌: {$count} 筆");
            }

            // 清理錯誤日誌
            if ($type === 'all' || $type === 'error') {
                $count = $this->cleanupErrorLogs($cutoffDate, $dryRun);
                $totalDeleted += $count;
                $this->info("錯誤日誌: {$count} 筆");
            }

            // 清理安全日誌
            if ($type === 'all' || $type === 'security') {
                $count = $this->cleanupSecurityLogs($cutoffDate, $dryRun);
                $totalDeleted += $count;
                $this->info("安全日誌: {$count} 筆");
            }

            // 清理審計日誌（保留時間較長，預設不刪除）
            if ($type === 'audit') {
                $count = $this->cleanupAuditLogs($cutoffDate, $dryRun);
                $totalDeleted += $count;
                $this->info("審計日誌: {$count} 筆");
            }

            if (!$dryRun) {
                DB::commit();
                $this->info("成功清理 {$totalDeleted} 筆日誌資料");
                
                Log::info('日誌清理完成', [
                    'days' => $days,
                    'type' => $type,
                    'total_deleted' => $totalDeleted,
                    'cutoff_date' => $cutoffDate->toDateTimeString(),
                ]);
            } else {
                DB::rollBack();
                $this->info("模擬模式: 將會刪除 {$totalDeleted} 筆日誌資料");
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("清理失敗: {$e->getMessage()}");
            
            Log::error('日誌清理失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * 清理 API 請求日誌
     *
     * @param \Carbon\Carbon $cutoffDate 截止日期
     * @param bool $dryRun 是否為模擬模式
     * @return int 刪除的記錄數
     */
    protected function cleanupApiRequestLogs($cutoffDate, bool $dryRun): int
    {
        $query = ApiRequestLog::where('created_at', '<', $cutoffDate);

        if ($dryRun) {
            return $query->count();
        }

        return $query->delete();
    }

    /**
     * 清理錯誤日誌
     *
     * @param \Carbon\Carbon $cutoffDate 截止日期
     * @param bool $dryRun 是否為模擬模式
     * @return int 刪除的記錄數
     */
    protected function cleanupErrorLogs($cutoffDate, bool $dryRun): int
    {
        $query = ErrorLog::where('created_at', '<', $cutoffDate);

        if ($dryRun) {
            return $query->count();
        }

        return $query->delete();
    }

    /**
     * 清理安全日誌
     *
     * @param \Carbon\Carbon $cutoffDate 截止日期
     * @param bool $dryRun 是否為模擬模式
     * @return int 刪除的記錄數
     */
    protected function cleanupSecurityLogs($cutoffDate, bool $dryRun): int
    {
        $query = SecurityLog::where('created_at', '<', $cutoffDate);

        if ($dryRun) {
            return $query->count();
        }

        return $query->delete();
    }

    /**
     * 清理審計日誌
     *
     * @param \Carbon\Carbon $cutoffDate 截止日期
     * @param bool $dryRun 是否為模擬模式
     * @return int 刪除的記錄數
     */
    protected function cleanupAuditLogs($cutoffDate, bool $dryRun): int
    {
        $query = AuditLog::where('created_at', '<', $cutoffDate);

        if ($dryRun) {
            return $query->count();
        }

        return $query->delete();
    }
}
