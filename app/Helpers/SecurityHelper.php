<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 安全輔助類別
 * 
 * 提供各種安全相關的輔助方法
 */
class SecurityHelper
{
    /**
     * 清理輸入字串，防止 XSS 攻擊
     *
     * @param  string  $input
     * @return string
     */
    public static function sanitizeInput(string $input): string
    {
        // 移除 HTML 標籤
        $input = strip_tags($input);
        
        // 轉換特殊字元為 HTML 實體
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 移除控制字元
        $input = preg_replace('/[\x00-\x1F\x7F]/u', '', $input);
        
        return trim($input);
    }

    /**
     * 清理 HTML 內容，允許安全的 HTML 標籤
     *
     * @param  string  $html
     * @param  array  $allowedTags
     * @return string
     */
    public static function sanitizeHtml(string $html, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'a', 'ul', 'ol', 'li'];
        }
        
        $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
        
        return strip_tags($html, $allowedTagsString);
    }

    /**
     * 驗證 SQL 參數，防止 SQL Injection
     * 
     * 注意：Laravel 的 Query Builder 和 Eloquent 已經提供了參數綁定保護
     * 這個方法主要用於額外的驗證層
     *
     * @param  mixed  $value
     * @param  string  $type
     * @return bool
     */
    public static function validateSqlParameter($value, string $type): bool
    {
        switch ($type) {
            case 'integer':
                return is_numeric($value) && (int)$value == $value;
                
            case 'float':
                return is_numeric($value);
                
            case 'boolean':
                return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
                
            case 'string':
                // 檢查是否包含可疑的 SQL 關鍵字
                $suspiciousPatterns = [
                    '/(\bUNION\b.*\bSELECT\b)/i',
                    '/(\bDROP\b.*\bTABLE\b)/i',
                    '/(\bINSERT\b.*\bINTO\b)/i',
                    '/(\bDELETE\b.*\bFROM\b)/i',
                    '/(\bUPDATE\b.*\bSET\b)/i',
                    '/(\bEXEC\b|\bEXECUTE\b)/i',
                    '/(\bSCRIPT\b)/i',
                    '/(--|\#|\/\*|\*\/)/i',
                ];
                
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return false;
                    }
                }
                return true;
                
            case 'date':
            case 'datetime':
                return self::validateDate($value);
                
            default:
                return true;
        }
    }

    /**
     * 驗證日期格式
     *
     * @param  string  $date
     * @param  string  $format
     * @return bool
     */
    public static function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * 生成安全的隨機 Token
     *
     * @param  int  $length
     * @return string
     */
    public static function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * 驗證 API Key 格式
     *
     * @param  string  $apiKey
     * @return bool
     */
    public static function validateApiKeyFormat(string $apiKey): bool
    {
        // API Key 應該是 64 個字元的十六進制字串
        return preg_match('/^[a-f0-9]{64}$/i', $apiKey) === 1;
    }

    /**
     * 檢查 IP 是否在白名單中
     *
     * @param  string  $ip
     * @param  array  $whitelist
     * @return bool
     */
    public static function isIpWhitelisted(string $ip, array $whitelist): bool
    {
        if (empty($whitelist)) {
            return true;
        }

        foreach ($whitelist as $allowedIp) {
            // 支援 CIDR 表示法
            if (Str::contains($allowedIp, '/')) {
                if (self::ipInRange($ip, $allowedIp)) {
                    return true;
                }
            } else {
                if ($ip === $allowedIp) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 檢查 IP 是否在指定的 CIDR 範圍內
     *
     * @param  string  $ip
     * @param  string  $cidr
     * @return bool
     */
    protected static function ipInRange(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * 記錄安全事件
     *
     * @param  string  $eventType
     * @param  array  $details
     * @return void
     */
    public static function logSecurityEvent(string $eventType, array $details): void
    {
        DB::table('security_logs')->insert([
            'event_type' => $eventType,
            'client_id' => $details['client_id'] ?? null,
            'ip_address' => $details['ip_address'] ?? request()->ip(),
            'details' => json_encode($details),
            'created_at' => now(),
        ]);
    }
}
