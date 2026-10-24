/**
 * InvoicePayments — واریزهای فاکتور (تسویهٔ یک‌جا یا چندمرحله‌ای). مشترک بین «فهرست
 * فاکتورها» و «گزارش همکاران» (درخواست ۱۴۰۵/۰۸/۰۲). سرور: crm-service/invoice_payments.go
 *
 *   InvoicePayments.cellHtml(row, 'openPayments(12)')
 *       → بج وضعیت تسویه + زیرش «واریز: X — مانده: Y» (برای ستون «تسویه» جدول).
 *         row باید settlement_status، paid_amount، payment_count و مبلغ فاکتور
 *         (total_amount یا invoice_amount) داشته باشد.
 *
 *   InvoicePayments.open({
 *     invoiceId: 12,
 *     title:     'فاکتور شماره ۱۲۳ — نام مشتری',
 *     api:       (method, path, body) => Promise<json>,  // path نسبت به /crm/api، مثل apiSend صفحه
 *     onChanged: summary => {},   // بعد از هر ثبت/حذف: {settlement_status, paid_amount, payment_count, ...}
 *   });
 *
 * وضعیت تسویه دیگر دستی انتخاب نمی‌شود؛ سرور از جمع واریزها حسابش می‌کند.
 * فاکتور قدیمیِ «تسویه شده» بدون واریز = تسویهٔ پیش از این قابلیت (مانده صفر).
 *
 * عمدا var + محافظ (نه const سراسری) — قاعدهٔ فایل‌های مشترک. وابستگی: bootstrap.bundle،
 * persian-datepicker.js (+css)، money-shortcut.js (اختیاری: میانبر * روی مبلغ).
 */
