<?php

/**
 * API: header/bootstrap.php
 * جمع‌کردن ۴ فراخوانی سطح header.php (که در «همه‌ی صفحات» سایت روی
 * بارگذاری اولیه اجرا می‌شن: اعلان‌ها، پیام‌ها، وضعیت حضور، چت) در یک
 * درخواست HTTP واحد. هر endpoint دقیقا با همون فایل اصلی خودش
 * (in-process، با output buffering) اجرا می‌شه — منطق تکرار نشده،
 * صفحاتی که مستقیم این ۴ فایل رو صدا می‌زنن بدون تغییر کار می‌کنن.
 *
 * چون این فایل‌ها با query-string‌های متفاوتی صدا زده می‌شن (که در
 * include از همون $_GET سراسری درخواست خونده می‌شن)، قبل از هر
 * include، $_GET موقتا با پارامترهای همون endpoint جایگزین می‌شه.
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

// چک اولیه‌ی احراز هویت — اگه نامعتبر بود، همین‌جا با ۴۰۱ متوقف می‌شه
requireAuth();

function bpm_header_capture(string $absPath, array $getParams = []): array {
    $originalGet = $_GET;
    $_GET = $getParams;

    ob_start();
    include $absPath;
    $raw = ob_get_clean();

    $_GET = $originalGet;

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'invalid upstream response'];
}

$root = $_SERVER['DOCUMENT_ROOT'];

// unread_only عمدا حذف شد: قبلا مورد خوانده‌شده در بارگذاری بعدی
// دراپ‌داون از دیتا اصلا برنمی‌گشت (چون سرور فقط نخوانده‌ها رو می‌فرستاد)
// و از لیست ناپدید می‌شد؛ فیلتر خوانده‌نشده/همه از این به بعد سمت UI
// (chip) روی همین دیتای کامل اعمال می‌شه، نه با پارامتر ثابت سرور
$notifications = bpm_header_capture($root . '/api/notifications/list.php', ['limit' => '50']);
$announcements = bpm_header_capture($root . '/api/announcements/list.php', ['limit' => '50', 'offset' => '0']);
$attendance    = bpm_header_capture($root . '/api/attendance/today-status.php');
$conversations = bpm_header_capture($root . '/api/chat/conversations.php');

http_response_code(200);
echo json_encode([
    'success'        => true,
    'notifications'  => $notifications,
    'announcements'  => $announcements,
    'attendance'     => $attendance,
    'conversations'  => $conversations,
]);
