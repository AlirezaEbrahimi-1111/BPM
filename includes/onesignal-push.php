<?php
/**
 * ============================================================
 *  ارسالِ Push از طریقِ OneSignal (سرویسِ انتخاب‌شده برایِ فازِ ۰)
 * ============================================================
 *  فقط یک تابع دارد: onesignal_send_push() — کاربرِ هدف را با همان
 *  external_id ای که اپ هنگامِ لاگین با OneSignal.login() ثبت می‌کند
 *  (که دقیقاً همان user.id خودِ ماست) پیدا می‌کند.
 * ============================================================
 */

// از پنلِ OneSignal → Settings → Keys & IDs
const ONESIGNAL_APP_ID = 'c6203850-9f7b-45e0-a0a3-5aeb2baf1648';
const ONESIGNAL_REST_API_KEY = 'REPLACE_WITH_REST_API_KEY';

/**
 * @param int|string $userId شناسه‌ی کاربرِ ما (همان external_id سمتِ اپ)
 * @param array<string,string> $data برایِ دیپ‌لینک — مثلاً ['route'=>'task_detail','task_id'=>'123']
 * @return array ['ok'=>bool, 'response'=>mixed]
 */
function onesignal_send_push(
    $userId,
    string $title,
    string $message,
    array $data = []
): array {
    $payload = json_encode([
        'app_id' => ONESIGNAL_APP_ID,
        'target_channel' => 'push',
        'include_aliases' => [
            'external_id' => [(string) $userId],
        ],
        'headings' => ['en' => $title],
        'contents' => ['en' => $message],
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Key ' . ONESIGNAL_REST_API_KEY,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $out = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($out ?: '', true);
    $ok = $httpCode >= 200 && $httpCode < 300 && empty($decoded['errors']);

    return ['ok' => $ok, 'http_code' => $httpCode, 'response' => $decoded];
}
