<?php



require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';

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

$user_id = null;

// روش 1: SESSION
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    error_log("✅ User ID from SESSION: " . $user_id);
}

// روش 2: JWT Token از header
if (!$user_id) {
    $user_id = $auth->getUserFromToken();
    if ($user_id) {
        error_log("✅ User ID from JWT header: " . $user_id);
    }
}

// روش 3: JWT Token از Cookie
if (!$user_id && isset($_COOKIE['auth_token'])) {
    error_log("🔍 Trying cookie token...");
    $token = $_COOKIE['auth_token'];
    $user_id = $auth->validateToken($token);
    if ($user_id) {
        error_log("✅ User ID from cookie: " . $user_id);
    }
}

// ✅ فقط date_helper رو include کن
require_once '../../includes/date_helper.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sms.php';

// تابع تبدیل اعداد فارسی به انگلیسی
function convertPersianToEnglish($string)
{
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($persian, $english, $string);
}


// تابع تولید کد یکتا
function generateRequestCode($prefix, $user_id)
{
    $today = date('Y-m-d');
    list($g_y, $g_m, $g_d) = explode('-', $today);
    list($j_y, $j_m, $j_d) = gregorianToJalali($g_y, $g_m, $g_d);

    // فقط 2 رقم سال + ماه + روز + user_id + 2 رقم random
    $random = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
    $code = $prefix . sprintf('%02d%02d%02d%02d', $j_y % 100, $j_m, $j_d, $user_id) . $random;

    return $code;
}

// ✅ تابع چک کردن آیا مدیر کاربر همان مسئول کل است یا نه
function isManagerSupervisor($db, $user_id)
{
    try {
        // دریافت manager_id کاربر
        $stmt = $db->prepare("SELECT manager_id, manager_code FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user_info || !$user_info['manager_id']) {
            return false;
        }

        // دریافت اطلاعات مدیر
        $stmt = $db->prepare("SELECT manager_code FROM users WHERE id = ?");
        $stmt->execute([$user_info['manager_id']]);
        $manager_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // اگر manager_code مدیر خالی باشد = مسئول کل است
        return $manager_info && empty($manager_info['manager_code']);

    } catch (Exception $e) {
        error_log("Error in isManagerSupervisor: " . $e->getMessage());
        return false;
    }
}

// دریافت داده‌های POST
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$type = $data['type'] ?? null;

if (!$type) {
    echo json_encode(['success' => false, 'message' => 'نوع درخواست مشخص نیست']);
    exit;
}

$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';

if ($start_time && $end_time) {
    $start = strtotime($start_time);
    $end = strtotime($end_time);

    if ($end <= $start) {
        // برگردوندن خطا به کاربر
        echo json_encode(['success' => false, 'message' => 'ساعت پایان نمی‌تواند قبل از ساعت شروع باشد.']);
        exit;
    }
}

/**
 * ارسال نوتیفیکیشن و پیامک به نفر بعدی در زنجیره تأیید
 */
/**
 * ارسال نوتیفیکیشن و پیامک به نفر بعدی در زنجیره تأیید
 * 
 * مسئول = کسی که role=manager و manager_code=id خودش
 * مشکل فنی = فقط supervisor تأیید می‌کند (نه مسئول)
 */
