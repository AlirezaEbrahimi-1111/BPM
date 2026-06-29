<?php
/**
 * ============================================================
 *  توابع ارتباط با درگاه پرداخت زرین‌پال (نسخه‌ی API v4)
 * ============================================================
 *  این فایل خودش به تنهایی کاری نمی‌کند؛ فقط دو تابع آماده دارد:
 *    - zarinpal_request()  → شروع پرداخت و گرفتن لینک درگاه
 *    - zarinpal_verify()   → تأیید پرداخت بعد از بازگشت کاربر
 *
 *  این فایل را در همان پوشه‌ی payment-request.php و callback.php بگذار.
 * ============================================================
 */

/* ┌─────────────────────────────────────────────┐
   │  تنظیمات — این دو مقدار را خودت پر کن         │
   └─────────────────────────────────────────────┘ */

// کد ۳۶ کاراکتری درگاه که از پنل زرین‌پال می‌گیری:
const ZP_MERCHANT = '29af838c-250e-4d69-9331-3a1be3a7b483';

// واحد پول:  'IRT' = تومان   |   'IRR' = ریال
// چون ستون price شما تومان است، مقدار IRT درست است.
const ZP_CURRENCY = 'IRT';

// برای تست بدون پول واقعی true بگذار، برای حالت واقعی false.
const ZP_SANDBOX = true;


/* ┌─────────────────────────────────────────────┐
   │  از اینجا به بعد چیزی را تغییر نده             │
   └─────────────────────────────────────────────┘ */

function zp_base(): string {
    // در حالت تست از دامنه‌ی sandbox استفاده می‌شود
    return ZP_SANDBOX ? 'https://sandbox.zarinpal.com' : 'https://api.zarinpal.com';
}

function zp_pay_base(): string {
    return ZP_SANDBOX ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
}

/**
 * مرحله ۱: درخواست پرداخت
 * @return array ['ok'=>bool, 'authority'=>string, 'startUrl'=>string, 'error'=>mixed]
 */
function zarinpal_request(int $amount, string $callback, string $desc): array {
    $payload = json_encode([
        'merchant_id'  => ZP_MERCHANT,
        'amount'       => $amount,
        'currency'     => ZP_CURRENCY,
        'callback_url' => $callback,
        'description'  => $desc,
    ]);

    $res = zp_curl(zp_base() . '/pg/v4/payment/request.json', $payload);

    if ((int)($res['data']['code'] ?? 0) === 100) {
        $authority = $res['data']['authority'];
        return [
            'ok'        => true,
            'authority' => $authority,
            'startUrl'  => zp_pay_base() . '/pg/StartPay/' . $authority,
        ];
    }

    return ['ok' => false, 'error' => $res['errors'] ?? 'request_failed'];
}

/**
 * مرحله ۳: تأیید پرداخت (سمت سرور — مهم‌ترین مرحله‌ی امنیتی)
 * @return array ['ok'=>bool, 'ref_id'=>string, 'code'=>int, 'error'=>mixed]
 */
function zarinpal_verify(int $amount, string $authority): array {
    $payload = json_encode([
        'merchant_id' => ZP_MERCHANT,
        'amount'      => $amount,      // باید دقیقاً برابر مبلغ مرحله‌ی درخواست باشد
        'authority'   => $authority,
    ]);

    $res  = zp_curl(zp_base() . '/pg/v4/payment/verify.json', $payload);
    $code = (int)($res['data']['code'] ?? 0);

    // 100 = پرداخت موفق   |   101 = قبلاً تأیید شده (هر دو معتبر هستند)
    if ($code === 100 || $code === 101) {
        return [
            'ok'     => true,
            'ref_id' => (string)($res['data']['ref_id'] ?? ''),
            'code'   => $code,
        ];
    }

    return ['ok' => false, 'error' => $res['errors'] ?? 'verify_failed'];
}

/**
 * تابع کمکی برای ارسال درخواست به زرین‌پال
 */
function zp_curl(string $url, string $json): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);

    return json_decode($out, true) ?: ['errors' => 'no_response'];
}
