<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Admin User Seeder
 * 
 * 建立系統管理員帳號
 */
class AdminUserSeeder extends Seeder
{
    /**
     * 執行 Seeder
     */
    public function run(): void
    {
        // 建立預設管理員帳號
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '系統管理員',
                'password' => Hash::make('admin123456'),
            ]
        );

        // 指派管理員角色
        $adminRole = Role::findByName(Role::ROLE_ADMIN);
        if ($adminRole) {
            $admin->assignRole($adminRole);
            $this->command->info("管理員帳號已建立並指派管理員角色");
        } else {
            $this->command->warn("找不到管理員角色，請先執行 RoleSeeder");
        }

        // 建立測試用一般使用者
        $testUser = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => '測試使用者',
                'password' => Hash::make('user123456'),
            ]
        );

        // 指派一般使用者角色
        $userRole = Role::findByName(Role::ROLE_USER);
        if ($userRole) {
            $testUser->assignRole($userRole);
            $this->command->info("測試使用者帳號已建立並指派使用者角色");
        }

        $this->command->info('');
        $this->command->info('=== 管理員帳號資訊 ===');
        $this->command->info("Email: admin@example.com");
        $this->command->info("Password: admin123456");
        $this->command->info('');
        $this->command->info('=== 測試使用者帳號資訊 ===');
        $this->command->info("Email: user@example.com");
        $this->command->info("Password: user123456");
        $this->command->info('');
        $this->command->warn('請在生產環境中修改預設密碼！');
    }
}
