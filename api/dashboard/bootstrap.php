<?php

/**
 * API: dashboard/bootstrap.php
 * جمع‌کردنِ ۵ فراخوانیِ جداگانه‌یِ داشبورد (my-tasks, delegated-tasks,
 * workflows/list, workflows/active-summary, reports/top-delayed-users)
 * در یک درخواستِ HTTP واحد — بدونِ تکرارِ منطقِ هرکدوم.
 *
 * هر endpoint دقیقاً با همون فایلِ اصلیِ خودش (in-process، با
 * output buffering) اجرا می‌شه، پس رفتار و منطقِ هرکدوم همیشه با
 * نسخه‌یِ مستقلِ خودش (که صفحاتِ دیگه هنوز مستقیم صداش می‌زنن) یکی
 * می‌مونه. هیچ‌کدوم از این ۵ فایل در مسیرِ موفقیت exit/die صدا
 * نمی‌زنن (فقط در مسیرهایِ خطا)، پس include‌کردنِ متوالی‌شون امنه.
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

// چکِ اولیه‌یِ احراز هویت — اگه نامعتبر بود، همین‌جا با ۴۰۱ متوقف می‌شه
// (requireAuth خودش exit می‌زنه) و ۵ include بی‌فایده اجرا نمی‌شه.
$user_id = requireAuth();

function bpm_dashboard_capture(string $absPath): array {
    ob_start();
    include $absPath;
    $raw = ob_get_clean();
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'invalid upstream response'];
}

$root = $_SERVER['DOCUMENT_ROOT'];

// از قبل اینجا لود می‌شد ولی پایین‌تر — نیازش داریم تا نطاقِ پیش‌فرضِ
// «فعالیت‌های اخیر» (پایین‌تر) رو بر اساسِ نقش تعیین کنیم
$database = new Database();
$db = $database->getConnection();
$me = loadUserForPermissions($db, $user_id);
$db = null;

$mine      = bpm_dashboard_capture($root . '/api/tasks/my-tasks.php');
$delegated = bpm_dashboard_capture($root . '/api/tasks/delegated-tasks.php');

// «فعالیت‌های اخیر» در داشبورد باید فقط فعالیتِ خودِ کاربر باشه — حتی برایِ
// مدیر/سوپرادمین — نه کلِ سازمان (که رفتارِ پیش‌فرضِ list.php برایِ مدیرانه،
// مالِ صفحه‌ی جداگانه‌ی نظارتِ کامل workflow-monitor.php).
$_GET['personal_only'] = '1';
$recent = bpm_dashboard_capture($root . '/api/workflows/list.php');
unset($_GET['personal_only']);

$routines  = bpm_dashboard_capture($root . '/api/workflows/active-summary.php');

// 🆕 تبِ «فعالیت‌های اخیر» در داشبورد یه لاگِ تاریخچه‌ایه (چه‌کاری/چه‌وقتی)،
// نه لیستِ فرآیندهایِ در‌جریان (که همون $recent بالاست و برایِ ویجتِ
// گلوگاه‌ها هنوز لازمه) — منبعِ جدا: api/dashboard/recent-activity.php
// پیش‌فرضِ نطاق: مدیر/سرپرست → سازمانی (چون سوییچرِ نطاق فقط تویِ
// داشبوردِ مدیر هست)؛ بقیه → شخصی (بدونِ سوییچری که نشونش بده)
$isManagerRole = in_array($me['role'] ?? '', ['manager', 'supervisor'], true) || isSuperAdmin($me);
$activityScopeDefault = $isManagerRole ? 'org' : 'personal';
$_GET['scope'] = $activityScopeDefault;
$activityLog = bpm_dashboard_capture($root . '/api/dashboard/recent-activity.php');
unset($_GET['scope']);

// reports/top-delayed-users.php مخصوصِ کاربرانی‌ست که مجوزِ
// view_all_org_tasks دارن (مدیران) — requirePermission داخلِ اون فایل
// در صورتِ نبودِ مجوز خودش exit می‌زنه، که include‌شدنش این‌جا کلِ
// پردازشِ بوت‌استرپ رو (برایِ کاربرانِ عادی) قطع می‌کرد. برایِ همین قبل
// از include، خودمون مجوز رو (بدونِ exit) چک می‌کنیم.

// 🆕 view_org_dashboard_reports: مجوزِ محدودتری که فقط همین ویجت‌هایِ
// داشبورد رو سازمانی می‌کنه (بدونِ دسترسیِ اضافه به endpointهایی مثل
// api/admin/routines-all.php که پشتِ همون دو مجوزِ اصلی قایم شدن)
$canViewOrgTasks = hasPermission($me, 'view_all_org_tasks') || hasPermission($me, 'view_org_dashboard_reports');
$canMonitorAllWorkflows = hasPermission($me, 'monitor_all_workflows') || hasPermission($me, 'view_org_dashboard_reports');

if ($canViewOrgTasks) {
    $topDelayed = bpm_dashboard_capture($root . '/api/reports/top-delayed-users.php');

    // 🆕 «کارهایِ واگذارشده‌ی تأخیردار» در داشبوردِ مدیر باید سازمانی باشه،
    // نه فقط کارهایی که خودِ مدیر شخصاً واگذار کرده — چون ممکنه مدیر خودش
    // چیزی واگذار نکرده باشه ولی سازمان پر از کارِ تأخیردار باشه
    $_GET['scope'] = 'org';
    $orgDelegated = bpm_dashboard_capture($root . '/api/tasks/delegated-tasks.php');
    unset($_GET['scope']);
} else {
    $topDelayed = ['success' => true, 'users' => []];
    $orgDelegated = ['success' => true, 'tasks' => []];
}

// 🆕 «گزارشِ گلوگاه‌ها» هم همین‌طور — قبلاً از لیستِ شخصیِ فعالیت‌هایِ
// اخیر (personal_only) ساخته می‌شد که برایِ مدیر تقریباً همیشه خالی بود
if ($canMonitorAllWorkflows) {
    $orgBottlenecks = bpm_dashboard_capture($root . '/api/reports/bottleneck-report.php');
} else {
    $orgBottlenecks = ['success' => true, 'bottlenecks' => []];
}

// 🆕 تنظیماتِ روزانه‌ی داشبورد (کارهایِ ستاره‌دار + تبِ/فیلترِ پیش‌فرض) —
// با CURDATE()ِ سرور خونده می‌شه، نه با مقایسه‌ی تاریخِ ساعتِ سیستمِ
// کلاینت (localStorage) که کاربرهایی با ساعتِ سیستمِ نادرست هیچ‌وقت
// ریست‌شدنش رو نمی‌دیدن
$database2 = new Database();
$dbPrefs = $database2->getConnection();
$prefsStmt = $dbPrefs->prepare("
    SELECT pref_key, pref_value FROM user_dashboard_prefs
    WHERE user_id = ? AND pref_date = CURDATE()
");
$prefsStmt->execute([$user_id]);
$dashboardPrefs = ['starred_tasks' => [], 'default_tab' => '', 'default_filter' => ''];
foreach ($prefsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if ($row['pref_key'] === 'starred_tasks') {
        $decoded = json_decode($row['pref_value'], true);
        $dashboardPrefs['starred_tasks'] = is_array($decoded) ? $decoded : [];
    } elseif (in_array($row['pref_key'], ['default_tab', 'default_filter'], true)) {
        $dashboardPrefs[$row['pref_key']] = (string) $row['pref_value'];
    }
}

http_response_code(200);
echo json_encode([
    'success'                => true,
    'mine'                   => $mine,
    'delegated'              => $delegated,
    'recent'                 => $recent,
    'activityLog'            => $activityLog,
    'activityScopeDefault'   => $activityScopeDefault,
    'routines'               => $routines,
    'topDelayed'             => $topDelayed,
    'orgDelegated'           => $orgDelegated,
    'orgBottlenecks'         => $orgBottlenecks,
    'canViewOrgTasks'        => $canViewOrgTasks,
    'canMonitorAllWorkflows' => $canMonitorAllWorkflows,
    'dashboardPrefs'         => $dashboardPrefs,
]);
