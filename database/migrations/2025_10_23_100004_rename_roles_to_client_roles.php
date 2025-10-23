<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 步驟 1: 重新命名 roles 表為 client_roles
        if (Schema::hasTable('roles') && !Schema::hasTable('client_roles')) {
            Schema::rename('roles', 'client_roles');
            DB::statement("ALTER TABLE client_roles COMMENT='API 客戶端角色表'");
        }
        
        // 步驟 2: 重新命名關聯表 client_roles (pivot) 為 api_client_roles
        // 注意：原本的 client_roles 關聯表已經存在，需要重新命名
        // 但現在 roles 表已經改名為 client_roles，所以需要先檢查
        
        // 檢查是否有舊的 client_roles 關聯表（有 client_id 和 role_id 欄位）
        $pivotTableName = null;
        
        // 查詢所有包含 client_id 和 role_id 的表
        $tables = DB::select("
            SELECT DISTINCT table_name 
            FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND column_name IN ('client_id', 'role_id')
            GROUP BY table_name
            HAVING COUNT(DISTINCT column_name) = 2
        ");
        
        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME ?? $table->table_name;
            // 排除已經是 api_client_roles 的表
            if ($tableName !== 'api_client_roles' && $tableName !== 'client_roles') {
                // 這應該是舊的關聯表，但名稱可能不同
                // 檢查是否有 user_id 欄位，如果沒有就是客戶端關聯表
                $columns = Schema::getColumnListing($tableName);
                if (!in_array('user_id', $columns) && in_array('client_id', $columns) && in_array('role_id', $columns)) {
                    $pivotTableName = $tableName;
                    break;
                }
            }
        }
        
        // 如果找到舊的關聯表，重新命名為 api_client_roles
        if ($pivotTableName && !Schema::hasTable('api_client_roles')) {
            Schema::rename($pivotTableName, 'api_client_roles');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('api_client_roles')) {
            // 需要找出原本的表名，這裡假設是 client_roles_pivot
            // 實際上回滾可能需要手動處理
        }
        
        if (Schema::hasTable('client_roles') && !Schema::hasTable('roles')) {
            Schema::rename('client_roles', 'roles');
        }
    }
};
