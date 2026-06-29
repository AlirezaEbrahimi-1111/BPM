<?php
/**
 * API: دریافت تمام درخواست‌های کاربر
 * مسیر: /attendance_system/api/requests/get-all.php
 * متد: GET
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

// بررسی authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

try {
    // بررسی وجود جدول requests
    $check_table = $db->query("SELECT 1 FROM requests LIMIT 1");
    
    // دریافت تمام درخواست‌های کاربر
    $stmt = $db->prepare("
        SELECT 
            id,
            request_date,
            request_type,
            description,
            status
        FROM requests
        WHERE user_id = ?
        ORDER BY request_date DESC
    ");
    $stmt->execute([$user_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $requests
    ]);
    
} catch (Exception $e) {
    // اگر جدول موجود نباشد
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
}
?>