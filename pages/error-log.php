<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// فقط سوپرادمین — این صفحه پیام‌هایِ خطایِ داخلیِ سرور (مسیرها، جزئیاتِ
// فنی) رو نشون می‌ده که نباید دستِ کاربرِ عادی باشه.
if ((int) $__me['id'] !== 1) {
    header('Location: /pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهده‌ی لاگِ خطا - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">

    <style>
        /* توضیحِ مبتدی: این بخش فقط ظاهرِ همین صفحه‌ست — چیزیِ مشترک با
           بقیه‌ی سایت رو عوض نمی‌کنه. رنگِ اصلیِ سایت (#8e57fe) و رنگِ
           هاورِ استانداردِ سایت (feedback_hover_color_standard) این‌جا هم
           رعایت شده. */
        .elog-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin: 1rem 0 1.25rem;
        }

        .elog-toolbar .elog-search {
            flex: 1 1 240px;
            min-width: 200px;
        }

        .elog-toolbar .persian-datepicker-wrapper {
            width: 150px;
        }

        .elog-status {
            color: #718096;
            font-size: 13px;
            margin-bottom: .75rem;
        }

        .elog-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .elog-entry {
            background: #fff;
            border: 1px solid #eef0f4;
            border-radius: 12px;
            padding: 12px 16px;
            transition: background .15s;
        }

        .elog-entry:hover {
            background: rgba(142, 87, 254, .12);
        }

        .elog-entry-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .elog-time {
            font-size: 12px;
            color: #8a8fa3;
            font-family: inherit;
        }

        .elog-level {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: #f1f2f6;
            color: #55597a;
        }

        .elog-level.is-error {
            background: rgba(220, 53, 69, .12);
            color: #dc3545;
        }

        .elog-level.is-warn {
            background: rgba(245, 158, 11, .15);
            color: #b7791f;
        }

        .elog-message {
            font-size: 13px;
            line-height: 1.9;
            color: #363c53;
            white-space: pre-wrap;
            word-break: break-word;
            direction: ltr;
            text-align: left;
            font-family: 'Courier New', monospace;
        }

        .elog-empty {
            text-align: center;
            padding: 60px 20px;
            color: #8a8fa3;
        }

        :root[data-theme="dark"] .elog-entry {
            background: #1e2030;
            border-color: #2b2e42;
        }

        :root[data-theme="dark"] .elog-entry:hover {
            background: rgba(142, 87, 254, .18);
        }

        :root[data-theme="dark"] .elog-time {
            color: #9a9fc0;
        }

        :root[data-theme="dark"] .elog-level {
            background: #2b2e42;
            color: #c7cbe6;
        }

        :root[data-theme="dark"] .elog-message {
            color: #d6d9ee;
        }

        :root[data-theme="dark"] .elog-status {
            color: #9a9fc0;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-terminal"></i> مشاهده‌ی لاگِ خطا</h1>
                <p>خطاهای فنیِ خودِ اپِ BPM (سرورِ Apache) — فقط برایِ سوپرادمین</p>
            </div>
        </div>

        <div class="elog-toolbar">
            <input type="text" class="form-control elog-search" id="elogSearch" placeholder="جست‌وجو در متنِ خطا...">
            <div class="persian-datepicker-wrapper" id="dateFromWrap" data-restrict-past="-1">
                <input type="text" class="persian-datepicker-input form-control" id="dateFrom" placeholder="از تاریخ" readonly>
                <div class="persian-datepicker">
                    <div class="datepicker-header">
                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                        <span class="datepicker-current"></span>
                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                    </div>
                    <div class="datepicker-weekdays">
                        <div class="datepicker-weekday">ش</div>
                        <div class="datepicker-weekday">ی</div>
                        <div class="datepicker-weekday">د</div>
                        <div class="datepicker-weekday">س</div>
                        <div class="datepicker-weekday">چ</div>
                        <div class="datepicker-weekday">پ</div>
                        <div class="datepicker-weekday">ج</div>
                    </div>
                    <div class="datepicker-days"></div>
                    <button type="button" class="datepicker-today-btn">امروز</button>
                </div>
            </div>
            <span class="text-muted">تا</span>
            <div class="persian-datepicker-wrapper" id="dateToWrap" data-restrict-past="-1">
                <input type="text" class="persian-datepicker-input form-control" id="dateTo" placeholder="تا تاریخ" readonly>
                <div class="persian-datepicker">
                    <div class="datepicker-header">
                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                        <span class="datepicker-current"></span>
                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                    </div>
                    <div class="datepicker-weekdays">
                        <div class="datepicker-weekday">ش</div>
                        <div class="datepicker-weekday">ی</div>
                        <div class="datepicker-weekday">د</div>
                        <div class="datepicker-weekday">س</div>
                        <div class="datepicker-weekday">چ</div>
                        <div class="datepicker-weekday">پ</div>
                        <div class="datepicker-weekday">ج</div>
                    </div>
                    <div class="datepicker-days"></div>
                    <button type="button" class="datepicker-today-btn">امروز</button>
                </div>
            </div>
            <button class="btn btn-outline-secondary btn-sm" id="btnRefresh"><i class="bi bi-arrow-clockwise ms-1"></i> به‌روزرسانی</button>
        </div>

        <div class="elog-status" id="elogStatus"></div>
        <div class="elog-list" id="elogList">
            <p class="text-muted">در حال بارگذاری…</p>
        </div>

    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <script>
        // توضیحِ مبتدی: این صفحه فقط یک API را صدا می‌زند
        // (api/admin/error-log.php) و نتیجه را لیست می‌کند — منطقِ اصلی
        // (خواندن/فیلترِ فایلِ لاگ) سمتِ سرور است، این‌جا فقط نمایشه.
        let range = {
            from: null,
            to: null
        };
        let searchDebounce = null;

        function fmtISO(o) {
            return o.year + '-' + String(o.month).padStart(2, '0') + '-' + String(o.day).padStart(2, '0');
        }

        async function apiGet(url) {
            try {
                const res = await fetch(url, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                return await res.json();
            } catch (e) {
                console.error('API error:', url, e);
                return {
                    success: false
                };
            }
        }

        function levelClass(level) {
            if (!level) return '';
            const l = level.toLowerCase();
            if (l.indexOf('error') !== -1 || l.indexOf('fatal') !== -1) return 'is-error';
            if (l.indexOf('warn') !== -1) return 'is-warn';
            return '';
        }

        function toJalaliLabel(ts) {
            if (!ts) return '';
            const wrap = document.getElementById('dateFromWrap');
            const dp = wrap && wrap.datepickerInstance;
            if (!dp) return ts;
            try {
                const d = new Date(ts.replace(' ', 'T'));
                const j = dp.gregorianToJalali(d);
                const hh = String(d.getHours()).padStart(2, '0');
                const mm = String(d.getMinutes()).padStart(2, '0');
                return faDigits(`${j.year}/${String(j.month).padStart(2,'0')}/${String(j.day).padStart(2,'0')} - ${hh}:${mm}`);
            } catch (e) {
                return ts;
            }
        }

        function render(data) {
            const list = document.getElementById('elogList');
            const status = document.getElementById('elogStatus');

            if (!data || data.success === false) {
                list.innerHTML = '<div class="elog-empty">دریافتِ لاگ با خطا مواجه شد — دوباره تلاش کنید.</div>';
                status.textContent = '';
                return;
            }

            if (data.message) {
                status.textContent = data.message;
            } else {
                status.textContent = data.truncated
                    ? `${data.entries.length} خط نمایش داده شد (نتایجِ بیشتری هم هست — جست‌وجو را محدودتر کنید)`
                    : `${data.entries.length} خط نمایش داده شد`;
            }

            if (!data.entries || data.entries.length === 0) {
                list.innerHTML = '<div class="elog-empty">هیچ خطایی با این فیلتر پیدا نشد.</div>';
                return;
            }

            list.innerHTML = data.entries.map(e => `
                <div class="elog-entry">
                    <div class="elog-entry-head">
                        <span class="elog-time">${toJalaliLabel(e.timestamp)}</span>
                        ${e.level ? `<span class="elog-level ${levelClass(e.level)}">${e.level}</span>` : ''}
                    </div>
                    <div class="elog-message"></div>
                </div>
            `).join('');

            // متنِ پیام رو با textContent می‌ذاریم (نه innerHTML) تا اگه خودِ
            // پیامِ خطا حاویِ کاراکترهایِ HTMLی بود، به‌عنوانِ کد اجرا نشه.
            const messages = list.querySelectorAll('.elog-message');
            data.entries.forEach((e, i) => {
                messages[i].textContent = e.message;
            });
        }

        async function load() {
            document.getElementById('elogList').innerHTML = '<p class="text-muted">در حال بارگذاری…</p>';
            const params = new URLSearchParams();
            const q = document.getElementById('elogSearch').value.trim();
            if (q) params.set('q', q);
            if (range.from) params.set('from', range.from);
            if (range.to) params.set('to', range.to);
            const data = await apiGet(`/api/admin/error-log.php?${params.toString()}`);
            render(data);
        }

        document.getElementById('elogSearch').addEventListener('input', function () {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(load, 400);
        });
        document.getElementById('dateFrom').addEventListener('change', function () {
            range.from = this.dataset.date || null;
            load();
        });
        document.getElementById('dateTo').addEventListener('change', function () {
            range.to = this.dataset.date || null;
            load();
        });
        document.getElementById('btnRefresh').addEventListener('click', load);

        load();
    </script>

</body>

</html>
