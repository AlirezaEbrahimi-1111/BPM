// نمایشِ مشترکِ «تأخیر» (دقیقه → متنِ فارسی) — صفحه‌ها خودشان تأخیر را حساب نمی‌کنند؛
// عددِ delay_minutes را سرور می‌دهد (api/workflows/detail.php و go-api/internal/reports/bottleneck.go
// با فرمولِ یکسان: پایانِ مرحله (یا الان) − موعدِ مرحله، به دقیقه، حداقل صفر).
// استفاده: workflow-monitor.php (جزئیات روتین)، bottleneck-report.php (تحلیل گلوگاه)
// (بدونِ let/const سطحِ بالا تا با اسکریپت‌های صفحه تداخل نکند)
window.formatDelayMinutes = function (minutes) {
    var fa = function (n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var total = Math.max(0, Math.round(Number(minutes) || 0));
    if (total < 1) return 'کمتر از ۱ دقیقه';
    var d = Math.floor(total / 1440), h = Math.floor((total % 1440) / 60), m = total % 60;
    var parts = [];
    if (d) parts.push(fa(d) + ' روز');
    if (h) parts.push(fa(h) + ' ساعت');
    // دقیقه فقط وقتی کمتر از یک روز است — «۳ روز و ۲ ساعت و ۷ دقیقه» شلوغ است
    if (m && !d) parts.push(fa(m) + ' دقیقه');
    if (!parts.length) parts.push(fa(h) + ' ساعت');
    return parts.join(' و ');
};
