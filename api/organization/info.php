<?php
/**
 * API: دریافت اطلاعات سازمان
 * GET /api/organization/info.php
 * فقط برای supervisor
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

try {
    // احراز هویت - مطابق list.php
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // دریافت اطلاعات کاربر فعلی
    $stmt = $db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        throw new Exception("User not found");
    }

    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'view_org_settings');
    $orgId = $currentUser['organization_id'];

    $orgId = $currentUser['organization_id'];

    // اطلاعات سازمان
    $stmt = $db->prepare("SELECT * FROM organizations WHERE id = ?");
    $stmt->execute([$orgId]);
    $org = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$org) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'سازمان یافت نشد']);
        exit;
    }

    // آخرین اشتراک
    $stmt = $db->prepare("
        SELECT * FROM subscriptions 
        WHERE organization_id = ? 
        ORDER BY end_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$orgId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    // آمار سازمان
    $stats = [];

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE organization_id = ?");
    $stmt->execute([$orgId]);
    $stats['total_users'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

// تعداد واحدهای یونیک (از فیلد activity_unit کاربران سازمان)
$stmt = $db->prepare("SELECT COUNT(DISTINCT activity_unit) as total FROM users WHERE organization_id = ?");
$stmt->execute([$orgId]);
$stats['total_units'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];


    // کل تسک‌های سازمان (بدون حذف‌شده‌ها)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM tasks 
        WHERE organization_id = ? AND is_deleted = 0
    ");
    $stmt->execute([$orgId]);
    $stats['total_tasks'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // تسک‌های فعال (نه تکمیل، نه لغو، نه متوقف)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM tasks 
        WHERE organization_id = ? AND is_deleted = 0
        AND status NOT IN ('completed', 'approved', 'stopped')
    ");
    $stmt->execute([$orgId]);
    $stats['active_tasks'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];


    // محاسبه روزهای باقیمانده
    $daysRemaining = null;
    $subscriptionStatus = 'inactive';
    if ($subscription) {
        $endDate = new DateTime($subscription['end_date']);
        $today = new DateTime();
        $diff = $today->diff($endDate);
        $daysRemaining = $endDate > $today ? (int)$diff->days : -(int)$diff->days;

        if ($subscription['is_active'] && $daysRemaining > 0) {
            $subscriptionStatus = 'active';
        } elseif ($daysRemaining <= 0) {
            $subscriptionStatus = 'expired';
        } else {
            $subscriptionStatus = 'inactive';
        }
    }

    echo json_encode([
        'success' => true,
        'organization' => [
            'id' => (int)$org['id'],
            'name' => $org['name'],
            'subdomain' => $org['subdomain'] ?? '',
            'logo' => $org['logo'] ?? '',
            'phone' => $org['phone'] ?? '',
            'address' => $org['address'] ?? '',
            'max_users' => (int)($org['max_users'] ?? 0),
            'is_active' => (bool)($org['is_active'] ?? false),
            'created_at' => $org['created_at'] ?? ''
        ],
        'subscription' => $subscription ? [
            'id' => (int)$subscription['id'],
            'plan_type' => $subscription['plan_type'],
            'max_users' => (int)$subscription['max_users'],
            'price' => $subscription['price'],
            'start_date' => $subscription['start_date'],
            'end_date' => $subscription['end_date'],
            'is_active' => (bool)$subscription['is_active'],
            'days_remaining' => $daysRemaining,
            'status' => $subscriptionStatus
        ] : null,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Organization info error: " . $e->getMessage());
}
