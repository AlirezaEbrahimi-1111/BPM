<?php
/**
 * scripts/bump-version.php — یک قدمِ به‌جایِ سه‌تا برایِ بامپِ نسخه‌ی فوتر.
 *
 * قبلاً هر بار: 1) ساعتِ شمسیِ فعلی رو با یک دستورِ inline PHP حساب می‌کردم،
 * 2) عددِ نسخه‌ی فعلی رو از footer.php می‌خوندم، 3) هردو رو دستی ادیت می‌کردم.
 * این اسکریپت همون سه قدم رو خودکار می‌کنه — فقط یک اجرا، هم زمان و هم
 * ریسکِ تایپوی دستی رو حذف می‌کنه.
 *
 * اجرا:  php scripts/bump-version.php
 */

require __DIR__ . '/../includes/JalaliHelper.php';

date_default_timezone_set('Asia/Tehran');

$footerPath = __DIR__ . '/../pages/footer.php';
$content = file_get_contents($footerPath);
if ($content === false) {
    fwrite(STDERR, "footer.php را نتوانستم بخوانم\n");
    exit(1);
}

if (!preg_match('/نسخه:\s*([۰-۹.]+)/u', $content, $m)) {
    fwrite(STDERR, "الگویِ نسخه در footer.php پیدا نشد\n");
    exit(1);
}

$currentFa = $m[1];
$currentEn = JalaliHelper::toEnglish($currentFa);
[$major, $minor] = array_pad(explode('.', $currentEn), 2, '0');
$minor = (int) $minor + 1;
if ($minor >= 100) {
    $minor = 0;
    $major = (int) $major + 1;
}
$newVersionEn = $major . '.' . str_pad((string) $minor, 2, '0', STR_PAD_LEFT);
$newVersionFa = JalaliHelper::Persian($newVersionEn);

$nowJalali = JalaliHelper::formatJalaliDate(date('Y-m-d H:i:s'));
$nowTimeFa = JalaliHelper::Persian(date('H:i'));
$newTitle = $nowJalali . ' - ' . $nowTimeFa;

$content = preg_replace(
    '/title="آخرین به‌روزرسانی: [^"]*"/u',
    'title="آخرین به‌روزرسانی: ' . $newTitle . '"',
    $content,
    1
);
$content = preg_replace(
    '/نسخه:\s*[۰-۹.]+/u',
    'نسخه: ' . $newVersionFa,
    $content,
    1
);

file_put_contents($footerPath, $content);

echo "نسخه به‌روزرسانی شد: {$currentFa} → {$newVersionFa} ({$newTitle})\n";
