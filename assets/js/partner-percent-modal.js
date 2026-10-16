/**
 * PartnerPercentModal — پنجرهٔ «درصد سهم همکار» برای یک فاکتور (هر وضعیتی، حتی
 * تأییدشده). مشترک بین «فهرست فاکتورها» و «گزارش همکاران»؛ هر صفحه فقط ذخیره
 * را خودش انجام می‌دهد (با apiSend خودش) و بعد جدولش را دوباره می‌خواند.
 *
 *   PartnerPercentModal.open({
 *     title:          'فاکتور شماره ۱۲۳',
 *     partnerName:    'نام همکار',
 *     monthPercent:   3,          // درصد ماه صدور
 *     override:       5 | null,   // استثنای فعلی (null = درصد ماه)
 *     preTax:         1000000,    // مبلغ پیش از مالیات (برای پیش‌نمایش سود)
 *     profitRecorded: false,      // سود در شایگان ثبت شده؟ → هشدار + تیک تأیید
 *     save:    percentOrNull => Promise,   // PATCH .../partner-percent
 *     onSaved: () => {},                   // مثلا بارگذاری دوبارهٔ جدول
 *   });
 *
 *   PartnerPercentModal.parsePercent('۲٫۵') → 2.5   (null = نامعتبر یا خارج از ۰..۱۰۰)
 *
 * عمدا var + محافظ (نه const سراسری): اگر فایل دو بار در یک صفحه بیاید خطای
 * «تعریف دوباره» ندهد. وابستگی: bootstrap.bundle و faDigits (common-bundle).
 */
