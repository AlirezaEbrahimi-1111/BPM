<?php
/**
 * api/server-time.php
 * منبع یگانهٔ «زمان اکنون» برای کلاینت — تا هیچ‌جای UI به ساعت/تاریخ دستگاه
 * کاربر تکیه نکند. بدون احراز هویت (چیزی حساس لو نمی‌دهد) و بدون دیتابیس.
 *
 * خروجی:
 *   epoch_ms       — یونیکس اپک سرور به میلی‌ثانیه (مستقل از تایم‌زون) → برای skew
 *   iso            — ISO-8601 با آفست، مثل 2026-08-27T15:30:00+03:30
 *   mysql          — "Y-m-d H:i:s" به وقت تهران (همان فرمتی که در DB ذخیره می‌شود)
 *   tz             — Asia/Tehran
 *   offset_minutes — آفست تهران بر حسب دقیقه (۲۱۰)
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$tz  = new DateTimeZone('Asia/Tehran');
$now = new DateTime('now', $tz);

echo json_encode([
    'success'        => true,
    'epoch_ms'       => (int) round(microtime(true) * 1000),
    'iso'            => $now->format('c'),
    'mysql'          => $now->format('Y-m-d H:i:s'),
    'tz'             => 'Asia/Tehran',
    'offset_minutes' => (int) ($now->getOffset() / 60),
], JSON_UNESCAPED_UNICODE);
