/*
 * ucrm-sales-dash.js — داشبورد فروش CRM روی داشبورد اصلی (۱۴۰۵/۰۸/۰۳).
 * ------------------------------------------------------------------
 *   UcrmDash.initTabs()  مدیر/حسابداری: دکمه‌های «عملیات | فروش» + تب فروش
 *                        (pages/partials/ucrm-dash-sales.php)
 *   UcrmDash.initRep()   کارشناس فروش: کارت «کارهای کارشناس فروش»
 *                        (pages/partials/ucrm-dash-rep.php)
 * API: crm-service/ucrm_dashboard.go و ucrm_followups.go (از راه UCRM در ucrm-common.js).
 * همهٔ تاریخ‌ها با ساعت سرور (TimeSync)، نه ساعت دستگاه.
 * فقط var/function (بدون let/const سطح‌بالا) و گارد ضدِ دوبار لود — مثل بقیهٔ فایل‌های مشترک.
 */
(function (w) {
    'use strict';
    if (w.UcrmDash) return;

    /* رنگ سری‌ها — با validate_palette (dataviz) روی سطح روشن و تاریک سنجیده شده */
    var PALETTE = {
        light: { target: '#7c5ce0', sales: '#2fa38c', rest: '#e5e7eb', grid: '#eef0f4', text: '#4b5563' },
        dark: { target: '#9473f0', sales: '#26957c', rest: '#374151', grid: '#2a2f3a', text: '#cbd5e1' }
    };
    var TYPE_LABEL = { call: 'تماس تلفنی', sms: 'پیامک', meeting: 'جلسهٔ حضوری', email: 'ایمیل', proforma: 'پیگیری پیش‌فاکتور', other: 'سایر' };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }
    function fa(s) { return w.faDigits ? w.faDigits(s) : String(s); }
    function pad2(n) { return String(n).padStart(2, '0'); }
    function isDark() { return document.documentElement.getAttribute('data-theme') === 'dark'; }
    function pal() { return isDark() ? PALETTE.dark : PALETTE.light; }
    function toast(msg, type) { if (w.showToast) w.showToast(msg, type); }

    /* «2026-10-25» → «۱۴۰۵/۰۸/۰۳» بدون وابستگی به منطقهٔ زمانی دستگاه */
    function jDate(g) {
        if (!g) return '';
        var p = g.slice(0, 10).split('-');
        var j = TimeSync.gregorianToJalali(+p[0], +p[1], +p[2]);
        return fa(j[0] + '/' + pad2(j[1]) + '/' + pad2(j[2]));
    }
    /* «2026-10-25 10:40:00» (وقت تهران) → «۱۴۰۵/۰۸/۰۳ - ۱۰:۴۰» */
    function jDateTime(s) {
        if (!s) return '';
        return jDate(s) + ' - ' + fa(s.slice(11, 16));
    }

    /* ═════════════════════ تب‌ها + داشبورد فروش ═════════════════════ */
    var S = { data: null, range: null, charts: {}, loaded: false };

    function jMonthRange(jy, jm) {
        var days = jm <= 6 ? 31 : (jm <= 11 ? 30 : (isValidPersianDate(jy + '/12/30') ? 30 : 29));
        return { from: convertToGregorian(jy + '/' + pad2(jm) + '/01'), to: convertToGregorian(jy + '/' + pad2(jm) + '/' + pad2(days)) };
    }
    function addDays(g, n) {
        var d = new Date(g + 'T12:00:00Z');
        d.setUTCDate(d.getUTCDate() + n);
        return d.toISOString().slice(0, 10);
    }
    function quickRange(q) {
        var j = TimeSync.serverJalali(), today = TimeSync.serverToday();
        if (q === 'month') return jMonthRange(j[0], j[1]);
        if (q === 'last') return j[1] === 1 ? jMonthRange(j[0] - 1, 12) : jMonthRange(j[0], j[1] - 1);
        return { from: addDays(today, -(+q - 1)), to: today };
    }
    function setRange(r) {
        S.range = r;
        document.getElementById('ucdRangeLbl').textContent = jDate(r.from) + ' - ' + jDate(r.to);
        [['ucdFrom', r.from], ['ucdTo', r.to]].forEach(function (x) {
            var el = document.getElementById(x[0]);
            el.setAttribute('data-date', x[1]);
            el.value = jDate(x[1]);
        });
    }

    function showTab(t) {
        document.documentElement.classList.toggle('ucd-on-sales', t === 'sales');
        document.querySelectorAll('.ucd-switch button').forEach(function (b) {
            var on = b.getAttribute('data-ucd') === t;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        try { localStorage.setItem('dash-tab', t); } catch (e) { /* مرورگر اجازه نداد */ }
        if (t === 'sales' && !S.loaded) load();
    }

    function load() {
        S.loaded = true;
        var rep = document.getElementById('ucdRep').value;
        var q = '?from=' + S.range.from + '&to=' + S.range.to + (rep ? '&rep_id=' + rep : '');
        return UCRM.get('/dashboard' + q).then(function (d) {
            S.data = d;
            fillReps(d.reps || []);
            renderNeeds();
            renderMatrix();
            renderCharts();
        }).catch(function (e) {
            toast(e.message || 'خطا در دریافت داشبورد فروش', 'error');
            document.getElementById('ucdNeeds').innerHTML = '<tr><td colspan="5" class="empty">' + esc(e.message || 'خطا در بارگذاری') + '</td></tr>';
            document.getElementById('ucdMatrix').innerHTML = '<tr><td colspan="5" class="empty">' + esc(e.message || 'خطا در بارگذاری') + '</td></tr>';
        });
    }

    function fillReps(reps) {
        var sel = document.getElementById('ucdRep');
        if (sel.options.length > 1) return;
        reps.forEach(function (r) {
            var o = document.createElement('option');
            o.value = r.id;
            o.textContent = r.name;
            sel.appendChild(o);
        });
    }

    function needsRows() {
        var q = (document.getElementById('ucdSearch').value || '').trim();
        var rows = (S.data && S.data.needs_followup) || [];
        if (!q) return rows;
        return rows.filter(function (r) { return (r.name + ' ' + r.rep_name).indexOf(q) !== -1; });
    }

    function renderNeeds() {
        var d = S.data, rows = needsRows(), days = d.settings.followup_alert_days;
        var me = +(w.UCRM_ME || 0);
        document.getElementById('ucdNeedsNote').innerHTML = '<i class="bi bi-info-circle"></i>مشتری فعالی که بیش از ' + fa(days) +
            ' روز پیگیری انجام‌شده نداشته باشد این‌جا می‌آید. این عدد در «تنظیمات تارگت» عوض می‌شود.';
        if (!rows.length) {
            var all = (d.needs_followup || []).length;
            document.getElementById('ucdNeeds').innerHTML = '<tr><td colspan="5" class="empty">' +
                (all ? 'مشتری‌ای با این جستجو پیدا نشد.' : 'همهٔ مشتری‌های فعال در ' + fa(days) + ' روز گذشته پیگیری شده‌اند.') + '</td></tr>';
            return;
        }
        document.getElementById('ucdNeeds').innerHTML = rows.map(function (r) {
            var can = r.rep_id > 0 && r.rep_id !== me;
            var tip = !r.rep_id ? 'این مشتری کارشناس ندارد' : (r.rep_id === me ? 'کارشناس این مشتری خود شما هستید' : 'اعلان و پیامک به کارشناس');
            return '<tr>' +
                '<td><a href="ucrm-customer.php?id=' + r.id + '">' + esc(r.name) + '</a></td>' +
                '<td>' + (r.rep_name ? esc(r.rep_name) : '<span class="ucd-muted">تعیین نشده</span>') + '</td>' +
                '<td>' + (r.last_followup_at ? jDate(r.last_followup_at) : '<span class="ucd-muted">هنوز پیگیری نشده</span>') + '</td>' +
                '<td class="c"><span class="ucd-days">' + fa(r.days) + ' روز</span></td>' +
                '<td class="c"><button type="button" class="ucd-bell" data-nudge="' + r.id + '" title="' + tip + '"' + (can ? '' : ' disabled') +
                '><i class="bi bi-bell"></i>اطلاع‌رسانی</button></td>' +
                '</tr>';
        }).join('');
    }

    function nudge(btn) {
        var id = +btn.getAttribute('data-nudge');
        btn.disabled = true;
        UCRM.send('POST', '/customers/' + id + '/nudge', {}).then(function (r) {
            toast(r.message || 'به کارشناس اطلاع داده شد.', 'success');
            btn.innerHTML = '<i class="bi bi-check2"></i>اطلاع داده شد';
        }).catch(function (e) {
            toast(e.message || 'خطا', 'error');
            btn.disabled = false;
        });
    }

    function band(pct) {
        var set = S.data.settings;
        if (pct < set.target_red_below) return 'red';
        return pct < set.target_green_from ? 'orange' : 'green';
    }
    function pctOf(r) { return r.target > 0 ? Math.round(r.sales * 100 / r.target) : null; }

    function renderMatrix() {
        var rows = S.data.matrix || [];
        if (!rows.length) {
            document.getElementById('ucdMatrix').innerHTML = '<tr><td colspan="5" class="empty">کارشناس فروشی پیدا نشد.</td></tr>';
            return;
        }
        document.getElementById('ucdMatrix').innerHTML = rows.map(function (r) {
            var p = pctOf(r), diff = r.target - r.sales;
            var pc = p == null ? '<span class="ucd-muted">تارگت ندارد</span>' :
                '<div class="ucd-pct"><div class="bar"><i class="' + band(p) + '" style="width:' + Math.min(100, p) + '%"></i></div><span>' + fa(p) + '٪</span></div>';
            var df = diff > 0 ? '<span class="ucd-diff neg">' + UCRM.money(diff) + ' کمتر</span>' :
                (diff < 0 ? '<span class="ucd-diff pos">' + UCRM.money(-diff) + ' بیشتر</span>' : '<span class="ucd-muted">برابر</span>');
            return '<tr><td>' + esc(r.rep_name) + '</td><td>' + UCRM.money(r.target) + '</td><td>' + UCRM.money(r.sales) +
                '</td><td>' + pc + '</td><td>' + df + '</td></tr>';
        }).join('');
    }

    function axisOpts(c, horizontal) {
        var money = function (v) { return fa(Number(v).toLocaleString('en-US')); };
        var val = { beginAtZero: true, ticks: { color: c.text, callback: money, precision: 0 }, grid: { color: c.grid } };
        var cat = { ticks: { color: c.text }, grid: { display: false } };
        return horizontal ? { x: val, y: cat } : { x: cat, y: val };
    }
    function tooltipMoney(ctx) {
        return ' ' + ctx.dataset.label + ': ' + UCRM.money(ctx.parsed[ctx.chart.options.indexAxis === 'y' ? 'x' : 'y']) + ' تومان';
    }

    function renderCharts() {
        if (!w.Chart) return;
        var c = pal(), rows = S.data.matrix || [];
        var labels = rows.map(function (r) { return r.rep_name; });
        Chart.defaults.font.family = "'Vazirmatn', sans-serif";
        Chart.defaults.color = c.text;
        Object.keys(S.charts).forEach(function (k) { S.charts[k].destroy(); });
        S.charts = {};
        var bar = { borderRadius: 4, borderSkipped: 'start', maxBarThickness: 22 };

        // مقایسه تیم فروش — فروش هر کارشناس (یک سری)؛ بدون فروش = پیام خالی به‌جای محور بی‌معنا
        var noSales = !rows.some(function (r) { return r.sales > 0; });
        document.getElementById('ucdTeamEmpty').hidden = !noSales;
        S.charts.team = new Chart(document.getElementById('ucdChTeam'), {
            type: 'bar',
            data: { labels: labels, datasets: [Object.assign({ label: 'فروش', data: rows.map(function (r) { return r.sales; }), backgroundColor: c.sales }, bar)] },
            options: {
                indexAxis: 'y', maintainAspectRatio: false, scales: noSales ? { x: { display: false }, y: { display: false } } : axisOpts(c, true),
                plugins: { legend: { display: false }, tooltip: { rtl: true, callbacks: { label: tooltipMoney } } }
            }
        });

        // شاخص‌های کلیدی — تحقق تارگت کل تیم
        var tgt = 0, sal = 0, withT = 0, sumPct = 0;
        rows.forEach(function (r) {
            tgt += r.target; sal += r.sales;
            if (r.target > 0) { withT++; sumPct += r.sales * 100 / r.target; }
        });
        var teamPct = tgt > 0 ? Math.round(sal * 100 / tgt) : 0;
        document.getElementById('ucdKpiPct').textContent = fa(teamPct) + '٪';
        S.charts.kpi = new Chart(document.getElementById('ucdChKpi'), {
            type: 'doughnut',
            data: {
                labels: ['فروش', 'باقی‌مانده تا تارگت'],
                datasets: [{ data: [sal, Math.max(0, tgt - sal)], backgroundColor: [c.sales, c.rest], borderColor: isDark() ? '#1a1a19' : '#fff', borderWidth: 2 }]
            },
            options: {
                maintainAspectRatio: false, cutout: '72%',
                plugins: {
                    legend: { position: 'bottom', rtl: true, labels: { boxWidth: 10, color: c.text } },
                    tooltip: { rtl: true, callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + UCRM.money(ctx.parsed) + ' تومان'; } } }
                }
            }
        });
        var need = (S.data.needs_followup || []).length;
        document.getElementById('ucdKpis').innerHTML =
            '<div>تارگت کل<b>' + UCRM.money(tgt) + '</b></div>' +
            '<div>فروش کل<b>' + UCRM.money(sal) + '</b></div>' +
            '<div>میانگین تحقق کارشناس‌ها<b>' + fa(withT ? Math.round(sumPct / withT) : 0) + '٪</b></div>' +
            '<div>نیازمند پیگیری<b>' + fa(need) + ' مشتری</b></div>';

        // مقایسه هدف و فروش کارشناس‌ها — دو سری کنار هم
        S.charts.cmp = new Chart(document.getElementById('ucdChCmp'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    Object.assign({ label: 'تارگت', data: rows.map(function (r) { return r.target; }), backgroundColor: c.target }, bar),
                    Object.assign({ label: 'فروش', data: rows.map(function (r) { return r.sales; }), backgroundColor: c.sales }, bar)
                ]
            },
            options: {
                maintainAspectRatio: false, scales: axisOpts(c, false),
                datasets: { bar: { categoryPercentage: .7, barPercentage: .9 } },
                plugins: {
                    legend: { position: 'bottom', rtl: true, labels: { boxWidth: 10, color: c.text } },
                    tooltip: { rtl: true, callbacks: { label: tooltipMoney } }
                }
            }
        });
    }

    function exportExcel() {
        var rows = needsRows();
        var head = ['نام مشتری', 'کارشناس مسئول', 'آخرین پیگیری', 'مدت عدم پیگیری (روز)'];
        var data = rows.map(function (r) {
            return [r.name, r.rep_name || 'تعیین نشده', r.last_followup_at ? jDate(r.last_followup_at) : 'هنوز پیگیری نشده', r.days];
        });
        var ws = XLSX.utils.aoa_to_sheet([head].concat(data));
        ws['!cols'] = [{ wch: 32 }, { wch: 22 }, { wch: 16 }, { wch: 20 }];
        var wb = XLSX.utils.book_new();
        wb.Workbook = { Views: [{ RTL: true }] };
        XLSX.utils.book_append_sheet(wb, ws, 'نیازمند پیگیری');
        XLSX.writeFile(wb, 'moshtarian-niazmand-peygiri-' + TimeSync.serverToday() + '.xlsx');
        if (!data.length) toast('فعلا مشتری نیازمند پیگیری نیست؛ فایل فقط سرستون‌ها را دارد.', 'info');
    }

    function initTabs() {
        if (!document.getElementById('ucdSales')) return;
        document.querySelectorAll('.ucd-switch button').forEach(function (b) {
            b.addEventListener('click', function () { showTab(b.getAttribute('data-ucd')); });
        });
        setRange(quickRange('month'));
        var hideRange = function () { bootstrap.Dropdown.getOrCreateInstance(document.getElementById('ucdRangeBtn')).hide(); };
        document.querySelectorAll('#ucdQuick button').forEach(function (b) {
            b.addEventListener('click', function () { setRange(quickRange(b.getAttribute('data-q'))); hideRange(); load(); });
        });
        document.getElementById('ucdRangeApply').addEventListener('click', function () {
            var f = document.getElementById('ucdFrom').getAttribute('data-date'), t = document.getElementById('ucdTo').getAttribute('data-date');
            if (!f || !t) return toast('هر دو تاریخ را انتخاب کنید.', 'warning');
            setRange(f <= t ? { from: f, to: t } : { from: t, to: f });
            hideRange();
            load();
        });
        document.getElementById('ucdRep').addEventListener('change', load);
        document.getElementById('ucdSearch').addEventListener('input', function () { if (S.data) renderNeeds(); });
        document.getElementById('ucdExcel').addEventListener('click', exportExcel);
        document.getElementById('ucdNeeds').addEventListener('click', function (e) {
            var b = e.target.closest('[data-nudge]');
            if (b && !b.disabled) nudge(b);
        });
        // تغییر تم → رنگ نمودارها از نو
        new MutationObserver(function () { if (S.data) renderCharts(); })
            .observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        showTab(document.documentElement.classList.contains('ucd-on-sales') ? 'sales' : 'ops');
    }

    /* ═════════════════════ کارت «کارهای کارشناس فروش» ═════════════════════ */
    var R = { items: [] };

    function repLoad() {
        return UCRM.get('/followups/open').then(function (d) {
            R.items = d.items || [];
            repRender();
        }).catch(function (e) {
            document.getElementById('ucdFuBody').innerHTML = '<tr><td colspan="5" class="dash-loading">' + esc(e.message || 'خطا در بارگذاری') + '</td></tr>';
        });
    }

    function repRender() {
        var body = document.getElementById('ucdFuBody');
        if (!R.items.length) {
            body.innerHTML = '<tr><td colspan="5" class="dash-loading">پیگیری انجام‌نشده‌ای ندارید.</td></tr>';
            return;
        }
        body.innerHTML = R.items.map(function (f) {
            var key = 'fu' + f.id;
            var menu = '<div class="row-menu-wrap" id="rowWrap-' + key + '">' +
                '<button type="button" class="row-kebab" onclick="rowToggleMenu(\'' + key + '\', event)" aria-label="عملیات"><i class="bi bi-three-dots-vertical"></i></button>' +
                '<div class="row-menu" id="rowMenu-' + key + '">' +
                '<button class="act-done" data-fu-act="done" data-fu="' + f.id + '"><i class="bi bi-check-lg"></i> ثبت انجام</button>' +
                '<button class="act-edit" data-fu-act="edit" data-fu="' + f.id + '"><i class="bi bi-pencil"></i> ویرایش پیگیری</button>' +
                '<button class="act-open" data-fu-act="open" data-fu="' + f.id + '"><i class="bi bi-person"></i> رفتن به صفحهٔ مشتری</button>' +
                '<button class="act-cancel" data-fu-act="cancel" data-fu="' + f.id + '"><i class="bi bi-x-lg"></i> لغو پیگیری</button>' +
                '</div></div>';
            return '<tr data-cust="' + f.customer_id + '">' +
                '<td><span class="cut" title="' + esc(f.customer_name) + '">' + esc(f.customer_name) + '</span></td>' +
                '<td><span class="cut" title="' + esc(f.title) + '">' + esc(f.title) + '</span></td>' +
                '<td class="' + (f.overdue ? 'late' : '') + '"' + (f.overdue ? ' title="موعد این پیگیری گذشته است"' : '') + '><span class="ucd-when">' + jDateTime(f.due_at) + '</span></td>' +
                '<td><span class="ucd-type ' + esc(f.type) + '">' + esc(TYPE_LABEL[f.type] || 'سایر') + '</span></td>' +
                '<td class="td-ops">' + menu + '</td></tr>';
        }).join('');
    }

    function repAction(act, id) {
        var f = R.items.filter(function (x) { return x.id === id; })[0];
        if (!f) return;
        if (w.rowCloseMenus) w.rowCloseMenus();
        if (act === 'open') { location.href = 'ucrm-customer.php?id=' + f.customer_id; return; }
        if (act === 'edit') { location.href = 'ucrm-customer.php?id=' + f.customer_id + '&fu=' + f.id; return; }
        var go = function () {
            UCRM.send('POST', '/followups/' + id + '/status', { status: act === 'done' ? 'done' : 'cancelled' }).then(function (r) {
                toast(r.message || 'ذخیره شد', 'success');
                repLoad();
            }).catch(function (e) { toast(e.message || 'خطا', 'error'); });
        };
        if (act === 'cancel' && w.uiConfirm) {
            w.uiConfirm('پیگیری «' + esc(f.title) + '» برای مشتری «' + esc(f.customer_name) + '» لغو شود؟', go,
                { danger: true, yesText: 'لغو پیگیری', noText: 'انصراف' });
        } else {
            go();
        }
    }

    function initRep() {
        var body = document.getElementById('ucdFuBody');
        if (!body) return;
        // منوها به body منتقل می‌شوند (rowToggleMenu داشبورد) — پس کلیک را روی سند می‌گیریم
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-fu-act]');
            if (!b) {
                var tr = e.target.closest('#ucdFuBody tr[data-cust]');
                if (tr && !e.target.closest('.td-ops')) location.href = 'ucrm-customer.php?id=' + tr.getAttribute('data-cust');
                return;
            }
            e.stopPropagation();
            repAction(b.getAttribute('data-fu-act'), +b.getAttribute('data-fu'));
        });
        repLoad();
    }

    w.UcrmDash = { initTabs: initTabs, initRep: initRep };
})(window);
