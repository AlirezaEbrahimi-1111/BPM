<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// 🔒 طبق درخواست صریح — فقط id=1 (نه حتی سوپرادمین دیگه) به این صفحه
// دسترسی داره، دقیقا مثل الگوی error-log.php/security-log.php
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
    <title>حکمت روزانه - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/fonts/Vazirmatn-font-face.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">

    <style>
        /* توضیح مبتدی: این استایل‌ها فقط مال همین صفحه‌ن. رنگ اصلی سایت
           (#8e57fe) و رنگ استاندارد هاور (feedback_hover_color_standard)
           رعایت شده؛ بقیه‌ی کلاس‌ها (card/table/form-control) از custom.css
           می‌آن و تم تاریک‌شون خودکاره. */
        .hk-card {
            margin-bottom: 1.25rem;
        }

        .hk-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .hk-count-badge {
            font-size: .72rem;
            font-weight: 600;
            color: #8e57fe;
            background: rgba(142, 87, 254, 0.12);
            border-radius: 20px;
            padding: 3px 12px;
        }

        .hk-preview-box {
            border: 1px dashed rgba(142, 87, 254, 0.4);
            border-radius: 10px;
            padding: 14px 16px;
            white-space: pre-wrap;
            font-size: .9rem;
            line-height: 1.9;
            background: rgba(142, 87, 254, 0.05);
        }

        :root[data-theme="dark"] .hk-preview-box {
            background: rgba(142, 87, 254, 0.08);
        }

        .hk-time-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hk-time-row .form-select,
        .hk-time-row input {
            max-width: 90px;
        }

        .hk-log-table tbody tr:hover {
            background: rgba(142, 87, 254, 0.12);
        }

        :root[data-theme="dark"] .hk-log-table tbody tr:hover {
            background: rgba(142, 87, 254, 0.18);
        }

        .hk-status-sent { color: #16a34a; font-weight: 600; }
        .hk-status-failed { color: #dc2626; font-weight: 600; }
        .hk-quote-cell {
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="bi bi-moon-stars"></i>
                ارسال روزانه‌ی حکمت نهج‌البلاغه
            </h1>
        </div>

        <div class="row g-4">
            <!-- تنظیمات + پیش‌نمایش -->
            <div class="col-lg-6">
                <div class="card hk-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-gear ms-2"></i>تنظیمات ارسال</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="hkEnabled">
                            <label class="form-check-label" for="hkEnabled">ارسال خودکار روزانه فعال باشد</label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ساعت ارسال (به وقت تهران)</label>
                            <div class="hk-time-row">
                                <select class="form-select" id="hkHour"></select>
                                <span>:</span>
                                <select class="form-select" id="hkMinute"></select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">نحوه‌ی انتخاب جمله‌ی هر روز</label>
                            <select class="form-select" id="hkRotation">
                                <option value="sequential">به‌ترتیب لیست (بعد از آخری، از اول شروع می‌شود)</option>
                                <option value="random">تصادفی</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">متن انتهای پیام (اختیاری)</label>
                            <textarea class="form-control" id="hkClosing" rows="2" placeholder="مثلا نام سازمان یا یک جمله‌ی ثابت که ته هر پیام بیاید"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">شماره‌ی هشدار (اگر ارسال روزانه ناموفق شد، پیامک خطا اینجا می‌آید)</label>
                            <input type="text" class="form-control" id="hkAlertPhone" placeholder="مثلا 09121234567" style="max-width:220px;">
                        </div>

                        <button class="btn btn-primary btn-sm" onclick="hkSaveSettings()">
                            <i class="bi bi-check-lg me-1"></i>ذخیره‌ی تنظیمات
                        </button>
                    </div>
                </div>

                <div class="card hk-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-eye ms-2"></i>پیش‌نمایش پیام بعدی</h5>
                    </div>
                    <div class="card-body">
                        <div class="hk-preview-box" id="hkPreviewBox">در حال بارگذاری...</div>

                        <hr>
                        <label class="form-label">ارسال آزمایشی به یک شماره</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="text" class="form-control" id="hkTestPhone" placeholder="مثلا 09121234567" style="max-width:220px;">
                            <button class="btn btn-outline-secondary btn-sm" onclick="hkTestSend()">
                                <i class="bi bi-send me-1"></i>ارسال تست (همین پیش‌نمایش)
                            </button>
                        </div>

                        <div class="mt-3">
                            <button class="btn btn-warning btn-sm" onclick="hkSendNow()">
                                <i class="bi bi-lightning-charge me-1"></i>ارسال دستی همین الان برای همه
                            </button>
                            <div class="form-text">این دکمه واقعا برای همه‌ی گیرنده‌های فعال پیامک می‌فرستد و جای ارسال خودکار امروز را می‌گیرد (دیگر امروز دوباره خودکار نمی‌فرستد).</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- لیست جملات و شماره‌ها -->
            <div class="col-lg-6">
                <div class="card hk-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-quote ms-2"></i>لیست حکمت‌ها</h5>
                        <span class="hk-count-badge" id="hkQuotesCount">۰ جمله</span>
                    </div>
                    <div class="card-body">
                        <div class="form-text mb-2">هر جمله را در یک خط بنویس یا پیست کن. ذخیره کردن، کل لیست قبلی را جایگزین می‌کند.</div>
                        <textarea class="form-control" id="hkQuotesText" rows="8" placeholder="جمله‌ی اول&#10;جمله‌ی دوم&#10;..."></textarea>
                        <button class="btn btn-primary btn-sm mt-2" onclick="hkSaveQuotes()">
                            <i class="bi bi-check-lg me-1"></i>ذخیره‌ی لیست جملات
                        </button>
                    </div>
                </div>

                <div class="card hk-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-telephone ms-2"></i>لیست شماره‌ تلفن‌ها</h5>
                        <span class="hk-count-badge" id="hkRecipientsCount">۰ شماره</span>
                    </div>
                    <div class="card-body">
                        <div class="form-text mb-2">هر شماره را در یک خط بنویس یا پیست کن (مثلا 09121234567). ذخیره کردن، کل لیست قبلی را جایگزین می‌کند و شماره‌های تکراری/نامعتبر خودکار حذف می‌شوند.</div>
                        <textarea class="form-control" id="hkRecipientsText" rows="8" placeholder="09121234567&#10;09131234567&#10;..."></textarea>
                        <button class="btn btn-primary btn-sm mt-2" onclick="hkSaveRecipients()">
                            <i class="bi bi-check-lg me-1"></i>ذخیره‌ی لیست شماره‌ها
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- تاریخچه‌ی ارسال -->
        <div class="card hk-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history ms-2"></i>تاریخچه‌ی ارسال (۵۰ مورد اخیر)</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="hkLoadAll()">
                    <i class="bi bi-arrow-clockwise me-1"></i>بروزرسانی
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-scroll">
                    <table class="table hk-log-table mb-0">
                        <thead>
                            <tr>
                                <th>تاریخ</th>
                                <th>جمله</th>
                                <th>گیرنده</th>
                                <th>نوع</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody id="hkLogBody">
                            <tr><td colspan="5" class="text-center text-muted py-3">در حال بارگذاری...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        let hkState = { settings: null, quotes: [], recipients: [], preview: null, log: [] };

        function hkFillTimeSelects() {
            const hourSel = document.getElementById('hkHour');
            const minSel = document.getElementById('hkMinute');
            hourSel.innerHTML = '';
            minSel.innerHTML = '';
            for (let h = 0; h < 24; h++) {
                hourSel.innerHTML += `<option value="${h}">${toFa(String(h).padStart(2, '0'))}</option>`;
            }
            for (let m = 0; m < 60; m += 5) {
                minSel.innerHTML += `<option value="${m}">${toFa(String(m).padStart(2, '0'))}</option>`;
            }
        }

        async function hkLoadAll() {
            try {
                const res = await fetch('/api/admin/hekmat-get.php', {
                    headers: { 'Authorization': 'Bearer ' + authToken }
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.message || 'خطا در بارگذاری اطلاعات', 'error');
                    return;
                }
                hkState.settings = data.settings;
                hkState.quotes = data.quotes;
                hkState.recipients = data.recipients;
                hkState.preview = data.preview;
                hkState.log = data.log;
                hkRenderAll();
            } catch (e) {
                console.error(e);
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        function hkRenderAll() {
            const s = hkState.settings;
            document.getElementById('hkEnabled').checked = Number(s.is_enabled) === 1;
            document.getElementById('hkHour').value = String(Number(s.send_hour));
            // نزدیک‌ترین گزینه‌ی ۵دقیقه‌ای رو انتخاب کن (سلکت فقط مضرب ۵ داره)
            const roundedMin = Math.round(Number(s.send_minute) / 5) * 5 % 60;
            document.getElementById('hkMinute').value = String(roundedMin);
            document.getElementById('hkRotation').value = s.rotation_mode;
            document.getElementById('hkClosing').value = s.closing_text || '';
            document.getElementById('hkAlertPhone').value = s.alert_phone || '';

            document.getElementById('hkQuotesText').value = hkState.quotes.map(q => q.text).join('\n');
            document.getElementById('hkQuotesCount').textContent = `${toFa(hkState.quotes.length)} جمله`;

            document.getElementById('hkRecipientsText').value = hkState.recipients.map(r => r.phone).join('\n');
            document.getElementById('hkRecipientsCount').textContent = `${toFa(hkState.recipients.length)} شماره`;

            const p = hkState.preview;
            document.getElementById('hkPreviewBox').textContent = (p && p.has_quote)
                ? p.message
                : 'هیچ جمله‌ی فعالی برای پیش‌نمایش وجود ندارد — اول لیست حکمت‌ها را ذخیره کن.';

            hkRenderLog();
        }

        function hkRenderLog() {
            const body = document.getElementById('hkLogBody');
            if (!hkState.log.length) {
                body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">هنوز هیچ ارسالی ثبت نشده</td></tr>';
                return;
            }
            body.innerHTML = hkState.log.map(l => `
                <tr>
                    <td>${esc(l.send_date)}</td>
                    <td class="hk-quote-cell" title="${escAttr(l.quote_text)}">${esc(l.quote_text)}</td>
                    <td>${esc(l.recipient_phone)}</td>
                    <td>${Number(l.is_test) === 1 ? 'آزمایشی' : 'خودکار/دستی'}</td>
                    <td class="${l.status === 'sent' ? 'hk-status-sent' : 'hk-status-failed'}">
                        ${l.status === 'sent' ? 'ارسال شد' : 'ناموفق'}
                    </td>
                </tr>
            `).join('');
        }

        function escAttr(s) {
            return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
        }

        async function hkSaveSettings() {
            const payload = {
                send_hour: Number(document.getElementById('hkHour').value),
                send_minute: Number(document.getElementById('hkMinute').value),
                closing_text: document.getElementById('hkClosing').value,
                is_enabled: document.getElementById('hkEnabled').checked,
                rotation_mode: document.getElementById('hkRotation').value,
                alert_phone: document.getElementById('hkAlertPhone').value,
            };
            try {
                const res = await fetch('/api/admin/hekmat-save-settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    showToast('✅ تنظیمات ذخیره شد', 'success');
                    hkLoadAll();
                } else {
                    showToast('خطا: ' + (data.message || 'نامشخص'), 'error');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        async function hkSaveQuotes() {
            const text = document.getElementById('hkQuotesText').value;
            try {
                const res = await fetch('/api/admin/hekmat-save-quotes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ text })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`✅ ${toFa(data.count)} جمله ذخیره شد`, 'success');
                    hkLoadAll();
                } else {
                    showToast('خطا: ' + (data.message || 'نامشخص'), 'error');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        async function hkSaveRecipients() {
            const text = document.getElementById('hkRecipientsText').value;
            try {
                const res = await fetch('/api/admin/hekmat-save-recipients.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ text })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(`✅ ${toFa(data.count)} شماره ذخیره شد`, 'success');
                    hkLoadAll();
                } else {
                    showToast('خطا: ' + (data.message || 'نامشخص'), 'error');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        async function hkTestSend() {
            const phone = document.getElementById('hkTestPhone').value.trim();
            if (!phone) {
                showToast('شماره تلفن را وارد کن', 'warning');
                return;
            }
            try {
                const res = await fetch('/api/admin/hekmat-test-send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ phone })
                });
                const data = await res.json();
                showToast(data.message || (data.success ? 'ارسال شد' : 'خطا'), data.success ? 'success' : 'error');
                if (data.success) hkLoadAll();
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'error');
            }
        }

        function hkSendNow() {
            uiConfirm(
                'این کار همین الان یک پیامک واقعی برای همه‌ی شماره‌های فعال می‌فرستد و جای ارسال خودکار امروز را می‌گیرد. مطمئنی؟',
                async function () {
                    try {
                        const res = await fetch('/api/admin/hekmat-send-now.php', {
                            method: 'POST',
                            headers: { 'Authorization': 'Bearer ' + authToken }
                        });
                        const data = await res.json();
                        showToast(data.message || (data.success ? 'ارسال شد' : 'خطا'), data.success ? 'success' : 'error');
                        if (data.success) hkLoadAll();
                    } catch (e) {
                        showToast('خطا در ارتباط با سرور', 'error');
                    }
                },
                { danger: true, yesText: 'بله، ارسال کن', noText: 'انصراف' }
            );
        }

        document.addEventListener('DOMContentLoaded', function () {
            hkFillTimeSelects();
            hkLoadAll();
        });
    </script>
</body>

</html>
