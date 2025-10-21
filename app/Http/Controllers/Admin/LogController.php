<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiRequestLog;
use App\Models\ErrorLog;
use App\Models\SecurityLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * 日誌查詢控制器
 * 
 * 提供各類日誌的查詢和檢視功能
 */
class LogController extends Controller
{
    /**
     * 取得 API 請求日誌列表（分頁）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function apiRequestLogs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 20);
            
            // 建立查詢
            $query = ApiRequestLog::with(['client', 'function']);
            
            // 時間範圍篩選
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->input('start_date'));
            }
            
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->input('end_date'));
            }
            
            // Function 篩選
            if ($request->has('function_id')) {
                $query->where('function_id', $request->input('function_id'));
            }
            
            // 客戶端篩選
            if ($request->has('client_id')) {
                $query->where('client_id', $request->input('client_id'));
            }
            
            // HTTP 狀態碼篩選
            if ($request->has('http_status')) {
                $query->where('http_status', $request->input('http_status'));
            }
            
            // IP 地址篩選
            if ($request->has('ip_address')) {
                $query->where('ip_address', $request->input('ip_address'));
            }
            
            // 排序（預設按時間倒序）
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('取得 API 請求日誌列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌列表失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得單一 API 請求日誌詳情
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function apiRequestLogDetail(int $id): JsonResponse
    {
        try {
            $log = ApiRequestLog::with(['client', 'function'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOG_NOT_FOUND',
                    'message' => '找不到指定的日誌記錄',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得 API 請求日誌詳情失敗', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌詳情失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得錯誤日誌列表（分頁）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function errorLogs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 20);
            
            // 建立查詢
            $query = ErrorLog::query();
            
            // 時間範圍篩選
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->input('start_date'));
            }
            
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->input('end_date'));
            }
            
            // 錯誤類型篩選
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }
            
            // 關鍵字搜尋
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('message', 'like', "%{$search}%")
                      ->orWhere('stack_trace', 'like', "%{$search}%");
                });
            }
            
            // 排序（預設按時間倒序）
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('取得錯誤日誌列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌列表失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得單一錯誤日誌詳情
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function errorLogDetail(int $id): JsonResponse
    {
        try {
            $log = ErrorLog::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOG_NOT_FOUND',
                    'message' => '找不到指定的日誌記錄',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得錯誤日誌詳情失敗', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌詳情失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得安全日誌列表（分頁）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function securityLogs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 20);
            
            // 建立查詢
            $query = SecurityLog::with(['client']);
            
            // 時間範圍篩選
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->input('start_date'));
            }
            
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->input('end_date'));
            }
            
            // 事件類型篩選
            if ($request->has('event_type')) {
                $query->where('event_type', $request->input('event_type'));
            }
            
            // 客戶端篩選
            if ($request->has('client_id')) {
                $query->where('client_id', $request->input('client_id'));
            }
            
            // IP 地址篩選
            if ($request->has('ip_address')) {
                $query->where('ip_address', $request->input('ip_address'));
            }
            
            // 排序（預設按時間倒序）
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('取得安全日誌列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌列表失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得單一安全日誌詳情
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function securityLogDetail(int $id): JsonResponse
    {
        try {
            $log = SecurityLog::with(['client'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOG_NOT_FOUND',
                    'message' => '找不到指定的日誌記錄',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得安全日誌詳情失敗', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌詳情失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得審計日誌列表（分頁）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function auditLogs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 20);
            
            // 建立查詢
            $query = AuditLog::with(['user']);
            
            // 時間範圍篩選
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->input('start_date'));
            }
            
            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->input('end_date'));
            }
            
            // 操作類型篩選
            if ($request->has('action')) {
                $query->where('action', $request->input('action'));
            }
            
            // 資源類型篩選
            if ($request->has('resource_type')) {
                $query->where('resource_type', $request->input('resource_type'));
            }
            
            // 使用者篩選
            if ($request->has('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }
            
            // 排序（預設按時間倒序）
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('取得審計日誌列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌列表失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得單一審計日誌詳情
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function auditLogDetail(int $id): JsonResponse
    {
        try {
            $log = AuditLog::with(['user'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'LOG_NOT_FOUND',
                    'message' => '找不到指定的日誌記錄',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得審計日誌詳情失敗', [
                'log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得日誌詳情失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得日誌統計資訊
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // 時間範圍（預設最近 7 天）
            $startDate = $request->input('start_date', now()->subDays(7)->startOfDay());
            $endDate = $request->input('end_date', now()->endOfDay());
            
            // API 請求統計
            $apiRequestStats = [
                'total' => ApiRequestLog::whereBetween('created_at', [$startDate, $endDate])->count(),
                'success' => ApiRequestLog::whereBetween('created_at', [$startDate, $endDate])
                    ->whereBetween('http_status', [200, 299])->count(),
                'client_error' => ApiRequestLog::whereBetween('created_at', [$startDate, $endDate])
                    ->whereBetween('http_status', [400, 499])->count(),
                'server_error' => ApiRequestLog::whereBetween('created_at', [$startDate, $endDate])
                    ->whereBetween('http_status', [500, 599])->count(),
                'avg_execution_time' => ApiRequestLog::whereBetween('created_at', [$startDate, $endDate])
                    ->avg('execution_time'),
            ];
            
            // 錯誤日誌統計
            $errorStats = [
                'total' => ErrorLog::whereBetween('created_at', [$startDate, $endDate])->count(),
            ];
            
            // 安全日誌統計
            $securityStats = [
                'total' => SecurityLog::whereBetween('created_at', [$startDate, $endDate])->count(),
                'authentication_failures' => SecurityLog::whereBetween('created_at', [$startDate, $endDate])
                    ->where('event_type', 'authentication_failure')->count(),
                'authorization_failures' => SecurityLog::whereBetween('created_at', [$startDate, $endDate])
                    ->where('event_type', 'authorization_failure')->count(),
            ];
            
            // 審計日誌統計
            $auditStats = [
                'total' => AuditLog::whereBetween('created_at', [$startDate, $endDate])->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    'api_requests' => $apiRequestStats,
                    'errors' => $errorStats,
                    'security' => $securityStats,
                    'audit' => $auditStats,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('取得日誌統計資訊失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得統計資訊失敗',
                ],
            ], 500);
        }
    }
}
