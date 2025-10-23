<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * AdminPermission Model
 * 
 * 代表後台管理功能權限
 */
class AdminPermission extends Model
{
    use HasFactory;

    protected $table = 'admin_permissions';

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
     * 後台功能權限常數
     */
    public const PERM_MANAGE_FUNCTIONS = 'manage_functions';
    public const PERM_MANAGE_CLIENTS = 'manage_clients';
    public const PERM_MANAGE_USERS = 'manage_users';
    public const PERM_MANAGE_PERMISSIONS = 'manage_permissions';
    public const PERM_VIEW_LOGS = 'view_logs';
    public const PERM_MANAGE_ROLES = 'manage_roles';
    public const PERM_VIEW_DASHBOARD = 'view_dashboard';

    /**
     * 取得擁有此權限的角色
     */
    public function adminRoles(): BelongsToMany
    {
        return $this->belongsToMany(AdminRole::class, 'admin_role_permissions', 'admin_permission_id', 'admin_role_id')
            ->withTimestamps();
    }

    /**
     * 根據權限名稱查找
     */
    public static function findByName(string $name): ?self
    {
        return self::where('name', $name)->first();
    }

    /**
     * 取得或建立權限
     */
    public static function findOrCreateByName(string $name, string $displayName, ?string $description = null): self
    {
        $permission = self::findByName($name);

        if (!$permission) {
            $permission = self::create([
                'name' => $name,
                'display_name' => $displayName,
                'description' => $description ?? $displayName,
            ]);
        }

        return $permission;
    }

    /**
     * 取得所有權限分組
     */
    public static function getGroupedPermissions(): array
    {
        return [
            'API 管理' => [
                self::PERM_MANAGE_FUNCTIONS,
                self::PERM_MANAGE_CLIENTS,
                self::PERM_MANAGE_PERMISSIONS,
            ],
            '系統管理' => [
                self::PERM_MANAGE_USERS,
                self::PERM_MANAGE_ROLES,
            ],
            '監控查詢' => [
                self::PERM_VIEW_DASHBOARD,
                self::PERM_VIEW_LOGS,
            ],
        ];
    }
}
