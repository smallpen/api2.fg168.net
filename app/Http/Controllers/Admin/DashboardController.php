<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiFunction;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin Dashboard 控制器
 * 
 * 提供管理介面的儀表板功能，顯示系統概覽和統計資訊
 */
class DashboardController extends Controller
{
    /**
     * 顯示 Dashboard 首頁
     *
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $stats = $this->getSystemStats();
        
        // 如果是 API 請求，返回 JSON
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        }
        
        // 否則返回視圖
        return view('admin.dashboard', compact('stats'));
    }

    /**
     * 取得系統統計資訊
     *
     * @return array
     */
    protected function getSystemStats(): array
    {
        // 取得 API Functions 統計
        $totalFunctions = ApiFunction::count();
        $activeFunctions = ApiFunction::where('is_active', true)->count();
        $inactiveFunctions = $totalFunctions - $activeFunctions;

        // 取得 API Clients 統計
        $totalClients = ApiClient::count();
        $activeClients = ApiClient::where('is_active', true)->count();
        $inactiveClients = $totalClients - $activeClients;

        // 取得今日 API 請求統計
        $todayRequests = ApiRequestLog::whereDate('created_at', today())->count();
        $todaySuccessRequests = ApiRequestLog::whereDate('created_at', today())
            ->whereBetween('http_status', [200, 299])
            ->count();
        $todayErrorRequests = $todayRequests - $todaySuccessRequests;

        // 取得最近 7 天的請求趨勢
        $requestTrend = ApiRequestLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN http_status BETWEEN 200 AND 299 THEN 1 ELSE 0 END) as success'),
                DB::raw('SUM(CASE WHEN http_status >= 400 THEN 1 ELSE 0 END) as error')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // 取得最常使用的 API Functions (Top 10)
        $topFunctions = ApiRequestLog::select(
                'function_id',
                DB::raw('COUNT(*) as request_count')
            )
            ->with('function:id,name,identifier')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('function_id')
            ->orderBy('request_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'function_id' => $log->function_id,
                    'function_name' => $log->function->name ?? 'Unknown',
                    'function_identifier' => $log->function->identifier ?? 'unknown',
                    'request_count' => $log->request_count,
                ];
            });

        // 取得平均回應時間
        $avgResponseTime = ApiRequestLog::whereDate('created_at', today())
            ->avg('execution_time');

        return [
            'functions' => [
                'total' => $totalFunctions,
                'active' => $activeFunctions,
                'inactive' => $inactiveFunctions,
            ],
            'clients' => [
                'total' => $totalClients,
                'active' => $activeClients,
                'inactive' => $inactiveClients,
            ],
            'requests' => [
                'today' => $todayRequests,
                'today_success' => $todaySuccessRequests,
                'today_error' => $todayErrorRequests,
                'avg_response_time' => round($avgResponseTime ?? 0, 3),
            ],
            'trends' => [
                'requests' => $requestTrend,
                'top_functions' => $topFunctions,
            ],
        ];
    }

    /**
     * 取得系統健康狀態
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        try {
            // 檢查資料庫連線
            DB::connection()->getPdo();
            $dbStatus = 'healthy';
        } catch (\Exception $e) {
            $dbStatus = 'unhealthy';
        }

        try {
            // 檢查 Redis 連線
            \Illuminate\Support\Facades\Redis::connection()->ping();
            $redisStatus = 'healthy';
        } catch (\Exception $e) {
            $redisStatus = 'unhealthy';
        }

        $overallStatus = ($dbStatus === 'healthy' && $redisStatus === 'healthy') 
            ? 'healthy' 
            : 'degraded';

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $overallStatus,
                'timestamp' => now()->toIso8601String(),
                'services' => [
                    'database' => $dbStatus,
                    'redis' => $redisStatus,
                ],
            ],
        ]);
    }
}
