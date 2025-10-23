<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRole;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 客戶端角色管理控制器
 * 
 * 提供客戶端角色的 CRUD 操作和權限管理介面
 */
class RoleController extends Controller
{
    /**
     * 取得客戶端角色列表
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $roles = ClientRole::withCount(['clients', 'permissions'])->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
            ]);
        } catch (\Exception $e) {
            Log::error('取得角色列表失敗', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得角色列表失敗',
                ],
            ], 500);
        }
    }

    /**
     * 創建新角色
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:client_roles,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ], [
            'name.required' => '角色名稱為必填',
            'name.unique' => '角色名稱已存在',
            'display_name.required' => '顯示名稱為必填',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '參數驗證失敗',
                    'details' => $validator->errors(),
                ],
            ], 400);
        }

        try {
            $role = ClientRole::create($request->all());

            Log::info('角色創建成功', [
                'role_id' => $role->id,
                'name' => $role->name,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $role,
                'message' => '角色創建成功',
            ], 201);
        } catch (\Exception $e) {
            Log::error('創建角色失敗', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '創建角色失敗',
                ],
            ], 500);
        }
    }

    /**
     * 更新角色
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:client_roles,name,' . $id],
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ], [
            'name.required' => '角色名稱為必填',
            'name.unique' => '角色名稱已存在',
            'display_name.required' => '顯示名稱為必填',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '參數驗證失敗',
                    'details' => $validator->errors(),
                ],
            ], 400);
        }

        try {
            $role = ClientRole::findOrFail($id);

            // 系統角色的 name 不可修改
            if ($role->isSystemRole() && $request->has('name') && $request->input('name') !== $role->name) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SYSTEM_ROLE',
                        'message' => '系統角色的識別碼不可修改',
                    ],
                ], 403);
            }

            $role->update($request->only(['name', 'display_name', 'description']));

            Log::info('角色更新成功', [
                'role_id' => $role->id,
                'name' => $role->name,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $role,
                'message' => '角色更新成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ROLE_NOT_FOUND',
                    'message' => '找不到指定的角色',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('更新角色失敗', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '更新角色失敗',
                ],
            ], 500);
        }
    }

    /**
     * 刪除角色
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $role = ClientRole::findOrFail($id);

            // 防止刪除系統角色
            if ($role->isSystemRole()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SYSTEM_ROLE',
                        'message' => '無法刪除系統角色',
                    ],
                ], 403);
            }

            DB::beginTransaction();

            // 移除角色的所有關聯
            $role->permissions()->detach();
            $role->clients()->detach();
            $role->delete();

            DB::commit();

            Log::info('角色刪除成功', [
                'role_id' => $id,
                'name' => $role->name,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '角色刪除成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ROLE_NOT_FOUND',
                    'message' => '找不到指定的角色',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('刪除角色失敗', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '刪除角色失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得角色的權限列表
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function getPermissions(int $id): JsonResponse
    {
        try {
            $role = ClientRole::findOrFail($id);
            $permissions = $role->permissions;

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ROLE_NOT_FOUND',
                    'message' => '找不到指定的角色',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得角色權限失敗', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得角色權限失敗',
                ],
            ], 500);
        }
    }

    /**
     * 更新角色的權限
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'function_ids' => 'required|array',
            'function_ids.*' => 'integer|exists:api_functions,id',
        ], [
            'function_ids.required' => 'Function IDs 為必填',
            'function_ids.*.exists' => '指定的 Function 不存在',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => '參數驗證失敗',
                    'details' => $validator->errors(),
                ],
            ], 400);
        }

        try {
            $role = ClientRole::findOrFail($id);

            DB::beginTransaction();

            // 移除舊的 Function 權限
            $role->permissions()
                ->where('resource_type', Permission::RESOURCE_FUNCTION)
                ->where('action', Permission::ACTION_EXECUTE)
                ->delete();

            // 建立新的 Function 權限
            $permissionIds = [];
            foreach ($request->input('function_ids') as $functionId) {
                $permission = Permission::findOrCreate(
                    Permission::RESOURCE_FUNCTION,
                    $functionId,
                    Permission::ACTION_EXECUTE
                );
                $permissionIds[] = $permission->id;
            }

            // 同步權限
            $role->permissions()->syncWithoutDetaching($permissionIds);

            DB::commit();

            Log::info('角色權限更新成功', [
                'role_id' => $id,
                'function_count' => count($request->input('function_ids')),
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '角色權限更新成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ROLE_NOT_FOUND',
                    'message' => '找不到指定的角色',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('更新角色權限失敗', [
                'role_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '更新角色權限失敗',
                ],
            ], 500);
        }
    }
}
