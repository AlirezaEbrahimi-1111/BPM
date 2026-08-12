<?php
// api/health.php — نقطه‌ی سلامت‌سنجیِ عمومی برایِ سرویس‌هایِ مانیتورینگِ بیرونی
// عمداً بدونِ نیاز به احرازِ هویته (باید از بیرون قابلِ‌پول باشه)، ولی هیچ
// اطلاعاتِ حساسی (پیام‌خطایِ خام، مسیر، نسخه) برنمی‌گردونه — فقط وضعیتِ کلی
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$checks  = [];
$healthy = true;

// دیتابیس
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $db->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Exception $e) {
    $checks['database'] = 'fail';
    $healthy = false;
}

// فضایِ دیسک
$free  = @disk_free_space('/');
$total = @disk_total_space('/');
if ($free !== false && $total !== false && $total > 0) {
    $usedPercent = round((1 - $free / $total) * 100, 1);
    $checks['disk_used_percent'] = $usedPercent;
    if ($usedPercent > 90) {
        $healthy = false;
    }
} else {
    $checks['disk_used_percent'] = null;
}

http_response_code($healthy ? 200 : 503);
echo json_encode([
    'status'    => $healthy ? 'ok' : 'degraded',
    'checks'    => $checks,
    'timestamp' => date('c'),
], JSON_UNESCAPED_UNICODE);
