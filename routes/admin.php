<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| 這裡註冊所有管理介面的路由。這些路由會套用 'web' 和 'auth.admin' 
| 中介軟體群組，確保只有已驗證的管理員可以存取。
|
*/

// Admin 登入路由（不需要驗證）
Route::get('/login', function () {
    return view('admin.login');
})->name('admin.login');

Route::post('/login', function () {
    // TODO: 實作登入邏輯
})->name('admin.login.post');

Route::post('/logout', function () {
    auth()->logout();
    return redirect()->route('admin.login')
        ->with('success', '已成功登出');
})->name('admin.logout');

// 需要管理員驗證的路由
Route::middleware(['auth.admin'])->group(function () {
    
    // Dashboard 路由
    Route::get('/', [DashboardController::class, 'index'])
        ->name('admin.dashboard');
    
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('admin.dashboard.index');
    
    // 系統健康狀態 API
    Route::get('/health', [DashboardController::class, 'health'])
        ->name('admin.health');
    
    // API Functions 管理路由（預留給後續任務）
    // Route::resource('functions', FunctionController::class);
    
    // API Clients 管理路由（預留給後續任務）
    // Route::resource('clients', ClientController::class);
    
    // 日誌查詢路由（預留給後續任務）
    // Route::get('logs', [LogController::class, 'index'])->name('admin.logs.index');
    // Route::get('logs/{id}', [LogController::class, 'show'])->name('admin.logs.show');
});
