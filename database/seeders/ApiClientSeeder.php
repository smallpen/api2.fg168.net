<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * API Client Seeder
 * 
 * 建立測試用 API 客戶端
 */
class ApiClientSeeder extends Seeder
{
    /**
     * 執行 Seeder
     */
    public function run(): void
    {
        // 建立管理員客戶端（用於測試完整權限）
        $adminClient = $this->createAdminClient();
        
        // 建立一般使用者客戶端（用於測試基本 API 存取）
        $userClient = $this->createUserClient();
        
        // 建立訪客客戶端（用於測試受限存取）
        $guestClient = $this->createGuestClient();

        // 建立高速率限制的企業客戶端
        $enterpriseClient = $this->createEnterpriseClient();

        $this->command->info('');
        $this->command->info('=== API 客戶端建立完成 ===');
        $this->command->info('');
        $this->displayClientInfo($adminClient, '管理員客戶端');
        $this->displayClientInfo($userClient, '一般使用者客戶端');
        $this->displayClientInfo($guestClient, '訪客客戶端');
        $this->displayClientInfo($enterpriseClient, '企業客戶端');
        $this->command->info('');
        $this->command->warn('請妥善保管 API Key 和 Secret！');
    }

    /**
     * 建立管理員客戶端
     */
    private function createAdminClient(): ApiClient
    {
        $apiKey = ApiClient::generateApiKey();
        $secret = ApiClient::generateSecret();

        $client = ApiClient::firstOrCreate(
            ['name' => 'Admin Test Client'],
            [
                'client_type' => ApiClient::TYPE_API_KEY,
                'api_key' => $apiKey,
                'secret' => Hash::make($secret),
                'is_active' => true,
                'rate_limit' => 1000, // 每分鐘 1000 次請求
            ]
        );

        // 儲存原始 secret 以便顯示（僅在建立時）
        $client->plain_secret = $secret;

        // 指派管理員角色
        $adminRole = Role::findByName(Role::ROLE_ADMIN);
        if ($adminRole && !$client->hasRole(Role::ROLE_ADMIN)) {
            $client->roles()->attach($adminRole->id);
        }

        return $client;
    }

    /**
     * 建立一般使用者客戶端
     */
    private function createUserClient(): ApiClient
    {
        $apiKey = ApiClient::generateApiKey();
        $secret = ApiClient::generateSecret();

        $client = ApiClient::firstOrCreate(
            ['name' => 'User Test Client'],
            [
                'client_type' => ApiClient::TYPE_API_KEY,
                'api_key' => $apiKey,
                'secret' => Hash::make($secret),
                'is_active' => true,
                'rate_limit' => 60, // 每分鐘 60 次請求
            ]
        );

        $client->plain_secret = $secret;

        // 指派一般使用者角色
        $userRole = Role::findByName(Role::ROLE_USER);
        if ($userRole && !$client->hasRole(Role::ROLE_USER)) {
            $client->roles()->attach($userRole->id);
        }

        return $client;
    }

    /**
     * 建立訪客客戶端
     */
    private function createGuestClient(): ApiClient
    {
        $apiKey = ApiClient::generateApiKey();
        $secret = ApiClient::generateSecret();

        $client = ApiClient::firstOrCreate(
            ['name' => 'Guest Test Client'],
            [
                'client_type' => ApiClient::TYPE_API_KEY,
                'api_key' => $apiKey,
                'secret' => Hash::make($secret),
                'is_active' => true,
                'rate_limit' => 30, // 每分鐘 30 次請求
            ]
        );

        $client->plain_secret = $secret;

        // 指派訪客角色
        $guestRole = Role::findByName(Role::ROLE_GUEST);
        if ($guestRole && !$client->hasRole(Role::ROLE_GUEST)) {
            $client->roles()->attach($guestRole->id);
        }

        return $client;
    }

    /**
     * 建立企業客戶端
     */
    private function createEnterpriseClient(): ApiClient
    {
        $apiKey = ApiClient::generateApiKey();
        $secret = ApiClient::generateSecret();

        $client = ApiClient::firstOrCreate(
            ['name' => 'Enterprise Test Client'],
            [
                'client_type' => ApiClient::TYPE_BEARER_TOKEN,
                'api_key' => $apiKey,
                'secret' => Hash::make($secret),
                'is_active' => true,
                'rate_limit' => 10000, // 每分鐘 10000 次請求
            ]
        );

        $client->plain_secret = $secret;

        // 指派一般使用者角色（企業客戶端通常有標準權限）
        $userRole = Role::findByName(Role::ROLE_USER);
        if ($userRole && !$client->hasRole(Role::ROLE_USER)) {
            $client->roles()->attach($userRole->id);
        }

        return $client;
    }

    /**
     * 顯示客戶端資訊
     */
    private function displayClientInfo(ApiClient $client, string $label): void
    {
        $this->command->info("--- {$label} ---");
        $this->command->info("Name: {$client->name}");
        $this->command->info("Type: {$client->client_type}");
        $this->command->info("API Key: {$client->api_key}");
        
        if (isset($client->plain_secret)) {
            $this->command->info("Secret: {$client->plain_secret}");
        }
        
        $this->command->info("Rate Limit: {$client->rate_limit} requests/minute");
        $roles = $client->roles->pluck('name')->implode(', ');
        $this->command->info("Roles: {$roles}");
        $this->command->info('');
    }
}
