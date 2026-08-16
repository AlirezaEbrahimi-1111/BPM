<?php
/**
 * لاگِ ممیزیِ رویدادهایِ امنیتی (نه لاگِ عمومیِ هر API — فقط رویدادهایِ
 * واقعاً حساس: ورود موفق/ناموفق، خروج، تغییرِ رمز، فعال/غیرفعال‌سازیِ
 * کاربر). برایِ گزارشِ افتا: هر رویداد شاملِ عاملِ اقدام، زمان، و IP.
 *
 * این تابع هیچ‌وقت نباید عملیاتِ اصلی رو بشکنه — اگه خودِ لاگ‌کردن با
 * خطا مواجه بشه، فقط error_log می‌شه و اجرا ادامه پیدا می‌کنه.
 */
function logSecurityEvent($user_id, $action, $target_user_id = null, $details = [])
{
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("
            INSERT INTO security_audit_log (user_id, action, target_user_id, ip_address, details, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            $target_user_id,
            $_SERVER['REMOTE_ADDR'] ?? null,
            !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null
        ]);
    } catch (Exception $e) {
        error_log("logSecurityEvent failed | action={$action} | " . $e->getMessage());
    }
}
