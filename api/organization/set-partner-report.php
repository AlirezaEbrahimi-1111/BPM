<?php
/**
 * api/organization/set-partner-report.php
 * روشن/خاموش‌کردنِ «گزارش همکاران» برای یک سازمان. فقط سوپرادمین.
 * body: { org_id:int, enabled:0|1 }
 */
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $auth = new Auth($db);

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) $user_id = $auth->getUserFromToken();
    if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

    // 🔒 فقط مدیر کل سیستم (همان کسانی که superAdmin.php را می‌بینند)
    if (!$user_id || !in_array((int) $user_id, getSuperAdminIds(), true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $orgId = (int) ($in['org_id'] ?? 0);
    $enabled = !empty($in['enabled']) ? 1 : 0;
    if ($orgId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسهٔ سازمان نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = $db->prepare("UPDATE organizations SET partner_report_enabled = ? WHERE id = ?");
    $st->execute([$enabled, $orgId]);

    if ($st->rowCount() === 0) {
        // یا سازمان پیدا نشد، یا مقدار از قبل همین بود — هر دو را موفق می‌گیریم جز نبودِ سازمان
        $exists = $db->prepare("SELECT 1 FROM organizations WHERE id = ?");
        $exists->execute([$orgId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'سازمان یافت نشد'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'enabled' => $enabled,
        'message' => $enabled ? 'گزارش همکاران برای این سازمان روشن شد' : 'گزارش همکاران برای این سازمان خاموش شد',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('set-partner-report.php failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
