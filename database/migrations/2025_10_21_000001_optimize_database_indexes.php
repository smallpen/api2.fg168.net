<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 執行 Migration
     * 
     * 優化資料庫索引以提升查詢效能
     */
    public function up(): void
    {
        // 優化 api_functions 資料表索引
        Schema::table('api_functions', function (Blueprint $table) {
            // 複合索引：常用的查詢條件組合
            $table->index(['is_active', 'identifier'], 'idx_active_identifier');
            $table->index(['is_active', 'created_at'], 'idx_active_created');
        });

        // 優化 function_parameters 資料表索引
        Schema::table('function_parameters', function (Blueprint $table) {
            // 複合索引：function_id 和 position 常一起查詢
            $table->index(['function_id', 'position'], 'idx_function_position');
            $table->index(['function_id', 'is_required'], 'idx_function_required');
        });

        // 優化 api_clients 資料表索引
        Schema::table('api_clients', function (Blueprint $table) {
            // 複合索引：驗證時常用的查詢條件
            $table->index(['is_active', 'client_type'], 'idx_active_type');
        });

        // 優化 api_tokens 資料表索引
        Schema::table('api_tokens', function (Blueprint $table) {
            // 複合索引：Token 驗證時的查詢條件
            $table->index(['client_id', 'expires_at'], 'idx_client_expires');
            $table->index(['token', 'expires_at'], 'idx_token_expires');
        });

        // 優化 api_request_logs 資料表索引
        Schema::table('api_request_logs', function (Blueprint $table) {
            // 複合索引：日誌查詢常用的條件
            $table->index(['created_at', 'http_status'], 'idx_created_status');
            $table->index(['client_id', 'created_at'], 'idx_client_created');
            $table->index(['function_id', 'created_at'], 'idx_function_created');
        });

        // 優化 function_permissions 資料表索引
        Schema::table('function_permissions', function (Blueprint $table) {
            // 複合索引：權限檢查時的查詢條件
            $table->index(['function_id', 'client_id', 'allowed'], 'idx_permission_check');
        });

        // 分析資料表以更新統計資訊（MySQL）
        if (DB::getDriverName() === 'mysql') {
            $tables = [
                'api_functions',
                'function_parameters',
                'function_responses',
                'function_error_mappings',
                'api_clients',
                'api_tokens',
                'roles',
                'permissions',
                'role_permissions',
                'client_roles',
                'function_permissions',
                'api_request_logs',
                'error_logs',
                'security_logs',
                'audit_logs',
            ];

            foreach ($tables as $table) {
                DB::statement("ANALYZE TABLE {$table}");
            }
        }
    }

    /**
     * 回滾 Migration
     */
    public function down(): void
    {
        // 移除優化索引
        Schema::table('api_functions', function (Blueprint $table) {
            $table->dropIndex('idx_active_identifier');
            $table->dropIndex('idx_active_created');
        });

        Schema::table('function_parameters', function (Blueprint $table) {
            $table->dropIndex('idx_function_position');
            $table->dropIndex('idx_function_required');
        });

        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropIndex('idx_active_type');
        });

        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_client_expires');
            $table->dropIndex('idx_token_expires');
        });

        Schema::table('api_request_logs', function (Blueprint $table) {
            $table->dropIndex('idx_created_status');
            $table->dropIndex('idx_client_created');
            $table->dropIndex('idx_function_created');
        });

        Schema::table('function_permissions', function (Blueprint $table) {
            $table->dropIndex('idx_permission_check');
        });
    }
};
