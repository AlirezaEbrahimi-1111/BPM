<?php
// api/reports/save.php - ذخیره گزارش روزانه
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');

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
    
    if (empty($input['unit']) || empty($input['date']) || empty($input['content'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'واحد، تاریخ و محتوا الزامی است']);
        exit;
    }
    
    $unit = $input['unit'];
    $date = $input['date'];
    $content = $input['content'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی اینکه آیا قبلاً برای این روز و واحد گزارش ارسال شده
    $stmt = $db->prepare("SELECT id FROM reports WHERE user_id = ? AND activity_unit = ? AND report_date = ?");
    $stmt->execute([$user_id, $unit, $date]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'شما قبلاً برای این تاریخ و واحد فعالیت گزارش ارسال کرده‌اید'
        ]);
        exit;
    }
    
    // تولید کد یونیک
    $unique_code = generateUniqueCode($db);
    
    // ذخیره گزارش
    $sql = "INSERT INTO reports (unique_code, user_id, activity_unit, report_date, content) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$unique_code, $user_id, $unit, $date, $content]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'گزارش با موفقیت ذخیره شد',
            'unique_code' => $unique_code
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در ذخیره گزارش']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}

function generateUniqueCode($db) {
    do {
        $code = 'RPT' . date('ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT id FROM reports WHERE unique_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    
    return $code;
}
?>