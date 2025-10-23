<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * AdminRole Model
 * 
 * 代表後台管理員角色，用於控制後台功能的存取權限
 */
class AdminRole extends Model
{
    use HasFactory;

    protected $table = 'admin_roles';

    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 預設後台角色
     */
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_API_MANAGER = 'api_manager';
    public const ROLE_LOG_VIEWER = 'log_viewer';

    /**
     * 取得此角色的使用者
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_admin_roles', 'admin_role_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * 取得此角色的權限
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_role_permissions', 'admin_role_id', 'admin_permission_id')
            ->withTimestamps();
    }

    /**
     * 檢查角色是否有指定權限
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }

    /**
     * 為角色新增權限
     */
    public function grantPermission(AdminPermission $permission): void
    {
        if (!$this->permissions()->where('id', $permission->id)->exists()) {
            $this->permissions()->attach($permission->id);
        }
    }

    /**
     * 移除角色的權限
     */
    public function revokePermission(AdminPermission $permission): void
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
     * 檢查是否為超級管理員角色
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === self::ROLE_SUPER_ADMIN;
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
    public static function findOrCreateByName(string $name, string $displayName, ?string $description = null): self
    {
        $role = self::findByName($name);

        if (!$role) {
            $role = self::create([
                'name' => $name,
                'display_name' => $displayName,
                'description' => $description ?? $displayName,
            ]);
        }

        return $role;
    }

    /**
     * 檢查是否為系統預設角色（不可刪除）
     */
    public function isSystemRole(): bool
    {
        return in_array($this->name, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_API_MANAGER,
            self::ROLE_LOG_VIEWER,
        ]);
    }
}
