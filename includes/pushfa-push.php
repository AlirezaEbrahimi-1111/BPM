<?php
/**
 * ============================================================
 *  ارسالِ Push از طریقِ پوشفا (سرویسِ انتخاب‌شده برایِ فازِ ۰)
 * ============================================================
 *  فقط یک تابع دارد: pushfa_send_push() — کاربرِ هدف را با همان
 *  alias ای که اپ هنگامِ لاگین با PushfaService.identify() ثبت می‌کند
 *  (با label='user_id'، value=همان user.id خودِ ما) پیدا می‌کند.
 * ============================================================
 */

// 🔒 طبقِ همان قاعده‌ی config/config.php و includes/sms.php: کلیدها در
// یک فایلِ جدا و gitignore‌شده نگه داشته می‌شوند، نه این‌جا
require_once __DIR__ . '/pushfa-config.php';

/**
 * @param int|string $userId شناسه‌ی کاربرِ ما (همان alias با label='user_id')
 * @param string|null $linkUrl برایِ دیپ‌لینک — مثلاً "malekbpm://task?id=123"
 * @return array ['ok'=>bool, 'response'=>mixed]
 */
function pushfa_send_push(
    $userId,
    string $title,
    string $message,
    ?string $linkUrl = null
): array {
    $payload = [
        'api_public_key' => PUSHFA_API_PUBLIC_KEY,
        'api_private_key' => PUSHFA_API_PRIVATE_KEY,
        'label' => 'user_id',
        'values' => [(string) $userId],
        'title' => $title,
        'body' => $message,
        'send_time' => 'current',
    ];
    if ($linkUrl !== null) {
        $payload['link_url'] = $linkUrl;
    }

    $ch = curl_init('https://pushfa.com/api/webservices/send-via-alias');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);
    $out = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = json_decode($out ?: '', true);
    // 🔧 طبقِ پاسخِ واقعیِ API (تست‌شده): خطا هم ممکن است در بدنه با
    // errors=true/error_code بیاید، نه فقط با کدِ HTTP
    $ok = $httpCode >= 200 && $httpCode < 300 && empty($decoded['errors']);

    return [
        'ok' => $ok,
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
        'response' => $decoded,
    ];
}