var InvoicePayments = window.InvoicePayments || (function () {

    var LABEL = { unsettled: 'تسویه نشده', partial: 'بخشی تسویه شده', settled: 'تسویه شده' };
    var METHOD = { cash: 'نقد', card: 'کارت', cheque: 'چک', transfer: 'حواله' };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fa(v) {
        return String(v).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    }

    function money(n) {
        return fa(Math.round(Number(n) || 0).toLocaleString('en-US'));
    }

    function toInt(v) {
        var s = String(v == null ? '' : v)
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
            .replace(/[^\d]/g, '');
        return s ? parseInt(s, 10) : 0;
    }

    function jDate(g) {
        if (!g) return '—';
        if (typeof convertToJalali === 'function') {
            var j = convertToJalali(g);
            if (j) return fa(j);
        }
        try {
            return new Date(g + 'T12:00:00').toLocaleDateString('fa-IR', { timeZone: 'Asia/Tehran' });
        } catch (e) {
            return g;
        }
    }

    // ── ستون «تسویه» جدول ──
    function cellHtml(row, onclickJs) {
        var st = row.settlement_status || 'unsettled';
        var total = Number(row.total_amount != null ? row.total_amount : row.invoice_amount) || 0;
        var paid = Number(row.paid_amount) || 0;
        var count = Number(row.payment_count) || 0;
        var sub = '';
        if (count > 0 && st !== 'settled') {
            sub = 'واریز: ' + money(paid) + ' — مانده: ' + money(Math.max(0, total - paid));
        } else if (count === 0 && st === 'partial') {
            sub = 'مبلغ واریز ثبت نشده';
        }
        var tip = row.status === 'cancelled' ? 'فاکتور باطل‌شده' :
            (count ? 'برای دیدن واریزها کلیک کنید' : 'برای ثبت واریز کلیک کنید');
        return '<span class="ipm-cell"' + (onclickJs ? ' role="button" onclick="' + esc(onclickJs) + '"' : '') +
            ' title="' + tip + '"><span class="pm-badge ' + st + '">' + (LABEL[st] || st) + '</span>' +
            (sub ? '<span class="ipm-sub' + (count ? '' : ' warn') + '">' + sub + '</span>' : '') + '</span>';
    }

    function style() {
        if (document.getElementById('ipm-style')) return;
        var s = document.createElement('style');
        s.id = 'ipm-style';
        s.textContent =
            // سلول جدول: بج + یک خط ریز زیرش، در همان ارتفاع ردیف
            '.ipm-cell{display:inline-flex;flex-direction:column;align-items:flex-start;gap:2px;line-height:1.25;cursor:pointer;vertical-align:middle}' +
            '.ipm-cell .pm-badge{padding:3px 8px}' +
            '.ipm-sub{font-size:.68rem;color:#6b7280;white-space:nowrap}' +
            '.ipm-sub.warn{color:#b45309}' +
            ':root[data-theme="dark"] .ipm-sub{color:#a8b0bd}' +
            ':root[data-theme="dark"] .ipm-sub.warn{color:#fcd34d}' +
            // پنجره
            '.ipm-modal .modal-header{background:#8e57fe;color:#fff;border:0;border-radius:.4rem .4rem 0 0;padding:.8rem 1rem}' +
            '.ipm-modal .modal-title{font-size:1rem;font-weight:700}' +
            '.ipm-modal .btn-close{filter:invert(1) grayscale(1) brightness(2)}' +
            '.ipm-modal .ipm-meta{font-size:.85rem;margin-bottom:.6rem}' +
            '.ipm-sum{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:.8rem}' +
            '.ipm-sum>div{border-radius:10px;padding:8px 10px;background:rgba(142,87,254,.07);font-size:.8rem}' +
            '.ipm-sum b{display:block;font-size:.95rem;margin-top:2px}' +
            '.ipm-sum .paid b{color:#0f7a57}.ipm-sum .rem b{color:#b45309}' +
            '.ipm-table{width:100%;font-size:.82rem;margin-bottom:.8rem}' +
            '.ipm-table th{font-weight:600;color:#6b7280;border-bottom:1px solid rgba(0,0,0,.08);padding:6px 4px;white-space:nowrap}' +
            '.ipm-table td{padding:6px 4px;border-bottom:1px solid rgba(0,0,0,.05);vertical-align:middle}' +
            '.ipm-table tr:hover td{background:rgba(142,87,254,.12)}' +
            '.ipm-table .ipm-del{border:0;background:none;color:#dc2626;padding:2px 6px;border-radius:6px}' +
            '.ipm-table .ipm-del:hover{background:rgba(220,38,38,.1)}' +
            '.ipm-empty{font-size:.82rem;color:#6b7280;text-align:center;padding:10px 0 14px}' +
            '.ipm-form{border-top:1px dashed rgba(0,0,0,.12);padding-top:.8rem}' +
            '.ipm-form .ipm-grid{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:8px}' +
            '.ipm-form .form-label{font-size:.8rem;margin-bottom:3px}' +
            '.ipm-modal input.ipm-amount[type=text]{text-align:center}' +
            '.ipm-err{font-size:.82rem}' +
            '@media (max-width:576px){.ipm-form .ipm-grid,.ipm-sum{grid-template-columns:1fr}}' +
            ':root[data-theme="dark"] .ipm-sum>div{background:rgba(142,87,254,.14)}' +
            ':root[data-theme="dark"] .ipm-sum .paid b{color:#34d399}:root[data-theme="dark"] .ipm-sum .rem b{color:#fcd34d}' +
            ':root[data-theme="dark"] .ipm-table th{color:#a8b0bd;border-color:rgba(255,255,255,.1)}' +
            ':root[data-theme="dark"] .ipm-table td{border-color:rgba(255,255,255,.06)}' +
            ':root[data-theme="dark"] .ipm-table tr:hover td{background:rgba(142,87,254,.18)}' +
            ':root[data-theme="dark"] .ipm-form{border-color:rgba(255,255,255,.15)}' +
            ':root[data-theme="dark"] .ipm-empty,:root[data-theme="dark"] .ipm-modal .text-muted{color:#a8b0bd!important}';
        document.head.appendChild(s);
    }

    var DATEPICKER =
        '<div class="persian-datepicker-wrapper">' +
        '  <input type="text" class="persian-datepicker-input form-control ipm-date" readonly>' +
        '  <div class="persian-datepicker">' +
        '    <div class="datepicker-header">' +
        '      <button type="button" class="datepicker-nav" data-action="prev">►</button>' +
        '      <span class="datepicker-current"></span>' +
        '      <button type="button" class="datepicker-nav" data-action="next">◄</button>' +
        '    </div>' +
        '    <div class="datepicker-weekdays">' +
        ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'].map(function (d) { return '<div class="datepicker-weekday">' + d + '</div>'; }).join('') +
        '    </div>' +
        '    <div class="datepicker-days"></div>' +
        '    <button type="button" class="datepicker-today-btn">امروز</button>' +
        '  </div>' +
        '</div>';

    function open(o) {
        style();
        var el = document.createElement('div');
        el.className = 'modal fade ipm-modal';
        el.tabIndex = -1;
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered modal-lg">' +
            '<div class="modal-content">' +
            '  <div class="modal-header">' +
            '    <h5 class="modal-title"><i class="bi bi-cash-coin ms-2"></i>واریزها و تسویه</h5>' +
            '    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>' +
            '  </div>' +
            '  <div class="modal-body">' +
            '    <div class="ipm-meta text-muted">' + esc(o.title || '') + ' <span class="ipm-st"></span></div>' +
            '    <div class="ipm-sum">' +
            '      <div>مبلغ فاکتور<b class="ipm-total">—</b></div>' +
            '      <div class="paid">واریز شده<b class="ipm-paid">—</b></div>' +
            '      <div class="rem">مانده<b class="ipm-rem">—</b></div>' +
            '    </div>' +
            '    <div class="ipm-list"><div class="ipm-empty">در حال بارگذاری…</div></div>' +
            '    <div class="ipm-form" hidden>' +
            '      <div class="ipm-grid">' +
            '        <div><label class="form-label">مبلغ واریز (ریال) <span class="text-danger">*</span></label>' +
            '          <input type="text" class="form-control ipm-amount" inputmode="numeric" autocomplete="off" data-money dir="ltr"></div>' +
            '        <div><label class="form-label">تاریخ واریز</label>' + DATEPICKER + '</div>' +
            '        <div><label class="form-label">روش پرداخت</label>' +
            '          <select class="form-select ipm-method"><option value="">— انتخاب نشده —</option>' +
            Object.keys(METHOD).map(function (k) { return '<option value="' + k + '">' + METHOD[k] + '</option>'; }).join('') +
            '          </select></div>' +
            '      </div>' +
            '      <div class="mt-2"><label class="form-label">توضیح (اختیاری)</label>' +
            '        <input type="text" class="form-control ipm-note" maxlength="500" placeholder="مثلا شمارهٔ چک یا پیگیری"></div>' +
            '      <div class="ipm-err text-danger mt-2" hidden></div>' +
            '    </div>' +
            '    <div class="ipm-closed text-muted small" hidden></div>' +
            '  </div>' +
            '  <div class="modal-footer border-0 d-flex justify-content-between">' +
            '    <button type="button" class="btn btn-outline-success ipm-full" hidden><i class="bi bi-check2-all ms-1"></i>تسویه کامل (کل مانده)</button>' +
            '    <div class="d-flex gap-2 ms-auto">' +
            '      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>' +
            '      <button type="button" class="btn btn-primary px-4 ipm-add" hidden>ثبت واریز</button>' +
            '    </div>' +
            '  </div>' +
            '</div></div>';
        document.body.appendChild(el);

        var modal = new bootstrap.Modal(el);
        var q = function (sel) { return el.querySelector(sel); };
        var amount = q('.ipm-amount'), dateIn = q('.ipm-date'), method = q('.ipm-method'), note = q('.ipm-note');
        var err = q('.ipm-err'), addBtn = q('.ipm-add'), fullBtn = q('.ipm-full');
        var state = null, busy = false;

        function showErr(m) {
            err.textContent = m || '';
            err.hidden = !m;
        }

        // قالب‌بندی زندهٔ مبلغ با جداکنندهٔ هزارگان
        amount.addEventListener('input', function () {
            var n = toInt(amount.value);
            amount.value = n ? money(n) : '';
        });

        function setToday(g) {
            if (!g || dateIn.getAttribute('data-date')) return;
            dateIn.setAttribute('data-date', g);
            dateIn.value = (typeof convertToJalali === 'function') ? convertToJalali(g) : jDate(g);
        }

        function render(d) {
            state = d;
            q('.ipm-total').textContent = money(d.total_amount);
            q('.ipm-paid').textContent = money(d.paid_amount);
            q('.ipm-rem').textContent = money(d.remaining_amount);
            var st = d.settlement_status || 'unsettled';
            q('.ipm-st').innerHTML = '<span class="pm-badge ' + st + '">' + (LABEL[st] || st) + '</span>';

            var items = d.items || [];
            if (!items.length) {
                q('.ipm-list').innerHTML = '<div class="ipm-empty">' +
                    (st === 'settled' ? 'این فاکتور پیش از ثبت واریزها «تسویه شده» علامت خورده و مبلغ واریزی برایش ثبت نشده است.' :
                        st === 'partial' ? 'این فاکتور پیش از ثبت واریزها «بخشی تسویه شده» علامت خورده؛ مبالغ واریزشده را ثبت کنید.' :
                            'هنوز واریزی ثبت نشده است.') + '</div>';
            } else {
                q('.ipm-list').innerHTML = '<table class="ipm-table"><thead><tr>' +
                    '<th>#</th><th>تاریخ واریز</th><th>مبلغ (ریال)</th><th>روش</th><th>توضیح</th><th>ثبت‌کننده</th><th></th>' +
                    '</tr></thead><tbody>' + items.map(function (p, i) {
                        return '<tr><td>' + fa(i + 1) + '</td><td>' + jDate(p.pay_date) + '</td>' +
                            '<td><b>' + money(p.amount) + '</b></td><td>' + (METHOD[p.method] || '—') + '</td>' +
                            '<td>' + (esc(p.note) || '—') + '</td>' +
                            '<td title="' + esc(fa(p.created_at || '')) + '">' + (esc(p.created_by_name) || '—') + '</td>' +
                            '<td><button type="button" class="ipm-del" title="حذف این واریز" data-id="' + p.id + '"><i class="bi bi-trash"></i></button></td></tr>';
                    }).join('') + '</tbody></table>';
            }

            var cancelled = d.invoice_status === 'cancelled';
            var canAdd = !cancelled && d.total_amount - d.paid_amount > 0;
            q('.ipm-form').hidden = !canAdd;
            addBtn.hidden = !canAdd;
            fullBtn.hidden = !canAdd;
            var closed = q('.ipm-closed');
            closed.hidden = canAdd;
            closed.textContent = cancelled ? 'فاکتور باطل شده است؛ واریز تازه ثبت نمی‌شود.' :
                'کل مبلغ فاکتور واریز شده است.';
            el.querySelectorAll('.ipm-del').forEach(function (b) { b.hidden = cancelled; });
            setToday(d.today);
        }

        function changed(d) {
            render(d);
            if (typeof o.onChanged === 'function') o.onChanged(d);
        }

        function load() {
            return o.api('GET', '/inv/invoices/' + o.invoiceId + '/payments').then(render).catch(function (e) {
                q('.ipm-list').innerHTML = '<div class="ipm-empty text-danger">' + esc((e && e.message) || 'خطا در دریافت واریزها') + '</div>';
            });
        }

        function submit(value) {
            if (busy) return;
            showErr('');
            if (!(value > 0)) {
                showErr('مبلغ واریز را وارد کنید.');
                amount.focus();
                return;
            }
            var rem = state ? state.total_amount - state.paid_amount : 0;
            if (value > rem) {
                showErr('مبلغ واریز از ماندهٔ فاکتور (' + money(rem) + ' ریال) بیشتر است.');
                amount.focus();
                return;
            }
            var g = dateIn.getAttribute('data-date') || '';
            if (!g && dateIn.value && typeof convertToGregorian === 'function') g = convertToGregorian(dateIn.value) || '';
            if (!g) {
                showErr('تاریخ واریز را انتخاب کنید.');
                return;
            }
            busy = true;
            addBtn.disabled = fullBtn.disabled = true;
            o.api('POST', '/inv/invoices/' + o.invoiceId + '/payments', {
                amount: value, pay_date: g, method: method.value, note: note.value.trim()
            }).then(function (d) {
                amount.value = '';
                note.value = '';
                method.value = '';
                if (window.showToast) showToast('واریز ثبت شد', 'success');
                changed(d);
                if (!q('.ipm-form').hidden) amount.focus();
            }).catch(function (e) {
                showErr((e && e.message) || 'خطا در ثبت واریز');
            }).then(function () {
                busy = false;
                addBtn.disabled = fullBtn.disabled = false;
            });
        }

        addBtn.addEventListener('click', function () { submit(toInt(amount.value)); });
        amount.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); submit(toInt(amount.value)); }
        });
        fullBtn.addEventListener('click', function () {
            if (!state) return;
            var rem = state.total_amount - state.paid_amount;
            var go = function () { submit(rem); };
            var msg = 'کل ماندهٔ فاکتور (' + money(rem) + ' ریال) به‌عنوان یک واریز ثبت شود؟';
            if (typeof uiConfirm === 'function') uiConfirm(msg, go, { yesText: 'بله، ثبت شود', noText: 'انصراف' });
            else if (confirm(msg)) go();
        });
        q('.ipm-list').addEventListener('click', function (e) {
            var b = e.target.closest('.ipm-del');
            if (!b || busy) return;
            var p = (state.items || []).filter(function (x) { return String(x.id) === b.getAttribute('data-id'); })[0];
            if (!p) return;
            var go = function () {
                busy = true;
                o.api('DELETE', '/inv/invoices/' + o.invoiceId + '/payments/' + p.id).then(function (d) {
                    if (window.showToast) showToast('واریز حذف شد', 'success');
                    changed(d);
                }).catch(function (e2) {
                    if (window.showToast) showToast((e2 && e2.message) || 'خطا در حذف', 'error');
                }).then(function () { busy = false; });
            };
            var msg = 'واریز ' + money(p.amount) + ' ریالیِ ' + jDate(p.pay_date) + ' حذف شود؟';
            if (typeof uiConfirm === 'function') uiConfirm(msg, go, { yesText: 'بله، حذف شود', noText: 'انصراف' });
            else if (confirm(msg)) go();
        });

        el.addEventListener('hidden.bs.modal', function () {
            // تقویم به body منتقل شده (persian-datepicker.js) — با پنجره پاکش کن
            var w = el.querySelector('.persian-datepicker-wrapper');
            if (w && w.datepickerInstance && w.datepickerInstance.calendar) w.datepickerInstance.calendar.remove();
            el.remove();
        });
        // اولین فیلد قابل‌ویرایش فوکوس بگیرد (قاعدهٔ مودال‌های سایت) — بعد از پایان انیمیشن
        el.addEventListener('shown.bs.modal', function () {
            if (!q('.ipm-form').hidden) amount.focus();
        });

        if (typeof window.reinitPersianDatepickers === 'function') window.reinitPersianDatepickers();
        load().then(function () {
            if (el.classList.contains('show') && !q('.ipm-form').hidden) amount.focus();
        });
        modal.show();
    }

    return { open: open, cellHtml: cellHtml, LABEL: LABEL };
})();
window.InvoicePayments = InvoicePayments;
