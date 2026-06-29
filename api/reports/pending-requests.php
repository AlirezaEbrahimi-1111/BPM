<?php
// api/reports/pending-requests.php

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../RequestManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $managerId = requireAuth();
    
    // بررسی اینکه کاربر مدیر است
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$managerId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['role'], ['manager', 'superior', 'admin'])) {
        http_response_code(403);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'دسترسی رد شد']);
        exit;
    }

    $requestManager = new RequestManager($db);

    // دریافت درخواست‌های در انتظار
    $pendingRequests = $requestManager->getPendingRequestsForManager($managerId);

    // افزودن اطلاعات نام کاربر
    foreach ($pendingRequests as &$request) {
        $stmt = $db->prepare("
            SELECT first_name, last_name
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$request['user_id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        $request['employee_name'] = ($employee ? $employee['first_name'] . ' ' . $employee['last_name'] : 'نامشخص');
    }

    http_response_code(200);
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $pendingRequests
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Pending requests error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ]);
}

?>