<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
header('Content-Type: application/json; charset=utf-8');

// 🔒 فقط مدیر کل (superadmin)
if (!in_array((int)($_SESSION['user_id'] ?? 0), getSuperAdminIds(), true)) {
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$org_id = (int)($data['org_id'] ?? 0);
$max    = (int)($data['max_users'] ?? 0);

if (!$org_id || $max < 1 || $max > 100000) {
    echo json_encode(['success' => false, 'message' => 'پارامترهای نامعتبر'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // اشتراک فعال سازمان را پیدا کن
    $stmt = $db->prepare("
        SELECT id FROM subscriptions
        WHERE organization_id = ? AND is_active = 1
        ORDER BY end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$org_id]);
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sub) {
        echo json_encode(['success' => false, 'message' => 'اشتراک فعالی برای این سازمان یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->prepare("UPDATE subscriptions SET max_users = ? WHERE id = ?")
       ->execute([$max, $sub['id']]);

    echo json_encode(['success' => true, 'message' => "سقف کاربران به {$max} نفر تغییر کرد"], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("organization/set-user-limit.php failed | org_id={$org_id} | max_users={$max} | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در پردازش درخواست'], JSON_UNESCAPED_UNICODE);
}