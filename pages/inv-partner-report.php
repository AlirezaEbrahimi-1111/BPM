<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
// این گزارش را — علاوه بر تیم حسابداری — مدیران و سوپروایزرها هم می‌بینند.
if (!crmReportAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش همکاران - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/quick-add.js') ?>"></script>
    <script src="<?= asset('../../assets/js/money-shortcut.js') ?>"></script>
    <script src="<?= asset('../../assets/js/partner-percent-modal.js') ?>"></script>
    <script src="<?= asset('../../assets/js/assignee-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/multi-filter.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/invoice-payments.js') ?>"></script>

    <style>
        .crm-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: end;
            margin: 1rem 0 .5rem;
        }

        .crm-toolbar .fld {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .crm-toolbar .fld label {
            font-size: 11px;
            color: var(--text-muted, #6b7280);
        }

        .crm-toolbar .form-select {
            min-width: 130px;
        }

        .crm-toolbar .spacer {
            flex: 1;
        }

        .report-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            font-size: .9rem;
            margin: .25rem 0 .5rem;
            padding: 8px 12px;
            border-radius: 10px;
            background: rgba(142, 87, 254, .07);
        }

        .report-summary b {
            letter-spacing: 0;
            direction: ltr;
            unicode-bidi: isolate;
        }

        .num-ltr {
            direction: ltr;
            display: inline-block;
            letter-spacing: 0;
        }

        /* هم‌شکل با بج استاندارد پروژه (assets/css/custom.css: .status-badge) */
        .st-badge {
            /* display: inline-block; */
            padding: 7px 10px;
            border-radius: var(--badge-radius, 7px);
            font-size: 0.7rem;
            font-weight: var(--badge-font-weight, 500);
            text-align: center;
        }

        .st-badge.draft {
            background: rgba(107, 114, 128, .16);
            color: #4b5563;
        }

        .st-badge.approved {
            background: rgba(16, 122, 87, .14);
            color: #0f7a57;
        }

        .st-badge.cancelled {
            background: rgba(220, 38, 38, .14);
            color: #dc2626;
        }

        :root[data-theme="dark"] .st-badge.draft {
            color: #cbd5e1;
        }

        /* تسویه/مودیان دیگر <select> داخل سلول نیستند (کشویی‌شان بیرون
           فضای ردیف AG-Grid کلیپ می‌شد) — فقط بج وضعیت + دکمهٔ ویرایش
           که مودال جداگانه باز می‌کند. */
        /* هم‌شکل با بج استاندارد پروژه (assets/css/custom.css: .status-badge) */
        .pm-badge {
            /* display: inline-block; */
            padding: 7px 10px;
            border-radius: var(--badge-radius, 7px);
            font-size: 0.7rem;
            font-weight: var(--badge-font-weight, 500);
            text-align: center;
            white-space: nowrap;
        }

        .pm-badge.unsettled, .pm-badge.unregistered {
            background: rgba(107, 114, 128, .16);
            color: #4b5563;
        }

        .pm-badge.partial {
            background: rgba(245, 158, 11, .16);
            color: #b45309;
        }

        .pm-badge.settled, .pm-badge.registered {
            background: rgba(16, 122, 87, .14);
            color: #0f7a57;
        }

        :root[data-theme="dark"] .pm-badge.unsettled, :root[data-theme="dark"] .pm-badge.unregistered {
            color: #cbd5e1;
        }

        /* فاکتورِ ثبت‌شده در مؤدیان: به‌جای بج «ثبت شده»، خود کد پررنگ و سبز */
        .moadian-code {
            color: #0f7a57;
            font-weight: 700;
            font-size: 12px;
            direction: ltr;
            unicode-bidi: isolate;
            white-space: nowrap;
            cursor: copy;
        }

        :root[data-theme="dark"] .moadian-code {
            color: #68d391;
        }

        .ag-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 9px;
            border: 1px solid #e9e9e9;
            background: #fff;
            cursor: pointer;
            font-size: .8rem;
            color: #8e57fe;
            transition: background .12s, border-color .12s;
        }

        .ag-action-btn:hover {
            background: rgba(142, 87, 254, .12);
            border-color: rgba(142, 87, 254, .12);
        }

        :root[data-theme="dark"] .ag-action-btn {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .ag-action-btn:hover {
            background: rgba(142, 87, 254, .18);
        }

        /* ماه جاری در ویرایشگر درصدها برجسته شود */
        .pm-months .m.m-current input {
            border-color: #8e57fe;
            box-shadow: 0 0 0 .15rem rgba(142, 87, 254, .15);
        }

        .pm-cur-badge {
            background: rgba(142, 87, 254, .15);
            color: #8e57fe;
            font-size: 9px;
            padding: 1px 5px;
            border-radius: 8px;
            margin-inline-start: 3px;
        }

        .pm-months {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px 14px;
        }

        .pm-months .m {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .pm-months .m label {
            min-width: 62px;
            font-size: 12px;
        }

        .pm-months .m input {
            flex: 1;
        }

        /* ردیف «همکار / + / سال شمسی» در مودال مدیریت — سه کنترل هم‌ارتفاع */
        #pmSelPartner,
        #pmAddBtn,
        #pmSelYear {
            height: 38px;
        }

        :root[data-theme="dark"] .report-summary {
            background: rgba(142, 87, 254, .14);
        }

        .ag-theme-alpine,
        .ag-theme-alpine .ag-cell,
        .ag-theme-alpine .ag-header-cell,
        .ag-theme-alpine .ag-paging-panel {
            letter-spacing: 0 !important;
            word-spacing: 0 !important;
        }

        .site-footer { position: fixed !important; left: 0; right: 0; bottom: 0; top: auto !important; margin-top: 0 !important; z-index: 80; }
        body { padding-bottom: 46px; }
        .grid-fill { height: calc(100vh - 400px); min-height: 240px; }

        /* هاور استاندارد سایت (تینت بنفش نرم) — .btn-outline-secondary اصلا
           هاور اختصاصی نداشت (پیش‌فرض خاکستری بوت‌استرپ می‌ماند) */
        .btn-outline-primary:hover,
        .btn-outline-secondary:hover,
        .btn-outline-primary:active,
        .btn-outline-secondary:active,
        .btn-outline-primary.active,
        .btn-outline-secondary.active,
        .btn-outline-primary:active:focus,
        .btn-outline-secondary:active:focus {
            background: rgba(142, 87, 254, .12) !important;
            border-color: rgba(142, 87, 254, .12) !important;
            color: var(--primary, #8e57fe) !important;
            box-shadow: none !important;
        }

        :root[data-theme="dark"] .btn-outline-primary:hover,
        :root[data-theme="dark"] .btn-outline-secondary:hover,
        :root[data-theme="dark"] .btn-outline-primary:active,
        :root[data-theme="dark"] .btn-outline-secondary:active,
        :root[data-theme="dark"] .btn-outline-primary.active,
        :root[data-theme="dark"] .btn-outline-secondary.active {
            background: rgba(142, 87, 254, .18) !important;
            border-color: rgba(142, 87, 254, .18) !important;
        }

        /* نشان «درصد اختصاصی این فاکتور» کنار درصد سود */
        .pct-ov { font-size: .7rem; padding: 1px 5px; border-radius: 6px; margin-inline-start: 4px;
                  background: rgba(142, 87, 254, .12); color: var(--primary, #8e57fe); }
        :root[data-theme="dark"] .pct-ov { background: rgba(142, 87, 254, .18); }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container container-full" style="margin-top:70px">

        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-people"></i> گزارش همکاران</h1>
                <p>فاکتورهایی که به‌درخواست همکاران صادر شده — درصد و مبلغ سود، وضعیت ثبت/تسویه/مودیان</p>
            </div>
        </div>

        <div class="crm-toolbar">
            <div class="fld">
                <label>سال شمسی</label>
                <select id="fYear" class="form-select"></select>
            </div>
            <div class="fld">
                <label>ماه</label>
                <select id="fMonth" class="form-select">
                    <option value="">همهٔ ماه‌ها</option>
                </select>
            </div>
            <div class="fld">
                <label>همکار</label>
                <!-- انتخاب همکار با جستجو — همان AssigneePicker فیلترهای کارها (درخواست ۱۴۰۵/۰۸/۰۲) -->
                <div id="fPartnerPicker" style="min-width:210px"></div>
            </div>
            <!-- فیلتر «نوع سند» حذف شد: پیش‌فاکتور سود همکار ندارد و در این گزارش نمی‌آید (۱۴۰۵/۰۸/۰۱) -->
            <div class="fld">
                <label>وضعیت فاکتور</label>
                <!-- چندانتخابی (assets/js/multi-filter.js، ۱۴۰۵/۰۸/۰۲) — بدون انتخاب = به‌جز باطل‌شده -->
                <div id="fStatus" style="min-width:200px"></div>
            </div>
            <div class="spacer"></div>
            <button class="btn btn-outline-secondary" id="btnManagePartners"><i class="bi bi-gear ms-1"></i> مدیریت همکاران و درصدها</button>
        </div>

        <div class="report-summary" id="summary">
            <span>مجموع مبلغ فاکتور: <b id="sInvoice">۰</b></span>
            <span>مجموع مبلغ سود: <b id="sProfit">۰</b></span>
            <span>واریز شده: <b id="sPaid">۰</b></span>
            <span>مانده: <b id="sRemain">۰</b></span>
            <span>تعداد ردیف: <b id="sCount">۰</b></span>
        </div>

        <div id="repGrid" class="ag-theme-alpine grid-fill inv-grid" style="width:100%;"></div>
    </div>

    <!-- ── مودال مدیریت همکاران و درصدهای ماهانه ── -->
    <div class="modal fade" id="pmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">مدیریت همکاران و درصد سود ماهانه</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div id="pmAlert"></div>

                    <div class="row g-2 align-items-end mb-2">
                        <div class="col">
                            <label class="form-label">همکار</label>
                            <div class="d-flex gap-2">
                                <select id="pmSelPartner" class="form-select"></select>
                                <button id="pmAddBtn" class="btn btn-outline-primary flex-shrink-0" title="افزودن همکار جدید"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </div>
                        <div class="col-auto"><label class="form-label">سال شمسی</label><select id="pmSelYear" class="form-select"></select></div>
                    </div>

                    <div id="pmPartnerEdit" hidden>
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col"><label class="form-label">نام</label><input id="pmEditName" class="form-control"></div>
                            <div class="col-4"><label class="form-label">تلفن</label><input id="pmEditPhone" class="form-control"></div>
                            <div class="col-auto">
                                <div class="form-check mt-4">
                                    <input type="checkbox" id="pmEditActive" class="form-check-input">
                                    <label class="form-check-label" for="pmEditActive">فعال</label>
                                </div>
                            </div>
                            <div class="col-auto"><button id="pmSaveBtn" class="btn btn-outline-primary">ذخیرهٔ مشخصات</button></div>
                        </div>

                        <label class="form-label">درصد سهم سود در هر ماه <span class="text-muted">(٪ از مبلغ پیش از مالیات)</span></label>
                        <div id="pmMonths" class="pm-months"></div>
                        <p class="text-muted mt-2" style="font-size:12px">
                            درصد هر ماه روی همهٔ فاکتورهای همان ماه اثر می‌گذارد — صادرشده و بعدی. تغییر با از دست دادن فوکوس ذخیره می‌شود.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── مودال ویرایش وضعیت یک فاکتور (تسویه/مودیان) ──
         عمدا به‌جای <select> داخل سلول جدول: کشویی آن داخل ردیف
         AG-Grid کلیپ می‌شد و بخشی از گزینه‌ها دیده نمی‌شد. -->
    <div class="modal fade" id="pmStatusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ویرایش وضعیت فاکتور <span id="pmStIdLbl"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div id="pmStAlert"></div>
                    <!-- سند سود همکار (قبلا سوئیچ داخل ستون «ثبت سود» جدول).
                         form-check عمدا نیست: padding/margin منفی‌اش در RTL متن را روی سوئیچ می‌انداخت. -->
                    <div class="mb-3">
                        <div class="form-switch d-flex align-items-center m-0" style="gap:8px;padding:0">
                            <input class="form-check-input m-0" type="checkbox" id="pmStProfit" role="switch" style="margin:0;float:none;cursor:pointer">
                            <label class="form-check-label m-0" for="pmStProfit" style="cursor:pointer;user-select:none">سند سود همکار در حسابداری شایگان ثبت شد</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">وضعیت تسویه</label>
                        <div class="d-flex align-items-center flex-wrap" style="gap:8px">
                            <span id="pmStSettleInfo"></span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="pmStPaymentsBtn"><i class="bi bi-cash-coin ms-1"></i>واریزها</button>
                        </div>
                        <div class="form-text">از روی مبالغ واریزشده خودکار حساب می‌شود.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">وضعیت مودیان</label>
                        <select id="pmStMoadian" class="form-select">
                            <option value="unregistered">ثبت نشده</option>
                            <option value="registered">ثبت شده</option>
                        </select>
                    </div>
                    <div class="mb-1" id="pmStCodeWrap">
                        <label class="form-label">کد ثبت در سامانه</label>
                        <input id="pmStCode" class="form-control" placeholder="کد ثبت در سامانهٔ مودیان">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="pmStSaveBtn">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        /* ───────────────────────────────────────────────────────────────
         *  راهنمای مبتدی (لایه‌ها):
         *   • این صفحه فقط «نمایش» است؛ داده از سرویس Go می‌آید:
         *       GET  /crm/api/inv/partner-report      → ردیف‌های گزارش
         *       GET  /crm/api/inv/partners            → فهرست همکاران
         *       GET/PUT /crm/api/inv/partners/{id}/shares  → درصد ماه‌ها
         *       PATCH /crm/api/inv/invoices/{id}/partner-status → ۳ ستون وضعیت
         *   • «مبلغ سود» در سرور حساب می‌شود: درصد ماه صدور × (جمع منهای تخفیف).
         *     پس با تغییر درصد یک ماه، سود همهٔ فاکتورهای آن ماه عوض می‌شود.
         *   • اگر چیزی نمایش داده نشد: F12 → Console و Network را ببین؛
         *     پاسخ ۴۰۳ یعنی دسترسی نداری، ۵۰۰ یعنی خطای سرور.
         * ─────────────────────────────────────────────────────────────── */
        const API = '/crm/api';
        const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
            'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
        ];
        const DOC_LABEL = {
            official: 'فاکتور رسمی',
            proforma: 'پیش‌فاکتور'
        };
        const ST_LABEL = {
            draft: 'پیش‌نویس',
            approved: 'تأییدشده',
            cancelled: 'باطل'
        };

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function ahj() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            };
        }

        function money(n) {
            return faDigits(String(Math.round(Number(n) || 0).toLocaleString('en-US')));
        }

        function jDate(g) {
            if (!g) return '—';
            try {
                return new Date(g).toLocaleDateString('fa-IR', {
                    timeZone: 'Asia/Tehran'
                });
            } catch (e) {
                return faDigits(g);
            }
        }

        async function apiGet(path) {
            const r = await fetch(API + path, {
                headers: {
                    'Authorization': 'Bearer ' + tok()
                }
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        async function apiSend(method, path, body) {
            const r = await fetch(API + path, {
                method,
                headers: ahj(),
                body: body ? JSON.stringify(body) : undefined
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        // سال و ماه شمسی امروز
        function currentJYM() {
            try {
                const t = new Date();
                const g = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' +
                    String(t.getDate()).padStart(2, '0');
                const j = (typeof convertToJalali === 'function') ? convertToJalali(g) : '';
                const parts = String(j).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).split(/[\/\-]/);
                const y = parseInt(parts[0], 10),
                    m = parseInt(parts[1], 10);
                return {
                    jy: (y > 1300 && y < 1500) ? y : 1404,
                    jm: (m >= 1 && m <= 12) ? m : 1,
                };
            } catch (e) {
                return {
                    jy: 1404,
                    jm: 1
                };
            }
        }

        const CUR_JYM = currentJYM();
        const CUR_JY = CUR_JYM.jy;
        const CUR_JM = CUR_JYM.jm;
        let gridApi = null,
            allRows = [],
            partners = [];

        function yearOptions(sel, from, to, selected) {
            sel.innerHTML = '';
            for (let y = to; y >= from; y--) {
                const o = document.createElement('option');
                o.value = y;
                o.textContent = faDigits(y);
                if (y === selected) o.selected = true;
                sel.appendChild(o);
            }
        }

        const MOADIAN_LABEL = {
            unregistered: 'ثبت نشده',
            registered: 'ثبت شده'
        };

        // فقط نمایش — ویرایش از مودال pmStatusModal (pmEditStatus) انجام می‌شود.
        // بج تسویه + «واریز/مانده»؛ کلیک = پنجرهٔ واریزها (assets/js/invoice-payments.js)
        function settleCell(p) {
            return InvoicePayments.cellHtml(p.data, `pmOpenPayments(${p.data.id})`);
        }

        // ماندهٔ یک ردیف: «تسویه شده»ِ قدیمیِ بدون واریز = صفر
        function rowRemaining(r) {
            if (r.status === 'cancelled') return 0;
            if (r.settlement_status === 'settled' && !(r.payment_count > 0)) return 0;
            return Math.max(0, (Number(r.invoice_amount) || 0) - (Number(r.paid_amount) || 0));
        }

        function pmOpenPayments(id) {
            const d = allRows.find(r => r.id === id);
            if (!d) return;
            InvoicePayments.open({
                invoiceId: id,
                title: (d.number ? 'فاکتور شماره ' + faDigits(d.number) : 'پیش‌نویس #' + faDigits(d.id)) + ' — ' + (d.customer_name || ''),
                api: apiSend,
                onChanged: s => {
                    d.settlement_status = s.settlement_status;
                    d.paid_amount = s.paid_amount;
                    d.payment_count = s.payment_count;
                    gridApi.refreshCells({ force: true });
                    renderSummary();
                    if (pmStRow && pmStRow.id === id) pmStRenderSettle();
                },
            });
        }

        function moadianCell(p) {
            const d = p.data;
            const v = d.moadian_status || 'unregistered';
            // ثبت‌شده با کد: فقط خود کد، پررنگ و سبز (بج «ثبت شده» حذف شد). ثبت‌شده‌ی بی‌کد
            // (نباید پیش بیاید — سرور ثبت بی‌کد را رد می‌کند) و ثبت‌نشده همان بج قبلی را دارند.
            if (v === 'registered' && d.moadian_code) {
                const code = esc(d.moadian_code); // رقم انگلیسی، همان‌طور که در سامانه است (برای کپی)
                // کلیک = کپی در کلیپ‌بورد (در onRowDoubleClicked مستثناست تا فاکتور باز نشود)
                return `<span class="moadian-code" role="button" title="کد ثبت در سامانه‌ی مؤدیان — برای کپی کلیک کنید"
                    onclick="AgGridFa.copyText(this.textContent.trim(), 'کد مؤدیان در کلیپ‌بورد کپی شد')">${code}</span>`;
            }
            return `<span class="pm-badge ${v}">${MOADIAN_LABEL[v] || v}</span>`;
        }

        function statusActionCell(p) {
            let h = `<button class="ag-action-btn" title="سند سود همکار / تسویه / مودیان" onclick="pmEditStatus(${p.data.id})"><i class="bi bi-pencil"></i></button>`;
            h += `<button class="ag-action-btn" style="color:#0f7a57" title="واریزها و تسویه" onclick="pmOpenPayments(${p.data.id})"><i class="bi bi-cash-coin"></i></button>`;
            // درصد سهم همکار — هر وضعیتی جز باطل (حتی تأییدشده)
            if (p.data.status !== 'cancelled') {
                h += `<button class="ag-action-btn" style="color:#8e57fe" title="درصد سهم همکار" onclick="pmEditPct(${p.data.id})"><i class="bi bi-percent"></i></button>`;
            }
            return h;
        }

        // پنجرهٔ مشترک «درصد سهم همکار» (assets/js/partner-percent-modal.js)
        function pmEditPct(id) {
            const d = allRows.find(r => r.id === id);
            if (!d) return;
            PartnerPercentModal.open({
                title: d.number ? 'فاکتور شماره ' + faDigits(d.number) : 'پیش‌نویس #' + faDigits(d.id),
                partnerName: d.partner_name,
                monthPercent: d.month_percent,
                // گزارش مقدار استثنا را جدا نمی‌دهد؛ وقتی استثنا هست، درصد مؤثر همان است
                override: d.percent_is_override ? d.percent : null,
                preTax: d.pre_tax_amount,
                profitRecorded: !!d.partner_profit_recorded,
                save: p => apiSend('PATCH', '/inv/invoices/' + id + '/partner-percent', { percent: p }),
                onSaved: loadReport,
            });
        }

        const colDefs = [{
                headerName: 'نوع سند',
                field: 'doc_type',
                width: 110,
                cellRenderer: p => DOC_LABEL[p.value] || p.value
            },
            {
                headerName: 'شمارهٔ فاکتور',
                field: 'number',
                width: 130,
                comparator: AgGridFa.numericTextComparator, // عددی، نه حرف‌به‌حرف (۲ قبل از ۱۰)
                sort: 'desc',
                sortIndex: 1, // در یک تاریخ، شماره‌ی بزرگ‌تر بالا
                cellRenderer: p => p.value ? faDigits(p.value) :
                    `<span class="st-badge draft">${ST_LABEL[p.data.status]||'—'}</span>`
            },
            {
                headerName: 'مشتری',
                field: 'customer_name',
                flex: 1.4,
                minWidth: 140
            },
            {
                headerName: 'تاریخ صدور',
                field: 'issue_date',
                width: 115,
                sort: 'desc', // پیش‌فرض: جدیدترین بالا (تاریخ، بعد شماره)
                sortIndex: 0,
                cellRenderer: p => jDate(p.value)
            },
            {
                headerName: 'همکار',
                field: 'partner_name',
                flex: 1.2,
                minWidth: 130
            },
            {
                headerName: 'درصد سود',
                field: 'percent',
                width: 125,
                type: 'rightAligned',
                cellRenderer: p => faDigits(p.value || 0) + '٪' +
                    (p.data.percent_is_override ?
                        '<span class="pct-ov" title="این فاکتور درصد اختصاصی دارد و از درصد ماه پیروی نمی‌کند">اختصاصی</span>' : '')
            },
            {
                headerName: 'مبلغ فاکتور (ریال)',
                field: 'invoice_amount',
                width: 150,
                type: 'rightAligned',
                cellRenderer: p => money(p.value)
            },
            {
                headerName: 'مبلغ سود (ریال)',
                field: 'profit_amount',
                width: 145,
                type: 'rightAligned',
                cellRenderer: p => money(p.value)
            },
            {
                headerName: 'تسویه',
                field: 'settlement_status',
                width: 205,
                sortable: false,
                cellRenderer: settleCell
            },
            {
                headerName: 'مودیان',
                field: 'moadian_status',
                width: 215,
                sortable: false,
                cellRenderer: moadianCell
            },
            {
                headerName: '',
                colId: 'actions', // ستون عملیات (برای منوی کلیک راست ردیف)
                width: 120,
                sortable: false,
                filter: false,
                cellRenderer: statusActionCell
            },
        ];

        const gridOptions = {
            theme: AgGridFa.theme(),
            columnDefs: colDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [20, 50, 100],
            onPaginationChanged: () => AgGridFa.persianizePaging(),
            rowStyle: { cursor: 'pointer' },
            // کلیک = فقط انتخاب ردیف؛ دوبار کلیک = رفتن به حالت چاپ فاکتور (درخواست ۱۴۰۵/۰۷/۲۷).
            // دکمه‌های عملیات، کنترل‌های فرم و کد مؤدیان (کپی) مستثنا هستند.
            rowSelection: { mode: 'singleRow', checkboxes: false, enableClickSelection: true },
            onRowDoubleClicked: (e) => {
                if (e.event && e.event.target.closest && e.event.target.closest('.ag-action-btn, input, .form-check, .moadian-code, .ipm-cell')) return;
                // دوبار کلیک یک کلمه را هم انتخاب می‌کند — پاکش کن (جلوی رفتن را نگیرد)
                if (window.getSelection) window.getSelection().removeAllRanges();
                location.href = '/pages/inv-invoice-print.php?id=' + e.data.id;
            },
            defaultColDef: {
                resizable: true,
                sortable: true
            },
            overlayNoRowsTemplate: '<span class="text-muted">ردیفی برای این فیلترها نیست</span>',
        };

        // همکار انتخاب‌شده در فیلتر ('' = همه) — assets/js/assignee-picker.js
        let filterPartnerId = '', statusFilter = null;
        let partnerPicker = null;

        function qs() {
            const p = new URLSearchParams();
            const y = document.getElementById('fYear').value;
            const m = document.getElementById('fMonth').value;
            const pr = filterPartnerId;
            const st = statusFilter ? statusFilter.getSelected().join(',') : '';
            if (y) p.set('jy', y);
            if (m) p.set('jm', m);
            if (pr) p.set('partner_id', pr);
            if (st) p.set('status', st);
            return p.toString();
        }

        function renderSummary() {
            let inv = 0,
                prof = 0,
                paid = 0,
                rem = 0;
            allRows.forEach(r => {
                inv += Number(r.invoice_amount) || 0;
                prof += Number(r.profit_amount) || 0;
                paid += Number(r.paid_amount) || 0;
                rem += rowRemaining(r);
            });
            document.getElementById('sInvoice').textContent = money(inv);
            document.getElementById('sProfit').textContent = money(prof);
            document.getElementById('sPaid').textContent = money(paid);
            document.getElementById('sRemain').textContent = money(rem);
            document.getElementById('sCount').textContent = faDigits(allRows.length);
        }

        async function loadReport() {
            gridApi.showLoadingOverlay();
            try {
                const d = await apiGet('/inv/partner-report?' + qs());
                allRows = d.items || [];
                gridApi.setGridOption('rowData', allRows);
                renderSummary();
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                gridApi.setGridOption('rowData', []);
            }
        }

        async function apiPatch(path, body) {
            return apiSend('PATCH', path, body);
        }

        // ── ویرایش تسویه/مودیان از مودال (نه <select> داخل سلول — کشویی
        //    آن داخل ردیف AG-Grid کلیپ می‌شد و بخشی از گزینه‌ها دیده نمی‌شد) ──
        let pmStRow = null;

        function pmStToggleCode() {
            const reg = document.getElementById('pmStMoadian').value === 'registered';
            document.getElementById('pmStCodeWrap').hidden = !reg;
        }

        function pmEditStatus(id) {
            const row = allRows.find(r => r.id === id);
            if (!row) return;
            pmStRow = row;
            document.getElementById('pmStIdLbl').textContent = row.number ? ('#' + faDigits(row.number)) : '';
            pmStRenderSettle();
            document.getElementById('pmStMoadian').value = row.moadian_status || 'unregistered';
            document.getElementById('pmStCode').value = row.moadian_code || '';
            document.getElementById('pmStProfit').checked = !!row.partner_profit_recorded;
            pmStToggleCode();
            document.getElementById('pmStAlert').innerHTML = '';
            new bootstrap.Modal(document.getElementById('pmStatusModal')).show();
        }

        // وضعیت تسویه فقط نمایشی است (از جمع واریزها) — همان سلول جدول
        function pmStRenderSettle() {
            document.getElementById('pmStSettleInfo').innerHTML = pmStRow ? InvoicePayments.cellHtml(pmStRow, '') : '';
        }

        async function pmSaveStatus() {
            if (!pmStRow) return;
            const moadian = document.getElementById('pmStMoadian').value;
            const code = document.getElementById('pmStCode').value.trim();
            const alertBox = msg => document.getElementById('pmStAlert').innerHTML =
                msg ? `<div class="alert alert-danger py-2">${msg}</div>` : '';
            if (moadian === 'registered' && !code) {
                alertBox('برای «مودیان ثبت‌شده» کد ثبت در سامانه لازم است.');
                return;
            }
            alertBox('');
            const profit = document.getElementById('pmStProfit').checked;
            try {
                await apiPatch('/inv/invoices/' + pmStRow.id + '/partner-status', {
                    partner_profit_recorded: profit,
                    moadian_status: moadian,
                    moadian_code: moadian === 'registered' ? code : '',
                });
                pmStRow.partner_profit_recorded = profit;
                pmStRow.moadian_status = moadian;
                pmStRow.moadian_code = moadian === 'registered' ? code : '';
                gridApi.setGridOption('rowData', allRows);
                bootstrap.Modal.getInstance(document.getElementById('pmStatusModal')).hide();
                showToast('ذخیره شد', 'success');
            } catch (e) {
                alertBox(e.message || 'خطا');
            }
        }

        // ───────── همکاران ─────────
        async function loadPartners() {
            const out = [];
            try {
                let page = 1,
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/inv/partners?per=200&page=' + page);
                    out.push(...(d.items || []));
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
            } catch (e) {}
            partners = out;

            // همکار غیرفعال با برچسب «غیرفعال» زیر نامش (از مسیر «واحد» خود picker)
            if (partnerPicker) partnerPicker.updateData(
                partners.map(p => ({ id: p.id, full_name: p.name || '', activity_section: p.is_active ? '' : 'inactive' })),
                undefined, { inactive: 'غیرفعال' });

            const sp = document.getElementById('pmSelPartner');
            const keep2 = sp.value;
            sp.innerHTML = '<option value="">— انتخاب همکار —</option>' +
                partners.map(p => `<option value="${p.id}">${(p.name||'').replace(/</g,'&lt;')}${p.is_active?'':' (غیرفعال)'}</option>`).join('');
            sp.value = keep2;
        }

        function pmAlert(msg, kind = 'danger') {
            document.getElementById('pmAlert').innerHTML =
                msg ? `<div class="alert alert-${kind} py-2">${msg}</div>` : '';
        }

        // افزودن همکار جدید با مودال استاندارد؛ فعال‌بودن/درصدها را بعد از انتخاب پایین ویرایش می‌کنی.
        function pmAddPartner() {
            QuickAdd.partner(async p => {
                pmAlert('همکار افزوده شد — درصدها را پایین تنظیم کن.', 'success');
                await loadPartners();
                document.getElementById('pmSelPartner').value = p.id;
                pmOnSelectPartner();
            });
        }

        function pmCurrentPartner() {
            const id = +document.getElementById('pmSelPartner').value || 0;
            return partners.find(p => p.id === id) || null;
        }

        async function pmOnSelectPartner() {
            const p = pmCurrentPartner();
            const box = document.getElementById('pmPartnerEdit');
            if (!p) {
                box.hidden = true;
                return;
            }
            box.hidden = false;
            document.getElementById('pmEditName').value = p.name || '';
            document.getElementById('pmEditPhone').value = p.phone || '';
            document.getElementById('pmEditActive').checked = !!p.is_active;
            await pmLoadShares();
        }

        async function pmSavePartner() {
            const p = pmCurrentPartner();
            if (!p) return;
            try {
                await apiSend('PUT', '/inv/partners/' + p.id, {
                    name: document.getElementById('pmEditName').value.trim(),
                    phone: document.getElementById('pmEditPhone').value.trim(),
                    is_active: document.getElementById('pmEditActive').checked,
                });
                pmAlert('ذخیره شد.', 'success');
                await loadPartners();
                document.getElementById('pmSelPartner').value = p.id;
            } catch (e) {
                pmAlert(e.message || 'خطا');
            }
        }

        async function pmLoadShares() {
            const p = pmCurrentPartner();
            const jy = +document.getElementById('pmSelYear').value || CUR_JY;
            const wrap = document.getElementById('pmMonths');
            wrap.innerHTML = '';
            if (!p) return;
            let months = [];
            try {
                const d = await apiGet('/inv/partners/' + p.id + '/shares?jy=' + jy);
                months = d.months || [];
            } catch (e) {
                pmAlert(e.message || 'خطا در خواندن درصدها');
            }
            const byM = {};
            months.forEach(m => byM[m.jm] = m.percent);
            for (let m = 1; m <= 12; m++) {
                const isCur = (jy === CUR_JY && m === CUR_JM);
                const div = document.createElement('div');
                div.className = 'm' + (isCur ? ' m-current' : '');
                div.innerHTML = `<label>${MONTHS[m-1]}${isCur ? ' <span class="pm-cur-badge">ماه جاری</span>' : ''}</label>
                    <input type="number" step="0.001" min="0" max="100" class="form-control form-control-sm pm-month-inp"
                        data-jm="${m}" value="${byM[m] != null ? byM[m] : ''}" placeholder="۰">`;
                wrap.appendChild(div);
            }
            wrap.querySelectorAll('.pm-month-inp').forEach(inp => {
                inp.addEventListener('change', () => pmSaveShare(inp));
            });
        }

        async function pmSaveShare(inp) {
            const p = pmCurrentPartner();
            if (!p) return;
            const jy = +document.getElementById('pmSelYear').value || CUR_JY;
            const jm = +inp.dataset.jm;
            let percent = parseFloat(String(inp.value).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));
            if (isNaN(percent)) percent = 0;
            if (percent < 0 || percent > 100) {
                pmAlert('درصد باید بین ۰ تا ۱۰۰ باشد.');
                return;
            }
            try {
                await apiSend('PUT', '/inv/partners/' + p.id + '/shares', {
                    jy,
                    jm,
                    percent
                });
                pmAlert('درصد ' + MONTHS[jm - 1] + ' ذخیره شد.', 'success');
            } catch (e) {
                pmAlert(e.message || 'خطا');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }

            yearOptions(document.getElementById('fYear'), CUR_JY - 3, CUR_JY + 1, CUR_JY);
            // مودال: سال جاری پیش‌فرض، فقط سال قبل تا سال بعد
            yearOptions(document.getElementById('pmSelYear'), CUR_JY - 1, CUR_JY + 1, CUR_JY);
            const fm = document.getElementById('fMonth');
            for (let m = 1; m <= 12; m++) {
                const o = document.createElement('option');
                o.value = m;
                o.textContent = MONTHS[m - 1];
                fm.appendChild(o);
            }

            gridApi = agGrid.createGrid(document.getElementById('repGrid'), gridOptions);
            // کلیک راست روی ردیف = منوی همان موارد ستون عملیات (assets/js/ag-grid-fa.js)
            AgGridFa.rowContextMenu(document.getElementById('repGrid'), gridApi);

            statusFilter = MultiFilter.create({
                container: '#fStatus', allLabel: 'به‌جز باطل‌شده', unit: 'وضعیت', onChange: loadReport,
                persistKey: 'inv-partner-report', // بعد از رفتن/برگشتن و رفرش می‌ماند (۱۴۰۵/۰۸/۰۳)
                groups: { settle: 'تسویه', record: 'ثبت' },
                options: [
                    { value: 'draft', label: 'پیش‌نویس', group: 'status' },
                    { value: 'approved', label: 'تأییدشده', group: 'status' },
                    { value: 'cancelled', label: 'باطل‌شده', group: 'status' },
                    { value: 'settled', label: 'تسویه شده', group: 'settle' },
                    { value: 'unsettled', label: 'تسویه نشده', group: 'settle' },
                    { value: 'profit_unrecorded', label: 'ثبت نشده سود شایگان', group: 'record' },
                    { value: 'moadian_unregistered', label: 'ثبت نشده مودیان', group: 'record' },
                ],
            });
            ['fYear', 'fMonth'].forEach(id =>
                document.getElementById(id).addEventListener('change', loadReport));
            partnerPicker = AssigneePicker.create({
                container: '#fPartnerPicker',
                users: [],
                showSections: false,
                placeholder: 'همهٔ همکاران', // = حالت فیلتر (بدون «خودم»)
                usersGroupLabel: 'همکاران',
                onSelect: (type, value) => {
                    filterPartnerId = value || '';
                    loadReport();
                }
            });

            document.getElementById('btnManagePartners').addEventListener('click', () => {
                pmAlert('');
                new bootstrap.Modal(document.getElementById('pmModal')).show();
            });
            document.getElementById('pmModal').addEventListener('shown.bs.modal', () => {
                document.getElementById('pmSelPartner').focus();
            });
            document.getElementById('pmStatusModal').addEventListener('shown.bs.modal', () => {
                document.getElementById('pmStProfit').focus();
            });
            document.getElementById('pmStPaymentsBtn').addEventListener('click', () => {
                // پنجرهٔ وضعیت بسته شود تا دو پنجره روی هم نیفتند
                if (!pmStRow) return;
                const id = pmStRow.id;
                bootstrap.Modal.getInstance(document.getElementById('pmStatusModal')).hide();
                pmOpenPayments(id);
            });
            document.getElementById('pmAddBtn').addEventListener('click', pmAddPartner);
            document.getElementById('pmSelPartner').addEventListener('change', pmOnSelectPartner);
            document.getElementById('pmSelYear').addEventListener('change', pmLoadShares);
            document.getElementById('pmSaveBtn').addEventListener('click', pmSavePartner);
            document.getElementById('pmModal').addEventListener('hidden.bs.modal', loadReport);

            document.getElementById('pmStMoadian').addEventListener('change', pmStToggleCode);
            document.getElementById('pmStSaveBtn').addEventListener('click', pmSaveStatus);

            loadPartners().then(loadReport);
        });
    </script>
</body>

</html>
