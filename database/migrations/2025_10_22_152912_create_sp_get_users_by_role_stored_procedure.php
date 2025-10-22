<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 執行 Migration - 建立 Stored Procedure
     */
    public function up(): void
    {
        // 建立 Stored Procedure：根據角色類型取得使用者清單
        DB::unprepared("
            DROP PROCEDURE IF EXISTS sp_GetUsersByRole;
        ");
        
        DB::unprepared("
            CREATE PROCEDURE sp_GetUsersByRole(
                IN role_name VARCHAR(100)  -- 角色名稱（可選，NULL 表示取得所有使用者）
            )
            BEGIN
                -- 如果沒有指定角色，回傳所有使用者
                IF role_name IS NULL OR role_name = '' THEN
                    SELECT 
                        u.id,
                        u.name,
                        u.email,
                        u.email_verified_at,
                        u.created_at,
                        u.updated_at,
                        GROUP_CONCAT(r.name SEPARATOR ', ') AS roles
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    GROUP BY u.id, u.name, u.email, u.email_verified_at, u.created_at, u.updated_at
                    ORDER BY u.created_at DESC;
                ELSE
                    -- 根據指定角色回傳使用者
                    SELECT 
                        u.id,
                        u.name,
                        u.email,
                        u.email_verified_at,
                        u.created_at,
                        u.updated_at,
                        GROUP_CONCAT(r.name SEPARATOR ', ') AS roles
                    FROM users u
                    INNER JOIN user_roles ur ON u.id = ur.user_id
                    INNER JOIN roles r ON ur.role_id = r.id
                    WHERE EXISTS (
                        SELECT 1 
                        FROM user_roles ur2
                        INNER JOIN roles r2 ON ur2.role_id = r2.id
                        WHERE ur2.user_id = u.id 
                        AND r2.name = role_name
                    )
                    GROUP BY u.id, u.name, u.email, u.email_verified_at, u.created_at, u.updated_at
                    ORDER BY u.created_at DESC;
                END IF;
            END
        ");
    }

    /**
     * 回滾 Migration - 刪除 Stored Procedure
     */
    public function down(): void
    {
        DB::unprepared("DROP PROCEDURE IF EXISTS sp_GetUsersByRole");
    }
};
