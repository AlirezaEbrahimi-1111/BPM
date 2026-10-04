<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دسترسی به نسخه‌ی وب</title>
    <style>
        :root { --bg: #f5f6fa; --card: #ffffff; --text: #1f2430; --muted: #5b6272; --accent: #8e57fe; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) { --bg: #14161d; --card: #1d2030; --text: #eceef5; --muted: #a6acbd; --accent: #a983ff; }
        }
        :root[data-theme="dark"] { --bg: #14161d; --card: #1d2030; --text: #eceef5; --muted: #a6acbd; --accent: #a983ff; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg); color: var(--text); font-family: Tahoma, sans-serif; }
        .box { max-width: 480px; margin: 16px; padding: 28px 24px; background: var(--card); border-radius: 14px; text-align: center; box-shadow: 0 4px 18px rgba(0,0,0,.08); }
        h1 { font-size: 1.15rem; margin: 0 0 12px; color: var(--accent); }
        p { color: var(--muted); line-height: 1.9; font-size: .95rem; margin: 0 0 18px; }
        a { color: var(--accent); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="box">
        <h1>دسترسی به نسخه‌ی وب فعال نیست</h1>
        <p>پلن سازمان شما برای استفاده از نسخه‌ی وب فعال نیست یا مدت آن به پایان رسیده است.
            برای فعال‌سازی یا تمدید با پشتیبانی تماس بگیرید. استفاده از اپ موبایل همچنان طبق پلن سازمان ادامه دارد.</p>
        <a href="../index.php">بازگشت به صفحه‌ی ورود</a>
    </div>
</body>
</html>
