<?php

namespace App\Repositories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Client Repository
 * 
 * 提供 API Client 的資料存取操作
 */
class ClientRepository extends BaseRepository
{
    /**
     * ClientRepository constructor
     */
    public function __construct(ApiClient $model)
    {
        parent::__construct($model);
    }

    /**
     * 根據 API Key 查找客戶端
     */
    public function findByApiKey(string $apiKey): ?ApiClient
    {
        return $this->model
            ->with(['roles', 'tokens'])
            ->where('api_key', $apiKey)
            ->first();
    }

    /**
     * 根據 API Key 查找啟用的客戶端
     */
    public function findActiveByApiKey(string $apiKey): ?ApiClient
    {
        return $this->model
            ->with(['roles', 'tokens'])
            ->where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 取得所有啟用的客戶端
     */
    public function getAllActive(): Collection
    {
        return $this->model
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * 取得分頁的客戶端列表
     */
    public function paginate(int $perPage = 15, array $filters = [])
    {
        $query = $this->model->newQuery()->with(['roles']);

        // 套用篩選條件
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['client_type'])) {
            $query->where('client_type', $filters['client_type']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('api_key', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 建立新客戶端並生成憑證
     */
    public function createWithCredentials(array $data): ApiClient
    {
        // 生成 API Key 和 Secret
        $data['api_key'] = $data['api_key'] ?? ApiClient::generateApiKey();
        $data['secret'] = Hash::make($data['secret'] ?? ApiClient::generateSecret());
        $data['is_active'] = $data['is_active'] ?? true;
        $data['rate_limit'] = $data['rate_limit'] ?? ApiClient::DEFAULT_RATE_LIMIT;

        $client = $this->create($data);

        // 指派角色（如果提供）
        if (isset($data['roles']) && is_array($data['roles'])) {
            $client->roles()->sync($data['roles']);
        }

        return $client->load('roles');
    }

    /**
     * 更新客戶端
     */
    public function updateClient(int $id, array $data): ApiClient
    {
        $client = $this->findOrFail($id);

        // 如果提供新的 secret，進行加密
        if (isset($data['secret'])) {
            $data['secret'] = Hash::make($data['secret']);
        }

        $client->update($data);

        // 更新角色（如果提供）
        if (isset($data['roles'])) {
            $client->roles()->sync($data['roles']);
        }

        return $client->fresh(['roles']);
    }

    /**
     * 啟用客戶端
     */
    public function activate(int $id): bool
    {
        $client = $this->findOrFail($id);
        return $client->activate();
    }

    /**
     * 停用客戶端
     */
    public function deactivate(int $id): bool
    {
        $client = $this->findOrFail($id);
        return $client->deactivate();
    }

    /**
     * 重新生成 API Key
     */
    public function regenerateApiKey(int $id): ApiClient
    {
        $client = $this->findOrFail($id);
        $client->update([
            'api_key' => ApiClient::generateApiKey(),
        ]);

        return $client;
    }

    /**
     * 重新生成 Secret
     */
    public function regenerateSecret(int $id): array
    {
        $client = $this->findOrFail($id);
        $newSecret = ApiClient::generateSecret();
        
        $client->update([
            'secret' => Hash::make($newSecret),
        ]);

        // 返回明文 secret（僅此一次）
        return [
            'client' => $client,
            'secret' => $newSecret,
        ];
    }

    /**
     * 驗證客戶端憑證
     */
    public function verifyCredentials(string $apiKey, string $secret): ?ApiClient
    {
        $client = $this->findActiveByApiKey($apiKey);

        if (!$client) {
            return null;
        }

        if (!Hash::check($secret, $client->secret)) {
            return null;
        }

        return $client;
    }

    /**
     * 檢查客戶端是否可以存取指定的 Function
     */
    public function canAccessFunction(int $clientId, int $functionId): bool
    {
        $client = $this->findOrFail($clientId);
        return $client->canAccessFunction($functionId);
    }

    /**
     * 為客戶端指派角色
     */
    public function assignRole(int $clientId, int $roleId): void
    {
        $client = $this->findOrFail($clientId);
        
        if (!$client->roles()->where('role_id', $roleId)->exists()) {
            $client->roles()->attach($roleId);
        }
    }

    /**
     * 移除客戶端的角色
     */
    public function removeRole(int $clientId, int $roleId): void
    {
        $client = $this->findOrFail($clientId);
        $client->roles()->detach($roleId);
    }

    /**
     * 取得客戶端的統計資訊
     */
    public function getStatistics(int $clientId): array
    {
        $client = $this->findOrFail($clientId);

        return [
            'total_tokens' => $client->tokens()->count(),
            'active_tokens' => $client->tokens()->where('expires_at', '>', now())->count(),
            'total_roles' => $client->roles()->count(),
            'is_active' => $client->is_active,
            'rate_limit' => $client->getRateLimit(),
        ];
    }
}
