<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 執行資料庫種子
     */
    public function run(): void
    {
        $this->command->info('開始執行資料庫 Seeder...');
        $this->command->info('');

        // 1. 建立角色和權限
        $this->call(RoleSeeder::class);
        $this->command->info('');

        // 2. 建立管理員使用者
        $this->call(AdminUserSeeder::class);
        $this->command->info('');

        // 3. 建立 API 客戶端
        $this->call(ApiClientSeeder::class);
        $this->command->info('');

        // 4. 建立範例 API Functions
        $this->call(SampleFunctionSeeder::class);
        $this->command->info('');

        $this->command->info('=================================');
        $this->command->info('所有 Seeder 執行完成！');
        $this->command->info('=================================');
    }
}
