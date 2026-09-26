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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php'; // isSuperAdmin()
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/settings_helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/leave-balance-helper.php'; // jalaliPeriodKey()

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
    echo json_encode(['success' => false, 'message' => 'لطفا وارد شوید']);
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
    // دریافت اطلاعات درخواست (فقط با شناسه؛ مالکیت پایین‌تر بررسی می‌شود)
    $stmt = $db->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }

    $owner_id = (int) $request['user_id'];
    $is_own = ($owner_id === (int) $user_id);

    // 🆕 مسئول (supervisor / سوپرادمین) می‌تواند درخواست مأموریت، فراموشی و مشکل فنی
    // «ماه جاری» را حذف کند — حتی بعد از تأیید و حتی برای دیگر کاربران همان سازمان.
    // ماه جاری = ماه شمسی تاریخ شروع درخواست. سایر انواع (مرخصی/پاس) مشمول نیست.
    $sup_delete = false;
    $me_stmt = $db->prepare("SELECT id, role, organization_id, first_name, last_name FROM users WHERE id = ?");
    $me_stmt->execute([$user_id]);
    $me = $me_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $is_supervisor_user = !empty($me) && (isSuperAdmin($me) || ($me['role'] ?? '') === 'supervisor');

    if ($is_supervisor_user && in_array($request_type, ['mission', 'forget', 'technical'], true)) {
        $own_org_stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
        $own_org_stmt->execute([$owner_id]);
        $same_org = ((int) $own_org_stmt->fetchColumn() === (int) ($me['organization_id'] ?? 0));
        if ($same_org) {
            $req_date = substr($request['start_date'] ?? '', 0, 10);
            if ($req_date !== '' && jalaliPeriodKey($req_date) === jalaliPeriodKey(date('Y-m-d'))) {
                $sup_delete = true;
            } elseif (!$is_own) {
                echo json_encode(['success' => false, 'message' => 'فقط درخواست‌های ماه جاری قابل حذف هستند؛ این درخواست برای ماه دیگری ثبت شده است']);
                exit;
            }
        }
    }

    // غیرمالک بدون اختیار مسئول: وجود درخواست را فاش نکن
    if (!$is_own && !$sup_delete) {
        echo json_encode(['success' => false, 'message' => 'درخواست یافت نشد']);
        exit;
    }

    // بررسی امکان حذف
    $can_delete = false;
    $burn_quota = false; // مرخصی تأییدشدهٔ ماه جاری که حذف می‌شود → سهمیه برنمی‌گردد و «می‌سوزد»
    $error_message = '';

    if ($sup_delete) {
        $can_delete = true; // حذف توسط مسئول: بدون شرط وضعیت تأیید
    } elseif ($request_type === 'pass') {
        // پاس: تا pass_edit_hours «ساعت کاری» بعد از ارسال — جمعه/تعطیلات کاملا
        // نادیده گرفته می‌شوند
        $pass_edit_hours = (int) getSetting($db, 'pass_edit_hours', 24);
        $created_at = new DateTime($request['created_at']);
        $now = new DateTime();
        $__orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
        $__orgStmt->execute([$user_id]);
        $__org_id = (int) $__orgStmt->fetchColumn();
        $holidays = getHolidaySet($db, $__org_id);
        $recurringWeekdays = getRecurringHolidayWeekdays($db, $__org_id);
        $deadline = addWorkingHours($created_at, $pass_edit_hours, $holidays, $recurringWeekdays);

        if ($now <= $deadline) {
            $can_delete = true;
        } else {
            $error_message = "مهلت حذف درخواست پاس ({$pass_edit_hours} ساعت کاری) در تاریخ " .
                $deadline->format('Y-m-d H:i') . ' به پایان رسیده است';
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
        } elseif ($request_type === 'leave') {
            // استثنا: مرخصی همین ماه شمسی خود کاربر حتی بعد از تأیید نهایی هم قابل حذف است،
            // اما سهمیهٔ کسرشدهٔ آن برنمی‌گردد (می‌سوزد). ماه بر اساس تاریخ شروع مرخصی سنجیده می‌شود.
            $leave_date = substr($request['start_date'] ?? '', 0, 10);
            if ($leave_date !== '' && jalaliPeriodKey($leave_date) === jalaliPeriodKey(date('Y-m-d'))) {
                $can_delete = true;
                $burn_quota = true;
            } else {
                $error_message = 'فقط مرخصی همین ماه قابل حذف است؛ این درخواست برای ماه دیگری ثبت شده است';
            }
        } else {
            $error_message = 'این درخواست قبلا تأیید شده و قابل حذف نیست';
        }
    }

    if (!$can_delete) {
        error_log("Attendance request delete denied ({$error_message}) | user_id={$user_id} | request_id={$request_id} | request_type={$request_type}");
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }

    // ✅ مرخصی/پاس حذف‌شده: سهمیه‌ای که موقع ثبت کسر شده بود برمی‌گرده
    //    استثنا: مرخصی تأییدشدهٔ ماه جاری ($burn_quota) — سهمیه‌اش می‌سوزد و برنمی‌گردد.
    if (($request_type === 'leave' || $request_type === 'pass') && !$burn_quota) {
        $ded_amount = findLeaveDeduction($db, $request_type, (int) $request_id);
        if ($ded_amount !== null) {
            $stmt = $db->prepare("
                INSERT INTO leave_balance_transactions (user_id, type, amount, related_request_id, related_request_type, note)
                VALUES (?, 'manual_adjustment', ?, ?, ?, 'بازگشت سهمیه به‌دلیل حذف درخواست')
            ");
            $stmt->execute([$owner_id, -$ded_amount, $request_id, $request_type]);
        }
    }

    // حذف درخواست
    $stmt = $db->prepare("DELETE FROM {$table} WHERE id = ? AND user_id = ?");
    $stmt->execute([$request_id, $owner_id]);

    // 🆕 حذف درخواستِ دیگری توسط مسئول: ردپا در لاگ + اطلاع به صاحب درخواست
    if ($sup_delete && !$is_own) {
        $type_labels = ['mission' => 'مأموریت', 'forget' => 'فراموشی', 'technical' => 'مشکل فنی'];
        $by_name = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? ''));
        error_log("Attendance request deleted by supervisor | supervisor_id={$user_id} | owner_id={$owner_id} | request_id={$request_id} | request_type={$request_type} | request_code=" . ($request['request_code'] ?? '') . " | status=" . ($request['status'] ?? ''));
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
            $notif = new Notification($db);
            $notif->create([
                'to_user_id' => $owner_id,
                'title' => 'درخواست شما حذف شد',
                'message' => "درخواست {$type_labels[$request_type]} شما (کد " . ($request['request_code'] ?? '—') . ") توسط {$by_name} حذف شد.",
                'type' => 'warning',
                'link' => '/attendance_system/pages/requests.php',
                'skip_self_check' => true
            ]);
        } catch (Exception $notifError) {
            error_log("Notification error in attendance delete (supervisor): " . $notifError->getMessage());
        }
    }

    $ok_message = $burn_quota
        ? 'درخواست مرخصی حذف شد. توجه: سهمیهٔ این مرخصی بازنگشت و سوخت.'
        : 'درخواست با موفقیت حذف شد';
    echo json_encode(['success' => true, 'message' => $ok_message]);

} catch (Exception $e) {
    error_log("Delete request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در حذف درخواست']);
}
?>