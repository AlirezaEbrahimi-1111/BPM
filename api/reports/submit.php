<?php
// api/reports/submit.php - ارسال گزارش روزانه
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true);
    
    // اعتبارسنجی ورودی‌ها
    if (empty($input['activity_unit'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'واحد فعالیت الزامی است']);
        exit;
    }
    
    if (empty($input['content']) || strlen(trim($input['content'])) < 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'محتوای گزارش باید حداقل 50 کاراکتر باشد']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $activity_unit = $input['activity_unit'];
    $content = trim($input['content']);
    $report_date = $input['report_date'] ?? date('Y-m-d');
    
    // بررسی اینکه کاربر در این واحد فعالیت دارد
    $checkUnit = $db->prepare("
        SELECT 1 FROM user_activity_units 
        WHERE user_id = ? AND activity_unit = ?
        UNION
        SELECT 1 FROM users 
        WHERE id = ? AND activity_unit = ?
        LIMIT 1
    ");
    $checkUnit->execute([$user_id, $activity_unit, $user_id, $activity_unit]);
    
    if ($checkUnit->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما در این واحد فعالیت ندارید']);
        exit;
    }
    
    // بررسی اینکه قبلاً گزارش برای امروز و این واحد ارسال نشده باشد
    $checkReport = $db->prepare("
        SELECT id, unique_code FROM reports 
        WHERE user_id = ? AND activity_unit = ? AND report_date = ?
    ");
    $checkReport->execute([$user_id, $activity_unit, $report_date]);
    
    if ($existingReport = $checkReport->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'شما قبلاً برای امروز و این واحد گزارش ارسال کرده‌اید',
            'existing_code' => $existingReport['unique_code']
        ]);
        exit;
    }
    
    // تولید کد یونیک برای گزارش
    $unique_code = generateUniqueReportCode($db);
    
    // ذخیره گزارش
    $sql = "INSERT INTO reports (unique_code, user_id, activity_unit, report_date, content, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$unique_code, $user_id, $activity_unit, $report_date, $content]);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'گزارش با موفقیت ارسال شد',
            'report_code' => $unique_code,
            'report_id' => $db->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در ذخیره گزارش']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور',
        'error' => $e->getMessage()
    ]);
}

// تابع تولید کد یونیک
function generateUniqueReportCode($db) {
    $maxAttempts = 10;
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        $year = date('y');
        $month = date('m');
        $day = date('d');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $code = "RPT{$year}{$month}{$day}{$random}";
        
        // بررسی یکتا بودن
        $check = $db->prepare("SELECT id FROM reports WHERE unique_code = ?");
        $check->execute([$code]);
        
        if ($check->rowCount() === 0) {
            return $code;
        }
        
        $attempt++;
    }
    
    // اگر بعد از 10 بار موفق نشد، از timestamp استفاده کن
    return "RPT" . date('ymdHis');
}
?>