/*
 * fa-paste-fix.js — اصلاح متن فارسیِ چسبانده‌شده (درخواست ۱۴۰۵/۰۸/۰۲). سراسری: header.php
 * ------------------------------------------------------------------
 * کپی از شایگان (برنامهٔ ویندوزی غیریونیکد) دو مشکل داشت:
 *
 *  ۱) «انگلیسی درست، فارسی خراب»: شایگان متن را با رمزگذاری قدیمی Windows-1256 در
 *     کلیپ‌بورد می‌گذارد؛ اگر «زبان برنامه‌های غیریونیکد» ویندوز انگلیسی باشد، همان بایت‌ها
 *     با Windows-1252 خوانده می‌شوند و «سلام» می‌شود «ÓáÇã». حروف انگلیسی در هر دو یکسان‌اند
 *     و سالم می‌مانند. این‌جا همان نگاشت برعکس می‌شود (جدول ۱۲۵۲→۱۲۵۶ پایین).
 *     فقط وقتی انجام می‌شود که متن هیچ حرف فارسی/عربی نداشته باشد و بیشترِ نویسه‌های
 *     غیرانگلیسی‌اش در این جدول باشند — تا متن لاتین واقعی (مثلا é) دست نخورد.
 *
 *  ۲) «ي/ى/ك» عربی → «ی/ک» فارسی، و شکل‌های چسبیدهٔ عربی (Presentation Forms، مثل «ﻛﻴ»)
 *     → حرف معمولی. سرور هم هنگام ذخیره همین تبدیل را می‌کند (crm-service/helpers.go::faLetters).
 *
 * روی input متنی و textarea؛ فیلد رمز/ایمیل/عدد دست نمی‌خورد. با execCommand('insertText')
 * جایگذاری می‌شود تا Ctrl+Z و رویداد input (فرم‌ها، پیش‌نویس خودکار) مثل قبل کار کنند.
 *
 * فقط var/function و گارد ضدِ دوبار لود — قاعدهٔ فایل‌های مشترک.
 *   FaPasteFix.fix(text) → { text, repaired }   // برای تست/استفادهٔ دستی
 */
(function (w) {
    'use strict';
    if (w.FaPasteFix) return;

    // نویسهٔ Windows-1252 → نویسهٔ Windows-1256 با همان بایت (فقط آن‌هایی که فرق دارند)
    var CP1252_TO_1256 = {
        '\u0081': 'پ', 'Š': 'ٹ', '\u008d': 'چ', 'Ž': 'ژ', '\u008f': 'ڈ',
        '\u0090': 'گ', '˜': 'ک', 'š': 'ڑ', '\u009d': '‌', 'ž': '‍',
        'Ÿ': 'ں', '¡': '،', 'ª': 'ھ', 'º': '؛', '¿': '؟',
        'À': 'ہ', 'Á': 'ء', 'Â': 'آ', 'Ã': 'أ', 'Ä': 'ؤ',
        'Å': 'إ', 'Æ': 'ئ', 'Ç': 'ا', 'È': 'ب', 'É': 'ة',
        'Ê': 'ت', 'Ë': 'ث', 'Ì': 'ج', 'Í': 'ح', 'Î': 'خ',
        'Ï': 'د', 'Ð': 'ذ', 'Ñ': 'ر', 'Ò': 'ز', 'Ó': 'س',
        'Ô': 'ش', 'Õ': 'ص', 'Ö': 'ض', 'Ø': 'ط', 'Ù': 'ظ',
        'Ú': 'ع', 'Û': 'غ', 'Ü': 'ـ', 'Ý': 'ف', 'Þ': 'ق',
        'ß': 'ك', 'á': 'ل', 'ã': 'م', 'ä': 'ن', 'å': 'ه',
        'æ': 'و', 'ì': 'ى', 'í': 'ي', 'ð': 'ً', 'ñ': 'ٌ',
        'ò': 'ٍ', 'ó': 'َ', 'õ': 'ُ', 'ö': 'ِ', 'ø': 'ّ',
        'ú': 'ْ', 'ý': '‎', 'þ': '‏', 'ÿ': 'ے'
    };

    var ARABIC_SCRIPT = /[؀-ۿ]/;
    var PRESENTATION = /[ﭐ-﷿ﹰ-﻿]/g;

    function repairMojibake(s) {
        if (ARABIC_SCRIPT.test(s)) return null; // خودش فارسی درست دارد
        var hits = 0, nonAscii = 0;
        for (var i = 0; i < s.length; i++) {
            var c = s[i];
            if (c.charCodeAt(0) < 0x80) continue;
            nonAscii++;
            if (CP1252_TO_1256[c]) hits++;
        }
        if (hits < 2 || hits * 2 < nonAscii) return null;
        var out = '';
        for (var j = 0; j < s.length; j++) out += CP1252_TO_1256[s[j]] || s[j];
        return out;
    }

    function faLetters(s) {
        s = s.replace(PRESENTATION, function (c) { return c.normalize ? c.normalize('NFKC') : c; });
        return s.replace(/[يى]/g, 'ی').replace(/ك/g, 'ک');
    }

    function fix(text) {
        var t = String(text == null ? '' : text);
        var r = repairMojibake(t);
        var out = faLetters(r != null ? r : t);
        return { text: out, repaired: r != null };
    }

    var SKIP_TYPES = { password: 1, email: 1, number: 1, url: 1, file: 1, checkbox: 1, radio: 1 };

    document.addEventListener('paste', function (e) {
        var el = e.target;
        if (!el || !(el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && !SKIP_TYPES[(el.type || 'text').toLowerCase()]))) return;
        if (el.readOnly || el.disabled || !e.clipboardData) return;
        var raw = e.clipboardData.getData('text/plain');
        if (!raw) return;
        var r = fix(raw);
        if (r.text === raw) return;
        e.preventDefault();
        var ok = false;
        try { ok = document.execCommand('insertText', false, r.text); } catch (err) { ok = false; }
        if (!ok) {
            var a = el.selectionStart != null ? el.selectionStart : el.value.length;
            var b = el.selectionEnd != null ? el.selectionEnd : el.value.length;
            el.setRangeText(r.text, a, b, 'end');
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (r.repaired && typeof w.showToast === 'function') {
            w.showToast('متن فارسیِ کپی‌شده (از برنامه‌ای مثل شایگان) اصلاح شد', 'info');
        }
    }, true);

    w.FaPasteFix = { fix: fix };
})(window);
