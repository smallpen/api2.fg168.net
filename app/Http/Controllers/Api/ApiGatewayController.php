<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\FunctionRepository;
use App\Services\Authentication\AuthenticationManager;
use App\Services\Authorization\AuthorizationManager;
use App\Services\Gateway\RequestValidator;
use App\Services\Gateway\FunctionExecutor;
use App\Services\Gateway\ResponseFormatter;
use App\Services\Logging\LoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * API Gateway Controller
 * 
 * 統一的 API 入口點，負責路由、驗證、授權和執行所有 API 請求
 */
class ApiGatewayController extends Controller
{
    /**
     * Function Repository
     */
    protected FunctionRepository $functionRepository;

    /**
     * 驗證管理器
     */
    protected AuthenticationManager $authManager;

    /**
     * 授權管理器
     */
    protected AuthorizationManager $authzManager;

    /**
     * 請求驗證器
     */
    protected RequestValidator $requestValidator;

    /**
     * Function 執行器
     */
    protected FunctionExecutor $functionExecutor;

    /**
     * 回應格式化器
     */
    protected ResponseFormatter $responseFormatter;

    /**
     * 日誌服務
     */
    protected LoggingService $loggingService;

    /**
     * 建構函數
     */
    public function __construct(
        FunctionRepository $functionRepository,
        AuthenticationManager $authManager,
        AuthorizationManager $authzManager,
        RequestValidator $requestValidator,
        FunctionExecutor $functionExecutor,
        ResponseFormatter $responseFormatter,
        LoggingService $loggingService
    ) {
        $this->functionRepository = $functionRepository;
        $this->authManager = $authManager;
        $this->authzManager = $authzManager;
        $this->requestValidator = $requestValidator;
        $this->functionExecutor = $functionExecutor;
        $this->responseFormatter = $responseFormatter;
        $this->loggingService = $loggingService;
    }

