<?php
/**
 * API: دریافت تاریخچه تأییدات درخواست
 * مسیر: /attendance_system/api/requests/timeline.php
 * متد: GET
 * پارامترها: id, type
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

// 🔒 خط قرمز: این فایل قبلا هیچ احراز هویتی نداشت — هر کاربر ناشناس با
// فقط دانستن id/type می‌توانست جزئیات درخواست هر کارمندی را ببیند
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'لطفا وارد شوید']);
    exit;
}

$request_id = $_GET['id'] ?? null;
$request_type = $_GET['type'] ?? null;

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
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }

    // 🔒 دسترسی: فقط خود درخواست‌دهنده یا یکی از تأییدکنندگان این درخواست
    $related_ids = array_filter([
        $request['user_id'] ?? null,
        $request['substitute_id'] ?? null,
        $request['manager_id'] ?? null,
        $request['supervisor_id'] ?? null,
        $request['admin_id'] ?? null,
    ]);
    if (!in_array((int)$user_id, array_map('intval', $related_ids), true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // تابع تبدیل تاریخ میلادی به شمسی با ساعت
    function formatDateTimeJalali($datetime) {
        if (empty($datetime)) return null;
        
        $date = substr($datetime, 0, 10);
        $time = substr($datetime, 11, 5);
        
        list($gy, $gm, $gd) = explode('-', $date);
        $gy = (int)$gy; $gm = (int)$gm; $gd = (int)$gd;
        
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $jy = ($gy <= 1600) ? 0 : 979;
        $gy = ($gy <= 1600) ? ($gy - 621) : ($gy - 1600);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy) + floor(($gy2 + 3) / 4) - floor(($gy2 + 99) / 100) + floor(($gy2 + 399) / 400) - 80 + $gd + $g_d_m[$gm - 1];
        $jy += 33 * floor($days / 12053);
        $days %= 12053;
        $jy += 4 * floor($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += floor(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + floor($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $days -= 186;
            $jm = 7 + floor($days / 30);
            $jd = 1 + ($days % 30);
        }
        
        return sprintf('%d/%02d/%02d - %s', $jy, $jm, $jd, $time);
    }

    // تابع دریافت نام کاربر
    function getUserName($db, $user_id) {
        if (empty($user_id)) return null;
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? ($user['first_name'] . ' ' . $user['last_name']) : null;
    }

    // تابع تبدیل وضعیت به فارسی
    function getStatusLabel($status) {
        $labels = [
            'approved' => 'تأیید شده',
            'rejected' => 'رد شده',
            'pending' => 'در انتظار',
            'waiting' => 'در نوبت'
        ];
        return $labels[$status] ?? $status;
    }

    $timeline = [];

    // ===== مرخصی =====
    if ($request_type === 'leave') {
        // مرحله 1: جانشین
        $sub_status = $request['substitute_approval'] ?? 'pending';
        $timeline[] = [
            'role' => 'substitute',
            'role_label' => 'تأیید جانشین',
            'status' => $sub_status,
            'status_label' => getStatusLabel($sub_status),
            'date' => formatDateTimeJalali($request['substitute_date']),
            'approver_name' => getUserName($db, $request['substitute_id']),
            'notes' => $request['substitute_notes']
        ];

        // مرحله 2: مدیر
        $mgr_status = $request['manager_approval'] ?? 'pending';
        if ($sub_status !== 'approved' && $sub_status !== 'rejected') {
            $mgr_status = 'waiting';
        }
        $timeline[] = [
            'role' => 'manager',
            'role_label' => 'تأیید مدیر',
            'status' => $mgr_status,
            'status_label' => $mgr_status === 'waiting' ? 'در نوبت' : getStatusLabel($mgr_status),
            'date' => formatDateTimeJalali($request['manager_date']),
            'approver_name' => getUserName($db, $request['manager_id']),
            'notes' => $request['manager_notes']
        ];

        // مرحله 3: مسئول
        $sup_status = $request['supervisor_approval'] ?? 'pending';
        if ($mgr_status !== 'approved') {
            $sup_status = 'waiting';
        }
        $timeline[] = [
            'role' => 'supervisor',
            'role_label' => 'تأیید مسئول',
            'status' => $sup_status,
            'status_label' => $sup_status === 'waiting' ? 'در نوبت' : getStatusLabel($sup_status),
            'date' => formatDateTimeJalali($request['supervisor_date']),
            'approver_name' => getUserName($db, $request['supervisor_id']),
            'notes' => $request['supervisor_notes']
        ];
    }
    // ===== مأموریت و فراموشی =====
    elseif (in_array($request_type, ['mission', 'forget'])) {
        // مرحله 1: مدیر
        $mgr_status = $request['manager_approval'] ?? 'pending';
        $timeline[] = [
            'role' => 'manager',
            'role_label' => 'تأیید مدیر',
            'status' => $mgr_status,
            'status_label' => getStatusLabel($mgr_status),
            'date' => formatDateTimeJalali($request['manager_date']),
            'approver_name' => getUserName($db, $request['manager_id']),
            'notes' => $request['manager_notes']
        ];

        // مرحله 2: مسئول
        $sup_status = $request['supervisor_approval'] ?? 'pending';
        if ($mgr_status !== 'approved') {
            $sup_status = 'waiting';
        }
        $timeline[] = [
            'role' => 'supervisor',
            'role_label' => 'تأیید مسئول',
            'status' => $sup_status,
            'status_label' => $sup_status === 'waiting' ? 'در نوبت' : getStatusLabel($sup_status),
            'date' => formatDateTimeJalali($request['supervisor_date']),
            'approver_name' => getUserName($db, $request['supervisor_id']),
            'notes' => $request['supervisor_notes']
        ];
    }
    // ===== مشکل فنی =====
    elseif ($request_type === 'technical') {
        $admin_status = $request['status'] ?? 'pending';
        $timeline[] = [
            'role' => 'admin',
            'role_label' => 'بررسی ادمین',
            'status' => $admin_status,
            'status_label' => getStatusLabel($admin_status),
            'date' => formatDateTimeJalali($request['admin_date']),
            'approver_name' => getUserName($db, $request['admin_id']),
            'notes' => $request['admin_notes'] ?? null
        ];
    }
    // ===== پاس =====
    elseif ($request_type === 'pass') {
        $timeline[] = [
            'role' => 'auto',
            'role_label' => 'تأیید خودکار',
            'status' => 'approved',
            'status_label' => 'تأیید شده (خودکار)',
            'date' => formatDateTimeJalali($request['created_at']),
            'approver_name' => null,
            'notes' => null
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $timeline
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Timeline error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در پردازش درخواست']);
}
?>