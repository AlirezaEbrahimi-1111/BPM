<?php
/**
 * API: ارسال درخواست جدید
 * مسیر: /attendance_system/api/requests/submit.php
 * متد: POST
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

// بررسی authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection
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
    // دریافت داده‌های JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    error_log('Submit Request Data: ' . json_encode($data));
    
    if (!$data) {
        throw new Exception('Invalid JSON');
    }
    
    $request_type = $data['request_type'] ?? null;
    $request_date = $data['request_date'] ?? null; // تاریخ جلالی
    $description = $data['description'] ?? '';
    $start_time = $data['start_time'] ?? null;
    $end_time = $data['end_time'] ?? null;
    $substitute_id = $data['substitute_id'] ?? null;
    
    if (!$request_type || !$request_date) {
        throw new Exception('Missing required fields: type=' . $request_type . ', date=' . $request_date);
    }
    
    // تبدیل تاریخ جلالی "1404/10/10" به میلادی
    $parts = explode('/', str_replace(array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'), array('0','1','2','3','4','5','6','7','8','9'), $request_date));
    
    if (count($parts) !== 3) {
        throw new Exception('Invalid date format: ' . $request_date);
    }
    
    $jy = intval($parts[0]);
    $jm = intval($parts[1]);
    $jd = intval($parts[2]);
    
    // تبدیل جلالی به میلادی
    $gy = $jy + 1474;
    if ($gy <= 0) $gy = $gy - 1;
    $days = (365 * $gy) + (intval($gy / 33) * 8) + (intval(($gy % 33 + 3) / 4)) + 78 + $jd;
    if ($jm < 7) {
        $days = $days + ($jm - 1) * 31;
    } else {
        $days = $days + ($jm - 7) * 30 + 186;
    }
    
    $gy = 400 * intval($days / 146097);
    $days = $days % 146097;
    $flag = true;
    if ($days >= 36525) { 
        $days -= 36525; 
        $gy += 100 * intval($days / 36524); 
        $days = $days % 36524; 
        if ($days >= 365) { $days -= 365; $flag = false; } 
    }
    $gy = $gy + 4 * intval($days / 1461);
    $days = $days % 1461;
    if ($flag) { $gy = $gy + intval($days / 365); $days = $days % 365; }
    
    $gm_days = [0, 31, (($gy % 4 === 0 && ($gy % 100 !== 0 || $gy % 400 === 0)) ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    for ($i = 0; $i < 13; $i++) { 
        if ($days < $gm_days[$i]) break; 
        $days = $days - $gm_days[$i]; 
    }
    $gm = $i;
    $gd = $days + 1;
    
    $gregorian_date = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    
    // ایجاد رکورد درخواست
    $stmt = $db->prepare("
        INSERT INTO requests 
        (user_id, request_date, request_type, description, start_time, end_time, substitute_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $stmt->execute([
        $user_id,
        $gregorian_date,
        $request_type,
        $description,
        $start_time,
        $end_time,
        $substitute_id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'درخواست با موفقیت ثبت شد',
        'data' => [
            'id' => $db->lastInsertId(),
            'request_date' => $request_date,
            'gregorian_date' => $gregorian_date
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Submit Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>