    /**
     * 執行 API Function
     * 
     * 統一的 API 執行端點
     * 
     * @param Request $request HTTP 請求物件
     * @return JsonResponse JSON 回應
     */
    public function execute(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = $this->generateRequestId();
        $client = $request->attributes->get('api_client');
        $clientId = $client?->id ?? null;
        $functionId = null;
        $functionIdentifier = null;
        $params = [];
        $response = null;

        try {
            // 1. 解析請求
            $functionIdentifier = $this->parseFunctionIdentifier($request);
            $params = $this->parseParameters($request);

            Log::info('API Gateway 收到請求', [
                'request_id' => $requestId,
                'function' => $functionIdentifier,
                'params_count' => count($params),
                'ip' => $request->ip(),
            ]);

            // 2. 查找 Function 配置
            $function = $this->findFunction($functionIdentifier);

            if (!$function) {
                $executionTime = microtime(true) - $startTime;
                $response = $this->responseFormatter->error(
                    'FUNCTION_NOT_FOUND',
                    "找不到 API Function: {$functionIdentifier}",
                    404,
                    null,
                    ['request_id' => $requestId]
                );

                // 記錄失敗的請求
                $this->logApiRequest(
                    $clientId,
                    null,
                    ['function' => $functionIdentifier, 'params' => $params],
                    ['error' => 'FUNCTION_NOT_FOUND'],
                    404,
                    $executionTime,
                    $request
                );

                return $response;
            }

            $functionId = $function->id;

            // 3. 檢查 Function 是否啟用
            if (!$function->is_active) {
                $executionTime = microtime(true) - $startTime;
                $response = $this->responseFormatter->error(
                    'FUNCTION_DISABLED',
                    "API Function 已停用: {$functionIdentifier}",
                    403,
                    null,
                    ['request_id' => $requestId]
                );

                // 記錄失敗的請求
                $this->logApiRequest(
                    $clientId,
                    $functionId,
                    ['function' => $functionIdentifier, 'params' => $params],
                    ['error' => 'FUNCTION_DISABLED'],
                    403,
                    $executionTime,
                    $request
                );

                return $response;
            }

            Log::info('API Function 已找到', [
                'request_id' => $requestId,
                'function_id' => $function->id,
                'function_name' => $function->name,
                'stored_procedure' => $function->stored_procedure,
            ]);

            // 4. 驗證請求參數
            $validatedParams = $this->requestValidator->validateAndFillDefaults($params, $function);

            Log::debug('參數驗證成功', [
                'request_id' => $requestId,
                'validated_params_count' => count($validatedParams),
            ]);

            // 5. 執行 Function
            $result = $this->functionExecutor->execute($function, $validatedParams);

            $executionTime = microtime(true) - $startTime;

            Log::info('API Gateway 執行成功', [
                'request_id' => $requestId,
                'function' => $functionIdentifier,
                'execution_time' => $executionTime,
            ]);

            // 6. 格式化並返回成功回應
            $response = $this->responseFormatter->success(
                $result['data'],
                $function,
                [
                    'request_id' => $requestId,
                    'execution_time' => $executionTime,
                ]
            );

            // 記錄成功的請求
            $this->logApiRequest(
                $clientId,
                $functionId,
                ['function' => $functionIdentifier, 'params' => $validatedParams],
                $result['data'],
                200,
                $executionTime,
                $request
            );

            return $response;

        } catch (ValidationException $e) {
            $executionTime = microtime(true) - $startTime;

            Log::warning('參數驗證失敗', [
                'request_id' => $requestId,
                'errors' => $e->errors(),
            ]);

            $response = $this->responseFormatter->validationException(
                $e,
                ['request_id' => $requestId]
            );

            // 記錄驗證失敗的請求
            $this->logApiRequest(
                $clientId,
                $functionId,
                ['function' => $functionIdentifier, 'params' => $params],
                ['error' => 'VALIDATION_ERROR', 'details' => $e->errors()],
                400,
                $executionTime,
                $request
            );

            return $response;

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            Log::error('API Gateway 執行失敗', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime,
                'trace' => $e->getTraceAsString(),
            ]);

            $response = $this->responseFormatter->error(
                'INTERNAL_ERROR',
                '內部伺服器錯誤',
                500,
                config('app.debug') ? ['error' => $e->getMessage()] : null,
                [
                    'request_id' => $requestId,
                    'execution_time' => $executionTime,
                ]
            );

            // 記錄錯誤的請求
            $this->logApiRequest(
                $clientId,
                $functionId,
                ['function' => $functionIdentifier, 'params' => $params],
                ['error' => 'INTERNAL_ERROR', 'message' => $e->getMessage()],
                500,
                $executionTime,
                $request
            );

            return $response;
        }
    }

    /**
     * 解析 Function 識別碼
     * 
     * 從請求中提取 Function 識別碼
     * 
     * @param Request $request HTTP 請求物件
     * @return string Function 識別碼
     * @throws \InvalidArgumentException 缺少 Function 識別碼時拋出
     */
    protected function parseFunctionIdentifier(Request $request): string
    {
        $identifier = $request->input('function');

        if (empty($identifier)) {
            throw new \InvalidArgumentException('缺少必要參數: function');
        }

        if (!is_string($identifier)) {
            throw new \InvalidArgumentException('function 參數必須是字串');
        }

        return trim($identifier);
    }

    /**
     * 解析請求參數
     * 
     * 從請求中提取參數
     * 
     * @param Request $request HTTP 請求物件
     * @return array 參數陣列
     */
    protected function parseParameters(Request $request): array
    {
        $params = $request->input('params', []);

        if (!is_array($params)) {
            throw new \InvalidArgumentException('params 參數必須是陣列或物件');
        }

        return $params;
    }

    /**
     * 查找 API Function
     * 
     * 根據識別碼查找啟用的 Function 配置
     * 
     * @param string $identifier Function 識別碼
     * @return \App\Models\ApiFunction|null Function 物件或 null
     */
    protected function findFunction(string $identifier): ?\App\Models\ApiFunction
    {
        try {
            return $this->functionRepository->findByIdentifier($identifier);
        } catch (\Exception $e) {
            Log::error('查找 Function 失敗', [
                'identifier' => $identifier,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 生成請求 ID
     * 
     * @return string 唯一的請求 ID
     */
    protected function generateRequestId(): string
    {
        return 'req_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * 記錄 API 請求
     * 
     * @param int|null $clientId 客戶端 ID
     * @param int|null $functionId Function ID
     * @param array $requestData 請求資料
     * @param array $responseData 回應資料
     * @param int $httpStatus HTTP 狀態碼
     * @param float $executionTime 執行時間
     * @param Request $request HTTP 請求物件
     * @return void
     */
    protected function logApiRequest(
        ?int $clientId,
        ?int $functionId,
        array $requestData,
        array $responseData,
        int $httpStatus,
        float $executionTime,
        Request $request
    ): void {
        // 直接同步記錄日誌
        try {
            $this->loggingService->api()->logRequest([
                'client_id' => $clientId,
                'function_id' => $functionId,
                'request_data' => $requestData,
                'response_data' => $responseData,
                'http_status' => $httpStatus,
                'execution_time' => $executionTime,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Exception $e) {
            // 日誌記錄失敗不應影響主流程
            Log::error('API 日誌記錄失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
