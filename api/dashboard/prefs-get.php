<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  API: dashboard/prefs-get.php
 *  خواندن تنظیمات روزانه‌ی داشبورد (کارهای ستاره‌دار، تب پیش‌فرض،
 *  فیلتر پیش‌فرض) — همان کوئری api/dashboard/bootstrap.php، جدا شده
 *  تا صفحاتی مثل task-detail.php هم بتوانند بدون بارگذاری کل
 *  باندل داشبورد، فقط همین را بخوانند.
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'روش غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT pref_key, pref_value FROM user_dashboard_prefs
        WHERE user_id = ? AND pref_date = CURDATE()
    ");
    $stmt->execute([$user_id]);

    $prefs = ['starred_tasks' => [], 'default_tab' => '', 'default_filter' => ''];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['pref_key'] === 'starred_tasks') {
            $decoded = json_decode($row['pref_value'], true);
            $prefs['starred_tasks'] = is_array($decoded) ? $decoded : [];
        } elseif (in_array($row['pref_key'], ['default_tab', 'default_filter'], true)) {
            $prefs[$row['pref_key']] = (string) $row['pref_value'];
        }
    }

    echo json_encode(['success' => true, 'prefs' => $prefs], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log("dashboard/prefs-get.php failed | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
