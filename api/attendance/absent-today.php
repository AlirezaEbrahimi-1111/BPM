<?php
/**
 * api/attendance/absent-today.php
 * ─────────────────────────────────────────────────────────────
 * لیستِ پرسنلِ «سازمانِ کاربرِ جاری» که امروز هنوز ثبتِ ورود ندارند.
 * برای همهٔ کاربران باز است (نه فقط مدیر).
 *
 * قواعد:
 *   • فقط کاربرانِ فعالِ همان سازمان که حضوروغیاب برایشان واقعاً تعریف شده:
 *     shift_count >= 1 و shift_1_start پر (نه فقط پیش‌فرضِ فرمِ ویرایش) و
 *     حقوقِ ماهانه‌ی مشخص (monthly_salary > 0) — یعنی کسی که هنوز شیفت/حقوقش
 *     ثبت نشده، اصلاً وارد این محاسبه نمی‌شود. سرپرست‌ها و خودِ کاربرِ جاری نه.
 *   • «حاضر» = حداقل یک check_in امروز (هر شیفتی) → در لیست نمی‌آید.
 *   • مرخصیِ تأییدشده که امروز را پوشش می‌دهد → بجِ «مرخصی».
 *   • پاسِ امروز و «همین حالا داخلِ بازهٔ پاس» → در لیست نمی‌آید.
 *   • در غیرِ این‌صورت (نزده و بیرونِ بازهٔ پاس) → بجِ «غایب».
 *   • روزِ تعطیل (جمعه/تعطیلِ رسمی/هفتگی) → لیستِ خالی.
 *
 * بدونِ آستانهٔ زمانی: هر بار که صفحه رفرش شود دوباره محاسبه می‌شود،
 * پس هر کس بعداً ورود بزند از لیست برداشته می‌شود.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Tehran');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        $user_id = $auth->getUserFromToken();
    }
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $meStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $me = $meStmt->fetch(PDO::FETCH_ASSOC);
    if (!$me || $me['organization_id'] === null) {
        echo json_encode(['success' => true, 'holiday' => false, 'today' => date('Y-m-d'), 'absent' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $org_id = (int) $me['organization_id'];

    $today = date('Y-m-d');
    $now_t = date('H:i:s');

    // ── روزِ تعطیل → لیستِ خالی ──
    $holidays  = getHolidaySet($db, $org_id);
    $recurring = getRecurringHolidayWeekdays($db, $org_id);
    if (!isWorkingDay(new DateTime($today), $holidays, $recurring)) {
        echo json_encode(['success' => true, 'holiday' => true, 'today' => $today, 'absent' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── کاربرانِ واجدِ شرایطِ لیست ──
    $uStmt = $db->prepare("
        SELECT id, first_name, last_name
        FROM users
        WHERE organization_id = ?
          AND is_active = 1
          AND COALESCE(is_deleted, 0) = 0
          AND id <> ?
          AND COALESCE(role, '') <> 'supervisor'
          AND COALESCE(is_supervisor, 0) = 0
          AND COALESCE(shift_count, 0) >= 1
          AND shift_1_start IS NOT NULL
          AND COALESCE(monthly_salary, 0) > 0
        ORDER BY first_name, last_name
    ");
    $uStmt->execute([$org_id, $user_id]);
    $users = $uStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$users) {
        echo json_encode(['success' => true, 'holiday' => false, 'today' => $today, 'absent' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ids = array_map('intval', array_column($users, 'id'));
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    // امروز حداقل یک check_in دارند؟
    $aStmt = $db->prepare("SELECT DISTINCT user_id FROM attendance_records WHERE user_id IN ($ph) AND date = ? AND check_in IS NOT NULL");
    $aStmt->execute(array_merge($ids, [$today]));
    $present = array_fill_keys(array_map('intval', array_column($aStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id')), true);

    // مرخصیِ تأییدشده که امروز را پوشش می‌دهد
    $lStmt = $db->prepare("SELECT DISTINCT user_id FROM leave_requests WHERE user_id IN ($ph) AND status = 'approved' AND start_date <= ? AND end_date >= ?");
    $lStmt->execute(array_merge($ids, [$today, $today]));
    $on_leave = array_fill_keys(array_map('intval', array_column($lStmt->fetchAll(PDO::FETCH_ASSOC), 'user_id')), true);

    // پاسِ امروزِ لغونشده — با بازهٔ ساعتی
    $pStmt = $db->prepare("SELECT user_id, start_time, end_time FROM pass_requests WHERE user_id IN ($ph) AND pass_date = ? AND (status IS NULL OR status <> 'cancelled')");
    $pStmt->execute(array_merge($ids, [$today]));
    $pass_by_user = [];
    foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $pass_by_user[(int) $p['user_id']][] = ['start' => $p['start_time'], 'end' => $p['end_time']];
    }

    $absent = [];
    foreach ($users as $u) {
        $uid = (int) $u['id'];
        if (isset($present[$uid])) {
            continue; // حاضر
        }
        $name = trim($u['first_name'] . ' ' . $u['last_name']);

        if (isset($on_leave[$uid])) {
            $absent[] = ['user_id' => $uid, 'name' => $name, 'type' => 'leave'];
            continue;
        }

        // همین حالا داخلِ بازهٔ یک پاس؟ → در لیست نیاور
        $in_pass_now = false;
        if (!empty($pass_by_user[$uid])) {
            foreach ($pass_by_user[$uid] as $pw) {
                if (!empty($pw['start']) && !empty($pw['end']) && $now_t >= $pw['start'] && $now_t <= $pw['end']) {
                    $in_pass_now = true;
                    break;
                }
            }
        }
        if ($in_pass_now) {
            continue;
        }

        $absent[] = ['user_id' => $uid, 'name' => $name, 'type' => 'absent'];
    }

    echo json_encode([
        'success' => true,
        'holiday' => false,
        'today'   => $today,
        'absent'  => $absent,
        'count'   => count($absent),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('absent-today.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
