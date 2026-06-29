<?php
/**
 * API: بررسی وضعیت اشتراک سازمان
 * GET /api/organization/check-subscription.php
 * 
 * برای همه کاربران قابل دسترسی است (بدون محدودیت role)
 * فقط اطلاعات days_remaining و وضعیت اشتراک را برمی‌گرداند
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    // احراز هویت — برای همه نقش‌ها
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // دریافت organization_id کاربر
    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['organization_id']) {
        echo json_encode([
            'success' => false,
            'message' => 'سازمان کاربر یافت نشد'
        ]);
        exit;
    }

    $orgId = $user['organization_id'];

    // آخرین اشتراک فعال
    $stmt = $db->prepare("
        SELECT end_date, is_active 
        FROM subscriptions 
        WHERE organization_id = ? 
        ORDER BY end_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$orgId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        echo json_encode([
            'success' => true,
            'has_subscription' => false,
            'days_remaining' => 0,
            'status' => 'no_subscription'
        ]);
        exit;
    }

    // محاسبه روزهای باقیمانده
    // ریست ساعت به 00:00:00 تا محاسبه روز دقیق باشه
    // مثال: امروز 24 می ساعت 10:32 و انقضا 26 می → باید 2 روز باشه نه 1
    $endDate = new DateTime($subscription['end_date']);
    $endDate->setTime(0, 0, 0);

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    $diff = $today->diff($endDate);
    $daysRemaining = $endDate >= $today ? (int)$diff->days : -(int)$diff->days;

    // تعیین وضعیت
    if ($subscription['is_active'] && $daysRemaining > 0) {
        $status = 'active';
    } elseif ($daysRemaining <= 0) {
        $status = 'expired';
    } else {
        $status = 'inactive';
    }

    echo json_encode([
        'success' => true,
        'has_subscription' => true,
        'days_remaining' => $daysRemaining,
        'end_date' => $subscription['end_date'],
        'status' => $status
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور'
    ]);
    error_log("Check subscription error: " . $e->getMessage());
}