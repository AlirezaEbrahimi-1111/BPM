<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Tehran');

// به جای تعریف مجدد PDO
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}

// ✅ استفاده از کلاس Auth برای validate کردن JWT
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
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
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - No valid session or token'
    ]);
    exit;
}

// ============================================
// دریافت وضعیت حضور
// ============================================

try {
    $today = date('Y-m-d');
    
    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("
        SELECT 
            shift_count,
            shift_1_start,
            shift_1_end,
            shift_2_start,
            shift_2_end,
            monthly_salary,
            daily_work_hours
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // دریافت رکوردهای حضور امروز
    $stmt = $db->prepare("
        SELECT 
            id,
            shift_number,
            check_in,
            check_out
        FROM attendance_records
        WHERE user_id = ? AND date = ?
        ORDER BY shift_number ASC
    ");
    $stmt->execute([$user_id, $today]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // پردازش وضعیت
    $shift1_check_in = null;
    $shift1_check_out = null;
    $shift2_check_in = null;
    $shift2_check_out = null;
    
    foreach ($records as $record) {
        if ($record['shift_number'] == 1) {
            $shift1_check_in = $record['check_in'];
            $shift1_check_out = $record['check_out'];
        } elseif ($record['shift_number'] == 2) {
            $shift2_check_in = $record['check_in'];
            $shift2_check_out = $record['check_out'];
        }
    }
    
    // تعیین دکمه‌ها
    $buttons = [];
    $window_message = null; // پیامِ «الان زمانِ ثبت ورود نیست»

    if ($user['shift_count'] == 1) {
        if (!$shift1_check_in) {
            $buttons[] = [
                'type' => 'check_in',
                'shift' => 1,
                'label' => 'ورود',
                'enabled' => true
            ];
        } elseif (!$shift1_check_out) {
            $buttons[] = [
                'type' => 'check_out',
                'shift' => 1,
                'label' => 'خروج',
                'enabled' => true
            ];
        }
    } else {
        // ساعتِ فعلی برای بررسیِ پنجره‌های مجاز
        $now_obj = new DateTime(date('H:i:s'));
        $shift1_end_obj = !empty($user['shift_1_end']) ? new DateTime($user['shift_1_end']) : null;
        // شروعِ پنجره‌ی شیفت ۲ = ۳۰ دقیقه قبل از شروعِ شیفت ۲
        $shift2_allowed_obj = null;
        if (!empty($user['shift_2_start'])) {
            $shift2_allowed_obj = new DateTime($user['shift_2_start']);
            $shift2_allowed_obj->modify('-30 minutes');
        }

        // آیا ورود شیفت ۱ هنوز مجاز است؟ (قبل از پایانِ شیفت ۱)
        $shift1_in_open = ($shift1_end_obj === null) ? true : ($now_obj < $shift1_end_obj);
        // آیا ورود شیفت ۲ مجاز است؟ (بعد از ۳۰ دقیقه قبلِ شیفت ۲)
        $shift2_in_open = ($shift2_allowed_obj === null) ? true : ($now_obj >= $shift2_allowed_obj);

        // 🔒 اول بررسی می‌کنیم آیا شیفتِ ۲ همین الان «باز» است (ورود ثبت شده، خروج نه) —
        // مستقل از اینکه شیفتِ ۱ اصلاً زده شده یا نه. قبلاً این حالت چک نمی‌شد: وقتی
        // کاربر پنجره‌ی شیفتِ ۱ را کامل از دست می‌داد و مستقیم وارد شیفتِ ۲ می‌شد،
        // شرطِ زیر (elseif زنجیره‌ای بر پایه‌ی shift1_check_in) هیچ‌وقت به بخشِ
        // «خروجِ شیفتِ ۲» نمی‌رسید و همان دکمه‌ی سبزِ «ورود شیفت ۲» دوباره نمایش
        // داده می‌شد — یعنی بعد از ثبتِ ورودِ شیفتِ ۲، دکمه هرگز قرمز/خروج نمی‌شد.
        if ($shift2_check_in && !$shift2_check_out) {
            $buttons[] = ['type' => 'check_out', 'shift' => 2, 'label' => 'خروج شیفت 2', 'enabled' => true];
        } elseif ($shift2_check_in && $shift2_check_out) {
            // هر دو شیفت کامل شده (چه شیفتِ ۱ زده شده باشه چه رد شده باشه) — بدونِ دکمه
        } elseif (!$shift1_check_in) {
            // هنوز ورود شیفت ۱ نخورده
            if ($shift1_in_open) {
                $buttons[] = ['type' => 'check_in', 'shift' => 1, 'label' => 'ورود شیفت 1', 'enabled' => true];
            } elseif ($shift2_in_open) {
                // پنجره‌ی شیفت ۱ بسته شده ⟵ مستقیم ورود شیفت ۲
                $buttons[] = ['type' => 'check_in', 'shift' => 2, 'label' => 'ورود شیفت 2', 'enabled' => true];
            } else {
                // بینِ دو شیفت ⟵ هیچ ورودی مجاز نیست
                $window_message = 'الان زمانِ ثبتِ ورود نیست';
            }
        } elseif (!$shift1_check_out) {
            // شیفت ۱ باز است ⟵ همیشه می‌تواند خروجِ شیفت ۱ بزند
            $buttons[] = ['type' => 'check_out', 'shift' => 1, 'label' => 'خروج شیفت 1', 'enabled' => true];
        } else {
            // شیفت ۱ تمام شده، شیفتِ ۲ هنوز شروع نشده ⟵ ورود شیفت ۲ فقط در پنجره‌ی مجاز
            if ($shift2_in_open) {
                $buttons[] = ['type' => 'check_in', 'shift' => 2, 'label' => 'ورود شیفت 2', 'enabled' => true];
            } else {
                $window_message = 'الان زمانِ ثبتِ ورود نیست';
            }
        }
    }
    
    // پیام وضعیت
    $status_message = '';
    if ($shift1_check_in) {
        $status_message = 'ورود ثبت شد: ' . substr($shift1_check_in, 11, 5);
        if ($shift1_check_out) {
            $status_message .= ' | خروج: ' . substr($shift1_check_out, 11, 5);
        }
    }
    if ($shift2_check_in) {
        $status_message .= ' | ورود ش۲: ' . substr($shift2_check_in, 11, 5);
        if ($shift2_check_out) {
            $status_message .= ' | خروج ش۲: ' . substr($shift2_check_out, 11, 5);
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'shift_count' => (int)$user['shift_count'],
        'buttons' => $buttons,
        'window_message' => $window_message,
        'status_message' => $status_message,
        'current_time' => date('H:i'),
        'shift1' => [
            'check_in' => $shift1_check_in ? substr($shift1_check_in, 11, 5) : null,
            'check_out' => $shift1_check_out ? substr($shift1_check_out, 11, 5) : null
        ],
        'shift2' => [
            'check_in' => $shift2_check_in ? substr($shift2_check_in, 11, 5) : null,
            'check_out' => $shift2_check_out ? substr($shift2_check_out, 11, 5) : null
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Today status error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا: ' . $e->getMessage()
    ]);
}
?>