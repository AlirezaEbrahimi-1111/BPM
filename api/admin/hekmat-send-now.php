<?php
/**
 * API: api/admin/hekmat-send-now.php
 * ارسالِ دستیِ فوریِ حکمتِ بعدی به همه‌یِ گیرنده‌های فعال — همون منطقِ
 * cron (runDailySend)، صرفاً با کلیکِ دستی. چرخش و last_sent_date رو
 * دقیقاً مثلِ ارسالِ خودکار آپدیت می‌کنه (پس اگه cron هم امروز اجرا بشه،
 * دوباره نمی‌فرسته).
 *
 *   POST /api/admin/hekmat-send-now.php
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/middleware.php';
require_once __DIR__ . '/../../includes/HekmatBroadcast.php';

try {
    $user_id = requireAuth();
    if ((int) $user_id !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز — فقط سوپرادمین'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $result = HekmatBroadcast::runDailySend($db);

    if (!$result['ok']) {
        $reasons = [
            'no_quotes'     => 'هیچ جمله‌ی فعالی در لیست نیست',
            'no_recipients' => 'هیچ گیرنده‌ی فعالی در لیست نیست',
        ];
        echo json_encode([
            'success' => false,
            'message' => $reasons[$result['reason']] ?? 'ارسال انجام نشد',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'sent'    => $result['sent'],
        'failed'  => $result['failed'],
        'message' => "ارسال شد — موفق: {$result['sent']}، ناموفق: {$result['failed']}",
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('hekmat-send-now.php failed | ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
