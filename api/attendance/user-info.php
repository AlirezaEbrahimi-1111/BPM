<?php
/**
 * API: دریافت اطلاعات کاربر جاری
 * مسیر: /api/attendance/user-info.php
 * متد: GET
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

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



try {
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("
        SELECT 
            first_name,
            last_name,
            shift_count,
            shift_1_start,
            shift_1_end,
            shift_2_start,
            shift_2_end,
            monthly_salary,
            daily_work_hours
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'user' => [
            'full_name' => $user['first_name'] . ' ' . $user['last_name'],
            'shift_count' => (int) $user['shift_count'],
            'shift_1_start' => substr($user['shift_1_start'], 0, 5),
            'shift_1_end' => substr($user['shift_1_end'], 0, 5),
            'shift_2_start' => $user['shift_2_start'] ? substr($user['shift_2_start'], 0, 5) : null,
            'shift_2_end' => $user['shift_2_end'] ? substr($user['shift_2_end'], 0, 5) : null,
            'monthly_salary' => (float) $user['monthly_salary'],
            'daily_work_hours' => (float) $user['daily_work_hours']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("User info error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا: ' . $e->getMessage()
    ]);
}
?>