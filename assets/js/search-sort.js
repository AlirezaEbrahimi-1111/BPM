// /assets/js/search-sort.js — مرتب‌سازی مشترک نتایج جستجو (فهرست‌هایی که هر ردیف «نام» دارد)
//
//   SearchSort.sort(rows, query, getName)   — درجا مرتب می‌کند و همان آرایه را برمی‌گرداند
//   SearchSort.rank(name, query)            — سطح شباهت یک نام (۰ بهترین)
//
// سطح‌ها:
//   ۰) کل عبارت به‌صورت «کلمهٔ کامل» در نام  — «شرکت» در «واریز حواله شرکت برای رضا»
//   ۱) کل عبارت، ولی چسبیده به پسوند/کلمهٔ دیگر — «شرکت» در «شرکت‌ها» یا «شرکت ها»
//   ۲) همهٔ کلمه‌های جستجو در نام (به هر ترتیب)
//   ۳) بقیه (مثلا فقط در تلفن/کد/توضیحات پیدا شده)
// داخل هر سطح: نام از الف تا ی (الفبای فارسی؛ «تست ۲» پیش از «تست ۱۰»). نام‌های کاملا یکسان
// ترتیب قبلی را نگه می‌دارند (sort پایدار است). هم‌منطق با اپ (lib/core/utils/task_search.dart).
//
// ⚠️ فقط روی آرایهٔ فیلترشده صدا بزنید، نه روی آرایهٔ اصلی صفحه.
// 🔒 طبق قاعدهٔ فایل‌های JS مشترک: هیچ let/const سطح‌بالا — همه داخل IIFE.
(function () {
    var collator = (typeof Intl !== 'undefined' && Intl.Collator)
        ? new Intl.Collator('fa', { sensitivity: 'base', numeric: true })
        : null;

    // پسوندهای جمع که با فاصله یا نیم‌فاصله به کلمه می‌چسبند: «شرکت ها» ≠ کلمهٔ کامل «شرکت»
    var PLURAL_SUFFIXES = ['ها', 'های', 'هایی', 'هایم', 'هایت', 'هایش', 'هایمان', 'هایتان', 'هایشان'];

    /** یکسان‌سازی: رقم فارسی/عربی → لاتین، ی/ک عربی → فارسی، نیم‌فاصله → فاصله، حروف کوچک */
    function normalize(text) {
        return String(text == null ? '' : text)
            .replace(/[۰-۹]/g, function (d) { return d.charCodeAt(0) - 1776; })
            .replace(/[٠-٩]/g, function (d) { return d.charCodeAt(0) - 1632; })
            .replace(/ي/g, 'ی').replace(/ك/g, 'ک')
            .replace(/‌/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    /** عبارت (نرمال‌شده) جایی در متن (نرمال‌شده) به‌صورت کلمهٔ کامل و بدون پسوند جمع بعدش آمده؟ */
    function hasWholeWord(text, phrase) {
        var t = ' ' + text + ' ', p = ' ' + phrase + ' ', from = 0, i;
        while ((i = t.indexOf(p, from)) !== -1) {
            var rest = t.slice(i + p.length);
            var next = rest.split(' ')[0];
            if (PLURAL_SUFFIXES.indexOf(next) === -1) return true;
            from = i + 1;
        }
        return false;
    }

    function rankNormalized(n, q) {
        if (!q) return 0;
        if (n.indexOf(q) !== -1) return hasWholeWord(n, q) ? 0 : 1;
        return q.split(' ').every(function (w) { return n.indexOf(w) !== -1; }) ? 2 : 3;
    }

    function rank(name, query) {
        return rankNormalized(normalize(name), normalize(query));
    }

    function compareNames(a, b) {
        return collator ? collator.compare(a, b) : (a < b ? -1 : a > b ? 1 : 0);
    }

    function sort(rows, query, getName) {
        var q = normalize(query);
        if (!q || !rows || rows.length < 2) return rows;
        getName = getName || function (r) { return r && r.name; };
        var meta = new Map();
        rows.forEach(function (r) {
            var n = normalize(getName(r));
            meta.set(r, { rank: rankNormalized(n, q), name: n });
        });
        rows.sort(function (a, b) {
            var ma = meta.get(a), mb = meta.get(b);
            return (ma.rank - mb.rank) || compareNames(ma.name, mb.name);
        });
        return rows;
    }

    window.SearchSort = { sort: sort, rank: rank, normalize: normalize };
})();
