<?php
/**
 * تست سایه‌ای روی پروداکشن — مقایسه‌ی خروجی همه‌ی endpointهای
 * READ-ONLY پورت‌شده بین PHP و go-api، روی همون دیتابیس واقعی.
 *
 * فقط GET/خواندنی‌ها امتحان می‌شن — عمدا هیچ endpoint نوشتنی (save/
 * submit/delete/mark-read/mark-all-read/add-ip/toggle/relabel/...) اینجا
 * صدا زده نمی‌شه، چون قبلا روی لوکال کامل تست شدن و لازم نیست روی
 * دیتای واقعی کاربرها ریسک کنیم.
 *
 * اجرا (روی خود سرور، از مسیر ریشه‌ی پروژه):
 *   php go-api/deploy/prod-shadow-test.php <user_id> [report_id] [ticket_id]
 *
 * <user_id> رو با شناسه‌ی یک کاربر واقعی که خودت باهاش تست می‌کنی پر کن
 * (ترجیحا یک supervisor/superadmin تا همه‌ی شاخه‌ها رو ببینه).
 * [ticket_id] اختیاریه — اگر بدی، tickets/detail هم (فقط GET، بدون
 * نوشتن) چک می‌شه.
 */

$userId   = isset($argv[1]) ? (int) $argv[1] : 0;
$ticketId = isset($argv[2]) ? (int) $argv[2] : 0;
if (!$userId) {
    fwrite(STDERR, "Usage: php prod-shadow-test.php <user_id> [ticket_id]\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

$db = (new Database())->getConnection();
$auth = new Auth($db);
$token = $auth->generateJWTToken($userId, 3600, 1);

function call($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $res = curl_exec($ch);
    if ($res === false) {
        return [0, 'CURL ERROR: ' . curl_error($ch)];
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $res];
}

function n($x) {
    if (is_array($x)) {
        ksort($x);
        return array_map('n', $x);
    }
    return $x;
}

$fails = 0;
function compare($label, $phpUrl, $goUrl, $token) {
    global $fails;
    [$pc, $pr] = call($phpUrl, $token);
    [$gc, $gr] = call($goUrl, $token);
    $pj = json_decode($pr, true);
    $gj = json_decode($gr, true);
    $match = ($pc === $gc) && (n($pj) === n($gj));
    echo ($match ? "MATCH   " : "MISMATCH") . " | $label | php=$pc go=$gc\n";
    if (!$match) {
        $fails++;
        echo "  PHP: " . substr($pr, 0, 500) . "\n";
        echo "  GO : " . substr($gr, 0, 500) . "\n";
    }
}

// دامنه رو با دامنه‌ی واقعی خود سایت جایگزین کن اگر فرق داشت.
// ⚠️ از ۱۴۰۵/۰۶/۲۲: itmalek.com فقط ریدایرکت به bpm.itmalek.com می‌کنه —
// curl پیش‌فرض ریدایرکت رو دنبال نمی‌کنه، پس باید مستقیم آدرس نهایی داده بشه.
$base = 'https://bpm.itmalek.com';
$php = $base . '/api';
$go  = $base . '/go/api';

compare('reports/stats',   "$php/reports/stats.php",   "$go/reports/stats",   $token);
compare('reports/list',    "$php/reports/list.php",    "$go/reports/list",    $token);

// سه endpoint زنده‌ی داشبورد/گزارش روزانه — تنها موارد این ماژول که
// فرانت واقعا صدا می‌زند، پس مهم‌ترین موارد همین تست‌اند.
compare('reports/get-today-activities', "$php/reports/get-today-activities.php", "$go/reports/get-today-activities", $token);
// یک تاریخ ثابت گذشته هم چک می‌شود تا شاخه‌ی ?date= و گروه‌بندی با
// دیتای واقعی (نه یک روز احتمالا خالی) سنجیده شود.
$pastDate = date('Y-m-d', strtotime('-7 days'));
compare('reports/get-today-activities (date)', "$php/reports/get-today-activities.php?date=$pastDate", "$go/reports/get-today-activities?date=$pastDate", $token);
compare('reports/bottleneck-report',    "$php/reports/bottleneck-report.php",    "$go/reports/bottleneck-report",    $token);
compare('reports/top-delayed-users',    "$php/reports/top-delayed-users.php",    "$go/reports/top-delayed-users",    $token);

compare('attendance/today-status', "$php/attendance/today-status.php", "$go/attendance/today-status", $token);
compare('attendance/absent-today', "$php/attendance/absent-today.php", "$go/attendance/absent-today", $token);
compare('attendance/allowed-ips (GET)', "$php/attendance/allowed-ips.php", "$go/attendance/allowed-ips", $token);
compare('attendance/devices (GET)',     "$php/attendance/devices.php",     "$go/attendance/devices",     $token);
compare('attendance/denied-log (GET)',  "$php/attendance/denied-log.php?range=today", "$go/attendance/denied-log?range=today", $token);

compare('notifications/list', "$php/notifications/list.php", "$go/notifications/list", $token);
compare('notifications/new (since=0)', "$php/notifications/new.php?since=0", "$go/notifications/new?since=0", $token);

compare('announcements/list', "$php/announcements/list.php", "$go/announcements/list", $token);

compare('tickets/list', "$php/tickets/list.php?mine=1&limit=50", "$go/tickets/list?mine=1&limit=50", $token);

compare('tasks/my-tasks', "$php/tasks/my-tasks.php", "$go/tasks/my-tasks", $token);
compare('tasks/my-tasks (checklist_archive)', "$php/tasks/my-tasks.php?filter=checklist_archive", "$go/tasks/my-tasks?filter=checklist_archive", $token);

if ($ticketId > 0) {
    compare('tickets/detail', "$php/tickets/detail.php?id=$ticketId", "$go/tickets/detail?id=$ticketId", $token);
}

echo "\n" . ($fails === 0 ? "همه چیز مطابقت داشت." : "$fails مورد عدم تطابق — قبل از سوییچ فرانت‌اند بررسی شود.") . "\n";
