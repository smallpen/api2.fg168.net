<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 檢查並建立 client_roles 表（如果不存在）
        if (!Schema::hasTable('client_roles')) {
            Schema::create('client_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique()->comment('角色名稱');
                $table->string('display_name')->comment('顯示名稱');
                $table->text('description')->nullable()->comment('角色描述');
                $table->timestamps();
                
                $table->index('name');
            });
            
            // 建立預設的客戶端角色
            DB::table('client_roles')->insert([
                [
                    'name' => 'internal',
                    'display_name' => '內部系統',
                    'description' => '內部系統，擁有完整的 API 存取權限',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'partner',
                    'display_name' => '合作夥伴',
                    'description' => '外部合作夥伴，擁有部分 API 存取權限',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'test',
                    'display_name' => '測試用戶',
                    'description' => '測試環境使用，擁有有限的 API 存取權限',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
        
        // 檢查並建立 api_client_roles 關聯表（如果不存在）
        if (!Schema::hasTable('api_client_roles')) {
            Schema::create('api_client_roles', function (Blueprint $table) {
                $table->foreignId('client_id')->constrained('api_clients')->onDelete('cascade');
                $table->foreignId('client_role_id')->constrained('client_roles')->onDelete('cascade');
                $table->timestamps();
                
                $table->primary(['client_id', 'client_role_id']);
            });
        }
        
        // 檢查並建立 client_role_permissions 關聯表（如果不存在）
        if (!Schema::hasTable('client_role_permissions')) {
            Schema::create('client_role_permissions', function (Blueprint $table) {
                $table->foreignId('client_role_id')->constrained('client_roles')->onDelete('cascade');
                $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
                $table->timestamps();
                
                $table->primary(['client_role_id', 'permission_id'], 'client_role_permission_primary');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_role_permissions');
        Schema::dropIfExists('api_client_roles');
        Schema::dropIfExists('client_roles');
    }
};
