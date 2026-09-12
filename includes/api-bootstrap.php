<?php
/**
 * includes/api-bootstrap.php
 * ─────────────────────────────────────────────────────────────────────
 * بوت‌استرپِ مشترکِ همه‌ی endpointهای api/ .
 *
 * چرا؟  الان ~۱۴۱ فایلِ api/ همین ۶ خطِ زیر را کپی دارند (و بعدش هم
 * includes/cors.php را require می‌کنند — افزونگیِ محض):
 *     header('Content-Type: application/json; charset=utf-8');
 *     $corsAllowedOrigins = [...];
 *     $corsRequestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';
 *     header('Access-Control-Allow-Origin: ...');
 *     header('Access-Control-Allow-Methods: ...');
 *     header('Access-Control-Allow-Headers: ...');
 *
 * از این پس، هر endpoint فقط این یک خط را در ابتدای فایل بگذارد:
 *     require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/api-bootstrap.php';
 * و بلوکِ دستیِ بالا + require‌ِ cors.php را حذف کند.
 *
 * سازگاریِ عقب‌رو: اگر فایلی هنوز بلوکِ دستی + cors.php را هم داشته باشد،
 * چون همه‌ی این‌ها همان هدرها را ست می‌کنند (و مقدارِ نهایی یکی است) هیچ
 * مشکلی پیش نمی‌آید. مهاجرت را می‌توان تدریجی و دسته‌به‌دسته انجام داد.
 * ─────────────────────────────────────────────────────────────────────
 */

// جلوگیری از اجرای دوباره (اگر یک فایل هم این‌جا و هم cors.php را require کند).
if (defined('API_BOOTSTRAP_LOADED')) {
    return;
}
define('API_BOOTSTRAP_LOADED', true);

// ── پاسخ همیشه JSON است ──
header('Content-Type: application/json; charset=utf-8');

// ── CORS ──
// همان سیاستِ includes/cors.php : فقط دامنه‌های خودی، با fallback به دامنه‌ی اصلی.
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (
    in_array($corsRequestOrigin, $corsAllowedOrigins, true)
        ? $corsRequestOrigin
        : 'https://itmalek.com'
));
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

// ── preflight ──
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * محدودکردنِ endpoint به یک یا چند متدِ HTTP.
 * اختیاری — هر فایل اگر خواست صدا بزند:
 *     api_require_method('POST');            // فقط POST
 *     api_require_method(['GET', 'POST']);   // یکی از این‌ها
 * در صورتِ عدمِ تطبیق، ۴۰۵ با بدنه‌ی JSON برمی‌گرداند و اجرا را تمام می‌کند.
 */
function api_require_method($methods): void
{
    $methods = array_map('strtoupper', (array) $methods);
    $current = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($current, $methods, true)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $methods));
        echo json_encode(
            ['success' => false, 'message' => 'متد درخواست مجاز نیست'],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}

/**
 * خروجیِ استانداردِ JSON. اختیاری — برای کوتاه‌کردنِ
 *     http_response_code(...); echo json_encode([...]); exit;
 * که همه‌جای api/ تکرار شده.
 *     json_out(true, ['tasks' => $rows]);
 *     json_out(false, 'کاری یافت نشد', 404);
 */
function json_out(bool $success, $payload = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    $body = ['success' => $success];
    if (is_string($payload)) {
        $body['message'] = $payload;
    } elseif (is_array($payload)) {
        $body += $payload;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}
