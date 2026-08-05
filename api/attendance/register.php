<?php
/**
 * API: ثبت ورود/خروج
 * پشتیبانی از JWT Token
 * ✅ محدودیت زمانی شیفت دوم (30 دقیقه قبل)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: https://bpm.computeryekta.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/AttendanceNotify.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// ✅ استفاده از کلاس Auth
$auth = new Auth($db);

$user_id = null;

// روش 1: SESSION
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
}

// روش 2: JWT Token
if (!$user_id) {
    $user_id = $auth->getUserFromToken();
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// دریافت organization_id از توکن
$organization_id = $auth->getOrganizationFromToken();
if (!$organization_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'سازمان نامعتبر']);
    exit;
}
// ============================================
// گارد امنیتی: IP داخلی + دستگاه تأییدشده
// ============================================
(function () use ($db, $organization_id, $user_id) {

    $client_ip  = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // اکشن و fingerprint از بدنه (برای لاگ)
    $body0   = json_decode(file_get_contents('php://input'), true) ?: [];
    $action0 = $body0['action'] ?? null;
    $fp0     = isset($body0['fingerprint']) ? trim($body0['fingerprint']) : '';
    $fp0_hash = $fp0 !== '' ? hash('sha256', $fp0) : null;

    // تابع لاگ تلاش ناموفق
    $logDenied = function ($reason) use ($db, $organization_id, $user_id, $action0, $client_ip, $fp0_hash, $user_agent) {
        try {
            $db->prepare("
                INSERT INTO attendance_denied_log
                    (organization_id, user_id, action, reason, ip_address, fingerprint_hash, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([$organization_id, $user_id, $action0, $reason, $client_ip, $fp0_hash, $user_agent]);
        } catch (Exception $e) {
            error_log("Attendance denied-log insert failed | user_id={$user_id} | organization_id={$organization_id} | reason={$reason} | error=" . $e->getMessage());
        }
    };

    $ipStmt = $db->prepare("
        SELECT COUNT(*) FROM attendance_allowed_ips
        WHERE organization_id = ? AND ip_address = ? AND is_active = 1
    ");
    $ipStmt->execute([$organization_id, $client_ip]);
    if ((int)$ipStmt->fetchColumn() === 0) {
                $logDenied('IP_NOT_ALLOWED');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'ثبت ورود/خروج فقط از شبکهٔ مجاز سازمان امکان‌پذیر است',
            'error_code' => 'IP_NOT_ALLOWED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // --- لایهٔ ۲: دستگاه تأییدشده ---
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $fp   = isset($body['fingerprint']) ? trim($body['fingerprint']) : '';

    if ($fp === '' || strlen($fp) < 16) {
                $logDenied('NO_FINGERPRINT');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'شناسهٔ دستگاه ارسال نشد. لطفاً صفحه را تازه کنید.',
            'error_code' => 'NO_FINGERPRINT'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fp_hash = hash('sha256', $fp);

    $dStmt = $db->prepare("
        SELECT status FROM attendance_devices
        WHERE organization_id = ? AND fingerprint_hash = ?
        LIMIT 1
    ");
    $dStmt->execute([$organization_id, $fp_hash]);
    $device = $dStmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        // دستگاه ناشناخته → ثبت به‌صورت «در انتظار تأیید»
        $ins = $db->prepare("
            INSERT IGNORE INTO attendance_devices
                (organization_id, fingerprint_hash, status, first_seen_ip, first_seen_user_id, created_at)
            VALUES (?, ?, 'pending', ?, ?, NOW())
        ");
        $ins->execute([$organization_id, $fp_hash, $client_ip, $user_id]);
        if ($ins->rowCount() > 0) {
            // فقط وقتی دستگاه واقعاً «جدید» ثبت شد → اطلاع به مدیران
            attendance_notify_managers_new_device($db, $organization_id, $user_id, $client_ip);
        }
        $logDenied('DEVICE_PENDING');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'این دستگاه هنوز تأیید نشده است. از سرپرست بخواهید آن را در پنل دستگاه‌ها تأیید کند.',
            'error_code' => 'DEVICE_PENDING'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($device['status'] !== 'approved') {
                $logDenied($device['status'] === 'rejected' ? 'DEVICE_REJECTED' : 'DEVICE_NOT_APPROVED');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => ($device['status'] === 'rejected')
                ? 'این دستگاه توسط سرپرست رد شده است.'
                : 'این دستگاه هنوز در انتظار تأیید سرپرست است.',
            'error_code' => 'DEVICE_NOT_APPROVED'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // دستگاه تأییدشده → به‌روزرسانی آخرین استفاده
    $db->prepare("UPDATE attendance_devices SET last_used_at = NOW() WHERE organization_id = ? AND fingerprint_hash = ?")
       ->execute([$organization_id, $fp_hash]);

})();
// ============================================
// پایان گارد امنیتی
// ============================================
// ============================================
// دریافت داده‌ها
// ============================================

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$action = $data['action'] ?? '';
$shift = isset($data['shift']) ? (int) $data['shift'] : 1; // ⭐ دریافت شماره شیفت

if (!in_array($action, ['check_in', 'check_out'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$current_time = date('H:i:s');

// ============================================
// ثبت ورود/خروج
// ============================================

try {
    // ⭐ دریافت اطلاعات کاربر (شیفت‌ها)
    $stmt = $db->prepare("
        SELECT shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    $shift_count = (int) $user['shift_count'];

    // ⭐⭐⭐ بررسی محدودیت شیفت دوم ⭐⭐⭐
    if ($action === 'check_in' && $shift === 2) {
        // بررسی 1: آیا دو شیفته است؟
        if ($shift_count < 2) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'شما کاربر دو شیفته نیستید'
            ]);
            exit;
        }

        // بررسی 2: آیا shift_2_start تعریف شده؟
        if (empty($user['shift_2_start'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ساعت شروع شیفت دوم تعریف نشده است'
            ]);
            exit;
        }

        // محاسبه 30 دقیقه قبل از شیفت 2
        $shift_start = new DateTime($user['shift_2_start']);
        $allowed_time = clone $shift_start;
        $allowed_time->modify('-30 minutes');

        $current_time_obj = new DateTime($current_time);

        // مقایسه زمان
        if ($current_time_obj < $allowed_time) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'الان زمانِ ثبتِ ورود نیست (ورود شیفت ۲ از ساعت ' .
                    $allowed_time->format('H:i') . ' امکان‌پذیر است)',
                'allowed_time' => $allowed_time->format('H:i:s'),
                'shift_2_start' => $user['shift_2_start'],
                'current_time' => $current_time,
                'error_code' => 'TOO_EARLY_FOR_SHIFT_2'
            ]);
            exit;
        }
}
    // ⭐⭐⭐ پایان بررسی محدودیت ⭐⭐⭐

    // ⭐⭐⭐ محدودیت ورود شیفت ۱: فقط تا پایانِ شیفت ۱ ⭐⭐⭐
    if ($action === 'check_in' && $shift === 1 && $shift_count >= 2 && !empty($user['shift_1_end'])) {
        $shift1_end_obj = new DateTime($user['shift_1_end']);
        $current_time_obj = new DateTime($current_time);
        if ($current_time_obj >= $shift1_end_obj) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'زمانِ ثبتِ ورودِ شیفت ۱ به پایان رسیده است',
                'error_code' => 'SHIFT_1_ENDED'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    // ⭐⭐⭐ پایان محدودیت ورود شیفت ۱ ⭐⭐⭐

    // بررسی وجود شیفت
    if ($shift > $shift_count) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'شماره شیفت نامعتبر است'
        ]);
        exit;
    }

    // بررسی رکورد امروز برای این شیفت
    $stmt = $db->prepare("
        SELECT * FROM attendance_records 
        WHERE user_id = ? AND date = ? AND shift_number = ?
    ");
    $stmt->execute([$user_id, $today, $shift]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'check_in') {
        // ✅ ثبت ورود

        if ($record) {
            // رکورد موجود است
            if ($record['check_in'] && !$record['check_out']) {
                // شیفت باز است
                echo json_encode([
                    'success' => false,
                    'message' => 'شما قبلاً ورود شیفت ' . $shift . ' را ثبت کرده‌اید'
                ]);
                exit;
            }

            // به‌روزرسانی ورود (اگر قبلاً خروج زده)
            $stmt = $db->prepare("
                UPDATE attendance_records 
                SET check_in = ?, check_out = NULL, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$now, $record['id']]);

            $message = 'ورود شیفت ' . $shift . ' مجدداً ثبت شد';

        } else {
            // ایجاد رکورد جدید
            $stmt = $db->prepare("
    INSERT INTO attendance_records 
    (user_id, organization_id, date, shift_number, check_in, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())
");
            $stmt->execute([$user_id, $organization_id, $today, $shift, $now]);


            $message = 'ورود شیفت ' . $shift . ' ثبت شد';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'shift_number' => $shift,
            'check_in' => $now
        ]);

    } elseif ($action === 'check_out') {
        // ✅ ثبت خروج

        if (!$record) {
            echo json_encode([
                'success' => false,
                'message' => 'ابتدا باید ورود شیفت ' . $shift . ' را ثبت کنید'
            ]);
            exit;
        }

        if (!$record['check_in']) {
            echo json_encode([
                'success' => false,
                'message' => 'ابتدا باید ورود شیفت ' . $shift . ' را ثبت کنید'
            ]);
            exit;
        }

        if ($record['check_out']) {
            echo json_encode([
                'success' => false,
                'message' => 'شما قبلاً خروج شیفت ' . $shift . ' را ثبت کرده‌اید'
            ]);
            exit;
        }

        // ثبت خروج
        $stmt = $db->prepare("
            UPDATE attendance_records 
            SET check_out = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$now, $record['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'خروج شیفت ' . $shift . ' ثبت شد',
            'shift_number' => $shift,
            'check_out' => $now
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Register error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا: ' . $e->getMessage()
    ]);
}
?>