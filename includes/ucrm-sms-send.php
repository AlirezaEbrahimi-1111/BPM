<?php
/**
 * ارسال الگوی تأییدشدهٔ ملی‌پیامک به شمارهٔ مشتری CRM (۱۴۰۵/۰۸/۱۹) — api/ucrm/sms-deliver.php.
 *
 * includes/sms.php در git نیست (اطلاعات حساب پیامک داخلش است و هر سرور نسخهٔ خودش را دارد)،
 * پس به آن دست نمی‌زنیم: همان کلاسِ SMS ساخته می‌شود و نام کاربری/رمز و توابعِ کمکیِ خصوصیِ
 * آن (normalizePhone و logSMS) با Reflection خوانده می‌شوند — اطلاعات حساب فقط یک جا می‌ماند.
 * قرارداد همان sendPattern: BaseServiceNumber، مقادیر با «;»، موفق = Value > 100.
 */
require_once __DIR__ . '/sms.php';

/**
 * مدیریت الگوها با REST ملی‌پیامک (ShareService — مستندات melipayamak.com/api، آبان ۱۴۰۵).
 * password همان API Key حساب است (مقدارِ password در includes/sms.php).
 * @return array{ok:bool, value:mixed, data:mixed, error:?string}
 */
function ucrmPatternApi(PDO $db, string $method, array $fields): array
{
    $sms = new SMS($db);
    $get = function (string $n) use ($sms) {
        $p = new ReflectionProperty($sms, $n);
        if (PHP_VERSION_ID < 80100) $p->setAccessible(true);
        return $p->getValue($sms);
    };
    $ch = curl_init('https://rest.payamak-panel.com/api/ShareService/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['username' => $get('username'), 'password' => $get('password')] + $fields, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string) $raw, true);
    if ($http != 200 || !is_array($j)) {
        return ['ok' => false, 'value' => null, 'data' => null, 'error' => 'پاسخ نامعتبر از ملی‌پیامک (HTTP ' . $http . ')'];
    }
    $base = $j['MyBase'] ?? $j;
    $value = $base['Value'] ?? null;
    $retStatus = (int) ($base['RetStatus'] ?? 0);
    $ok = $retStatus === 1;
    return ['ok' => $ok, 'value' => $value, 'data' => $j['Data'] ?? null,
        'error' => $ok ? null : ucrmPatternError((string) $value, (string) ($base['StrRetStatus'] ?? ''))];
}

/** پیامِ فارسیِ خطاهای ShareService (طبق مستندات) */
function ucrmPatternError(string $value, string $str): string
{
    $map = [
        '0' => 'نام کاربری یا کلید API ملی‌پیامک نادرست است.',
        '-1' => 'مجوز «درج الگوی خدماتی» روی حساب ملی‌پیامک فعال نیست.',
        '-2' => 'عنوان الگو خالی است.',
        '-3' => 'متن الگو خالی است.',
        '-5' => 'در وضعیت فعلیِ الگو این کار ممکن نیست (اصلاح فقط وقتی ملی‌پیامک «نیاز به اصلاح» گفته باشد).',
        '-8' => 'عنوان الگو حداکثر ۵۰ نویسه است.',
        '-108' => 'تعداد درخواست‌ها به ملی‌پیامک زیاد است؛ کمی بعد دوباره امتحان کنید.',
    ];
    if (isset($map[$value])) return $map[$value];
    if (in_array($value, ['-9', '-10', '-11', '-12', '-13', '-14', '-15'], true)) {
        return 'نوشتن متغیرها در متن الگو درست نیست؛ از {0}، {1}، … با رقم انگلیسی استفاده کنید.';
    }
    if (in_array($value, ['-109', '-110', '-111', '-112'], true)) {
        return 'دسترسی وب‌سرویس ملی‌پیامک برای این سرور بسته است (IP یا کلید API) — کد ' . $value;
    }
    return 'خطای ملی‌پیامک' . ($value !== '' ? ' (کد ' . $value . ($str ? '، ' . $str : '') . ')' : '');
}

/** @return array{ok:bool, error:?string} */
function ucrmSendPatternToPhone(PDO $db, string $phone, int $bodyId, array $args, int $senderUserId): array
{
    $sms = new SMS($db);
    $prop = function (string $name) use ($sms) {
        $p = new ReflectionProperty($sms, $name);
        if (PHP_VERSION_ID < 80100) $p->setAccessible(true);
        return $p->getValue($sms);
    };
    $call = function (string $name, array $params) use ($sms) {
        $m = new ReflectionMethod($sms, $name);
        if (PHP_VERSION_ID < 80100) $m->setAccessible(true);
        return $m->invokeArgs($sms, $params);
    };

    $phone = (string) $call('normalizePhone', [$phone]);
    $text = implode(';', array_map(fn($v) => trim((string) $v), $args));
    $log = function (string $status, $response, ?string $err) use ($call, $senderUserId, $phone, $text, $bodyId) {
        try {
            $call('logSMS', [$senderUserId, $phone, $text, 'ucrm-pattern:' . $bodyId, null, $status, $response, $err]);
        } catch (Throwable $e) {
            error_log('ucrmSendPatternToPhone log: ' . $e->getMessage());
        }
    };

    try {
        $ch = curl_init('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $prop('username'),
            'password' => $prop('password'),
            'to'       => $phone,
            'bodyId'   => $bodyId,
            'text'     => $text,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $j = json_decode((string) $response, true);
        $ok = ($http == 200 && isset($j['Value']) && (int) $j['Value'] > 100);
        $err = $ok ? null : ('کد بازگشتی پنل پیامک: ' . ($j['Value'] ?? ('HTTP ' . $http)));
        $log($ok ? 'sent' : 'failed', $response, $err);
        return ['ok' => $ok, 'error' => $err];
    } catch (Throwable $e) {
        $log('failed', null, $e->getMessage());
        return ['ok' => false, 'error' => 'خطا در ارتباط با پنل پیامک'];
    }
}
