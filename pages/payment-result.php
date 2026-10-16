<?php
/**
 * صفحه‌ی سبکِ «نتیجه‌ی پرداخت» — مخصوصِ بازگشتِ اپِ موبایل از درگاه.
 * عمداً بدونِ سشن/لاگین است (WebViewِ اپ کوکیِ وب ندارد) — اپ معمولاً
 * این صفحه را حتی نمی‌گذارد کامل لود شود (با رصدِ URL در WebView، همین‌
 * که مسیر به اینجا رسید می‌بندَدش)؛ این فقط یک پیامِ بازگشتیِ ساده
 * برایِ حالتی است که کسی این لینک را مستقیم در مرورگر باز کند.
 */

$status = $_GET['payment'] ?? '';

$map = [
    'success'  => ['✅', 'پرداخت موفق بود', 'پلنِ سازمانِ شما به‌روزرسانی شد. این صفحه را می‌توانید ببندید.'],
    'already'  => ['ℹ️', 'این پرداخت قبلاً تأیید شده', 'نیازی به اقدامِ دیگری نیست.'],
    'cancel'   => ['✖️', 'پرداخت لغو شد', 'هر زمان خواستید می‌توانید دوباره تلاش کنید.'],
    'failed'   => ['⚠️', 'پرداخت ناموفق بود', 'مبلغی کسر نشده است. دوباره تلاش کنید.'],
    'error'    => ['⚠️', 'خطایی رخ داد', 'در صورتِ کسرِ مبلغ، با پشتیبانی تماس بگیرید.'],
    'notfound' => ['⚠️', 'تراکنش یافت نشد', 'لطفاً دوباره تلاش کنید.'],
];
[$icon, $title, $desc] = $map[$status] ?? ['⚠️', 'نتیجه‌ی نامشخص', ''];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<style>
  body { font-family: Tahoma, sans-serif; background:#0f172a; color:#f1f5f9; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
  .card { text-align:center; padding:32px; max-width:360px; }
  .icon { font-size:48px; margin-bottom:12px; }
  h1 { font-size:18px; margin:0 0 8px; }
  p { font-size:14px; color:#94a3b8; margin:0; }
</style>
</head>
<body>
  <div class="card">
    <div class="icon"><?= $icon ?></div>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><?= htmlspecialchars($desc) ?></p>
  </div>
</body>
</html>
