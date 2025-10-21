<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\ClientRepository;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ApiClient;

/**
 * 客戶端管理控制器
 * 
 * 提供 API 客戶端的 CRUD 操作介面
 */
class ClientController extends Controller
{
    /**
     * @var ClientRepository
     */
    protected $clientRepository;

    /**
     * ClientController constructor
     */
    public function __construct(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    /**
     * 取得客戶端列表（分頁）
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            
            // 建立篩選條件
            $filters = [];
            
            if ($request->has('is_active')) {
                $filters['is_active'] = $request->boolean('is_active');
            }
            
            if ($request->has('client_type')) {
                $filters['client_type'] = $request->input('client_type');
            }
            
            if ($request->has('search')) {
                $filters['search'] = $request->input('search');
            }

            $clients = $this->clientRepository->paginate($perPage, $filters);

            return response()->json([
                'success' => true,
                'data' => $clients->items(),
                'meta' => [
                    'current_page' => $clients->currentPage(),
                    'per_page' => $clients->perPage(),
                    'total' => $clients->total(),
                    'last_page' => $clients->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('取得客戶端列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得客戶端列表失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得單一客戶端詳情
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $client = $this->clientRepository->findOrFail($id);
            
            // 載入關聯資料
            $client->load(['roles', 'tokens', 'functionPermissions']);

            // 取得統計資訊
            $statistics = $this->clientRepository->getStatistics($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'client' => $client,
                    'statistics' => $statistics,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得客戶端詳情失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得客戶端詳情失敗',
                ],
            ], 500);
        }
    }

    /**
     * 創建新的客戶端
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // 驗證請求資料
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'client_type' => 'required|string|in:api_key,bearer_token,oauth',
            'rate_limit' => 'nullable|integer|min:1',
            'token_expires_at' => 'nullable|date',
            'is_active' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
        ], [
            'name.required' => '客戶端名稱為必填',
            'client_type.required' => '客戶端類型為必填',
            'client_type.in' => '客戶端類型必須為 api_key、bearer_token 或 oauth',
            'roles.*.exists' => '指定的角色不存在',
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
            DB::beginTransaction();

            // 建立客戶端並生成憑證
            $client = $this->clientRepository->createWithCredentials($request->all());

            // 儲存原始 secret 用於回傳（僅此一次）
            $plainSecret = ApiClient::generateSecret();
            $client->update([
                'secret' => bcrypt($plainSecret),
            ]);

            DB::commit();

            Log::info('API 客戶端創建成功', [
                'client_id' => $client->id,
                'name' => $client->name,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'client' => $client->load('roles'),
                    'credentials' => [
                        'api_key' => $client->api_key,
                        'secret' => $plainSecret, // 僅此一次顯示
                    ],
                ],
                'message' => '客戶端創建成功，請妥善保存 Secret，此訊息僅顯示一次',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('創建客戶端失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '創建客戶端失敗',
                ],
            ], 500);
        }
    }

    /**
     * 更新客戶端
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // 驗證請求資料
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'client_type' => 'sometimes|required|string|in:api_key,bearer_token,oauth',
            'rate_limit' => 'nullable|integer|min:1',
            'token_expires_at' => 'nullable|date',
            'is_active' => 'boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
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
            // 檢查客戶端是否存在
            $this->clientRepository->findOrFail($id);

            DB::beginTransaction();

            // 更新客戶端
            $client = $this->clientRepository->updateClient($id, $request->all());

            DB::commit();

            Log::info('API 客戶端更新成功', [
                'client_id' => $id,
                'name' => $client->name,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $client,
                'message' => '客戶端更新成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('更新客戶端失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '更新客戶端失敗',
                ],
            ], 500);
        }
    }

    /**
     * 刪除客戶端
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $client = $this->clientRepository->findOrFail($id);
            
            DB::beginTransaction();

            // 刪除相關資料
            $client->tokens()->delete();
            $client->roles()->detach();
            $client->functionPermissions()->delete();
            
            // 刪除客戶端
            $this->clientRepository->delete($id);

            DB::commit();

            Log::info('API 客戶端刪除成功', [
                'client_id' => $id,
                'name' => $client->name,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '客戶端刪除成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('刪除客戶端失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '刪除客戶端失敗',
                ],
            ], 500);
        }
    }

    /**
     * 切換客戶端啟用狀態
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $client = $this->clientRepository->findOrFail($id);
            
            if ($client->is_active) {
                $this->clientRepository->deactivate($id);
                $message = '客戶端已停用';
            } else {
                $this->clientRepository->activate($id);
                $message = '客戶端已啟用';
            }

            Log::info('API 客戶端狀態切換', [
                'client_id' => $id,
                'new_status' => !$client->is_active,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_active' => !$client->is_active,
                ],
                'message' => $message,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('切換客戶端狀態失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '切換客戶端狀態失敗',
                ],
            ], 500);
        }
    }

    /**
     * 重新生成 API Key
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function regenerateApiKey(int $id): JsonResponse
    {
        try {
            $client = $this->clientRepository->regenerateApiKey($id);

            Log::info('API Key 重新生成', [
                'client_id' => $id,
                'regenerated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'api_key' => $client->api_key,
                ],
                'message' => 'API Key 重新生成成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('重新生成 API Key 失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '重新生成 API Key 失敗',
                ],
            ], 500);
        }
    }

    /**
     * 重新生成 Secret
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function regenerateSecret(int $id): JsonResponse
    {
        try {
            $result = $this->clientRepository->regenerateSecret($id);

            Log::info('Secret 重新生成', [
                'client_id' => $id,
                'regenerated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'secret' => $result['secret'], // 僅此一次顯示
                ],
                'message' => 'Secret 重新生成成功，請妥善保存，此訊息僅顯示一次',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('重新生成 Secret 失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '重新生成 Secret 失敗',
                ],
            ], 500);
        }
    }

    /**
     * 撤銷客戶端（停用並撤銷所有 Token）
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function revoke(int $id): JsonResponse
    {
        try {
            $client = $this->clientRepository->findOrFail($id);
            
            DB::beginTransaction();

            // 停用客戶端
            $this->clientRepository->deactivate($id);

            // 撤銷所有 Token
            $client->tokens()->delete();

            DB::commit();

            Log::info('API 客戶端撤銷', [
                'client_id' => $id,
                'revoked_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '客戶端已撤銷，所有 Token 已失效',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('撤銷客戶端失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '撤銷客戶端失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得客戶端的權限列表
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function getPermissions(int $id): JsonResponse
    {
        try {
            $client = $this->clientRepository->findOrFail($id);
            $permissions = $client->functionPermissions()->with('function')->get();

            return response()->json([
                'success' => true,
                'data' => $permissions,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得客戶端權限失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得客戶端權限失敗',
                ],
            ], 500);
        }
    }

    /**
     * 更新客戶端的權限
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
            $client = $this->clientRepository->findOrFail($id);

            DB::beginTransaction();

            // 刪除所有現有權限
            $client->functionPermissions()->delete();

            // 建立新的權限
            foreach ($request->input('function_ids') as $functionId) {
                $client->functionPermissions()->create([
                    'function_id' => $functionId,
                    'allowed' => true,
                ]);
            }

            DB::commit();

            Log::info('客戶端權限更新成功', [
                'client_id' => $id,
                'function_count' => count($request->input('function_ids')),
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => '客戶端權限更新成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLIENT_NOT_FOUND',
                    'message' => '找不到指定的客戶端',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('更新客戶端權限失敗', [
                'client_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '更新客戶端權限失敗',
                ],
            ], 500);
        }
    }
}
