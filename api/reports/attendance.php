<?php
header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once '../../includes/AttendanceManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    
    $target_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : $user_id;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    
    $database = new Database();
    $db = $database->getConnection();
    
    // بررسی مجوز (فقط خود، مدیر تیم، یا مسئول)
    if ($target_user != $user_id) {
        $stmt = $db->prepare("SELECT role, is_supervisor, manager_id, organization_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch();

        $stmt = $db->prepare("SELECT manager_id, organization_id FROM users WHERE id = ?");
        $stmt->execute([$target_user]);
        $target = $stmt->fetch();

        $has_permission = false;

        // آیا مدیر مستقیم است؟
        if ($target && $target['manager_id'] == $user_id) {
            $has_permission = true;
        }

        // 🔒 خط قرمز: آیا مسئولِ همان سازمان است؟ (نه سازمان دیگر)
        if (
            $target && $current_user['is_supervisor']
            && (int)$target['organization_id'] === (int)$current_user['organization_id']
        ) {
            $has_permission = true;
        }

        if (!$has_permission) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'شما مجاز به مشاهده این گزارش نیستید']);
            exit;
        }
    }
    
    $attendanceManager = new AttendanceManager($db);
    
    // دریافت تقویم حضور
    $calendar = $attendanceManager->getAttendanceCalendar($target_user, $year, $month);
    
    // دریافت کسرکار ماهانه
    $deficit = $attendanceManager->getMonthlyDeficit($target_user, $year, $month);
    
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT first_name, last_name, phone, daily_work_hours FROM users WHERE id = ?");
    $stmt->execute([$target_user]);
    $user_info = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'user' => $user_info,
        'year' => $year,
        'month' => $month,
        'calendar' => $calendar,
        'summary' => $deficit
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
}
?>