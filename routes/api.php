<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 在這裡註冊應用程式的 API 路由。這些路由會被 RouteServiceProvider
| 載入，並且會自動套用 "api" 中介軟體群組。
|
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now()->toIso8601String(),
        'service' => 'Dynamic API Manager',
    ]);
});

// Rate Limiting 範例路由
Route::middleware(['throttle.api:10,60'])->group(function () {
    Route::get('/test/rate-limit', function () {
        return response()->json([
            'message' => '此端點每分鐘限制 10 次請求',
            'timestamp' => now()->toIso8601String(),
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| API Gateway Routes
|--------------------------------------------------------------------------
|
| 統一的 API Gateway 端點，所有動態 API 請求都通過此端點執行
|
*/

Route::prefix('v1')->group(function () {
    // API Gateway 執行端點
    // 套用驗證、授權和限流 Middleware
    Route::post('/execute', [App\Http\Controllers\Api\ApiGatewayController::class, 'execute'])
        ->middleware([
            'auth.api',      // 驗證 Middleware
            'authorize.api', // 授權 Middleware
            'throttle.api',  // 限流 Middleware
        ])
        ->name('api.gateway.execute');
});

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| 後台管理 API 端點，用於管理 API Functions、客戶端和系統配置
|
*/

Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {
    // 測試認證端點（開發用）
    Route::get('/test-auth', function () {
        return response()->json([
            'success' => true,
            'message' => 'API 認證成功',
            'user' => Auth::user() ? [
                'id' => Auth::user()->id,
                'name' => Auth::user()->name,
                'email' => Auth::user()->email,
                'is_admin' => Auth::user()->isAdmin(),
            ] : null,
            'guard' => 'sanctum',
        ]);
    })->name('api.admin.test-auth');

    // API Function 管理
    Route::prefix('functions')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\FunctionController::class, 'index'])
            ->name('admin.functions.index');
        Route::get('/{id}', [App\Http\Controllers\Admin\FunctionController::class, 'show'])
            ->name('admin.functions.show');
        Route::post('/', [App\Http\Controllers\Admin\FunctionController::class, 'store'])
            ->name('admin.functions.store');
        Route::put('/{id}', [App\Http\Controllers\Admin\FunctionController::class, 'update'])
            ->name('admin.functions.update');
        Route::delete('/{id}', [App\Http\Controllers\Admin\FunctionController::class, 'destroy'])
            ->name('admin.functions.destroy');
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Admin\FunctionController::class, 'toggleStatus'])
            ->name('admin.functions.toggle-status');
    });

    // API 客戶端管理
    Route::prefix('clients')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\ClientController::class, 'index'])
            ->name('admin.clients.index');
        Route::get('/{id}', [App\Http\Controllers\Admin\ClientController::class, 'show'])
            ->name('admin.clients.show');
        Route::post('/', [App\Http\Controllers\Admin\ClientController::class, 'store'])
            ->name('admin.clients.store');
        Route::put('/{id}', [App\Http\Controllers\Admin\ClientController::class, 'update'])
            ->name('admin.clients.update');
        Route::delete('/{id}', [App\Http\Controllers\Admin\ClientController::class, 'destroy'])
            ->name('admin.clients.destroy');
        Route::post('/{id}/toggle-status', [App\Http\Controllers\Admin\ClientController::class, 'toggleStatus'])
            ->name('admin.clients.toggle-status');
        Route::post('/{id}/regenerate-api-key', [App\Http\Controllers\Admin\ClientController::class, 'regenerateApiKey'])
            ->name('admin.clients.regenerate-api-key');
        Route::post('/{id}/regenerate-secret', [App\Http\Controllers\Admin\ClientController::class, 'regenerateSecret'])
            ->name('admin.clients.regenerate-secret');
        Route::post('/{id}/revoke', [App\Http\Controllers\Admin\ClientController::class, 'revoke'])
            ->name('admin.clients.revoke');
        Route::get('/{id}/permissions', [App\Http\Controllers\Admin\ClientController::class, 'getPermissions'])
            ->name('admin.clients.permissions.get');
        Route::post('/{id}/permissions', [App\Http\Controllers\Admin\ClientController::class, 'updatePermissions'])
            ->name('admin.clients.permissions.update');
    });

    // 角色管理
    Route::prefix('roles')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\RoleController::class, 'index'])
            ->name('admin.roles.index');
        Route::post('/', [App\Http\Controllers\Admin\RoleController::class, 'store'])
            ->name('admin.roles.store');
        Route::delete('/{id}', [App\Http\Controllers\Admin\RoleController::class, 'destroy'])
            ->name('admin.roles.destroy');
        Route::get('/{id}/permissions', [App\Http\Controllers\Admin\RoleController::class, 'getPermissions'])
            ->name('admin.roles.permissions.get');
        Route::post('/{id}/permissions', [App\Http\Controllers\Admin\RoleController::class, 'updatePermissions'])
            ->name('admin.roles.permissions.update');
    });

    // Stored Procedures 管理
    Route::prefix('stored-procedures')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\StoredProcedureController::class, 'index'])
            ->name('admin.stored-procedures.index');
        Route::get('/{procedureName}/parameters', [App\Http\Controllers\Admin\StoredProcedureController::class, 'parameters'])
            ->name('admin.stored-procedures.parameters');
    });

    // 日誌查詢
    Route::prefix('logs')->group(function () {
        // API 請求日誌
        Route::get('/api-requests', [App\Http\Controllers\Admin\LogController::class, 'apiRequestLogs'])
            ->name('admin.logs.api-requests');
        Route::get('/api-requests/{id}', [App\Http\Controllers\Admin\LogController::class, 'apiRequestLogDetail'])
            ->name('admin.logs.api-requests.detail');
        
        // 錯誤日誌
        Route::get('/errors', [App\Http\Controllers\Admin\LogController::class, 'errorLogs'])
            ->name('admin.logs.errors');
        Route::get('/errors/{id}', [App\Http\Controllers\Admin\LogController::class, 'errorLogDetail'])
            ->name('admin.logs.errors.detail');
        
        // 安全日誌
        Route::get('/security', [App\Http\Controllers\Admin\LogController::class, 'securityLogs'])
            ->name('admin.logs.security');
        Route::get('/security/{id}', [App\Http\Controllers\Admin\LogController::class, 'securityLogDetail'])
            ->name('admin.logs.security.detail');
        
        // 審計日誌
        Route::get('/audit', [App\Http\Controllers\Admin\LogController::class, 'auditLogs'])
            ->name('admin.logs.audit');
        Route::get('/audit/{id}', [App\Http\Controllers\Admin\LogController::class, 'auditLogDetail'])
            ->name('admin.logs.audit.detail');
        
        // 統計資訊
        Route::get('/statistics', [App\Http\Controllers\Admin\LogController::class, 'statistics'])
            ->name('admin.logs.statistics');
    });
});