var PartnerPercentModal = window.PartnerPercentModal || (function () {

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    function fa(v) {
        return (typeof faDigits === 'function') ? faDigits(String(v)) : String(v);
    }

    function money(n) {
        return fa(Math.round(Number(n) || 0).toLocaleString('en-US'));
    }

    // «۲٫۵» / «2.5» / «2/5» / «۷٪» → عدد. num() صفحه‌ها «٫» را حذف می‌کند و ۲٫۵ را ۲۵
    // می‌کرد؛ برای درصد همیشه از همین تابع استفاده شود.
    function parsePercent(v) {
        var s = String(v == null ? '' : v)
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
            .trim().replace(/[٫\/]/g, '.').replace(/٪|%/g, '').trim();
        if (!/^\d+(\.\d+)?$/.test(s)) return null;
        var n = parseFloat(s);
        return (n >= 0 && n <= 100) ? n : null;
    }

    function style() {
        if (document.getElementById('ppm-style')) return;
        var s = document.createElement('style');
        s.id = 'ppm-style';
        s.textContent =
            '.ppm-modal .modal-header{background:#8e57fe;color:#fff;border:0;border-radius:.4rem .4rem 0 0;padding:.8rem 1rem}' +
            '.ppm-modal .modal-title{font-size:1rem;font-weight:700}' +
            '.ppm-modal .btn-close{filter:invert(1) grayscale(1) brightness(2)}' +
            '.ppm-modal .ppm-meta{font-size:.85rem;margin-bottom:.75rem}' +
            '.ppm-modal .ppm-opt{display:flex;align-items:center;gap:8px;margin:.35rem 0;font-size:.9rem}' +
            // رادیو/تیک بدون .form-check-input: custom.css آن کلاس را با !important به شکل سوئیچ ۳rem درمی‌آورد
            '.ppm-modal .ppm-opt input[type=radio],.ppm-modal .ppm-ack{width:16px;height:16px;margin:0;flex:0 0 auto;accent-color:#8e57fe;cursor:pointer}' +
            // custom.css: «.modal input[type=text]{width:100%}» قوی‌تر از .ppm-pct ساده است
            '.ppm-modal input.ppm-pct[type=text]{width:80px;padding:4px 6px;text-align:center}' +
            '.ppm-modal .ppm-preview{font-size:.85rem;margin-top:.6rem}' +
            '.ppm-modal .ppm-warn{font-size:.82rem;line-height:1.9}' +
            '.ppm-modal .ppm-err{font-size:.82rem}' +
            ':root[data-theme="dark"] .ppm-modal .ppm-warn{background:rgba(245,158,11,.14);border-color:rgba(245,158,11,.35);color:#fcd34d}' +
            // .text-muted پیش‌فرض روی زمینهٔ تیرهٔ مودال تقریبا ناخواناست
            ':root[data-theme="dark"] .ppm-modal .text-muted{color:#a8b0bd!important}';
        document.head.appendChild(s);
    }

    function open(o) {
        style();
        var hasOv = o.override !== null && o.override !== undefined;
        var el = document.createElement('div');
        el.className = 'modal fade ppm-modal';
        el.tabIndex = -1;
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '  <div class="modal-header">' +
            '    <h5 class="modal-title"><i class="bi bi-percent ms-2"></i>درصد سهم همکار</h5>' +
            '    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>' +
            '  </div>' +
            '  <div class="modal-body">' +
            '    <div class="ppm-meta text-muted">' + esc(o.title || '') +
            (o.partnerName ? ' — همکار: <b>' + esc(o.partnerName) + '</b>' : '') + '</div>' +
            (o.profitRecorded ?
                '    <div class="alert alert-warning ppm-warn py-2">سود این فاکتور قبلا در حسابداری شایگان ثبت شده است. ' +
                'اگر درصد را عوض کنید، رقم ثبت‌شده در شایگان را هم اصلاح کنید.' +
                '      <label class="d-flex align-items-center mt-1" style="gap:6px;cursor:pointer">' +
                '        <input type="checkbox" class="ppm-ack"> متوجه هستم؛ رقم شایگان را هم اصلاح می‌کنم' +
                '      </label></div>' : '') +
            '    <label class="ppm-opt"><input type="radio" name="ppm_mode" value="month"' + (hasOv ? '' : ' checked') + '>' +
            '      درصد ماه <span class="text-muted">(' + fa(o.monthPercent || 0) + '٪)</span></label>' +
            '    <label class="ppm-opt"><input type="radio" name="ppm_mode" value="custom"' + (hasOv ? ' checked' : '') + '>' +
            '      درصد اختصاصی این فاکتور' +
            '      <input type="text" class="form-control form-control-sm ppm-pct" inputmode="decimal" dir="ltr" autocomplete="off"' +
            '        aria-label="درصد اختصاصی" value="' + esc(hasOv ? fa(o.override) : '') + '"> ٪</label>' +
            '    <div class="ppm-preview">مبلغ سود همکار: <b class="ppm-profit">—</b> ریال</div>' +
            '    <div class="ppm-err text-danger mt-2" hidden></div>' +
            '  </div>' +
            '  <div class="modal-footer border-0 d-flex justify-content-end">' +
            '    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>' +
            '    <button type="button" class="btn btn-primary px-5 ppm-save">ذخیره</button>' +
            '  </div>' +
            '</div></div>';
        document.body.appendChild(el);

        var modal = new bootstrap.Modal(el);
        var inp = el.querySelector('.ppm-pct');
        var err = el.querySelector('.ppm-err');
        var saveBtn = el.querySelector('.ppm-save');
        var ack = el.querySelector('.ppm-ack');
        var mode = function () { return el.querySelector('input[name=ppm_mode]:checked').value; };

        // درصد انتخاب‌شده: null = درصد ماه؛ NaN = اختصاصی ولی نامعتبر
        function chosen() {
            if (mode() === 'month') return null;
            var p = parsePercent(inp.value);
            return p === null ? NaN : p;
        }

        function refresh() {
            var c = chosen();
            var pct = (c === null) ? (o.monthPercent || 0) : c;
            el.querySelector('.ppm-profit').textContent =
                Number.isNaN(pct) ? '—' : money((Math.max(0, o.preTax || 0) * pct) / 100);
            // غیرفعال نمی‌کنیم (فیلد disabled کلیک نمی‌گیرد و «کلیک = انتخاب اختصاصی» کار نمی‌کرد)؛ فقط کم‌رنگ
            inp.style.opacity = mode() === 'custom' ? '' : '.5';
            saveBtn.disabled = !!ack && !ack.checked;
        }

        el.querySelectorAll('input[name=ppm_mode]').forEach(function (r) {
            r.addEventListener('change', function () {
                if (mode() === 'custom') {
                    if (!inp.value.trim()) inp.value = fa(o.monthPercent || 0);
                    refresh();
                    inp.focus();
                    inp.select();
                } else {
                    refresh();
                }
            });
        });
        inp.addEventListener('input', refresh);
        // کلیک روی خود فیلد، گزینهٔ «اختصاصی» را هم انتخاب کند
        inp.addEventListener('focus', function () {
            var r = el.querySelector('input[name=ppm_mode][value=custom]');
            if (!r.checked) { r.checked = true; refresh(); }
        });
        if (ack) ack.addEventListener('change', refresh);

        saveBtn.addEventListener('click', function () {
            err.hidden = true;
            var c = chosen();
            if (Number.isNaN(c)) {
                err.textContent = 'درصد اختصاصی باید عددی بین ۰ تا ۱۰۰ باشد (مثلا ۲٫۵).';
                err.hidden = false;
                inp.focus();
                return;
            }
            saveBtn.disabled = true;
            Promise.resolve(o.save(c)).then(function () {
                modal.hide();
                if (window.showToast) showToast('درصد سهم همکار ذخیره شد', 'success');
                if (typeof o.onSaved === 'function') o.onSaved();
            }).catch(function (e) {
                err.textContent = (e && e.message) || 'خطا در ذخیره';
                err.hidden = false;
                saveBtn.disabled = false;
            });
        });

        el.addEventListener('hidden.bs.modal', function () { el.remove(); });
        // اولین فیلد قابل‌ویرایش فوکوس بگیرد (قاعدهٔ مودال‌های سایت): تیک تأیید شایگان
        // اگر هست، وگرنه فیلد درصد (اگر استثنا دارد) یا گزینهٔ انتخاب‌شده.
        // روی shown.bs.modal، نه setTimeout: بوت‌استرپ بعد از پایان انیمیشن خودش فوکوس
        // را به پنجره برمی‌گرداند و فوکوسِ زودتر را از بین می‌برد.
        el.addEventListener('shown.bs.modal', function () {
            var first = ack || (hasOv ? inp : el.querySelector('input[name=ppm_mode]:checked'));
            if (first) first.focus();
            if (first === inp) inp.select();
        });
        refresh();
        modal.show();
    }

    return { open: open, parsePercent: parsePercent };
})();
window.PartnerPercentModal = PartnerPercentModal;
