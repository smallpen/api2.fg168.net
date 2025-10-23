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
        // 建立後台權限
        $permissions = [
            [
                'name' => 'manage_functions',
                'display_name' => '管理 API Functions',
                'description' => '可以建立、編輯、刪除 API Functions',
            ],
            [
                'name' => 'manage_clients',
                'display_name' => '管理客戶端',
                'description' => '可以建立、編輯、刪除 API 客戶端',
            ],
            [
                'name' => 'manage_users',
                'display_name' => '管理系統帳號',
                'description' => '可以建立、編輯、刪除系統管理員帳號',
            ],
            [
                'name' => 'manage_permissions',
                'display_name' => '管理權限配置',
                'description' => '可以配置客戶端和角色的權限',
            ],
            [
                'name' => 'view_logs',
                'display_name' => '查看日誌',
                'description' => '可以查看系統日誌',
            ],
            [
                'name' => 'manage_roles',
                'display_name' => '管理角色',
                'description' => '可以建立、編輯、刪除角色',
            ],
            [
                'name' => 'view_dashboard',
                'display_name' => '查看儀表板',
                'description' => '可以查看系統儀表板和統計資訊',
            ],
        ];
        
        $permissionIds = [];
        foreach ($permissions as $permission) {
            $permissionIds[$permission['name']] = DB::table('admin_permissions')->insertGetId([
                'name' => $permission['name'],
                'display_name' => $permission['display_name'],
                'description' => $permission['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // 為超級管理員指派所有權限
        $superAdmin = DB::table('admin_roles')->where('name', 'super_admin')->first();
        if ($superAdmin) {
            foreach ($permissionIds as $permissionId) {
                DB::table('admin_role_permissions')->insert([
                    'admin_role_id' => $superAdmin->id,
                    'admin_permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        // 為 API 管理員指派部分權限
        $apiManager = DB::table('admin_roles')->where('name', 'api_manager')->first();
        if ($apiManager) {
            $apiManagerPermissions = [
                'manage_functions',
                'manage_clients',
                'manage_permissions',
                'view_logs',
                'view_dashboard',
            ];
            
            foreach ($apiManagerPermissions as $permName) {
                if (isset($permissionIds[$permName])) {
                    DB::table('admin_role_permissions')->insert([
                        'admin_role_id' => $apiManager->id,
                        'admin_permission_id' => $permissionIds[$permName],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        
        // 為日誌查看員指派查看權限
        $logViewer = DB::table('admin_roles')->where('name', 'log_viewer')->first();
        if ($logViewer) {
            $logViewerPermissions = ['view_logs', 'view_dashboard'];
            
            foreach ($logViewerPermissions as $permName) {
                if (isset($permissionIds[$permName])) {
                    DB::table('admin_role_permissions')->insert([
                        'admin_role_id' => $logViewer->id,
                        'admin_permission_id' => $permissionIds[$permName],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('admin_role_permissions')->truncate();
        DB::table('admin_permissions')->truncate();
    }
};
