<?php
// CSP در حالت Report-Only: چیزی را block نمی‌کند (صد در صد بدون ریسک
// خرابی صفحات آنلاین)، فقط در Console مرورگر گزارش نقض می‌دهد. هدف این
// مرحله صرفا سنجش میزان استفاده از inline-script/inline-style در کل
// سایت است تا بعدا بشه با آگاهی به نسخه‌ی enforcing (با nonce یا
// 'unsafe-inline') رفت. مبداهای خارجی شناخته‌شده: cdnjs (کتابخانه‌ی
// xlsx در payroll-report.php)، computeryekta.com (لوگو در index.php)،
// api.ipify.org (تشخیص IP در network-canvas.js)، najva.com (سرویس پوش‌نوتیف
// وب که در footer/header لود می‌شود — van/events.najva.com).
if (!headers_sent()) {
    // CSP فقط Report-Only است (چیزی را بلاک نمی‌کند). چون کل کدبیس پر از
    // <script> درون‌خطی و style="..." درون‌خطی است، 'unsafe-inline' برای هر دو
    // اضافه شده تا کنسول پر از گزارش نقض بی‌عمل نشود. یک CSP واقعی ضد XSS
    // در آینده به بازنویسی همهٔ inline-scriptها با nonce نیاز دارد.
    header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://van.najva.com https://cdn.najva.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://computeryekta.com https://*.najva.com; font-src 'self' data:; connect-src 'self' https://api.ipify.org https://events.najva.com https://van.najva.com; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self';");

    // CSP واقعی (enforcing) — فقط شامل دستورهایی که هیچ منبعی را محدود
    // نمی‌کنند و در این کدبیس امکان ندارد چیزی را بشکنند: هیچ تگ
    // <object>/<embed>/<base> و هیچ <form action> به دامنهٔ بیرونی نداریم،
    // و frame-ancestors همین حالا با X-Frame-Options اعمال شده. این‌ها
    // بردارهای واقعی تشدید XSS را می‌بندند (تزریق <base>، پلاگین، ربودن
    // مقصد فرم، قاب‌شدن). دستورهای پرریسک (script-src/img-src/connect-src/…)
    // فعلا فقط Report-Only می‌مانند تا از نبود نقض واقعی مطمئن شویم.
    header("Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self';");

    // هدرهای امنیتی پایه (این‌ها چیزی را نمی‌شکنند):
    header('X-Frame-Options: SAMEORIGIN');            // ضد clickjacking — قاب‌شدن فقط از همین دامنه
    header('X-Content-Type-Options: nosniff');        // مرورگر نوع فایل را حدس نزند
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // HSTS — سایت همیشه HTTPS است (ریدایرکت .htaccess). یک سال، بدون preload/includeSubDomains.
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
        'samesite' => 'Lax',   // محافظت پایه در برابر CSRF
    ]);
    session_start();
}