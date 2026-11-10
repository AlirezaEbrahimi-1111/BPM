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

/** @return array{ok:bool, error:?string} */
function ucrmSendPatternToPhone(PDO $db, string $phone, int $bodyId, array $args, int $senderUserId): array
{
    $sms = new SMS($db);
    $prop = function (string $name) use ($sms) {
        $p = new ReflectionProperty($sms, $name);
        $p->setAccessible(true);
        return $p->getValue($sms);
    };
    $call = function (string $name, array $params) use ($sms) {
        $m = new ReflectionMethod($sms, $name);
        $m->setAccessible(true);
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
