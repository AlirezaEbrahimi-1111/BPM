<?php
// api/reports/detail.php - دریافت جزئیات یک گزارش
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    if (empty($_GET['id']) && empty($_GET['code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه یا کد گزارش الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // جستجو با شناسه یا کد یونیک
    if (!empty($_GET['id'])) {
        $sql = "SELECT * FROM reports WHERE id = ? AND user_id = ?";
        $params = [$_GET['id'], $user_id];
    } else {
        $sql = "SELECT * FROM reports WHERE unique_code = ? AND user_id = ?";
        $params = [$_GET['code'], $user_id];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $report = $stmt->fetch();

    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'گزارش یافت نشد']);
        exit;
    }

    // نام‌های واحدها
    $unitNames = [
        'RS' => 'کامپیوتر',
        'ATM' => 'فضای مجازی + رسانه',
        'AM' => 'نوجوانان',
        'AC' => 'حسابداری',
        'PR' => 'روابط عمومی',
        'HE' => 'تربیتی'
    ];

    $report['unit_name'] = $unitNames[$report['activity_unit']] ?? $report['activity_unit'];

    echo json_encode([
        'success' => true,
        'report' => $report
    ]);
} catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور',
        'error' => 'internal_error'
    ]);
}
