<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User Model
 * 
 * 代表系統管理員使用者
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 取得使用者建立的 API Functions
     */
    public function apiFunctions()
    {
        return $this->hasMany(ApiFunction::class, 'created_by');
    }

    /**
     * 取得使用者的後台管理角色
     * 
     * 注意：雖然資料庫結構支援多角色，但系統限制每個使用者只能有一個後台角色
     */
    public function adminRoles()
    {
        return $this->belongsToMany(AdminRole::class, 'user_admin_roles', 'user_id', 'admin_role_id')
            ->withTimestamps();
    }

    /**
     * 檢查使用者是否擁有指定的後台角色
     *
     * @param string $roleName 角色名稱
     * @return bool
     */
    public function hasAdminRole(string $roleName): bool
    {
        return $this->adminRoles()->where('name', $roleName)->exists();
    }

    /**
     * 檢查使用者是否為管理員
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasAdminRole(AdminRole::ROLE_SUPER_ADMIN);
    }

    /**
     * 檢查使用者是否有指定的後台權限
     *
     * @param string $permissionName 權限名稱
     * @return bool
     */
    public function hasAdminPermission(string $permissionName): bool
    {
        // 超級管理員擁有所有權限
        if ($this->isAdmin()) {
            return true;
        }

        // 檢查使用者的角色是否有此權限
        foreach ($this->adminRoles as $role) {
            if ($role->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 檢查使用者是否可以管理 API Functions
     *
     * @return bool
     */
    public function canManageFunctions(): bool
    {
        return $this->hasAdminPermission(AdminPermission::PERM_MANAGE_FUNCTIONS);
    }

    /**
     * 檢查使用者是否可以管理客戶端
     *
     * @return bool
     */
    public function canManageClients(): bool
    {
        return $this->hasAdminPermission(AdminPermission::PERM_MANAGE_CLIENTS);
    }

    /**
     * 檢查使用者是否可以管理系統帳號
     *
     * @return bool
     */
    public function canManageUsers(): bool
    {
        return $this->hasAdminPermission(AdminPermission::PERM_MANAGE_USERS);
    }

    /**
     * 檢查使用者是否可以管理權限
     *
     * @return bool
     */
    public function canManagePermissions(): bool
    {
        return $this->hasAdminPermission(AdminPermission::PERM_MANAGE_PERMISSIONS);
    }

    /**
     * 檢查使用者是否可以查看日誌
     *
     * @return bool
     */
    public function canViewLogs(): bool
    {
        return $this->hasAdminPermission(AdminPermission::PERM_VIEW_LOGS);
    }

    /**
     * 為使用者指派後台角色
     *
     * @param AdminRole|string $role 角色物件或角色名稱
     * @return void
     */
    public function assignAdminRole($role): void
    {
        if (is_string($role)) {
            $role = AdminRole::findByName($role);
            if (!$role) {
                throw new \InvalidArgumentException("角色 '{$role}' 不存在");
            }
        }

        if (!$this->adminRoles()->where('admin_roles.id', $role->id)->exists()) {
            $this->adminRoles()->attach($role->id);
        }
    }

    /**
     * 移除使用者的後台角色
     *
     * @param AdminRole|string $role 角色物件或角色名稱
     * @return void
     */
    public function removeAdminRole($role): void
    {
        if (is_string($role)) {
            $role = AdminRole::findByName($role);
            if (!$role) {
                return;
            }
        }

        $this->adminRoles()->detach($role->id);
    }

    /**
     * 同步使用者的所有後台角色
     * 
     * 注意：系統限制每個使用者只能有一個後台角色，
     * 傳入的陣列應該只包含一個角色 ID
     *
     * @param array $roleIds 角色 ID 陣列（應只包含一個 ID）
     * @return void
     */
    public function syncAdminRoles(array $roleIds): void
    {
        $this->adminRoles()->sync($roleIds);
    }
}
