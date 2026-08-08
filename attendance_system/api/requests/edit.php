<?php
/**
 * API: ویرایش درخواست
 * مسیر: /attendance_system/api/requests/edit.php
 * متد: POST
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';

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

    // بررسی امکان ویرایش
    $can_edit = false;
    $error_message = '';

    if ($request_type === 'pass') {
        // پاس: تا pass_edit_hours ساعت بعد از ارسال (طبقِ تنظیماتِ واقعی، نه هاردکد)
        $pass_edit_hours = (int) getSetting($db, 'pass_edit_hours', 24);
        $created_at = new DateTime($request['created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created_at->getTimestamp();
        $hours_passed = $diff / 3600;

        if ($hours_passed <= $pass_edit_hours) {
            $can_edit = true;
        } else {
            $error_message = "مهلت ویرایش درخواست پاس ({$pass_edit_hours} ساعت) به پایان رسیده است";
        }
    } else {
        // بقیه: تا قبل از اولین تأیید
        $has_approval = false;

        if ($request_type === 'leave') {
            if ($request['substitute_approval'] === 'approved') $has_approval = true;
            if ($request['manager_approval'] === 'approved') $has_approval = true;
            if ($request['supervisor_approval'] === 'approved') $has_approval = true;
        } elseif ($request_type === 'mission' || $request_type === 'forget') {
            if ($request['manager_approval'] === 'approved') $has_approval = true;
            if ($request['supervisor_approval'] === 'approved') $has_approval = true;
        } elseif ($request_type === 'technical') {
            if ($request['status'] === 'approved') $has_approval = true;
        }

        if (!$has_approval) {
            $can_edit = true;
        } else {
            $error_message = 'این درخواست قبلاً تأیید شده و قابل ویرایش نیست';
        }
    }

    if (!$can_edit) {
        error_log("Attendance request edit denied ({$error_message}) | user_id={$user_id} | request_id={$request_id} | request_type={$request_type}");
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    // آپدیت درخواست بر اساس نوع
    if ($request_type === 'mission') {
        $start_datetime = $data['start_datetime'] ?? null;
        $end_datetime = $data['end_datetime'] ?? null;
        $description = $data['description'] ?? null;

        if (!$start_datetime || !$end_datetime || !$description) {
            echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
            exit;
        }

        $stmt = $db->prepare("UPDATE mission_requests SET start_date = ?, end_date = ?, purpose = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$start_datetime, $end_datetime, $description, $request_id]);
    }
    elseif ($request_type === 'leave') {
        $start_datetime = $data['start_datetime'] ?? null;
        $end_datetime = $data['end_datetime'] ?? null;
        $reason = $data['reason'] ?? null;

        if (!$start_datetime || !$end_datetime || !$reason) {
            echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
            exit;
        }

        list($start_date, $start_time) = explode(' ', $start_datetime);
        list($end_date, $end_time) = explode(' ', $end_datetime);

        $stmt = $db->prepare("UPDATE leave_requests SET start_date = ?, end_date = ?, start_time = ?, end_time = ?, reason = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$start_date, $end_date, $start_time, $end_time, $reason, $request_id]);
    }
    elseif ($request_type === 'pass') {
        $pass_date = $data['pass_date'] ?? null;
        $start_time = $data['start_time'] ?? null;
        $end_time = $data['end_time'] ?? null;
        $reason = $data['reason'] ?? null;

        if (!$pass_date || !$start_time || !$end_time || !$reason) {
            echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
            exit;
        }

        $stmt = $db->prepare("UPDATE pass_requests SET pass_date = ?, start_time = ?, end_time = ?, reason = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$pass_date, $start_time, $end_time, $reason, $request_id]);
    }
    elseif ($request_type === 'forget') {
        $datetime = $data['datetime'] ?? null;
        $end_time_full = $data['end_time'] ?? null;
        $description = $data['description'] ?? null;

        if (!$datetime || !$end_time_full || !$description) {
            echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
            exit;
        }

        $stmt = $db->prepare("UPDATE forget_requests SET start_date = ?, end_date = ?, description = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$datetime, $end_time_full, $description, $request_id]);
    }
    elseif ($request_type === 'technical') {
        $datetime = $data['datetime'] ?? null;
        $end_time = $data['end_time'] ?? null;
        $description = $data['description'] ?? null;

        if (!$datetime || !$end_time || !$description) {
            echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
            exit;
        }

        list($date_miladi, $start_time) = explode(' ', $datetime);

        $stmt = $db->prepare("UPDATE technical_issues SET start_date = ?, end_date = ?, start_time = ?, end_time = ?, description = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$date_miladi, $date_miladi, $start_time, $end_time, $description, $request_id]);
    }

    echo json_encode(['success' => true, 'message' => 'درخواست با موفقیت ویرایش شد']);

} catch (Exception $e) {
    error_log("Edit request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در ویرایش درخواست']);
}
?>