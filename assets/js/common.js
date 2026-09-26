/**
 * assets/js/common.js
 * توابع کوچک سراسری اپ که قبلا هرکدوم جداگانه در دوجین فایل تکرار می‌شدن.
 * هدف: از این به بعد، هر صفحه‌ی جدید همینجا رو صدا بزنه به‌جای کپی‌کردن
 * دوباره‌ی همین چندخط — دقیقا همون الگویی که باعث شد یک دور کامل این
 * جلسه صرف پیداکردن نقاط فراموش‌شده در ۱۲+ فایل بشه.
 *
 * ⚠️ نکته‌ی حیاتی: فقط toFa رو این‌جا به‌عنوان function سراسری تعریف می‌کنیم
 * (نه enTofaNumber/faNum). چون هر صفحه‌ای که این اسم‌ها رو صدا می‌زنه، از قبل
 * نسخه‌ی محلی خودش رو داره — بعضی‌هاشون با «const faNum = ...» (نه function).
 * اگه این‌جا هم به‌صورت سراسری با let/const alias می‌ذاشتیم، چون یک شناسه
 * نمی‌تونه هم‌زمان هم توسط let/const هم توسط چیز دیگه‌ای (حتی function)
 * در همون scope تعریف بشه، دقیقا همین باعث کرش کامل صفحه با
 * «Identifier has already been declared» می‌شد (چیزی که واقعا هم افتاد).
 * function toFa با function/var هم‌نام خودش تداخلی نداره (redeclare سالم)،
 * پس این یکی امنه؛ فقط همینو سراسری نگه می‌داریم.
 */

// تبدیل اعداد لاتین به فارسی — برای هر عددی که قراره به کاربر نمایش داده بشه
function toFa(n) {
    if (n === null || n === undefined || n === '') return '';
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

// نام‌های تاریخی همین «تبدیل رقم لاتین ← فارسی» که به‌صورت کپی محلی در
// ۲۰+ صفحه با اسم‌های مختلف تعریف شده بودند. این‌جا بدنه‌شان *دقیقا* برابر
// همان نسخه‌های محلی است (نه delegate به toFa) تا حذف کپی محلی صفحات
// اثباتا بدون تغییر رفتار باشد — از جمله رفتار لبه: X(null) → 'null'.
// بررسی شد: هیچ‌کدام در پروژه با const/let تعریف نشده‌اند → redeclare محلی
// سالم است. (faNum عمدا این‌جا نیست: چند صفحه «const faNum = ...» دارند.)
function toPersian(n)    { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function faDigits(s)     { return String(s == null ? '' : s).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function toFaDigits(n)   { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function toFaNum(n)      { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function enTofaNumber(n) { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

// نمایش تأخیر ساعتی (فقط کارهای روتین/فرآیندی ساعتی حساب می‌شن، بقیه
// روز کاری‌ان و اصلا از این تابع رد نمی‌شن). زیر ۲۴ ساعت: «۸۲ ساعت
// [پسوند]». از ۲۴ ساعت به بعد، به‌جای نمایش هم‌زمان عدد خام ساعت و
// شکسته‌شده‌ی روز/ساعت (که تکراری و شلوغ بود — مثلا «۷۴۲۷ ساعت (۳۰۹ روز
// و ۱۱ ساعت)»)، فقط شکسته‌شده نشون داده می‌شه: «۳۰۹ روز و ۱۱ ساعت
// [پسوند]» — دیگه اصلا بر اساس ساعت خام نیست.
function formatHourDelay(hours, suffix) {
    hours = Number(hours) || 0;
    const suf = suffix ? (' ' + suffix) : '';
    if (hours < 24) return toFa(hours) + ' ساعت' + suf;
    const days = Math.floor(hours / 24);
    const remHours = hours % 24;
    let text = toFa(days) + ' روز';
    if (remHours > 0) text += ' و ' + toFa(remHours) + ' ساعت';
    return text + suf;
}

// نام ماه‌های شمسی — مرجع یگانه (به‌جای ~۱۷ کپی محلی: months / persianMonths / J_MONTHS).
// صفحات: «const months = FA_MONTHS;» — بدون تغییر محل استفاده. (TimeSync.jMonthName(m)
// هم همین را می‌دهد ولی این آرایه برای index مستقیم مثل months[jm-1] دم‌دست‌تر است.)
var FA_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

// escape کردن رشته قبل از تزریق در innerHTML — جلوگیری از XSS
// بررسی شد: هرجا در پروژه از قبل «esc» تعریف شده، یا خودش function است
// (redeclare سالم، override می‌شه) یا داخل scope محلی/IIFE است (تداخلی
// با نسخه‌ی سراسری نداره) — پس این تعریف برای صفحاتی که هنوز escape
// محلی ندارن، امن اضافه می‌شه.
function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// escape برای مقادیری که داخل رشته‌ی جاوااسکریپت تک‌کوتیشن در یک
// attribute مثل onclick="fn('${x}')" قرار می‌گیرن. توجه: esc() معمولی
// این‌جا کافی نیست — چون &#39; که esc() تولید می‌کنه، توسط HTML parser
// قبل از این‌که JS parser بخونتش decode میشه و دوباره ' خام می‌شه (یعنی
// escape خنثی می‌شه). این تابع اول escape سطح رشته‌ی JS رو انجام می‌ده
// (backslash, quote, newline) که HTML entity decoding خرابش نمی‌کنه.
function escJsAttr(s) {
    return String(s ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/</g, '\\u003C')
        .replace(/>/g, '\\u003E');
}
