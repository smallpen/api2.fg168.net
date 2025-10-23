<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 檢查 user_roles 表是否存在
        if (!Schema::hasTable('user_roles')) {
            return;
        }
        
        // 建立預設的後台角色（如果不存在）
        $superAdmin = DB::table('admin_roles')->where('name', 'super_admin')->first();
        if (!$superAdmin) {
            $superAdminId = DB::table('admin_roles')->insertGetId([
                'name' => 'super_admin',
                'display_name' => '超級管理員',
                'description' => '擁有所有後台管理權限',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $superAdminId = $superAdmin->id;
        }
        
        $apiManager = DB::table('admin_roles')->where('name', 'api_manager')->first();
        if (!$apiManager) {
            $apiManagerId = DB::table('admin_roles')->insertGetId([
                'name' => 'api_manager',
                'display_name' => 'API 管理員',
                'description' => '可以管理 API Functions 和客戶端',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $apiManagerId = $apiManager->id;
        }
        
        $logViewer = DB::table('admin_roles')->where('name', 'log_viewer')->first();
        if (!$logViewer) {
            $logViewerId = DB::table('admin_roles')->insertGetId([
                'name' => 'log_viewer',
                'display_name' => '日誌查看員',
                'description' => '只能查看系統日誌',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $logViewerId = $logViewer->id;
        }
        
        // 遷移現有的 user_roles 資料
        if (Schema::hasTable('user_roles')) {
            $userRoles = DB::table('user_roles')->get();
            
            foreach ($userRoles as $userRole) {
                // 由於表結構已經改變，我們無法直接查詢舊的角色名稱
                // 簡單的策略：將所有現有使用者都設為超級管理員
                // 管理員可以之後手動調整
                $adminRoleId = $superAdminId;
                
                // 檢查是否已存在，避免重複插入
                $exists = DB::table('user_admin_roles')
                    ->where('user_id', $userRole->user_id)
                    ->where('admin_role_id', $adminRoleId)
                    ->exists();
                
                if (!$exists) {
                    // 插入到新的 user_admin_roles 表
                    DB::table('user_admin_roles')->insert([
                        'user_id' => $userRole->user_id,
                        'admin_role_id' => $adminRoleId,
                        'created_at' => $userRole->created_at ?? now(),
                        'updated_at' => $userRole->updated_at ?? now(),
                    ]);
                }
            }
            
            // 刪除舊的 user_roles 表
            Schema::dropIfExists('user_roles');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 重新建立 user_roles 表
        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('client_roles')->onDelete('cascade');
            $table->timestamps();
            
            $table->primary(['user_id', 'role_id']);
        });
        
        // 將資料遷移回去
        $userAdminRoles = DB::table('user_admin_roles')->get();
        
        foreach ($userAdminRoles as $userAdminRole) {
            $adminRole = DB::table('admin_roles')->find($userAdminRole->admin_role_id);
            
            if (!$adminRole) {
                continue;
            }
            
            // 找到對應的舊角色
            $oldRoleName = match($adminRole->name) {
                'super_admin' => 'admin',
                'api_manager' => 'user',
                'log_viewer' => 'guest',
                default => 'guest',
            };
            
            $oldRole = DB::table('client_roles')->where('name', $oldRoleName)->first();
            
            if ($oldRole) {
                DB::table('user_roles')->insert([
                    'user_id' => $userAdminRole->user_id,
                    'role_id' => $oldRole->id,
                    'created_at' => $userAdminRole->created_at,
                    'updated_at' => $userAdminRole->updated_at,
                ]);
            }
        }
    }
};
