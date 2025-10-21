<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin 驗證中介軟體
 * 
 * 確保只有已驗證的管理員使用者可以存取 Admin UI
 */
class AuthenticateAdmin
{
    /**
     * 處理傳入的請求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 檢查使用者是否已登入
        if (!auth()->check()) {
            // 如果是 API 請求，返回 JSON 錯誤
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'AUTHENTICATION_REQUIRED',
                        'message' => '需要管理員驗證',
                    ],
                ], 401);
            }
            
            // 否則重導向到登入頁面
            return redirect()->route('admin.login')
                ->with('error', '請先登入以存取管理介面');
        }

        // 檢查使用者是否有管理員權限
        $user = auth()->user();
        if (!$user->hasRole('admin')) {
            // 如果是 API 請求，返回 JSON 錯誤
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PERMISSION_DENIED',
                        'message' => '權限不足，需要管理員權限',
                    ],
                ], 403);
            }
            
            // 否則重導向到首頁
            return redirect()->route('home')
                ->with('error', '權限不足，無法存取管理介面');
        }

        return $next($request);
    }
}
