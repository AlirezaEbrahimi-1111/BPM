<?php
// CSP در حالتِ Report-Only: چیزی را block نمی‌کند (صد در صد بدون ریسکِ
// خرابیِ صفحاتِ آنلاین)، فقط در Console مرورگر گزارشِ نقض می‌دهد. هدف این
// مرحله صرفاً سنجشِ میزانِ استفاده از inline-script/inline-style در کل
// سایت است تا بعداً بشه با آگاهی به نسخه‌ی enforcing (با nonce یا
// 'unsafe-inline') رفت. مبداهایِ خارجیِ شناخته‌شده: cdnjs (کتابخانه‌ی
// xlsx در payroll-report.php)، computeryekta.com (لوگو در index.php)،
// api.ipify.org (تشخیصِ IP در network-canvas.js).
if (!headers_sent()) {
    header("Content-Security-Policy-Report-Only: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; style-src 'self'; img-src 'self' data: https://computeryekta.com; font-src 'self' data:; connect-src 'self' https://api.ipify.org; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self';");
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