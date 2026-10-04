<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-prices.php';

function planFa(string $s): string { return strtr($s, '0123456789', '۰۱۲۳۴۵۶۷۸۹'); }
function planPrice($v): string {
    if ($v === null) return '—';
    if ($v === 0) return 'به‌زودی';
    return planFa(number_format($v)) . ' ت';
}
$periods = ['1' => 'یک ماهه', '3' => 'سه ماهه', '6' => 'شش ماهه', '12' => 'یک ساله', 'permanent' => 'دائمی'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پلن‌ها و دسترسی به نسخه‌ی وب</title>
    <style>
        :root { --bg: #f5f6fa; --card: #ffffff; --text: #1f2430; --muted: #5b6272; --accent: #8e57fe; --line: #e3e6ee; --hover: rgba(142,87,254,.12); }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) { --bg: #14161d; --card: #1d2030; --text: #eceef5; --muted: #a6acbd; --accent: #a983ff; --line: #2e3348; --hover: rgba(142,87,254,.18); }
        }
        :root[data-theme="dark"] { --bg: #14161d; --card: #1d2030; --text: #eceef5; --muted: #a6acbd; --accent: #a983ff; --line: #2e3348; --hover: rgba(142,87,254,.18); }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--text); font-family: Tahoma, sans-serif; padding: 24px 12px; box-sizing: border-box; }
        .wrap { max-width: 900px; margin: 0 auto; }
        .card { background: var(--card); border-radius: 14px; padding: 22px; box-shadow: 0 4px 18px rgba(0,0,0,.08); margin-bottom: 18px; }
        h1 { font-size: 1.2rem; margin: 0 0 10px; color: var(--accent); }
        h2 { font-size: 1rem; margin: 0 0 12px; }
        p, li { color: var(--muted); line-height: 1.9; font-size: .92rem; }
        ul { margin: 0; padding-inline-start: 20px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        th, td { padding: 9px 8px; border-bottom: 1px solid var(--line); text-align: center; white-space: nowrap; }
        th { color: var(--muted); font-weight: 600; }
        tr:hover td { background: var(--hover); }
        .tag { display: inline-block; font-size: .72rem; padding: 2px 8px; border-radius: 999px; background: var(--hover); color: var(--accent); }
        .soon { color: var(--muted); font-size: .82rem; }
        .back { display: inline-block; margin-top: 6px; color: var(--accent); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>دسترسی به نسخه‌ی وب فعال نیست</h1>
        <p>پلن فعلی سازمان شما اجازه‌ی استفاده از نسخه‌ی وب را نمی‌دهد، یا مدت آن تمام شده است.
            استفاده از اپ موبایل طبق پلن سازمان ادامه دارد.</p>
        <p>برای خرید یا تمدید، با پشتیبانی تماس بگیرید. خرید آنلاین به‌زودی فعال می‌شود.</p>
        <a class="back" href="../index.php">بازگشت به صفحه‌ی ورود</a>
    </div>

    <div class="card">
        <h2>پلن‌ها</h2>
        <ul>
            <li><b>رایگان:</b> یک کاربر؛ امکانات پایه‌ی کار شخصی.</li>
            <li><b>نقره‌ای (فقط اپ):</b> تا ۱۰ کاربر؛ ساخت کار برای دیگران، کارهای واگذارشده و برنامه‌ی کاری.</li>
            <li><b>طلایی (اپ و وب):</b> تا ۴۰ کاربر؛ همه‌ی امکانات شامل ارجاع، کار روتین، گفتگو، اعلان‌ها، گزارش‌ها و مدیریت.</li>
            <li><b>تست ۱۴ روزه‌ی وب:</b> یک‌بار برای هر سازمان؛ در طول تست، امکانات طلایی در نسخه‌ی وب فعال است.</li>
        </ul>
    </div>

    <div class="card">
        <h2>قیمت‌ها</h2>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>سطح</th>
                    <th>کاربر</th>
                    <?php foreach ($periods as $label) : ?><th><?= $label ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php $s = PLAN_PRICE_TABLE['silver']; ?>
                <tr>
                    <td><span class="tag"><?= $s['label'] ?></span></td>
                    <td><?= planFa((string) $s['users']) ?></td>
                    <?php foreach (array_keys($periods) as $p) : ?><td><?= planPrice($s['prices'][$p]) ?></td><?php endforeach; ?>
                </tr>
                <?php foreach (PLAN_PRICE_TABLE['gold']['tiers'] as $users => $prices) : ?>
                <tr>
                    <td><span class="tag"><?= PLAN_PRICE_TABLE['gold']['label'] ?></span></td>
                    <td><?= planFa((string) $users) ?></td>
                    <?php foreach (array_keys($periods) as $p) : ?><td><?= planPrice($prices[$p]) ?></td><?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="soon">قیمت‌های «به‌زودی» هنوز قابل خرید نیستند.</p>
    </div>
</div>
</body>
</html>
