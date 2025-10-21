<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 效能監控中介軟體
 * 
 * 監控請求處理時間和記憶體使用量
 */
class PerformanceMonitoring
{
    /**
     * 處理傳入的請求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 記錄開始時間和記憶體使用量
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // 處理請求
        $response = $next($request);

        // 計算執行時間和記憶體使用量
        $executionTime = (microtime(true) - $startTime) * 1000; // 轉換為毫秒
        $memoryUsage = (memory_get_usage() - $startMemory) / 1024 / 1024; // 轉換為 MB

        // 添加效能資訊到回應標頭（僅在非 Production 環境）
        if (config('app.env') !== 'production') {
            $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
            $response->headers->set('X-Memory-Usage', round($memoryUsage, 2) . 'MB');
            $response->headers->set('X-Memory-Peak', round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB');
        }

        // 記錄慢請求（超過 2 秒）
        if ($executionTime > 2000) {
            Log::warning('慢請求偵測', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => round($executionTime, 2) . 'ms',
                'memory_usage' => round($memoryUsage, 2) . 'MB',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return $response;
    }
}
