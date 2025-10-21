<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission Model
 * 
 * 代表系統權限，定義對特定資源的存取控制
 */
class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'action',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 資源類型
     */
    public const RESOURCE_FUNCTION = 'function';
    public const RESOURCE_CLIENT = 'client';
    public const RESOURCE_ROLE = 'role';
    public const RESOURCE_LOG = 'log';

    /**
     * 動作類型
     */
    public const ACTION_ALL = '*';
    public const ACTION_VIEW = 'view';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_EXECUTE = 'execute';

    /**
     * 取得擁有此權限的角色
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * 檢查權限是否適用於指定的資源
     */
    public function appliesTo(string $resourceType, ?int $resourceId = null): bool
    {
        if ($this->resource_type !== $resourceType) {
            return false;
        }

        // 如果權限的 resource_id 為 null，表示適用於所有該類型的資源
        if (is_null($this->resource_id)) {
            return true;
        }

        // 否則必須完全匹配
        return $this->resource_id === $resourceId;
    }

    /**
     * 檢查權限是否允許指定的動作
     */
    public function allowsAction(string $action): bool
    {
        // 萬用字元 * 允許所有動作
        if ($this->action === self::ACTION_ALL) {
            return true;
        }

        return $this->action === $action;
    }

    /**
     * 取得權限的完整描述
     */
    public function getDescription(): string
    {
        $resource = $this->resource_id 
            ? "{$this->resource_type}:{$this->resource_id}" 
            : "{$this->resource_type}:*";

        return "{$this->action} on {$resource}";
    }

    /**
     * 建立或取得權限
     */
    public static function findOrCreate(string $resourceType, ?int $resourceId, string $action): self
    {
        $permission = self::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('action', $action)
            ->first();

        if (!$permission) {
            $permission = self::create([
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'action' => $action,
            ]);
        }

        return $permission;
    }

    /**
     * 建立 Function 執行權限
     */
    public static function createFunctionExecutePermission(?int $functionId = null): self
    {
        return self::findOrCreate(self::RESOURCE_FUNCTION, $functionId, self::ACTION_EXECUTE);
    }

    /**
     * 建立完整的 CRUD 權限集合
     */
    public static function createCrudPermissions(string $resourceType, ?int $resourceId = null): array
    {
        $actions = [
            self::ACTION_VIEW,
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
        ];

        $permissions = [];
        foreach ($actions as $action) {
            $permissions[] = self::findOrCreate($resourceType, $resourceId, $action);
        }

        return $permissions;
    }

    /**
     * 驗證資源類型是否有效
     */
    public static function isValidResourceType(string $type): bool
    {
        return in_array($type, [
            self::RESOURCE_FUNCTION,
            self::RESOURCE_CLIENT,
            self::RESOURCE_ROLE,
            self::RESOURCE_LOG,
        ]);
    }

    /**
     * 驗證動作是否有效
     */
    public static function isValidAction(string $action): bool
    {
        return in_array($action, [
            self::ACTION_ALL,
            self::ACTION_VIEW,
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_EXECUTE,
        ]);
    }
}
