<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 定義應用程式的命令排程
     */
    protected function schedule(Schedule $schedule): void
    {
        // 每天凌晨 2 點清理 30 天前的日誌資料
        $schedule->command('logs:cleanup --days=30 --type=all')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * 註冊應用程式的命令
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
