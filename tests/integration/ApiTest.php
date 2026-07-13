<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  تست یکپارچه: مسیرهای بحرانی API
 *  محل: /tests/integration/ApiTest.php
 * ───────────────────────────────────────────────────────────────────
 *  این تست‌ها با درخواست واقعی HTTP اجرا می‌شوند (جعبه‌سیاه).
 *  بررسی می‌کنند که:
 *    • احراز هویت واقعاً اجباری است
 *    • ساختار پاسخ APIها پایدار است
 *    • فیلدهای حیاتی (که رابط کاربری به آن‌ها وابسته است) حذف نشده‌اند
 *
 *  ⚠️ پیش‌نیاز: توکن معتبر در فایل tests/config.php
 *     اگر توکن تنظیم نشده باشد، این تست‌ها رد می‌شوند (skip).
 * ═══════════════════════════════════════════════════════════════════
 */

$cfg = require dirname(__DIR__) . '/config.php';

/**
 * ارسال درخواست GET به API
 *
 * @return array ['status' => کد HTTP, 'body' => آرایهٔ پاسخ, 'raw' => متن خام]
 */
function apiGet(string $path, ?string $token, array $cfg): array
{
    $ch = curl_init($cfg['base_url'] . $path);

    $headers = ['Accept: application/json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,   // میزبانی اشتراکی
    ]);

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $status,
        'body'   => json_decode($raw, true),
        'raw'    => $raw,
    ];
}

/** استخراج آرایهٔ کارها از ساختارهای مختلف پاسخ */
function extractTasks($body): array
{
    if (!is_array($body)) return [];
    if (isset($body['tasks']) && is_array($body['tasks'])) return $body['tasks'];
    if (isset($body['data']['tasks'])) return $body['data']['tasks'];
    return [];
}

$token = $cfg['auth_token'] ?? '';

return [

    'name' => 'تست یکپارچهٔ API',

    'tests' => [

        // ═══════════════════════════════════════════════
        //  امنیت: احراز هویت اجباری است
        // ═══════════════════════════════════════════════

        'درخواست بدون توکن، رد می‌شود' => function (Assert $a) use ($cfg) {
            $res = apiGet('/api/tasks/my-tasks.php', null, $cfg);

            $a->true(
                in_array($res['status'], [401, 403], true),
                '🔒 بدون توکن باید ۴۰۱ یا ۴۰۳ برگردد (دریافتی: ' . $res['status'] . ')'
            );
        },

        'درخواست با توکن نامعتبر، رد می‌شود' => function (Assert $a) use ($cfg) {
            $res = apiGet('/api/tasks/my-tasks.php', 'invalid.token.here', $cfg);

            $a->true(
                in_array($res['status'], [401, 403], true),
                '🔒 توکن جعلی باید رد شود (دریافتی: ' . $res['status'] . ')'
            );
        },


        // ═══════════════════════════════════════════════
        //  پایداری ساختار پاسخ
        // ═══════════════════════════════════════════════

        'API کارهای من، پاسخ معتبر می‌دهد' => function (Assert $a) use ($cfg, $token) {
            if ($token === '') {
                $a->true(true, '⏭ رد شد — توکن در tests/config.php تنظیم نشده');
                return;
            }

            $res = apiGet('/api/tasks/my-tasks.php', $token, $cfg);

            $a->equals(200, $res['status'], 'کد پاسخ باید ۲۰۰ باشد');
            $a->true(is_array($res['body']), 'پاسخ باید JSON معتبر باشد');
            $a->true(
                isset($res['body']['tasks']) || isset($res['body']['data']),
                'پاسخ باید فهرست کارها را داشته باشد'
            );
        },

        'کارهای دوره‌ای، فیلد موعد بعدی دارند' => function (Assert $a) use ($cfg, $token) {
            if ($token === '') {
                $a->true(true, '⏭ رد شد — توکن تنظیم نشده');
                return;
            }

            $res   = apiGet('/api/tasks/my-tasks.php', $token, $cfg);
            $tasks = extractTasks($res['body']);

            // فقط کارهای دوره‌ای را بررسی کن
            $continuous = array_filter($tasks, fn($t) => ($t['task_type'] ?? '') === 'continuous');

            if (empty($continuous)) {
                $a->true(true, '⏭ کار دوره‌ای برای بررسی وجود ندارد');
                return;
            }

            $sample = reset($continuous);

            // این فیلدها را رابط کاربری برای نمایش «موعد» و «مهلت» لازم دارد.
            // حذفشان، جدول‌ها را خالی می‌کند — همان باگی که قبلاً رخ داد.
            $a->true(
                array_key_exists('next_due_date', $sample),
                '🔑 فیلد next_due_date باید موجود باشد'
            );
            $a->true(
                array_key_exists('days_remaining', $sample),
                '🔑 فیلد days_remaining باید موجود باشد'
            );
            $a->true(
                array_key_exists('overdue_periods', $sample),
                '🔑 فیلد overdue_periods باید موجود باشد'
            );
        },

        'API کارهای واگذارشده، ساختار یکسان دارد' => function (Assert $a) use ($cfg, $token) {
            if ($token === '') {
                $a->true(true, '⏭ رد شد — توکن تنظیم نشده');
                return;
            }

            $res = apiGet('/api/tasks/delegated-tasks.php', $token, $cfg);

            $a->equals(200, $res['status'], 'کد پاسخ ۲۰۰');
            $a->true(
                isset($res['body']['tasks']),
                'ساختار پاسخ باید با my-tasks یکسان باشد'
            );
        },

        'API خلاصهٔ فرآیندهای فعال کار می‌کند' => function (Assert $a) use ($cfg, $token) {
            if ($token === '') {
                $a->true(true, '⏭ رد شد — توکن تنظیم نشده');
                return;
            }

            $res = apiGet('/api/workflows/active-summary.php', $token, $cfg);

            $a->equals(200, $res['status'], 'کد پاسخ ۲۰۰');
            $a->true(
                isset($res['body']['success']),
                'پاسخ باید فیلد success داشته باشد'
            );
        },

    ],
];
