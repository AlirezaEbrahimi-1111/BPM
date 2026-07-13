<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  پیکربندی تست‌ها
 *  محل: /tests/config.php
 * ───────────────────────────────────────────────────────────────────
 *  ⚠️ این فایل نباید در مخزن Git قرار گیرد (شامل توکن است).
 *     در .gitignore اضافه شود:  tests/config.php
 * ═══════════════════════════════════════════════════════════════════
 */

return [

    // نشانی پایهٔ سامانه (بدون اسلش انتهایی)
    'base_url' => 'https://bpm.computeryekta.com',

    /**
     * توکن معتبر برای تست‌های یکپارچه.
     *
     * نحوهٔ دریافت:
     *   ۱) وارد سامانه شوید
     *   ۲) کلید F12 → تب Console
     *   ۳) این را اجرا کنید:  localStorage.getItem('auth_token')
     *   ۴) مقدار را اینجا بگذارید
     *
     * اگر خالی بماند، تست‌های یکپارچه رد (skip) می‌شوند
     * و فقط تست‌های واحد اجرا می‌شوند.
     */
    'auth_token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoxLCJvcmdhbml6YXRpb25faWQiOjEsImlhdCI6MTc4MzkyODYwMCwiZXhwIjoxNzgzOTQ2NjAwfQ.fxrg9IUTAktk5kaQMmZCVEcu2eTQv1AzQfrk6ttQTb8',

    /**
     * کلید امنیتی اجرای تست از مرورگر.
     * ⚠️ حتماً به یک رشتهٔ تصادفی و طولانی تغییر دهید.
     */
    'run_secret' => 'yekta-test-9Kx4Mp2vRt7Lw',

];
