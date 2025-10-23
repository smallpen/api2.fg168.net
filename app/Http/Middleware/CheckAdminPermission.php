<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 檢查後台管理權限中介軟體
 */
class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNAUTHORIZED',
                        'message' => '請先登入',
                    ],
                ], 401);
            }
            
            return redirect()->route('admin.login');
        }

        // 超級管理員擁有所有權限
        if ($user->isAdmin()) {
            return $next($request);
        }

        // 檢查是否有特定權限
        if (!$user->hasAdminPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => '您沒有權限執行此操作',
                    ],
                ], 403);
            }

            abort(403, '您沒有權限執行此操作');
        }

        return $next($request);
    }
}
