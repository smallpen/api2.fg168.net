<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * API Client Model
 * 
 * 代表 API 客戶端，包含驗證憑證和權限設定
 */
class ApiClient extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'api_clients';

    protected $fillable = [
        'name',
        'client_type',
        'api_key',
        'secret',
        'token_expires_at',
        'is_active',
        'rate_limit',
    ];

    protected $hidden = [
        'secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 客戶端類型
     */
    public const TYPE_API_KEY = 'api_key';
    public const TYPE_BEARER_TOKEN = 'bearer_token';
    public const TYPE_OAUTH = 'oauth';

    /**
     * 預設速率限制（每分鐘請求數）
     */
    public const DEFAULT_RATE_LIMIT = 60;

    /**
     * 取得此客戶端的所有 Token
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(ApiToken::class, 'client_id');
    }

    /**
     * 取得此客戶端的角色
     */
    public function clientRoles(): BelongsToMany
    {
        return $this->belongsToMany(ClientRole::class, 'api_client_roles', 'client_id', 'client_role_id')
            ->withTimestamps();
    }

    /**
     * 向後相容的 roles 方法（別名）
     */
    public function roles(): BelongsToMany
    {
        return $this->clientRoles();
    }

    /**
     * 取得此客戶端的 Function 權限
     */
    public function functionPermissions(): HasMany
    {
        return $this->hasMany(FunctionPermission::class, 'client_id');
    }

    /**
     * 檢查客戶端是否啟用
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * 檢查 Token 是否過期
     */
    public function isTokenExpired(): bool
    {
        if (is_null($this->token_expires_at)) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    /**
     * 啟用客戶端
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * 停用客戶端
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * 生成新的 API Key
     */
    public static function generateApiKey(): string
    {
        return 'ak_' . Str::random(32);
    }

    /**
     * 生成新的 Secret
     */
    public static function generateSecret(): string
    {
        return Str::random(64);
    }

    /**
     * 取得速率限制
     */
    public function getRateLimit(): int
    {
        return $this->rate_limit ?? self::DEFAULT_RATE_LIMIT;
    }

    /**
     * 檢查客戶端是否有指定角色
     */
    public function hasClientRole(string $roleName): bool
    {
        return $this->clientRoles()->where('name', $roleName)->exists();
    }

    /**
     * 向後相容的 hasRole 方法（別名）
     */
    public function hasRole(string $roleName): bool
    {
        return $this->hasClientRole($roleName);
    }

    /**
     * 檢查客戶端是否可以存取指定的 Function
     */
    public function canAccessFunction(int $functionId): bool
    {
        // 檢查是否有明確的權限設定
        $permission = $this->functionPermissions()
            ->where('function_id', $functionId)
            ->first();

        if ($permission) {
            return $permission->allowed;
        }

        // 如果沒有明確設定，檢查角色權限
        return $this->hasPermissionThroughRole($functionId);
    }

    /**
     * 透過角色檢查權限
     */
    protected function hasPermissionThroughRole(int $functionId): bool
    {
        foreach ($this->clientRoles as $role) {
            if ($role->canAccessFunction($functionId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 為客戶端指派角色
     */
    public function assignClientRole($role): void
    {
        if (is_string($role)) {
            $role = ClientRole::findByName($role);
            if (!$role) {
                throw new \InvalidArgumentException("角色 '{$role}' 不存在");
            }
        }

        if (!$this->clientRoles()->where('client_roles.id', $role->id)->exists()) {
            $this->clientRoles()->attach($role->id);
        }
    }

    /**
     * 移除客戶端的角色
     */
    public function removeClientRole($role): void
    {
        if (is_string($role)) {
            $role = ClientRole::findByName($role);
            if (!$role) {
                return;
            }
        }

        $this->clientRoles()->detach($role->id);
    }

    /**
     * 同步客戶端的所有角色
     */
    public function syncClientRoles(array $roleIds): void
    {
        $this->clientRoles()->sync($roleIds);
    }

    /**
     * 根據 API Key 查找客戶端
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        return self::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();
    }
}
