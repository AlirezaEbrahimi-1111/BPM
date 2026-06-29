<?php
/**
 * API: حذف درخواست
 * مسیر: /attendance_system/api/requests/delete.php
 * متد: POST
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

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
$auth = new Auth($db);

// احراز هویت
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'لطفاً وارد شوید']);
    exit;
}

// دریافت داده‌ها
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$request_id = $data['id'] ?? null;
$request_type = $data['type'] ?? null;

if (!$request_id || !$request_type) {
    echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص است']);
    exit;
}

// تعیین جدول
$tables = [
    'mission' => 'mission_requests',
    'leave' => 'leave_requests',
    'pass' => 'pass_requests',
    'forget' => 'forget_requests',
    'technical' => 'technical_issues'
];

if (!isset($tables[$request_type])) {
    echo json_encode(['success' => false, 'message' => 'نوع درخواست نامعتبر است']);
    exit;
}

$table = $tables[$request_type];

try {
    // دریافت اطلاعات درخواست
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $user_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }

    // بررسی امکان حذف
    $can_delete = false;
    $error_message = '';

    if ($request_type === 'pass') {
        // پاس: تا 24 ساعت بعد از ارسال
        $created_at = new DateTime($request['created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created_at->getTimestamp();
        $hours_passed = $diff / 3600;

        if ($hours_passed <= 24) {
            $can_delete = true;
        } else {
            $error_message = 'مهلت حذف درخواست پاس (۲۴ ساعت) به پایان رسیده است';
        }
    } else {
        // بقیه: تا قبل از اولین تأیید
        $has_approval = false;

        if ($request_type === 'leave') {
            // مرخصی: چک substitute_approval
            if ($request['substitute_approval'] === 'approved') $has_approval = true;
            if ($request['manager_approval'] === 'approved') $has_approval = true;
            if ($request['supervisor_approval'] === 'approved') $has_approval = true;
        } elseif ($request_type === 'mission' || $request_type === 'forget') {
            // مأموریت و فراموشی: چک manager_approval
            if ($request['manager_approval'] === 'approved') $has_approval = true;
            if ($request['supervisor_approval'] === 'approved') $has_approval = true;
        } elseif ($request_type === 'technical') {
            // مشکل فنی: چک status
            if ($request['status'] === 'approved') $has_approval = true;
        }

        if (!$has_approval) {
            $can_delete = true;
        } else {
            $error_message = 'این درخواست قبلاً تأیید شده و قابل حذف نیست';
        }
    }

    if (!$can_delete) {
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    // حذف درخواست
    $stmt = $db->prepare("DELETE FROM {$table} WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $user_id]);

    echo json_encode(['success' => true, 'message' => 'درخواست با موفقیت حذف شد']);

} catch (Exception $e) {
    error_log("Delete request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در حذف درخواست']);
}
?>