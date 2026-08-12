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

$mine      = bpm_dashboard_capture($root . '/api/tasks/my-tasks.php');
$delegated = bpm_dashboard_capture($root . '/api/tasks/delegated-tasks.php');
$recent    = bpm_dashboard_capture($root . '/api/workflows/list.php');
$routines  = bpm_dashboard_capture($root . '/api/workflows/active-summary.php');

// reports/top-delayed-users.php مخصوصِ کاربرانی‌ست که مجوزِ
// view_all_org_tasks دارن (مدیران) — requirePermission داخلِ اون فایل
// در صورتِ نبودِ مجوز خودش exit می‌زنه، که include‌شدنش این‌جا کلِ
// پردازشِ بوت‌استرپ رو (برایِ کاربرانِ عادی) قطع می‌کرد. برایِ همین قبل
// از include، خودمون مجوز رو (بدونِ exit) چک می‌کنیم.
$database = new Database();
$db = $database->getConnection();
$me = loadUserForPermissions($db, $user_id);
$db = null;

if (hasPermission($me, 'view_all_org_tasks')) {
    $topDelayed = bpm_dashboard_capture($root . '/api/reports/top-delayed-users.php');
} else {
    $topDelayed = ['success' => true, 'users' => []];
}

http_response_code(200);
echo json_encode([
    'success'    => true,
    'mine'       => $mine,
    'delegated'  => $delegated,
    'recent'     => $recent,
    'routines'   => $routines,
    'topDelayed' => $topDelayed,
]);
