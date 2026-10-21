<?php
/**
 * ارسالِ Push از طریقِ Messaging APIِ متریکس.
 * گیرنده با همان customUserId ای پیدا می‌شود که اپ هنگامِ لاگین با
 * Metrix.setUserCustomId(user.id) ثبت می‌کند.
 */

// 🔒 مثلِ config/config.php: کلیدها در فایلِ جدا و gitignore‌شده.
// ⚠️ اگر این فایل روی سرور ساخته نشده باشد، require_once خطای «مرگبار» می‌داد و هر درخواستی
// که اعلان می‌فرستاد (ساخت کار، تأییدها، درخواست‌ها…) نیمه‌کاره می‌مرد. حالا بدون کلید فقط
// پوش ارسال نمی‌شود (در لاگ ثبت می‌شود) و بقیهٔ کار درست انجام می‌شود (۱۴۰۵/۰۷/۳۰).
if (is_file(__DIR__ . '/metrix-config.php')) {
    require_once __DIR__ . '/metrix-config.php';
}

/**
 * @param int|string $userId شناسه‌ی کاربرِ ما (customUserId سمتِ اپ)
 * @param array<string,string> $customContent برایِ دیپ‌لینک — مثلاً ['task_id'=>'123']
 */
function metrix_send_push($userId, string $title, string $message, array $customContent = []): array
{
    if (!defined('METRIX_APP_ID') || !defined('METRIX_SERVER_API_KEY')) {
        return ['ok' => false, 'http_code' => 0, 'response' => 'includes/metrix-config.php روی سرور نیست — پوش ارسال نشد'];
    }
    $msg = ['title' => $title, 'body' => $message];
    if ($customContent) {
        $msg['customContent'] = $customContent;
    }
    $payload = [
        'recipients' => [(string) $userId],
        'channel' => 'APP_PUSH',
        'message' => $msg,
    ];

    $ch = curl_init('https://entry.metrix.ir/push');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Application-Id: ' . METRIX_APP_ID,
            'X-API-Key: ' . METRIX_SERVER_API_KEY,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $out = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
        'response' => json_decode($out ?: '', true) ?? $out,
    ];
}
