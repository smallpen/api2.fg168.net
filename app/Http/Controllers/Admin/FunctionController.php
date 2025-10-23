<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\FunctionRepository;
use App\Services\Logging\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Function 管理控制器
 * 
 * 提供 API Function 的 CRUD 操作介面
 */
class FunctionController extends Controller
{
    /**
     * @var FunctionRepository
     */
    protected $functionRepository;

    /**
     * @var AuditLogger
     */
    protected $auditLogger;

    /**
     * FunctionController constructor
     */
    public function __construct(
        FunctionRepository $functionRepository,
        AuditLogger $auditLogger
    ) {
        $this->functionRepository = $functionRepository;
        $this->auditLogger = $auditLogger;
    }

    /**
     * 取得 Function 列表（分頁）
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
            
            if ($request->has('search')) {
                $filters['search'] = $request->input('search');
            }
            
            if ($request->has('stored_procedure')) {
                $filters['stored_procedure'] = $request->input('stored_procedure');
            }

            $functions = $this->functionRepository->paginate($perPage, $filters);

            return response()->json([
                'success' => true,
                'data' => $functions->items(),
                'meta' => [
                    'current_page' => $functions->currentPage(),
                    'per_page' => $functions->perPage(),
                    'total' => $functions->total(),
                    'last_page' => $functions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('取得 Function 列表失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得 Function 列表失敗',
                ],
            ], 500);
        }
    }

    /**
     * 取得單一 Function 詳情
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $function = $this->functionRepository->findOrFail($id);
            
            // 載入關聯資料
            $function->load(['parameters', 'responses', 'errorMappings', 'permissions']);

            return response()->json([
                'success' => true,
                'data' => $function,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FUNCTION_NOT_FOUND',
                    'message' => '找不到指定的 API Function',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('取得 Function 詳情失敗', [
                'function_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '取得 Function 詳情失敗',
                ],
            ], 500);
        }
    }

    /**
     * 創建新的 Function
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // 驗證請求資料
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'identifier' => 'required|string|max:255|regex:/^[a-z0-9._-]+$/i',
            'description' => 'nullable|string',
            'stored_procedure' => 'required|string|max:255',
            'is_active' => 'boolean',
            'parameters' => 'nullable|array',
            'parameters.*.name' => 'required|string|max:255',
            'parameters.*.data_type' => 'required|string|in:string,integer,float,boolean,date,datetime,json,array',
            'parameters.*.is_required' => 'boolean',
            'parameters.*.default_value' => 'nullable',
            'parameters.*.validation_rules' => 'nullable|array',
            'parameters.*.sp_parameter_name' => 'required|string|max:255',
            'parameters.*.position' => 'required|integer|min:0',
            'responses' => 'nullable|array',
            'responses.*.field_name' => 'required|string|max:255',
            'responses.*.sp_column_name' => 'required|string|max:255',
            'responses.*.data_type' => 'required|string|in:string,integer,float,boolean,date,datetime,json,array',
            'responses.*.transform_rule' => 'nullable|string',
            'error_mappings' => 'nullable|array',
            'error_mappings.*.error_code' => 'required|string|max:255',
            'error_mappings.*.http_status' => 'required|integer|min:400|max:599',
            'error_mappings.*.error_message' => 'required|string',
        ], [
            'name.required' => 'Function 名稱為必填',
            'identifier.required' => 'Function 識別碼為必填',
            'identifier.regex' => 'Function 識別碼格式不正確，只能包含字母、數字、點、底線和連字號',
            'stored_procedure.required' => 'Stored Procedure 名稱為必填',
            'parameters.*.name.required' => '參數名稱為必填',
            'parameters.*.data_type.required' => '參數資料類型為必填',
            'parameters.*.sp_parameter_name.required' => 'SP 參數名稱為必填',
            'parameters.*.position.required' => '參數位置為必填',
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
            // 檢查識別碼是否已存在
            if ($this->functionRepository->identifierExists($request->input('identifier'))) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'IDENTIFIER_EXISTS',
                        'message' => 'Function 識別碼已存在',
                    ],
                ], 409);
            }

            DB::beginTransaction();

            // 建立 Function 及其相關資料
            $data = $request->all();
            $data['created_by'] = auth()->id();

            $function = $this->functionRepository->createWithRelations($data);

            DB::commit();

            // 記錄審計日誌
            $this->auditLogger->logCreate(
                Auth::id(),
                AuditLogger::RESOURCE_API_FUNCTION,
                $function->id,
                [
                    'name' => $function->name,
                    'identifier' => $function->identifier,
                    'stored_procedure' => $function->stored_procedure,
                ]
            );

            Log::info('API Function 創建成功', [
                'function_id' => $function->id,
                'identifier' => $function->identifier,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $function->load(['parameters', 'responses', 'errorMappings']),
                'message' => 'Function 創建成功',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('創建 Function 失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '創建 Function 失敗',
                ],
            ], 500);
        }
    }

    /**
     * 更新 Function
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
            'identifier' => 'sometimes|required|string|max:255|regex:/^[a-z0-9._-]+$/i',
            'description' => 'nullable|string',
            'stored_procedure' => 'sometimes|required|string|max:255',
            'is_active' => 'boolean',
            'parameters' => 'nullable|array',
            'parameters.*.name' => 'required|string|max:255',
            'parameters.*.data_type' => 'required|string|in:string,integer,float,boolean,date,datetime,json,array',
            'parameters.*.is_required' => 'boolean',
            'parameters.*.default_value' => 'nullable',
            'parameters.*.validation_rules' => 'nullable|array',
            'parameters.*.sp_parameter_name' => 'required|string|max:255',
            'parameters.*.position' => 'required|integer|min:0',
            'responses' => 'nullable|array',
            'responses.*.field_name' => 'required|string|max:255',
            'responses.*.sp_column_name' => 'required|string|max:255',
            'responses.*.data_type' => 'required|string|in:string,integer,float,boolean,date,datetime,json,array',
            'responses.*.transform_rule' => 'nullable|string',
            'error_mappings' => 'nullable|array',
            'error_mappings.*.error_code' => 'required|string|max:255',
            'error_mappings.*.http_status' => 'required|integer|min:400|max:599',
            'error_mappings.*.error_message' => 'required|string',
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
            // 檢查 Function 是否存在
            $function = $this->functionRepository->findOrFail($id);

            // 如果更新識別碼，檢查是否與其他 Function 衝突
            if ($request->has('identifier') && 
                $request->input('identifier') !== $function->identifier) {
                if ($this->functionRepository->identifierExists($request->input('identifier'), $id)) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'IDENTIFIER_EXISTS',
                            'message' => 'Function 識別碼已存在',
                        ],
                    ], 409);
                }
            }

            DB::beginTransaction();

            // 保存舊資料用於審計日誌
            $oldData = [
                'name' => $function->name,
                'identifier' => $function->identifier,
                'stored_procedure' => $function->stored_procedure,
                'is_active' => $function->is_active,
            ];

            // 更新 Function 及其相關資料
            $updatedFunction = $this->functionRepository->updateWithRelations($id, $request->all());

            // 記錄審計日誌
            $newData = [
                'name' => $updatedFunction->name,
                'identifier' => $updatedFunction->identifier,
                'stored_procedure' => $updatedFunction->stored_procedure,
                'is_active' => $updatedFunction->is_active,
            ];
            
            $this->auditLogger->logUpdate(
                Auth::id(),
                AuditLogger::RESOURCE_API_FUNCTION,
                $id,
                $oldData,
                $newData
            );

            DB::commit();

            Log::info('API Function 更新成功', [
                'function_id' => $id,
                'identifier' => $updatedFunction->identifier,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $updatedFunction,
                'message' => 'Function 更新成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FUNCTION_NOT_FOUND',
                    'message' => '找不到指定的 API Function',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('更新 Function 失敗', [
                'function_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '更新 Function 失敗',
                ],
            ], 500);
        }
    }

    /**
     * 刪除 Function
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $function = $this->functionRepository->findOrFail($id);
            
            // 保存資料用於審計日誌
            $deletedData = [
                'name' => $function->name,
                'identifier' => $function->identifier,
                'stored_procedure' => $function->stored_procedure,
            ];
            
            DB::beginTransaction();

            // 刪除相關資料
            $function->parameters()->delete();
            $function->responses()->delete();
            $function->errorMappings()->delete();
            $function->permissions()->delete();
            
            // 刪除 Function
            $this->functionRepository->delete($id);

            // 記錄審計日誌
            $this->auditLogger->logDelete(
                Auth::id(),
                AuditLogger::RESOURCE_API_FUNCTION,
                $id,
                $deletedData
            );

            DB::commit();

            Log::info('API Function 刪除成功', [
                'function_id' => $id,
                'identifier' => $function->identifier,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Function 刪除成功',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FUNCTION_NOT_FOUND',
                    'message' => '找不到指定的 API Function',
                ],
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('刪除 Function 失敗', [
                'function_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '刪除 Function 失敗',
                ],
            ], 500);
        }
    }

    /**
     * 切換 Function 啟用狀態
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $function = $this->functionRepository->findOrFail($id);
            
            $newStatus = !$function->is_active;
            
            if ($function->is_active) {
                $this->functionRepository->deactivate($id);
                $message = 'Function 已停用';
                
                // 記錄停用審計日誌
                $this->auditLogger->logDisable(
                    Auth::id(),
                    AuditLogger::RESOURCE_API_FUNCTION,
                    $id
                );
            } else {
                $this->functionRepository->activate($id);
                $message = 'Function 已啟用';
                
                // 記錄啟用審計日誌
                $this->auditLogger->logEnable(
                    Auth::id(),
                    AuditLogger::RESOURCE_API_FUNCTION,
                    $id
                );
            }

            Log::info('API Function 狀態切換', [
                'function_id' => $id,
                'new_status' => $newStatus,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_active' => $newStatus,
                ],
                'message' => $message,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FUNCTION_NOT_FOUND',
                    'message' => '找不到指定的 API Function',
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('切換 Function 狀態失敗', [
                'function_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '切換 Function 狀態失敗',
                ],
            ], 500);
        }
    }
}
