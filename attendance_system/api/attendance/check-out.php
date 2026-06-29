<?php
/**
 * API: ثبت خروج (Check-out)
 * مسیر: /attendance_system/api/attendance/check-out.php
 * متد: POST
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');

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
    date_default_timezone_set('Asia/Tehran');
    
    // دریافت داده‌های JSON
    $json = file_get_contents('php://input');
    $request_data = json_decode($json, true);
    
    // دریافت ساعت از کلاینت (مهم‌ترین!)
    $check_datetime = $request_data['check_datetime'] ?? null;
    $check_time = $request_data['check_time'] ?? null;
    
    if (!$check_datetime && !$check_time) {
        throw new Exception('No time provided from client');
    }
    
    // اگر کلاینت datetime را فرستاد، از آن استفاده کن
    if ($check_datetime) {
        $check_out_datetime = $check_datetime;
        $check_out_time = substr($check_datetime, 11, 8);
    } else {
        // اگر فقط ساعت داشتیم
        $check_out_time = $check_time;
        $today = date('Y-m-d');
        $check_out_datetime = $today . ' ' . $check_out_time;
    }
    
    error_log('Check-out - DateTime: ' . $check_out_datetime . ' | Time: ' . $check_out_time);
    
    $today = date('Y-m-d');
    
    // دریافت رکورد امروز
    $stmt = $db->prepare("
        SELECT id, check_in FROM attendance
        WHERE user_id = ? AND date(check_in) = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $today]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ابتدا باید ورود ثبت کنید'
        ]);
        exit;
    }
    
    // محاسبه ساعات کار
    $check_in = new DateTime($record['check_in']);
    $check_out_obj = new DateTime($check_out_datetime);
    $interval = $check_in->diff($check_out_obj);
    $work_hours = $interval->h + ($interval->i / 60);
    
    // به‌روزرسانی
    $stmt = $db->prepare("
        UPDATE attendance
        SET check_out = ?, updated_at = NOW()
        WHERE user_id = ? AND date(check_in) = ?
    ");
    $stmt->execute([$check_out_datetime, $user_id, $today]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'خروج با موفقیت ثبت شد',
        'check_out_time' => $check_out_time,
        'check_out_datetime' => $check_out_datetime
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Check-out error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ثبت خروج: ' . $e->getMessage()
    ]);
}
?>