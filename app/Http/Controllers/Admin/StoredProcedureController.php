<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stored Procedure 管理控制器
 * 
 * 提供從資料庫讀取 Stored Procedures 和參數資訊的功能
 */
class StoredProcedureController extends Controller
{
    /**
     * 取得所有 Stored Procedures 列表
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $database = config('database.connections.' . config('database.default') . '.database');
            
            // 從資料庫系統表查詢所有 Stored Procedures
            $procedures = DB::select("
                SELECT 
                    ROUTINE_NAME as name,
                    ROUTINE_SCHEMA as schema_name,
                    CREATED as created_at,
                    LAST_ALTERED as updated_at
                FROM INFORMATION_SCHEMA.ROUTINES
                WHERE ROUTINE_TYPE = 'PROCEDURE'
                    AND ROUTINE_SCHEMA = ?
                ORDER BY ROUTINE_NAME
            ", [$database]);

            return response()->json([
                'success' => true,
                'data' => $procedures,
                'meta' => [
                    'total' => count($procedures),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => '載入 Stored Procedures 失敗',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * 取得指定 Stored Procedure 的參數資訊
     * 
     * @param string $procedureName
     * @return \Illuminate\Http\JsonResponse
     */
    public function parameters($procedureName)
    {
        try {
            $database = config('database.connections.' . config('database.default') . '.database');
            
            // 從資料庫系統表查詢 SP 參數
            $parameters = DB::select("
                SELECT 
                    PARAMETER_NAME as name,
                    DATA_TYPE as data_type,
                    PARAMETER_MODE as direction,
                    CHARACTER_MAXIMUM_LENGTH as length,
                    NUMERIC_PRECISION as `precision`,
                    NUMERIC_SCALE as scale,
                    ORDINAL_POSITION as position
                FROM INFORMATION_SCHEMA.PARAMETERS
                WHERE SPECIFIC_SCHEMA = ?
                    AND SPECIFIC_NAME = ?
                ORDER BY ORDINAL_POSITION
            ", [$database, $procedureName]);

            return response()->json([
                'success' => true,
                'data' => $parameters,
                'meta' => [
                    'procedure_name' => $procedureName,
                    'total_parameters' => count($parameters),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'message' => '載入 Stored Procedure 參數失敗',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
