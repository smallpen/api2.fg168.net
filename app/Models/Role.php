<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role Model
 * 
 * 代表系統角色，用於基於角色的存取控制（RBAC）
 */
class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 預設角色
     */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';
    public const ROLE_GUEST = 'guest';

    /**
     * 取得此角色的權限
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id')
            ->withTimestamps();
    }

    /**
     * 取得擁有此角色的客戶端
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(ApiClient::class, 'client_roles', 'role_id', 'client_id')
            ->withTimestamps();
    }

    /**
     * 檢查角色是否有指定權限
     */
    public function hasPermission(string $resourceType, ?int $resourceId = null, ?string $action = null): bool
    {
        $query = $this->permissions()
            ->where('resource_type', $resourceType);

        if (!is_null($resourceId)) {
            $query->where(function ($q) use ($resourceId) {
                $q->where('resource_id', $resourceId)
                  ->orWhereNull('resource_id');
            });
        }

        if (!is_null($action)) {
            $query->where(function ($q) use ($action) {
                $q->where('action', $action)
                  ->orWhere('action', '*');
            });
        }

        return $query->exists();
    }

    /**
     * 檢查角色是否可以存取指定的 Function
     */
    public function canAccessFunction(int $functionId): bool
    {
        return $this->hasPermission('function', $functionId, 'execute');
    }

    /**
     * 為角色新增權限
     */
    public function grantPermission(Permission $permission): void
    {
        if (!$this->permissions()->where('id', $permission->id)->exists()) {
            $this->permissions()->attach($permission->id);
        }
    }

    /**
     * 移除角色的權限
     */
    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    /**
     * 同步角色的所有權限
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * 檢查是否為管理員角色
     */
    public function isAdmin(): bool
    {
        return $this->name === self::ROLE_ADMIN;
    }

    /**
     * 根據角色名稱查找
     */
    public static function findByName(string $name): ?self
    {
        return self::where('name', $name)->first();
    }

    /**
     * 取得或建立角色
     */
    public static function findOrCreateByName(string $name, ?string $description = null): self
    {
        $role = self::findByName($name);

        if (!$role) {
            $role = self::create([
                'name' => $name,
                'description' => $description ?? $name,
            ]);
        }

        return $role;
    }
}
