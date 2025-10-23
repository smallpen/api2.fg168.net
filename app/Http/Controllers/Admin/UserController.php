<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 使用者管理控制器
 */
class UserController extends Controller
{
    /**
     * 取得所有使用者列表
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = User::with('adminRoles');

            // 搜尋功能
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // 排序
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // 分頁
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            Log::error('取得使用者列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '取得使用者列表失敗',
            ], 500);
        }
    }

    /**
     * 取得單一使用者資訊
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $user = User::with('adminRoles')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '找不到該使用者',
            ], 404);
        }
    }

    /**
     * 建立新使用者
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password' => ['required', Password::defaults()],
                'admin_role_id' => ['nullable', 'exists:admin_roles,id'],
            ], [
                'name.required' => '請輸入使用者名稱',
                'email.required' => '請輸入電子郵件',
                'email.email' => '電子郵件格式不正確',
                'email.unique' => '此電子郵件已被使用',
                'password.required' => '請輸入密碼',
                'admin_role_id.exists' => '選擇的角色不存在',
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // 指派後台角色（只能有一個）
            if (!empty($validated['admin_role_id'])) {
                $user->syncAdminRoles([$validated['admin_role_id']]);
            }

            Log::info('建立新使用者', [
                'user_id' => $user->id,
                'created_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '使用者建立成功',
                'data' => $user->load('adminRoles'),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('建立使用者失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '建立使用者失敗',
            ], 500);
        }
    }

    /**
     * 更新使用者資訊
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'password' => ['nullable', Password::defaults()],
                'admin_role_id' => ['nullable', 'exists:admin_roles,id'],
            ], [
                'name.required' => '請輸入使用者名稱',
                'email.required' => '請輸入電子郵件',
                'email.email' => '電子郵件格式不正確',
                'email.unique' => '此電子郵件已被使用',
                'admin_role_id.exists' => '選擇的角色不存在',
            ]);

            // 更新基本資訊
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }
            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }
            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            // 更新後台角色（只能有一個）
            if (isset($validated['admin_role_id'])) {
                $user->syncAdminRoles([$validated['admin_role_id']]);
            }

            Log::info('更新使用者資訊', [
                'user_id' => $user->id,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '使用者資訊更新成功',
                'data' => $user->load('adminRoles'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('更新使用者失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '更新使用者失敗',
            ], 500);
        }
    }

    /**
     * 刪除使用者
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);

            // 防止刪除自己
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => '無法刪除自己的帳號',
                ], 400);
            }

            $user->delete();

            Log::info('刪除使用者', [
                'user_id' => $user->id,
                'deleted_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '使用者已刪除',
            ]);
        } catch (\Exception $e) {
            Log::error('刪除使用者失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '刪除使用者失敗',
            ], 500);
        }
    }

    /**
     * 取得當前登入使用者的個人資料
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        try {
            $user = Auth::user()->load('adminRoles');

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '取得個人資料失敗',
            ], 500);
        }
    }

    /**
     * 更新當前登入使用者的個人資料
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();

            $validated = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'current_password' => ['required_with:password', 'string'],
                'password' => ['nullable', 'confirmed', Password::defaults()],
            ], [
                'name.required' => '請輸入使用者名稱',
                'email.required' => '請輸入電子郵件',
                'email.email' => '電子郵件格式不正確',
                'email.unique' => '此電子郵件已被使用',
                'current_password.required_with' => '請輸入目前的密碼',
                'password.confirmed' => '密碼確認不一致',
            ]);

            // 如果要更新密碼，需要驗證目前密碼
            if (!empty($validated['password'])) {
                if (!Hash::check($validated['current_password'], $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => '目前密碼不正確',
                        'errors' => [
                            'current_password' => ['目前密碼不正確'],
                        ],
                    ], 422);
                }
                $user->password = Hash::make($validated['password']);
            }

            // 更新基本資訊
            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }
            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }

            $user->save();

            Log::info('更新個人資料', [
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => '個人資料更新成功',
                'data' => $user->load('adminRoles'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('更新個人資料失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '更新個人資料失敗',
            ], 500);
        }
    }
}
