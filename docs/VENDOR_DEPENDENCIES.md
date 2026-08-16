# کتابخانه‌های Vendor شده — فهرست و نسخه‌ها

این پروژه از Composer/npm استفاده نمی‌کند؛ کتابخانه‌های third-party به‌صورتِ
فایلِ استاتیک مستقیم در `assets/js/` و `assets/css/` کپی شده‌اند. این سند
جایگزینِ `composer.json`/`package.json` است — چون نصب/آپدیت از طریقِ این
فایل‌ها انجام نمی‌شود، هدف صرفاً **ردیابیِ نسخه‌ها** برای پیگیریِ CVE و
تصمیم‌گیریِ آگاهانه درباره‌ی به‌روزرسانی است.

**نحوه‌ی استفاده:** قبل از هر آپدیت، جدولِ زیر را با نسخه‌ی جدید به‌روز کنید.
هر چند ماه یک‌بار (یا بعدِ اعلامِ CVE برای یکی از این کتابخانه‌ها)، این فهرست
باید بازبینی شود.

## ✅ رفعِ ناسازگاریِ نسخه‌ی Bootstrap

بررسیِ کاملِ پروژه نشان داد که در واقع **سه** نسخه‌ی متفاوت هم‌زمان استفاده
می‌شد (نه فقط دو تا):
- CSS در اکثرِ صفحات → `assets/css/bootstrap.min.css` → **v5.2.3**
- CSS در ۴ صفحه (`create-task.php`, `group-management.php`, `holidays.php`,
  `registerCo.php`) → `assets/js/cdn/bootstrap.min.css` → **v5.1.3** (قدیم‌ترین!)
- JS تقریباً در همه‌ی صفحات → `assets/js/cdn/bootstrap.bundle.min.js` → **v5.3.0**

یعنی همان ۴ صفحه، CSS نسخه‌ی 5.1.3 را همراه با JS نسخه‌ی 5.3.0 لود
می‌کردند — بیشترین فاصله‌ی نسخه در کل پروژه.

**اقدام:** آن ۴ صفحه به همان `assets/css/bootstrap.min.css` (v5.2.3) که
بقیه‌ی صفحات از قبل با موفقیت استفاده می‌کنند ارجاع داده شدند — یعنی الان
همه‌ی صفحات دقیقاً یک جفتِ ثابت (CSS v5.2.3 + JS v5.3.0) لود می‌کنند.
فایل‌های زیر از این پس در هیچ صفحه‌ای reference نمی‌شوند (باقی گذاشته
شدند چون بی‌ضررند، ولی کاندیدِ حذفِ نهایی‌اند):
- `assets/js/cdn/bootstrap.min.css` (v5.1.3)
- `assets/js/bootstrap.bundle.min.js` (v5.2.3، نسخه‌یِ JS تکراری)

## فهرست

| کتابخانه | نسخه | مسیر |
|---|---|---|
| Bootstrap CSS (استفاده‌شده در همه‌ی صفحات) | 5.2.3 | `assets/css/bootstrap.min.css` |
| Bootstrap JS (استفاده‌شده در همه‌ی صفحات) | 5.3.0 | `assets/js/cdn/bootstrap.bundle.min.js` |
| Bootstrap CSS (بلااستفاده، باقی‌مانده از قبل) | 5.1.3 | `assets/js/cdn/bootstrap.min.css` |
| Bootstrap JS (بلااستفاده، باقی‌مانده از قبل) | 5.2.3 | `assets/js/bootstrap.bundle.min.js` |
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

این پروژه یک سرویسِ پایتونیِ مجزا هم دارد. `ai-service/requirements.txt`
موجود است؛ در بازبینیِ این سند، `pydantic` (import مستقیم در `main.py`)
در فهرست جا افتاده بود — اضافه شد. نسخه‌ها pin نشده‌اند (سبکِ فعلیِ فایل)؛
اگر روی سرور virtualenv مجزا دارد، پیشنهاد می‌شود در آینده با
`pip freeze > requirements.txt` روی همان venv نسخه‌ها pin شوند تا از
drift جلوگیری شود.

## توصیه برای آینده

مهاجرت به یک ابزارِ واقعیِ مدیریتِ بسته (npm برای فرانت‌اند، حداقل برای
پیگیریِ نسخه و CVE، حتی اگر نصبِ واقعی هنوز دستی/vendored بماند) — کارِ
بزرگ‌تری‌ست که نیاز به زمانِ جداگانه دارد و در این دورِ رفعِ ایراد انجام
نشد.
