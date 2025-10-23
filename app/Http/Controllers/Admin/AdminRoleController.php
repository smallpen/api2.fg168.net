<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 後台角色管理控制器
 */
class AdminRoleController extends Controller
{
    /**
     * 取得所有後台角色
     */
    public function index()
    {
        try {
            $roles = AdminRole::withCount(['users', 'permissions'])->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
            ]);
        } catch (\Exception $e) {
            Log::error('取得後台角色列表失敗', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得後台角色列表失敗',
            ], 500);
        }
    }

    /**
     * 更新後台角色
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:admin_roles,name,' . $id,
            'display_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ], [
            'name.required' => '角色名稱為必填',
            'name.unique' => '角色名稱已存在',
            'display_name.required' => '顯示名稱為必填',
        ]);

        try {
            $role = AdminRole::findOrFail($id);

            // 系統角色的 name 不可修改
            if ($role->isSystemRole() && $request->has('name') && $request->input('name') !== $role->name) {
                return response()->json([
                    'success' => false,
                    'message' => '系統角色的識別碼不可修改',
                ], 403);
            }

            $role->update($request->only(['name', 'display_name', 'description']));

            Log::info('更新後台角色', [
                'role_id' => $id,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $role,
                'message' => '角色更新成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到指定的角色',
            ], 404);
        } catch (\Exception $e) {
            Log::error('更新後台角色失敗', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '更新角色失敗',
            ], 500);
        }
    }

    /**
     * 取得角色的權限
     */
    public function getPermissions($id)
    {
        try {
            $role = AdminRole::findOrFail($id);
            $permissions = $role->permissions;

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '取得角色權限失敗',
            ], 404);
        }
    }

    /**
     * 更新角色的權限
     */
    public function updatePermissions(Request $request, $id)
    {
        $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:admin_permissions,id',
        ]);

        try {
            $role = AdminRole::findOrFail($id);

            // 系統預設角色的權限不可修改
            if ($role->isSystemRole() && $role->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => '超級管理員角色的權限不可修改',
                ], 400);
            }

            DB::beginTransaction();

            $role->syncPermissions($request->permission_ids);

            DB::commit();

            Log::info('更新後台角色權限', [
                'role_id' => $id,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '角色權限更新成功',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('更新後台角色權限失敗', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '更新角色權限失敗',
            ], 500);
        }
    }

    /**
     * 取得所有後台權限
     */
    public function getAllPermissions()
    {
        try {
            $permissions = AdminPermission::all();

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '取得權限列表失敗',
            ], 500);
        }
    }
}
