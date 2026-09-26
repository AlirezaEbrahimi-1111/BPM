<?php
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
    
    $target_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی مجوز
    if ($target_user != $user_id) {
        $stmt = $db->prepare("SELECT is_supervisor, manager_id, organization_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch();

        $stmt = $db->prepare("SELECT manager_id, organization_id FROM users WHERE id = ?");
        $stmt->execute([$target_user]);
        $target = $stmt->fetch();

        // 🔒 خط قرمز: مسئول فقط روی کاربران همان سازمان خودش این اختیار را دارد،
        // وگرنه یک supervisor می‌تواند مرخصی/مأموریت کاربران سازمان دیگر را ببیند
        $sameOrg = $target && (int)$target['organization_id'] === (int)$current_user['organization_id'];

        if (!$target || ($target['manager_id'] != $user_id && !($current_user['is_supervisor'] && $sameOrg))) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'شما مجاز به مشاهده این گزارش نیستید']);
            exit;
        }
    }
    
    // دریافت موجودی سالانه
    $stmt = $db->prepare("
        SELECT month, monthly_quota, carry_forward, used_leave, used_pass, pass_count_used
        FROM leave_balances
        WHERE user_id = ? AND year = ?
        ORDER BY month ASC
    ");
    $stmt->execute([$target_user, $year]);
    $monthly_balances = $stmt->fetchAll();
    
    // دریافت درخواست‌های مرخصی
    $stmt = $db->prepare("
        SELECT * FROM leave_requests
        WHERE user_id = ? AND YEAR(start_date) = ?
        ORDER BY start_date ASC
    ");
    $stmt->execute([$target_user, $year]);
    $leave_requests = $stmt->fetchAll();
    
    // دریافت پاس‌ها
    $stmt = $db->prepare("
        SELECT * FROM pass_requests
        WHERE user_id = ? AND YEAR(pass_date) = ?
        ORDER BY pass_date ASC
    ");
    $stmt->execute([$target_user, $year]);
    $pass_requests = $stmt->fetchAll();
    
    // محاسبه خلاصه
    $total_used_leave = 0;
    $total_used_pass = 0;
    $total_pass_count = 0;
    
    foreach ($monthly_balances as $balance) {
        $total_used_leave += $balance['used_leave'];
        $total_used_pass += $balance['used_pass'];
        $total_pass_count += $balance['pass_count_used'];
    }
    
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT first_name, last_name, daily_work_hours FROM users WHERE id = ?");
    $stmt->execute([$target_user]);
    $user_info = $stmt->fetch();
    
    $annual_quota = 24; // 24 روز در سال
    $remaining_annual = $annual_quota - $total_used_leave;
    
    echo json_encode([
        'success' => true,
        'user' => $user_info,
        'year' => $year,
        'summary' => [
            'annual_quota' => $annual_quota,
            'used_leave_days' => round($total_used_leave, 2),
            'used_pass_days' => round($total_used_pass, 2),
            'remaining_days' => round($remaining_annual, 2),
            'pass_count_used' => $total_pass_count
        ],
        'monthly_balances' => $monthly_balances,
        'leave_requests' => $leave_requests,
        'pass_requests' => $pass_requests
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>