<?php

/**
 * ═══════════════════════════════════════════════════════════════════
 *  API: dashboard/prefs-set.php
 *  ذخیره‌ی یکی از سه‌تا تنظیمِ روزانه‌ی داشبورد (کارهایِ ستاره‌دار، تبِ
 *  پیش‌فرض، فیلترِ پیش‌فرض) — سمتِ سرور، با تاریخِ سرور (نه کلاینت).
 * ═══════════════════════════════════════════════════════════════════
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'روش غیرمجاز']);
    exit;
}

// فقط همین سه کلید مجازن — جلوگیری از استفاده‌یِ این endpoint به‌عنوانِ
// یه key-value store عمومی
$ALLOWED_KEYS = ['starred_tasks', 'default_tab', 'default_filter'];

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);

    $prefKey = $input['pref_key'] ?? '';
    if (!in_array($prefKey, $ALLOWED_KEYS, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کلید نامعتبر']);
        exit;
    }

    // مقدار همیشه به‌صورتِ رشته ذخیره می‌شه (starred_tasks خودش JSON رشته‌شده می‌فرسته)
    $prefValue = $input['pref_value'] ?? '';
    if (!is_string($prefValue)) {
        $prefValue = json_encode($prefValue, JSON_UNESCAPED_UNICODE);
    }
    // محدودیتِ طول برایِ جلوگیری از سوءاستفاده (لیستِ ستاره‌ها معمولاً خیلی کوچیکه)
    if (strlen($prefValue) > 20000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'مقدار خیلی بزرگ است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        INSERT INTO user_dashboard_prefs (user_id, pref_key, pref_value, pref_date)
        VALUES (?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE pref_value = VALUES(pref_value), pref_date = VALUES(pref_date)
    ");
    $stmt->execute([$user_id, $prefKey, $prefValue]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    error_log("dashboard/prefs-set.php failed | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
