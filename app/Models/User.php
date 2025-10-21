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
     * 取得使用者的角色
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withTimestamps();
    }

    /**
     * 檢查使用者是否擁有指定角色
     *
     * @param string $roleName 角色名稱
     * @return bool
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * 檢查使用者是否為管理員
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ROLE_ADMIN);
    }

    /**
     * 為使用者指派角色
     *
     * @param Role|string $role 角色物件或角色名稱
     * @return void
     */
    public function assignRole($role): void
    {
        if (is_string($role)) {
            $role = Role::findByName($role);
            if (!$role) {
                throw new \InvalidArgumentException("角色 '{$role}' 不存在");
            }
        }

        if (!$this->roles()->where('roles.id', $role->id)->exists()) {
            $this->roles()->attach($role->id);
        }
    }

    /**
     * 移除使用者的角色
     *
     * @param Role|string $role 角色物件或角色名稱
     * @return void
     */
    public function removeRole($role): void
    {
        if (is_string($role)) {
            $role = Role::findByName($role);
            if (!$role) {
                return;
            }
        }

        $this->roles()->detach($role->id);
    }

    /**
     * 同步使用者的所有角色
     *
     * @param array $roleIds 角色 ID 陣列
     * @return void
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
    }
}
