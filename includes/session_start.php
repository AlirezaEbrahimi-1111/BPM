<?php
// CSP در حالتِ Report-Only: چیزی را block نمی‌کند (صد در صد بدون ریسکِ
// خرابیِ صفحاتِ آنلاین)، فقط در Console مرورگر گزارشِ نقض می‌دهد. هدف این
// مرحله صرفاً سنجشِ میزانِ استفاده از inline-script/inline-style در کل
// سایت است تا بعداً بشه با آگاهی به نسخه‌ی enforcing (با nonce یا
// 'unsafe-inline') رفت. مبداهایِ خارجیِ شناخته‌شده: cdnjs (کتابخانه‌ی
// xlsx در payroll-report.php)، computeryekta.com (لوگو در index.php)،
// api.ipify.org (تشخیصِ IP در network-canvas.js)، najva.com (سرویسِ پوش‌نوتیفِ
// وب که در footer/header لود می‌شود — van/events.najva.com).
if (!headers_sent()) {
    // style-src شاملِ 'unsafe-inline' است چون کلِ رابط از style="..."ِ درون‌خطی
    // استفاده می‌کند (بازنویسیِ همه‌شان عملی نیست) و ریسکِ inline-style بسیار
    // کمتر از inline-script است. script-src عمداً سخت‌گیر مانده تا گزارشِ
    // نقض‌های اسکریپت (که برایِ XSS مهم‌اند) در کنسول دیده شود.
    header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com https://van.najva.com https://cdn.najva.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://computeryekta.com https://*.najva.com; font-src 'self' data:; connect-src 'self' https://api.ipify.org https://events.najva.com https://van.najva.com; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self';");

    // هدرهای امنیتیِ پایه (این‌ها چیزی را نمی‌شکنند):
    header('X-Frame-Options: SAMEORIGIN');            // ضدِ clickjacking — قاب‌شدن فقط از همین دامنه
    header('X-Content-Type-Options: nosniff');        // مرورگر نوعِ فایل را حدس نزند
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // HSTS — سایت همیشه HTTPS است (ریدایرکتِ .htaccess). یک سال، بدون preload/includeSubDomains.
    if (($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,    // فقط روی HTTPS ارسال شود
        'httponly' => true,    // جاوااسکریپت به کوکی دسترسی نداشته باشد
        'samesite' => 'Lax',   // محافظتِ پایه در برابر CSRF
    ]);
    session_start();
}