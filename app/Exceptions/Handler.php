<?php

namespace App\Exceptions;

use App\Models\ErrorLog;
use Illuminate\Auth\AuthenticationException as LaravelAuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * 不應該被報告的例外類型列表
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        AuthorizationException::class,
        AuthenticationException::class,
        ValidationException::class,
        FunctionNotFoundException::class,
    ];

    /**
     * 不應該被快閃的輸入列表
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'api_key',
        'secret',
        'token',
    ];

    /**
     * 錯誤碼映射表
     *
     * @var array<string, array{code: string, status: int}>
     */
    protected array $errorCodeMap = [
        AuthenticationException::class => [
            'code' => 'AUTHENTICATION_REQUIRED',
            'status' => 401,
        ],
        AuthorizationException::class => [
            'code' => 'PERMISSION_DENIED',
            'status' => 403,
        ],
        ValidationException::class => [
            'code' => 'VALIDATION_ERROR',
            'status' => 400,
        ],
        FunctionNotFoundException::class => [
            'code' => 'FUNCTION_NOT_FOUND',
            'status' => 404,
        ],
        StoredProcedureException::class => [
            'code' => 'STORED_PROCEDURE_ERROR',
            'status' => 500,
        ],
        QueryException::class => [
            'code' => 'DATABASE_ERROR',
            'status' => 500,
        ],
        TooManyRequestsHttpException::class => [
            'code' => 'RATE_LIMIT_EXCEEDED',
            'status' => 429,
        ],
        NotFoundHttpException::class => [
            'code' => 'NOT_FOUND',
            'status' => 404,
        ],
    ];

    /**
     * 註冊應用程式的例外處理回呼
     */
    public function register(): void
    {
        // 報告例外時記錄到日誌
        $this->reportable(function (Throwable $e) {
            $this->logException($e);
        });

        // 處理 API 請求的例外
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $this->handleApiException($request, $e);
            }
        });
    }

    /**
     * 處理 API 例外
     *
     * @param Request $request
     * @param Throwable $exception
     * @return JsonResponse
     */
    protected function handleApiException(Request $request, Throwable $exception): JsonResponse
    {
        // 如果例外有自己的 render 方法，使用它
        if (method_exists($exception, 'render')) {
            return $exception->render($request);
        }

        // 取得錯誤碼和狀態碼
        $errorInfo = $this->getErrorInfo($exception);
        
        // 建立錯誤回應
        $response = [
            'success' => false,
            'error' => [
                'code' => $errorInfo['code'],
                'message' => $this->getErrorMessage($exception),
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', uniqid('req_')),
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        // 如果是驗證錯誤，加入詳細資訊
        if ($exception instanceof LaravelValidationException || $exception instanceof ValidationException) {
            $response['error']['details'] = $this->getValidationErrors($exception);
        }

        // 如果是 Stored Procedure 錯誤，加入上下文資訊
        if ($exception instanceof StoredProcedureException) {
            $response['error']['context'] = $exception->getContext();
        }

        // 在開發環境中加入除錯資訊
        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(5)->toArray(),
            ];
        }

        return response()->json($response, $errorInfo['status']);
    }

    /**
     * 取得錯誤資訊（錯誤碼和狀態碼）
     *
     * @param Throwable $exception
     * @return array{code: string, status: int}
     */
    protected function getErrorInfo(Throwable $exception): array
    {
        // 檢查例外類別是否在映射表中
        $exceptionClass = get_class($exception);
        
        if (isset($this->errorCodeMap[$exceptionClass])) {
            return $this->errorCodeMap[$exceptionClass];
        }

        // 檢查是否為 HTTP 例外
        if ($exception instanceof HttpException) {
            return [
                'code' => 'HTTP_ERROR',
                'status' => $exception->getStatusCode(),
            ];
        }

        // 檢查是否有自訂的錯誤碼方法
        if (method_exists($exception, 'getErrorCode')) {
            $code = $exception->getErrorCode();
            $status = method_exists($exception, 'getStatusCode') 
                ? $exception->getStatusCode() 
                : 500;
            
            return [
                'code' => $code,
                'status' => $status,
            ];
        }

        // 預設為內部伺服器錯誤
        return [
            'code' => 'INTERNAL_ERROR',
            'status' => 500,
        ];
    }

    /**
     * 取得錯誤訊息
     *
     * @param Throwable $exception
     * @return string
     */
    protected function getErrorMessage(Throwable $exception): string
    {
        // 在生產環境中，對於 500 錯誤使用通用訊息
        if (!config('app.debug') && $this->isInternalError($exception)) {
            return '伺服器發生錯誤，請稍後再試';
        }

        return $exception->getMessage() ?: '發生未知錯誤';
    }

    /**
     * 判斷是否為內部錯誤
     *
     * @param Throwable $exception
     * @return bool
     */
    protected function isInternalError(Throwable $exception): bool
    {
        $errorInfo = $this->getErrorInfo($exception);
        return $errorInfo['status'] >= 500;
    }

    /**
     * 取得驗證錯誤詳情
     *
     * @param Throwable $exception
     * @return array
     */
    protected function getValidationErrors(Throwable $exception): array
    {
        if ($exception instanceof LaravelValidationException) {
            return $exception->errors();
        }

        if ($exception instanceof ValidationException && method_exists($exception, 'getErrors')) {
            return $exception->getErrors();
        }

        return [];
    }

    /**
     * 記錄例外到日誌
     *
     * @param Throwable $exception
     * @return void
     */
    protected function logException(Throwable $exception): void
    {
        // 取得例外類型
        $exceptionClass = get_class($exception);
        
        // 準備日誌上下文
        $context = [
            'exception' => $exceptionClass,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        // 如果是 Stored Procedure 例外，加入額外資訊
        if ($exception instanceof StoredProcedureException) {
            $context = array_merge($context, $exception->getContext());
        }

        // 根據例外類型選擇日誌等級
        if ($this->shouldReport($exception)) {
            if ($this->isInternalError($exception)) {
                Log::error("例外發生: {$exceptionClass}", $context);
                
                // 記錄到錯誤日誌資料表
                $this->logToErrorTable($exception, $context);
            } else {
                Log::warning("例外發生: {$exceptionClass}", $context);
            }
        }
    }

    /**
     * 記錄錯誤到資料表
     *
     * @param Throwable $exception
     * @param array $context
     * @return void
     */
    protected function logToErrorTable(Throwable $exception, array $context): void
    {
        try {
            ErrorLog::create([
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
                'context' => $context,
            ]);
        } catch (\Exception $e) {
            // 如果記錄到資料表失敗，只記錄到系統日誌
            Log::error('無法記錄錯誤到資料表', [
                'error' => $e->getMessage(),
                'original_exception' => get_class($exception),
            ]);
        }
    }

    /**
     * 將 Laravel 的 AuthenticationException 轉換為 JSON 回應
     *
     * @param Request $request
     * @param LaravelAuthenticationException $exception
     * @return JsonResponse
     */
    protected function unauthenticated($request, LaravelAuthenticationException $exception): JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTHENTICATION_REQUIRED',
                    'message' => '需要驗證才能存取此資源',
                ],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 401);
        }

        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }
}
