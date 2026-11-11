// /assets/js/search-sort.js — مرتب‌سازی مشترک نتایج جستجو (فهرست‌هایی که هر ردیف «نام» دارد)
//
//   SearchSort.sort(rows, query, getName)   — درجا مرتب می‌کند و همان آرایه را برمی‌گرداند
//
// ترتیب: ۱) کل عبارت جستجو در نام  ۲) همهٔ کلمه‌ها در نام  ۳) بقیه (مثلا فقط در تلفن/کد پیدا شده)
// و داخل هر سطح: نام از الف تا ی (الفبای فارسی؛ «تست ۲» پیش از «تست ۱۰»). نام‌های کاملا یکسان
// ترتیب قبلی را نگه می‌دارند (sort پایدار است). هم‌منطق با sortBySearchRank در task-filters.js.
//
// ⚠️ فقط روی آرایهٔ فیلترشده صدا بزنید، نه روی آرایهٔ اصلی صفحه.
// 🔒 طبق قاعدهٔ فایل‌های JS مشترک: هیچ let/const سطح‌بالا — همه داخل IIFE.
(function () {
    var collator = (typeof Intl !== 'undefined' && Intl.Collator)
        ? new Intl.Collator('fa', { sensitivity: 'base', numeric: true })
        : null;

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

    function rank(name, q) {
        var n = normalize(name);
        if (!q) return 0;
        if (n.indexOf(q) !== -1) return 0;
        return q.split(' ').every(function (w) { return n.indexOf(w) !== -1; }) ? 1 : 2;
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
            meta.set(r, { rank: rank(n, q), name: n });
        });
        rows.sort(function (a, b) {
            var ma = meta.get(a), mb = meta.get(b);
            return (ma.rank - mb.rank) || compareNames(ma.name, mb.name);
        });
        return rows;
    }

    window.SearchSort = { sort: sort, normalize: normalize };
})();
