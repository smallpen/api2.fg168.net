<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 管理員驗證控制器
 */
class AuthController extends Controller
{
    /**
     * 顯示登入表單
     *
     * @return \Illuminate\View\View
     */
    public function showLoginForm()
    {
        return view('admin.login');
    }

    /**
     * 處理登入請求
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(Request $request)
    {
        // 驗證輸入
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => '請輸入電子郵件',
            'email.email' => '電子郵件格式不正確',
            'password.required' => '請輸入密碼',
        ]);

        $remember = $request->boolean('remember');

        // 記錄登入嘗試
        Log::info('管理員登入嘗試', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
        ]);

        // 嘗試登入
        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // 檢查是否為管理員
            if (!$user->isAdmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                Log::warning('非管理員嘗試登入後台', [
                    'email' => $credentials['email'],
                    'ip' => $request->ip(),
                ]);

                return back()->withErrors([
                    'email' => '您沒有權限存取管理後台',
                ])->withInput($request->only('email'));
            }

            Log::info('管理員登入成功', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return redirect()->intended(route('admin.dashboard'));
        }

        // 登入失敗
        Log::warning('管理員登入失敗', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
        ]);

        return back()->withErrors([
            'email' => '電子郵件或密碼錯誤',
        ])->withInput($request->only('email'));
    }

    /**
     * 處理登出請求
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        Log::info('管理員登出', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'ip' => $request->ip(),
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', '已成功登出');
    }
}
