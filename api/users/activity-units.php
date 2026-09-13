<?php
// api/users/units.php - دریافت واحدهای فعالیت کاربر
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $database = new Database();
    $db = $database->getConnection();
    
    // دریافت واحدهای فعالیت کاربر از جدول user_activity_units
    $sql = "SELECT uau.activity_unit, uau.is_primary,
                   (SELECT COUNT(*) 
                    FROM reports r 
                    WHERE r.user_id = ? 
                    AND r.activity_unit = uau.activity_unit 
                    AND r.report_date = CURDATE()) as has_report_today
            FROM user_activity_units uau
            WHERE uau.user_id = ?
            ORDER BY uau.is_primary DESC, uau.activity_unit ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $units = $stmt->fetchAll();
    
    // اگر کاربر واحدی در جدول user_activity_units ندارد، از فیلد activity_unit در جدول users استفاده کن
    if (empty($units)) {
        $sql = "SELECT activity_unit as activity_unit, 
                       1 as is_primary,
                       (SELECT COUNT(*) 
                        FROM reports r 
                        WHERE r.user_id = ? 
                        AND r.activity_unit = users.activity_unit 
                        AND r.report_date = CURDATE()) as has_report_today
                FROM users 
                WHERE id = ? AND activity_unit IS NOT NULL";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $user_id]);
        $units = $stmt->fetchAll();
    }
    
    // نام‌های فارسی واحدها
    $unitNames = [
        'RS' => 'کامپیوتر',
        'ATM' => 'فضای مجازی + رسانه',
        'AM' => 'نوجوانان',
        'AC' => 'حسابداری',
        'PR' => 'روابط عمومی',
        'HE' => 'تربیتی'
    ];
    
    // اضافه کردن نام فارسی به هر واحد
    foreach ($units as &$unit) {
        $unit['unit_name'] = $unitNames[$unit['activity_unit']] ?? $unit['activity_unit'];
        $unit['has_report_today'] = (int)$unit['has_report_today'] > 0;
    }
    
    echo json_encode([
        'success' => true,
        'units' => $units,
        'total' => count($units)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور',
        'error' => 'internal_error'
    ]);
    error_log("Get user units error: " . $e->getMessage());
}
?>