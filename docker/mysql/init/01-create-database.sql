-- 初始化資料庫腳本
-- 此腳本會在 MySQL 容器首次啟動時自動執行

-- 確保資料庫使用 UTF-8 編碼
ALTER DATABASE api_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 建立測試用的 Stored Procedure 範例
DELIMITER $$

CREATE PROCEDURE IF NOT EXISTS sp_health_check()
BEGIN
    SELECT 
        'OK' as status,
        NOW() as timestamp,
        VERSION() as mysql_version;
END$$

DELIMITER ;
