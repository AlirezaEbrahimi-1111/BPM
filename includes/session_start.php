<?php
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