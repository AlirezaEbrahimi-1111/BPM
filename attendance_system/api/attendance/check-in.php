<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');

// بررسی authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

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

try {
    date_default_timezone_set('Asia/Tehran');

    // دریافت داده‌های JSON
    $json = file_get_contents('php://input');
    $request_data = json_decode($json, true);

    // دریافت ساعت از کلاینت (مهم‌ترین!)
    $check_datetime = $request_data['check_datetime'] ?? null;
    $check_time = $request_data['check_time'] ?? null;

    // ⭐ دریافت shift_number از کلاینت
    $shift_number = isset($request_data['shift_number']) ? (int) $request_data['shift_number'] : 1;

    if (!$check_datetime && !$check_time) {
        throw new Exception('No time provided from client');
    }

    // اگر کلاینت datetime را فرستاد، از آن استفاده کن
    if ($check_datetime) {
        $check_in_datetime = $check_datetime;
        $check_in_time = substr($check_datetime, 11, 8);
    } else {
        // اگر فقط ساعت داشتیم
        $check_in_time = $check_time;
        $today = date('Y-m-d');
        $check_in_datetime = $today . ' ' . $check_in_time;
    }

    error_log('Check-in - DateTime: ' . $check_in_datetime . ' | Time: ' . $check_in_time . ' | Shift: ' . $shift_number);

    $today = date('Y-m-d');

    // ⭐ دریافت اطلاعات شیفت کاربر
    $stmt = $db->prepare("
        SELECT shift_count, shift_1_start, shift_1_end, shift_2_start, shift_2_end 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('کاربر یافت نشد');
    }

    // ⭐⭐⭐ بررسی محدودیت شیفت دوم ⭐⭐⭐
    if ($shift_number == 2) {
        // بررسی 1: آیا دو شیفته است؟
        if ($user['shift_count'] < 2) {
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

        $current_time_obj = new DateTime($check_in_time);

        // مقایسه زمان
        if ($current_time_obj < $allowed_time) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'شما نمی‌توانید قبل از ساعت ' .
                    $allowed_time->format('H:i') .
                    ' ورود شیفت دوم را ثبت کنید',
                'allowed_time' => $allowed_time->format('H:i:s'),
                'shift_2_start' => $user['shift_2_start'],
                'current_time' => $check_in_time,
                'error_code' => 'TOO_EARLY_FOR_SHIFT_2'
            ]);
            exit;
        }
    }
    // ⭐⭐⭐ پایان بررسی محدودیت ⭐⭐⭐

    // بررسی اعتبار شماره شیفت
    if ($shift_number < 1 || $shift_number > $user['shift_count']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'شماره شیفت نامعتبر است'
        ]);
        exit;
    }

    // بررسی اینکه آیا رکورد امروز برای این شیفت موجود است
    $stmt = $db->prepare("
        SELECT id, check_in, check_out FROM attendance_records
        WHERE user_id = ? AND date = ? AND shift_number = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $today, $shift_number]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // رکورد موجود است

        // اگر شیفت هنوز باز است (ورود ثبت شده ولی خروج نه)
        if ($existing['check_in'] && !$existing['check_out']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'شما قبلاً ورود شیفت ' . $shift_number . ' را ثبت کرده‌اید'
            ]);
            exit;
        }

        // اگر شیفت بسته است، امکان ثبت مجدد ورود
        $stmt = $db->prepare("
            UPDATE attendance_records
            SET check_in = ?, check_in_ip = ?, check_out = NULL, updated_at = NOW()
            WHERE user_id = ? AND date = ? AND shift_number = ?
        ");
        $stmt->execute([$check_in_datetime, $_SERVER['REMOTE_ADDR'], $user_id, $today, $shift_number]);
        $message = 'ورود شیفت ' . $shift_number . ' مجدداً ثبت شد';

    } else {
        // ایجاد جدید
        $stmt = $db->prepare("
            INSERT INTO attendance_records 
            (user_id, date, shift_number, check_in, check_in_ip, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $today, $shift_number, $check_in_datetime, $_SERVER['REMOTE_ADDR']]);
        $message = 'ورود شیفت ' . $shift_number . ' با موفقیت ثبت شد';
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'check_in_time' => $check_in_time,
        'check_in_datetime' => $check_in_datetime,
        'shift_number' => $shift_number
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Check-in error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ثبت ورود: ' . $e->getMessage()
    ]);
}
?>