<?php
// api/requests/by-date.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../RequestManager.php';
require_once '../JalaliDate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $userId = requireAuth();
    
    if (empty($_GET['date'])) {
        http_response_code(400);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'تاریخ الزامی است']);
        exit;
    }

    $date = $_GET['date'];
    // اگر تاریخ شمسی بود، تبدیل به میلادی
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        // بررسی اینکه میلادی است یا شمسی
        $parts = explode('-', $date);
        if ($parts[0] >= 1300) {
            // شمسی است
            $date = JalaliDate::toGregorian($date);
        }
    }

    $database = new Database();
    $db = $database->getConnection();
    $requestManager = new RequestManager($db);

    // دریافت درخواست‌ها
    $requests = $requestManager->getRequestsByDate($userId, $date);

    http_response_code(200);
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'date' => $date,
            'requests' => $requests
        ]
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ]);
}

?>