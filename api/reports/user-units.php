<?php
/**
 * API: user-units.php
 * دریافت واحدهای فعالیت کاربر و بررسی وضعیت گزارش امروز
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

    $user_id = requireAuth();
    $user = getUserInfo($user_id);

    if (!$user || !isset($user['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('اتصال به پایگاه داده ناموفق');
    }

    $today = date('Y-m-d');

    // نام واحدها
    $unitNames = [
        'RS' => 'کامپیوتر',
        'ATM' => 'فضای مجازی + رسانه',
        'AM' => 'نوجوانان',
        'AC' => 'حسابداری',
        'PR' => 'روابط عمومی',
        'HE' => 'تربیتی'
    ];

    // 1. دریافت واحد اصلی کاربر از جدول users
    $mainUnit = $user['activity_unit'] ?? null;

    // 2. دریافت واحدهای اضافی از جدول user_activity_units (اگر وجود داشت)
    $additionalUnits = [];
    try {
        $stmt = $db->prepare("
            SELECT activity_unit, is_primary 
            FROM user_activity_units 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $additionalUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    error_log('[' . basename(__FILE__) . '] ' . $e->getMessage());
        // جدول وجود ندارد یا خطای دیگر - ادامه بدون واحدهای اضافی
    }

    // 3. ترکیب واحدها
    $units = [];
    $addedUnits = [];

    // اضافه کردن واحد اصلی
    if ($mainUnit && !in_array($mainUnit, $addedUnits)) {
        $units[] = [
            'activity_unit' => $mainUnit,
            'unit_name' => $unitNames[$mainUnit] ?? $mainUnit,
            'is_primary' => true,
            'has_report_today' => false
        ];
        $addedUnits[] = $mainUnit;
    }

    // اضافه کردن واحدهای اضافی
    foreach ($additionalUnits as $unit) {
        if (!in_array($unit['activity_unit'], $addedUnits)) {
            $units[] = [
                'activity_unit' => $unit['activity_unit'],
                'unit_name' => $unitNames[$unit['activity_unit']] ?? $unit['activity_unit'],
                'is_primary' => (bool)$unit['is_primary'],
                'has_report_today' => false
            ];
            $addedUnits[] = $unit['activity_unit'];
        }
    }

    // اگر هیچ واحدی نداشت، همه واحدها را نشان بده
    if (empty($units)) {
        foreach ($unitNames as $code => $name) {
            $units[] = [
                'activity_unit' => $code,
                'unit_name' => $name,
                'is_primary' => false,
                'has_report_today' => false
            ];
        }
    }

    // 4. بررسی گزارش‌های امروز
    $stmt = $db->prepare("
        SELECT activity_unit 
        FROM reports 
        WHERE user_id = ? AND DATE(report_date) = ?
    ");
    $stmt->execute([$user_id, $today]);
    $todayReports = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // علامت‌گذاری واحدهایی که گزارش دارند
    foreach ($units as &$unit) {
        if (in_array($unit['activity_unit'], $todayReports)) {
            $unit['has_report_today'] = true;
        }
    }

    // 5. اطلاعات کاربر
    $userInfo = [
        'id' => $user['id'],
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'full_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'activity_unit' => $mainUnit,
        'report_prefix' => $user['report_prefix'] ?? '',
        'report_suffix' => $user['report_suffix'] ?? ''
    ];

    echo json_encode([
        'success' => true,
        'units' => $units,
        'user' => $userInfo,
        'today' => $today
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور',
        'error' => 'internal_error'
    ], JSON_UNESCAPED_UNICODE);
}

if (isset($db)) {
    $db = null;
}
?>