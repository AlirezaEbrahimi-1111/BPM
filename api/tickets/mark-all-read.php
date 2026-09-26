<?php
/**
 * «همه‌ی تیکت‌ها را خوانده‌شده علامت بزن» برای کاربر جاری.
 * برای هر تیکت قابل‌مشاهده، last_read_at = NOW() در ticket_message_reads ثبت
 * می‌شود؛ در نتیجه بج «در انتظار پاسخ شما» صفر می‌شود و از تیکت بعدی درست
 * کار می‌کند.
 */
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $hasReads = (bool) $db->query("SHOW TABLES LIKE 'ticket_message_reads'")->fetchColumn();
    if (!$hasReads) {
        echo json_encode(['success' => true, 'updated' => 0, 'message' => 'جدول ردیابی موجود نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $me = loadUserForPermissions($db, (int) $user_id);
    $iAmSupport = in_array((int) $user_id, getSuperAdminIds(), true);

    // محدوده: پشتیبان → همه‌ی تیکت‌ها؛ بقیه → فقط تیکت خودشان/ارجاع‌شده.
    $scopeSQL = '';
    $params   = [(int) $user_id];
    if (!$iAmSupport) {
        $scopeSQL = ' AND (t.created_by = ? OR t.assigned_to = ?)';
        $params[] = (int) $user_id;
        $params[] = (int) $user_id;
    }

    $sql = "
        INSERT INTO ticket_message_reads (ticket_id, user_id, last_read_at)
        SELECT t.id, ?, NOW()
        FROM tickets t
        WHERE t.deleted_at IS NULL{$scopeSQL}
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log('tickets/mark-all-read error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
