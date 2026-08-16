# کتابخانه‌های Vendor شده — فهرست و نسخه‌ها

این پروژه از Composer/npm استفاده نمی‌کند؛ کتابخانه‌های third-party به‌صورتِ
فایلِ استاتیک مستقیم در `assets/js/` و `assets/css/` کپی شده‌اند. این سند
جایگزینِ `composer.json`/`package.json` است — چون نصب/آپدیت از طریقِ این
فایل‌ها انجام نمی‌شود، هدف صرفاً **ردیابیِ نسخه‌ها** برای پیگیریِ CVE و
تصمیم‌گیریِ آگاهانه درباره‌ی به‌روزرسانی است.

**نحوه‌ی استفاده:** قبل از هر آپدیت، جدولِ زیر را با نسخه‌ی جدید به‌روز کنید.
هر چند ماه یک‌بار (یا بعدِ اعلامِ CVE برای یکی از این کتابخانه‌ها)، این فهرست
باید بازبینی شود.

## ⚠️ ناسازگاریِ یافته‌شده هنگامِ تهیه‌ی این سند

دو نسخه‌ی متفاوت از Bootstrap هم‌زمان در پروژه وجود دارد:
- `assets/js/bootstrap.bundle.min.js` → **v5.2.3**
- `assets/js/cdn/bootstrap.bundle.min.js` → **v5.3.0**

بسته به این‌که هر صفحه کدام مسیر را load می‌کند، ممکن است رفتار/ظاهرِ
Bootstrap بینِ صفحات ناهماهنگ باشد. نیاز به بررسی دارد کدام صفحات از کدام
نسخه استفاده می‌کنند و یکسان‌سازی شوند.

## فهرست

| کتابخانه | نسخه | مسیر |
|---|---|---|
| Bootstrap (نسخه‌ی ۱) | 5.2.3 | `assets/js/bootstrap.bundle.min.js` |
| Bootstrap (نسخه‌ی ۲) | 5.3.0 | `assets/js/cdn/bootstrap.bundle.min.js` |
| jQuery | 3.6.0 | `assets/js/cdn/jquery-3.6.0.min.js`, `assets/js/cdn/jquery.min.js` |
| Chart.js | 4.5.1 | `assets/js/cdn/chart.js` |
| persian-date | 1.1.0 | `assets/js/cdn/persian-date.min.js` |
| persian-datepicker | نامشخص از فایل | `assets/js/cdn/persian-datepicker.min.js` |
| moment.js | نامشخص از فایل | `assets/js/cdn/moment.min.js` |
| moment-jalaali | نامشخص از فایل | `assets/js/cdn/moment-jalaali.js` |
| intro.js | نامشخص از فایل | `assets/js/cdn/intro.min.js` |
| ag-grid-community | نامشخص از فایل — نیاز به شناسایی دستی | `assets/js/ag-grid-community.min.js` |
| SheetJS (xlsx.js) | نامشخص (کتابخانه‌ی داخلیِ cptable آن 1.15.0 است، خودِ xlsx.js نه لزوماً همین) | `assets/js/xlsx.full.min.js` |
| bootstrap-icons (فونت) | نامشخص از فایل | `assets/js/cdn/bootstrap-icons.css` + `assets/js/cdn/fonts/` |

## سرویسِ جدا (ai-service/)

این پروژه یک سرویسِ پایتونیِ مجزا هم دارد که وابستگی‌هایش را باید جداگانه
با `pip freeze > requirements.txt` مستند کند — در زمانِ تهیه‌ی این سند،
فایلِ قفلِ نسخه (`requirements.txt`) برای آن پیدا نشد.

## توصیه برای آینده

مهاجرت به یک ابزارِ واقعیِ مدیریتِ بسته (npm برای فرانت‌اند، حداقل برای
پیگیریِ نسخه و CVE، حتی اگر نصبِ واقعی هنوز دستی/vendored بماند) — کارِ
بزرگ‌تری‌ست که نیاز به زمانِ جداگانه دارد و در این دورِ رفعِ ایراد انجام
نشد.
