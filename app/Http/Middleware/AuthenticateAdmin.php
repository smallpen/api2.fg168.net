<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理員驗證中介軟體
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
        if (!Auth::check()) {
            return redirect()->route('admin.login')
                ->with('error', '請先登入');
        }

        // 檢查使用者是否為管理員
        $user = Auth::user();
        if (!$user->isAdmin()) {
            Auth::logout();
            return redirect()->route('admin.login')
                ->with('error', '您沒有權限存取管理後台');
        }

        return $next($request);
    }
}
