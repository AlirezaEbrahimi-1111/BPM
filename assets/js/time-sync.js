/* ============================================================
 * time-sync.js — همهٔ زمان‌ها از ساعت سرور خوانده شوند، نه از دستگاه کاربر.
 *
 * چرا: `new Date()` ساعت دستگاه است و `new Date("YYYY-MM-DD HH:MM:SS")`
 * رشتهٔ بدون آفست را با تایم‌زون دستگاه تفسیر می‌کند. اگر ساعت/تایم‌زون
 * کاربر غلط باشد، همهٔ «... پیش»ها و دسته‌بندی‌های تاریخی جابجا می‌شوند.
 *
 * این فایل:
 *   - یک‌بار با سرور هم‌کوک می‌شود (bootstrap درون‌خطی header یا /api/server-time.php)
 *   - TimeSync.serverNow() / serverNowMs()  → «اکنون» بر مبنای سرور
 *   - TimeSync.parseServerTime(str)         → Date درست (رشتهٔ خام = وقت تهران)
 *   - TimeSync.timeAgo(str)                 → «... پیش» یگانه (جایگزین کپی‌های محلی)
 *   - TimeSync.formatJalali(str)            → «YYYY/MM/DD» شمسی با آفست ثابت تهران
 *
 * قواعد اسکریپت مشترک: فقط var/function در سطح بالا، و محافظ در برابر لود دوباره.
 * ============================================================ */