function notifyNextApprover($db, $user_id, $request_type, $request_id, $request_code)
{
    try {
        // دریافت اطلاعات درخواست‌دهنده
        $stmt = $db->prepare("SELECT first_name, last_name, manager_code, manager_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $requester = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$requester)
            return;

        $requester_name = trim(($requester['first_name'] ?? '') . ' ' . ($requester['last_name'] ?? ''));

        $type_labels = [
            'mission' => 'مأموریت',
            'leave' => 'مرخصی',
            'pass' => 'پاس',
            'forget' => 'فراموشی ثبت',
            'technical' => 'مشکل فنی'
        ];
        $type_label = $type_labels[$request_type] ?? $request_type;

        $notify_user_id = null;
        $role_label = '';

        if ($request_type === 'leave') {
            // مرخصی: اول به جانشین
            $stmt = $db->prepare("SELECT substitute_id FROM leave_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($leave && !empty($leave['substitute_id'])) {
                $notify_user_id = $leave['substitute_id'];
                $role_label = 'جانشین';
            }

        } elseif (in_array($request_type, ['mission', 'forget'])) {
            // مأموریت و فراموشی: به مدیر
            $manager_id = $requester['manager_code'] ?: $requester['manager_id'];
            if ($manager_id) {
                $notify_user_id = $manager_id;
                $role_label = 'مدیر';
            }

        } elseif ($request_type === 'technical') {
            // مشکل فنی: فقط به supervisor ها (نه مسئول)
            // مسئول = کسی که role=manager و manager_code=id خودش
            $stmt = $db->prepare("
                SELECT id FROM users 
                WHERE is_supervisor = 1 
                  AND is_active = 1 
                  AND id != ?
                  AND NOT (role = 'manager' AND (manager_code = id OR manager_id = id))
            ");
            $stmt->execute([$user_id]);
            $supervisors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notif = new Notification($db);
            foreach ($supervisors as $sup) {
                $notif->create([
                    'to_user_id' => $sup['id'],
                    'title' => "درخواست مشکل فنی جدید",
                    'message' => "درخواست مشکل فنی از {$requester_name} ثبت شده و منتظر بررسی شماست.",
                    'type' => 'warning',
                    'related_type' => $request_type,
                    'related_id' => $request_id,
                    'link' => '/attendance_system/pages/requests.php?tab=pending-approvals'
                ]);
            }
            return;
        }

        if (!$notify_user_id)
            return;

        $notif = new Notification($db);
        $notif->create([
            'to_user_id' => $notify_user_id,
            'title' => "درخواست {$type_label} جدید",
            'message' => "درخواست {$type_label} از {$requester_name} ثبت شده و منتظر تأیید شما ({$role_label}) است.",
            'type' => 'warning',
            'related_type' => $request_type,
            'related_id' => $request_id,
            'link' => '/attendance_system/pages/requests.php?tab=pending-approvals'
        ]);

    } catch (Exception $e) {
        error_log("notifyNextApprover error: " . $e->getMessage());
    }
}
// ============================================================
// درخواست مأموریت
// ============================================================
if ($type === 'mission') {
    $start_datetime = $data['start_datetime'] ?? null;
    $end_datetime = $data['end_datetime'] ?? null;
    $description = $data['description'] ?? null;

    if (!$start_datetime || !$end_datetime || !$description) {
        echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
        exit;
    }

    // ✅ JavaScript الان میلادی می‌فرسته: "2025-12-30 15:10"
    // دیگه نیازی به تبدیل نیست!
    $start_date_miladi = $start_datetime;
    $end_date_miladi = $end_datetime;


    $request_code = generateRequestCode('ma', $user_id);


    $stmt = $db->prepare("
        INSERT INTO mission_requests (user_id, request_code, start_date, end_date, purpose, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");


    $stmt->execute([$user_id, $request_code, $start_date_miladi, $end_date_miladi, $description]);

    $last_id = $db->lastInsertId(); // ✅ همیشه بگیر


    // ✅ چک کنیم مدیر = مسئول است یا نه
    if (isManagerSupervisor($db, $user_id)) {
        $last_id = $db->lastInsertId();
        // supervisor_approval را از اول approved می‌کنیم
        $stmt = $db->prepare("UPDATE mission_requests SET supervisor_approval = 'approved' WHERE id = ?");
        $stmt->execute([$last_id]);
    }


    // ✅ نوتیفیکیشن و پیامک به مدیر
    notifyNextApprover($db, $user_id, 'mission', $last_id, $request_code);
    echo json_encode([
        'success' => true,
        'message' => 'درخواست مأموریت ثبت شد',
        'code' => $request_code
    ]);
    exit;
}

// ============================================================
// درخواست مرخصی
// ============================================================
if ($type === 'leave') {
    $start_datetime = $data['start_datetime'] ?? null;
    $end_datetime = $data['end_datetime'] ?? null;
    $reason = $data['reason'] ?? null;
    $substitute_id = $data['substitute_id'] ?? null; // ✅ جانشین

    if (!$start_datetime || !$end_datetime || !$reason) {
        echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
        exit;
    }

    // ✅ اعتبارسنجی جانشین
    if (!$substitute_id) {
        echo json_encode(['success' => false, 'message' => 'لطفاً جانشین خود را انتخاب کنید']);
        exit;
    }

    // بررسی وجود جانشین
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$substitute_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'جانشین انتخاب شده معتبر نیست']);
        exit;
    }

    // جانشین نباید خود کاربر باشد
    if ($substitute_id == $user_id) {
        echo json_encode(['success' => false, 'message' => 'نمی‌توانید خودتان را به عنوان جانشین انتخاب کنید']);
        exit;
    }

    // ✅ JavaScript الان میلادی می‌فرسته
    $start_date_miladi = $start_datetime;
    $end_date_miladi = $end_datetime;

    $request_code = generateRequestCode('mo', $user_id);

    // تجزیه به date و time
    list($start_date, $start_time) = explode(' ', $start_datetime);
    list($end_date, $end_time) = explode(' ', $end_datetime);

    $stmt = $db->prepare("
        INSERT INTO leave_requests (user_id, request_code, start_date, end_date, start_time, end_time, reason, substitute_id, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$user_id, $request_code, $start_date, $end_date, $start_time, $end_time, $reason, $substitute_id]);

    $leave_id = $db->lastInsertId();

    // ✅ ثبت/آپدیت جانشین در جدول substitutes
    // اول چک کن آیا قبلاً وجود دارد
    $stmt = $db->prepare("SELECT id FROM substitutes WHERE user_id = ? AND substitute_user_id = ?");
    $stmt->execute([$user_id, $substitute_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // آپدیت: فعال کردن
        $stmt = $db->prepare("UPDATE substitutes SET is_active = 1 WHERE user_id = ? AND substitute_user_id = ?");
        $stmt->execute([$user_id, $substitute_id]);
    } else {
        // ایجاد رکورد جدید
        $stmt = $db->prepare("INSERT INTO substitutes (user_id, substitute_user_id, is_active) VALUES (?, ?, 1)");
        $stmt->execute([$user_id, $substitute_id]);
    }

    // ✅ چک کنیم مدیر = مسئول است یا نه
    if (isManagerSupervisor($db, $user_id)) {
        // supervisor_approval را از اول approved می‌کنیم
        $stmt = $db->prepare("UPDATE leave_requests SET supervisor_approval = 'approved' WHERE id = ?");
        $stmt->execute([$leave_id]);
    }
    notifyNextApprover($db, $user_id, 'leave', $leave_id, $request_code);

    echo json_encode(['success' => true, 'message' => 'درخواست مرخصی ثبت شد', 'code' => $request_code]);
    exit;
}

// ============================================================
// درخواست پاس
// ============================================================
if ($type === 'pass') {
    $pass_date = $data['pass_date'] ?? null;
    $start_time = $data['start_time'] ?? null;
    $end_time = $data['end_time'] ?? null;
    $reason = $data['reason'] ?? null;

    if (!$pass_date || !$start_time || !$end_time || !$reason) {
        echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
        exit;
    }

    // ✅ JavaScript الان میلادی می‌فرسته: "2025-12-30"
    $pass_date_miladi = $pass_date;

    $request_code = generateRequestCode('pa', $user_id);

    $stmt = $db->prepare("
        INSERT INTO pass_requests (user_id, request_code, pass_date, start_time, end_time, reason, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$user_id, $request_code, $pass_date_miladi, $start_time, $end_time, $reason]);

    echo json_encode(['success' => true, 'message' => 'درخواست پاس ثبت شد', 'code' => $request_code]);
    exit;
}

// ============================================================
// درخواست فراموشی رمز
// ============================================================
if ($type === 'forget') {
    $datetime = $data['datetime'] ?? null;      // "2025-12-30 15:10"
    $end_time_full = $data['end_time'] ?? null; // "2025-12-30 15:50"
    $description = $data['description'] ?? null;

    if (!$datetime || !$end_time_full || !$description) {
        echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
        exit;
    }

    // ✅ JavaScript الان میلادی می‌فرسته با فرمت DATETIME
    $start_date = $datetime;       // "2025-12-30 15:10"
    $end_date = $end_time_full;    // "2025-12-30 15:50"

    $request_code = generateRequestCode('fa', $user_id);

    $stmt = $db->prepare("
        INSERT INTO forget_requests (user_id, request_code, start_date, end_date, description, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$user_id, $request_code, $start_date, $end_date, $description]);
    $last_id = $db->lastInsertId(); // ✅ همیشه بگیر

    // ✅ چک کنیم مدیر = مسئول است یا نه
    if (isManagerSupervisor($db, $user_id)) {
        $last_id = $db->lastInsertId();
        // supervisor_approval را از اول approved می‌کنیم
        $stmt = $db->prepare("UPDATE forget_requests SET supervisor_approval = 'approved' WHERE id = ?");
        $stmt->execute([$last_id]);
    }
    notifyNextApprover($db, $user_id, 'forget', $last_id, $request_code);

    echo json_encode(['success' => true, 'message' => 'درخواست فراموشی رمز ثبت شد', 'code' => $request_code]);
    exit;
}

// ============================================================
// درخواست مشکل فنی
// ============================================================
if ($type === 'technical') {
    $datetime = $data['datetime'] ?? null;      // "2025-12-30 15:10"
    $end_time = $data['end_time'] ?? null;      // "15:50"
    $description = $data['description'] ?? null;

    if (!$datetime || !$end_time || !$description) {
        echo json_encode(['success' => false, 'message' => 'فیلدهای الزامی خالی است']);
        exit;
    }

    // ✅ تجزیه datetime: "2025-12-30 15:10" → تاریخ + ساعت
    list($date_miladi, $start_time) = explode(' ', $datetime);

    $request_code = generateRequestCode('te', $user_id);

    $stmt = $db->prepare("
        INSERT INTO technical_issues (user_id, request_code, start_date, end_date, start_time, end_time, description, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $user_id,
        $request_code,
        $date_miladi,      // start_date (DATE)
        $date_miladi,      // end_date (DATE - همان تاریخ)
        $start_time,       // start_time (TIME)
        $end_time,         // end_time (TIME)
        $description       // description
    ]);
    $last_id = $db->lastInsertId(); // ✅ اضافه کن

    notifyNextApprover($db, $user_id, 'technical', $last_id, $request_code);

    echo json_encode(['success' => true, 'message' => 'درخواست مشکل فنی ثبت شد', 'code' => $request_code]);
    exit;
}
$end = microtime(true);
error_log("create.php end time: " . ($end - $start));

echo json_encode(['success' => false, 'message' => 'نوع درخواست نامعتبر است']);
?>