(function () {
    if (window.TimeSync && window.TimeSync.__installed) return;

    var SKEW_MS = 0;         // serverEpochMs - clientEpochMs در لحظهٔ هم‌کوکی
    var TZ_OFFSET_MIN = 210; // آفست Asia/Tehran (+03:30) — پیش‌فرض تا پاسخ سرور
    var synced = false;

    function applyPayload(d) {
        if (!d || typeof d.epoch_ms !== 'number') return false;
        SKEW_MS = d.epoch_ms - Date.now();
        if (typeof d.offset_minutes === 'number') TZ_OFFSET_MIN = d.offset_minutes;
        synced = true;
        return true;
    }

    /* هم‌کوکی ناهمگام (backup، یا برای صفحاتی که ساعت‌ها باز می‌مانند) */
    function sync() {
        try {
            return fetch('/api/server-time.php', { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) { applyPayload(d); return d; })
                .catch(function () { /* آفلاین: با آخرین skew/آفست ادامه بده */ });
        } catch (e) {
            return Promise.resolve();
        }
    }

    function serverNowMs() { return Date.now() + SKEW_MS; }
    function serverNow() { return new Date(serverNowMs()); }

    function pad2(n) { n = String(n); return n.length < 2 ? '0' + n : n; }

    var FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    function toFaDigits(x) {
        return String(x).replace(/[0-9]/g, function (c) { return FA_DIGITS[+c]; });
    }

    /* رشتهٔ تایم‌استمپ سرور → Date
     *  - ISO دارای Z یا آفست  → مستقیم
     *  - "YYYY-MM-DD HH:MM:SS"  → به‌عنوان وقت تهران (نه محلی دستگاه) */
    function parseServerTime(v) {
        if (v === null || v === undefined || v === '') return null;
        if (v instanceof Date) return isNaN(v.getTime()) ? null : v;
        if (typeof v === 'number') return new Date(v);
        var s = String(v).trim();
        if (/([zZ]|[+\-]\d{2}:?\d{2})$/.test(s)) {
            var iso = new Date(s.replace(' ', 'T'));
            return isNaN(iso.getTime()) ? null : iso;
        }
        var m = s.replace('T', ' ').match(/^(\d{4})-(\d{2})-(\d{2})[ ](\d{2}):(\d{2})(?::(\d{2}))?/);
        if (!m) {
            var d = new Date(s);
            return isNaN(d.getTime()) ? null : d;
        }
        var sign = TZ_OFFSET_MIN < 0 ? '-' : '+';
        var abs = Math.abs(TZ_OFFSET_MIN);
        var off = sign + pad2(Math.floor(abs / 60)) + ':' + pad2(abs % 60);
        return new Date(m[1] + '-' + m[2] + '-' + m[3] + 'T' +
            m[4] + ':' + m[5] + ':' + (m[6] || '00') + off);
    }

    /* «... پیش» — نسخهٔ یگانه، مبنا: ساعت سرور.
       بازه‌ها superset از همهٔ نسخه‌های محلی قبلی است. */
    function timeAgo(v) {
        var date = parseServerTime(v);
        if (!date) return '';
        var min = Math.floor((serverNowMs() - date.getTime()) / 60000);
        if (min < 1) return 'همین الان';
        if (min < 60) return toFaDigits(min) + ' دقیقه پیش';
        var hour = Math.floor(min / 60);
        if (hour < 24) return toFaDigits(hour) + ' ساعت پیش';
        var day = Math.floor(hour / 24);
        if (day === 1) return 'دیروز';
        if (day < 7) return toFaDigits(day) + ' روز پیش';
        if (day < 30) return toFaDigits(Math.floor(day / 7)) + ' هفته پیش';
        if (day < 365) return toFaDigits(Math.floor(day / 30)) + ' ماه پیش';
        return formatJalali(date);
    }

    /* گرگوری → جلالی (همان الگوریتم includes/JalaliHelper.php) */
    function gregorianToJalali(gy, gm, gd) {
        var gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var jy = (gy <= 1600) ? 0 : 979;
        gy -= (gy <= 1600) ? 621 : 1600;
        var gy2 = (gm > 2) ? (gy + 1) : gy;
        var days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
            Math.floor((gy2 + 399) / 400) - 80 + gd + gdm[gm - 1];
        jy += 33 * Math.floor(days / 12053);
        days %= 12053;
        jy += 4 * Math.floor(days / 1461);
        days %= 1461;
        if (days > 365) { jy += Math.floor((days - 1) / 365); days = (days - 1) % 365; }
        var jm, jd;
        if (days < 186) { jm = 1 + Math.floor(days / 31); jd = 1 + (days % 31); }
        else { jm = 7 + Math.floor((days - 186) / 30); jd = 1 + ((days - 186) % 30); }
        return [jy, jm, jd];
    }

    /* تاریخ مطلق شمسی با آفست ثابت تهران (نه تایم‌زون دستگاه) */
    function tehranParts(v) {
        var date = parseServerTime(v);
        if (!date) return null;
        var shifted = new Date(date.getTime() + TZ_OFFSET_MIN * 60000);
        return {
            y: shifted.getUTCFullYear(), mo: shifted.getUTCMonth() + 1, d: shifted.getUTCDate(),
            h: shifted.getUTCHours(), mi: shifted.getUTCMinutes(), s: shifted.getUTCSeconds()
        };
    }

    function formatJalali(v) {
        var p = tehranParts(v);
        if (!p) return '';
        var j = gregorianToJalali(p.y, p.mo, p.d);
        return toFaDigits(j[0]) + '/' + toFaDigits(pad2(j[1])) + '/' + toFaDigits(pad2(j[2]));
    }

    function formatJalaliTime(v) {
        var p = tehranParts(v);
        if (!p) return '';
        var j = gregorianToJalali(p.y, p.mo, p.d);
        return toFaDigits(j[0]) + '/' + toFaDigits(pad2(j[1])) + '/' + toFaDigits(pad2(j[2])) +
            ' ' + toFaDigits(pad2(p.h)) + ':' + toFaDigits(pad2(p.mi));
    }

    var JMONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /* «۵ شهریور ۱۴۰۵» */
    function formatJalaliLong(v) {
        var p = tehranParts(v);
        if (!p) return '';
        var j = gregorianToJalali(p.y, p.mo, p.d);
        return toFaDigits(j[2]) + ' ' + JMONTHS[j[1] - 1] + ' ' + toFaDigits(j[0]);
    }

    /* فقط ساعت: «۱۰:۱۶» */
    function formatTimeOnly(v) {
        var p = tehranParts(v);
        if (!p) return '';
        return toFaDigits(pad2(p.h)) + ':' + toFaDigits(pad2(p.mi));
    }

    /* نام روز هفته به وقت تهران — هفته از شنبه شروع می‌شود */
    var WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    function weekdayName(v) {
        var p = tehranParts(v);
        if (!p) return '';
        // Date.UTC(...).getUTCDay(): ۰=یکشنبه ... ۶=شنبه — دقیقا هم‌تراز WEEKDAYS
        var dow = new Date(Date.UTC(p.y, p.mo - 1, p.d)).getUTCDay();
        return WEEKDAYS[dow];
    }

    /* «چهارشنبه ۱۴۰۵/۰۴/۲۴» — برای جداکننده‌های تاریخ قدیمی‌تر از یک هفته */
    function formatJalaliWithWeekday(v) {
        var wd = weekdayName(v);
        var d = formatJalali(v);
        return wd && d ? (wd + ' ' + d) : (wd || d);
    }

    /* ═══ کمکی‌های «امروز سرور» برای منطق دسته‌بندی (فاز ۲) ═══ */

    function serverParts() { return tehranParts(serverNow()); }

    /* 'YYYY-MM-DD' میلادی امروز، به وقت تهران (هم‌فرمت با تاریخ موعد کارها) */
    function serverToday() {
        var p = serverParts();
        return p.y + '-' + pad2(p.mo) + '-' + pad2(p.d);
    }

    /* [jy, jm, jd] امروز به وقت تهران */
    function serverJalali() {
        var p = serverParts();
        return gregorianToJalali(p.y, p.mo, p.d);
    }

    /* 'YYYY-MM-DD' میلادی یک تایم‌استمپ، به وقت تهران */
    function dateOnly(v) {
        var p = tehranParts(v);
        if (!p) return '';
        return p.y + '-' + pad2(p.mo) + '-' + pad2(p.d);
    }

    /* اختلاف روز تقویمی نسبت به امروز سرور (مثبت = آینده، ۰ = امروز، ‑۱ = دیروز) */
    function daysFromToday(v) {
        var p = tehranParts(v);
        if (!p) return NaN;
        var t = serverParts();
        var a = Math.floor(Date.UTC(p.y, p.mo - 1, p.d) / 86400000);
        var b = Math.floor(Date.UTC(t.y, t.mo - 1, t.d) / 86400000);
        return a - b;
    }

    window.TimeSync = {
        __installed: true,
        sync: sync,
        isSynced: function () { return synced; },
        skewMs: function () { return SKEW_MS; },
        tzOffsetMinutes: function () { return TZ_OFFSET_MIN; },
        serverNow: serverNow,
        serverNowMs: serverNowMs,
        serverParts: serverParts,
        serverToday: serverToday,
        serverJalali: serverJalali,
        dateOnly: dateOnly,
        daysFromToday: daysFromToday,
        parseServerTime: parseServerTime,
        timeAgo: timeAgo,
        formatJalali: formatJalali,
        formatJalaliTime: formatJalaliTime,
        formatJalaliLong: formatJalaliLong,
        formatTimeOnly: formatTimeOnly,
        weekdayName: weekdayName,
        formatJalaliWithWeekday: formatJalaliWithWeekday,
        gregorianToJalali: gregorianToJalali,
        jMonthName: function (m) { return JMONTHS[m - 1] || ''; },
        toFaDigits: toFaDigits
    };

    /* میان‌برهای سراسری — تا جایگزینی کپی‌های محلی کم‌دردسر باشد */
    window.timeAgo = timeAgo;
    window.serverNow = serverNow;

    /* هم‌کوکی اولیه: اگر header مقدار درون‌خطی داده، همان لحظه اعمال کن
       (بدون round-trip)؛ بعد یک sync پشتیبان هم بزن. */
    if (window.__SERVER_TIME__ && applyPayload(window.__SERVER_TIME__)) {
        // skew از قبل ست شد
    } else {
        sync();
    }
})();
