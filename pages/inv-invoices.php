<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// فعلا فقط کاربر id=1 — ماژول فاکتور در حال ساخت است.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
if (!crmModuleAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاکتورهای فروش - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/partner-percent-modal.js') ?>"></script>
    <script src="<?= asset('../../assets/js/month-filter.js') ?>"></script>

    <style>
        .crm-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin: 1rem 0 .75rem;
        }

        .crm-toolbar .crm-search {
            flex: 1;
            min-width: 200px;
            max-width: 320px;
        }

        .crm-toolbar .spacer {
            flex: 1;
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
            margin: 0 2px;
            transition: background .12s, border-color .12s;
        }

        .ag-action-btn:hover {
            background: rgba(142, 87, 254, .12);
            border-color: rgba(142, 87, 254, .12);
        }

        .ag-action-btn.edit {
            color: #8e57fe;
        }

        .ag-action-btn.approve {
            color: #0f7a57;
        }

        .ag-action-btn.cancel {
            color: #d97706;
        }

        .ag-action-btn.del {
            color: #dc2626;
        }

        .ag-action-btn.print {
            color: #2563eb;
        }

        .ag-action-btn.view {
            color: #0f7a57;
        }

        /* هر ردیف فاکتور با کلیک باز می‌شود (به‌جز کلیک روی دکمه‌های عملیات) */
        .ag-theme-alpine .ag-row {
            cursor: pointer;
        }

        .ag-action-btn.profit {
            color: #6b7280;
        }

        .profit-st {
            font-size: 11px;
            color: #6b7280;
        }

        .profit-st.done {
            color: #0f7a57;
            font-weight: 700;
        }

        :root[data-theme="dark"] .profit-st {
            color: #9aa3b8;
        }

        :root[data-theme="dark"] .profit-st.done {
            color: #34d399;
        }

        :root[data-theme="dark"] .ag-action-btn {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-strong);
        }

        :root[data-theme="dark"] .ag-action-btn:hover {
            background: rgba(142, 87, 254, .18);
        }

        /* هم‌شکل با بج استاندارد پروژه (assets/css/custom.css: .status-badge) —
           فقط رنگ‌ها مخصوص وضعیت فاکتور هستند، شکل/اندازه یکسان شد */
        .st-badge {
            /* display: inline-block; */
            padding: 7px 10px;
            border-radius: var(--badge-radius, 7px);
            font-size: 0.7rem;
            font-weight: var(--badge-font-weight, 500);
            text-align: center;
        }

        .num-ltr {
            direction: ltr;
            display: inline-block;
            letter-spacing: 0;
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

        /* بج وضعیت تسویه/مودیان — فقط نمایش؛ ویرایش از مودال جداگانه
           (نه <select> داخل سلول جدول، چون کشویی‌اش داخل ردیف AG-Grid
           کلیپ می‌شود و بخشی از گزینه‌ها دیده نمی‌شود). */
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

        .ag-theme-alpine,
        .ag-theme-alpine .ag-cell,
        .ag-theme-alpine .ag-header-cell,
        .ag-theme-alpine .ag-paging-panel {
            letter-spacing: 0 !important;
            word-spacing: 0 !important;
        }

        .crm-toolbar .sb-trigger {
            height: 38px !important;
        }
        /* فوتر ثابت ته پنجره (نه sticky) تا همیشه دیده شود و صفحه اسکرول نخورد.
           جدول دقیقا فضای بین تولبار و فوتر را می‌گیرد؛ ردیف‌های زیاد
           داخل خود جدول اسکرول می‌شوند، نه کل صفحه. */
        .site-footer { position: fixed !important; left: 0; right: 0; bottom: 0; top: auto !important; margin-top: 0 !important; z-index: 80; }
        body { padding-bottom: 46px; }
        .grid-fill { height: calc(100vh - 340px); min-height: 260px; padding-top: 1rem; }

        /* نشان «درصد اختصاصی این فاکتور» کنار درصد سود همکار */
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
                <h1><i class="bi bi-receipt"></i> فاکتورهای فروش</h1>
                <p>صدور، تأیید و چاپ فاکتور فروش — سازمان ۱</p>
            </div>
        </div>

        <div class="crm-toolbar">
            <input type="text" id="crmSearch" class="form-control crm-search" placeholder="جست‌وجو در شماره، مشتری، همکار یا مبلغ…">
            <select id="stFilter" class="form-select" style="max-width:170px">
                <option value="">همه‌ی وضعیت‌ها</option>
                <option value="draft">پیش‌نویس</option>
                <option value="approved">تأییدشده</option>
                <option value="cancelled">باطل‌شده</option>
            </select>
            <!-- فیلتر ماه (چندانتخابی): پیش‌فرض ماه جاری — assets/js/month-filter.js -->
            <div id="monthFilter" style="width:190px"></div>
            <div class="spacer"></div>
            <a class="btn btn-primary" href="/pages/inv-invoice-edit.php"><i class="bi bi-plus-lg ms-1"></i> فاکتور جدید</a>
        </div>

        <div id="invGrid" class="ag-theme-alpine grid-fill inv-grid" style="width:100%;"></div>
    </div>

    <!-- ── مودال ویرایش وضعیت تسویه/مودیان (نه <select> داخل سلول جدول) ── -->
    <div class="modal fade" id="stModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ویرایش وضعیت فاکتور <span id="stIdLbl"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div id="stAlert"></div>
                    <!-- سند سود همکار (قبلا سوئیچ داخل ستون «ثبت سود» جدول) — فقط فاکتورِ دارای همکار.
                         form-check عمدا نیست: padding/margin منفی‌اش در RTL متن را روی سوئیچ می‌انداخت. -->
                    <div class="mb-3" id="stProfitWrap" hidden>
                        <div class="form-switch d-flex align-items-center m-0" style="gap:8px;padding:0">
                            <input class="form-check-input m-0" type="checkbox" id="stProfit" role="switch" style="margin:0;float:none;cursor:pointer">
                            <label class="form-check-label m-0" for="stProfit" style="cursor:pointer;user-select:none">سند سود همکار در حسابداری شایگان ثبت شد</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">وضعیت تسویه</label>
                        <select id="stSettlement" class="form-select">
                            <option value="unsettled">تسویه نشده</option>
                            <option value="settled">تسویه شده</option>
                            <option value="partial">بخشی تسویه شده</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">وضعیت مودیان</label>
                        <select id="stMoadian" class="form-select">
                            <option value="unregistered">ثبت نشده</option>
                            <option value="registered">ثبت شده</option>
                        </select>
                    </div>
                    <div class="mb-1" id="stCodeWrap">
                        <label class="form-label">کد ثبت در سامانه</label>
                        <input id="stCode" class="form-control" placeholder="کد ثبت در سامانهٔ مودیان">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="stSaveBtn">ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const API = '/crm/api';

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function ahj() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            };
        }

        // تاریخ میلادی "YYYY-MM-DD" → شمسی با رقم فارسی
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

        function money(n) {
            return faDigits(String(Math.round(Number(n) || 0).toLocaleString('en-US')));
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

        const ST_LABEL = {
            draft: 'پیش‌نویس',
            approved: 'تأییدشده',
            cancelled: 'باطل'
        };
        const TYPE_LABEL = {
            official: 'رسمی',
            proforma: 'پیش‌فاکتور'
        };
        let monthFilter = null, monthFilterInit = false;
        let gridApi = null,
            allRows = [],
            searchTimer;

        function actionCell(p) {
            const d = p.data;
            let h = `<button class="ag-action-btn print" title="چاپ" onclick="location.href='/pages/inv-invoice-print.php?id=${d.id}&print=1'"><i class="bi bi-printer"></i></button>`;
            if (d.status === 'draft') {
                h += `<button class="ag-action-btn edit" title="ویرایش" onclick="location.href='/pages/inv-invoice-edit.php?id=${d.id}'"><i class="bi bi-pencil"></i></button>`;
                h += `<button class="ag-action-btn approve" title="تأیید و صدور" onclick="approveInv(${d.id})"><i class="bi bi-check2-circle"></i></button>`;
                h += `<button class="ag-action-btn del" title="حذف" onclick="deleteInv(${d.id})"><i class="bi bi-trash"></i></button>`;
            } else if (d.status === 'approved') {
                // برگرداندن به پیش‌نویس برای ویرایش — پیش‌فاکتور تبدیل‌شده ندارد؛
                // فاکتور ثبت‌شده در مودیان دکمه را کم‌رنگ می‌بیند و با کلیک دلیلش را می‌خواند
                if (!d.converted_to_id) {
                    const locked = d.moadian_status === 'registered';
                    h += `<button class="ag-action-btn edit" ${locked ? 'style="opacity:.4"' : ''} title="${locked ? 'در مودیان ثبت شده — قابل ویرایش نیست' : 'برگرداندن به پیش‌نویس برای ویرایش'}" onclick="revertInv(${d.id})"><i class="bi bi-arrow-counterclockwise"></i></button>`;
                }
                h += `<button class="ag-action-btn cancel" title="ابطال" onclick="cancelInv(${d.id})"><i class="bi bi-x-octagon"></i></button>`;
            }
            if (d.doc_type === 'proforma' && d.status !== 'cancelled') {
                if (d.converted_to_id) {
                    h += `<button class="ag-action-btn" style="color:#2563eb" title="مشاهده‌ی فاکتور رسمی" onclick="location.href='/pages/inv-invoice-print.php?id=${d.converted_to_id}'"><i class="bi bi-box-arrow-up-left"></i></button>`;
                } else {
                    h += `<button class="ag-action-btn" style="color:#0f7a57" title="تبدیل به فاکتور رسمی" onclick="convertInv(${d.id})"><i class="bi bi-file-earmark-check"></i></button>`;
                }
            }
            // درصد سهم همکار — هر وضعیتی جز باطل (حتی تأییدشده)، فقط فاکتورِ دارای همکار (نه پیش‌فاکتور)
            if (hasPartnerProfit(d) && d.status !== 'cancelled') {
                h += `<button class="ag-action-btn" style="color:#8e57fe" title="درصد سهم همکار" onclick="editPartnerPct(${d.id})"><i class="bi bi-percent"></i></button>`;
            }
            h += `<button class="ag-action-btn" title="${hasPartnerProfit(d) ? 'سند سود همکار / تسویه / مودیان' : 'ویرایش وضعیت تسویه/مودیان'}" onclick="stEditStatus(${d.id})"><i class="bi bi-clipboard-check"></i></button>`;
            return h;
        }

        // پیش‌فاکتور «سود همکار» ندارد (درخواست ۱۴۰۵/۰۸/۰۱): نه درصد، نه مبلغ، نه ثبت سود —
        // سلول‌ها خالی، و دکمهٔ درصد/سوئیچ «سند سود همکار» هم برایش نمی‌آید.
        function hasPartnerProfit(d) {
            return !!d.partner_id && d.doc_type !== 'proforma';
        }

        // پنجرهٔ مشترک «درصد سهم همکار» (assets/js/partner-percent-modal.js)
        function editPartnerPct(id) {
            const d = allRows.find(r => r.id === id);
            if (!d) return;
            PartnerPercentModal.open({
                title: d.number ? 'فاکتور شماره ' + faDigits(d.number) : 'پیش‌نویس #' + faDigits(d.id),
                partnerName: d.partner_name,
                monthPercent: d.partner_month_percent,
                override: d.partner_percent_override,
                preTax: (d.subtotal_amount || 0) - (d.discount_amount || 0),
                profitRecorded: !!d.partner_profit_recorded,
                save: p => apiSend('PATCH', '/inv/invoices/' + id + '/partner-percent', { percent: p }),
                onSaved: loadAll,
            });
        }

        const SETTLE_LABEL = {
            unsettled: 'تسویه نشده',
            settled: 'تسویه شده',
            partial: 'بخشی تسویه شده'
        };
        const MOADIAN_LABEL = {
            unregistered: 'ثبت نشده',
            registered: 'ثبت شده'
        };

        function settleCell(p) {
            const v = p.value || 'unsettled';
            return `<span class="pm-badge ${v}">${SETTLE_LABEL[v] || v}</span>`;
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

        const colDefs = [{
                headerName: 'شماره',
                field: 'number',
                width: 130,
                comparator: AgGridFa.numericTextComparator, // عددی، نه حرف‌به‌حرف (۲ قبل از ۱۰)
                sort: 'desc',
                sortIndex: 1, // در یک تاریخ، شماره‌ی بزرگ‌تر بالا
                cellRenderer: p => p.value ? faDigits(p.value) : '<span class="st-badge draft">پیش‌نویس</span>'
            },
            {
                headerName: 'مشتری',
                field: 'customer_name',
                flex: 2,
                minWidth: 160
            },
            {
                headerName: 'نوع',
                field: 'doc_type',
                width: 100,
                cellRenderer: p => TYPE_LABEL[p.value] || p.value
            },
            {
                headerName: 'تاریخ صدور',
                field: 'issue_date',
                width: 120,
                // پیش‌فرض: جدیدترین بالا (تاریخ، بعد شماره). ترتیب سرور id است و فاکتورهای قدیمیِ
                // واردشده از اکسل (id بزرگ، تاریخ قدیمی) را بالای فهرست می‌آورد.
                // پیش‌نویسِ بی‌تاریخ با تاریخ ساختش مرتب می‌شود، همان که نمایش داده می‌شود.
                valueGetter: p => p.data.issue_date || (p.data.created_at || '').split(' ')[0],
                sort: 'desc',
                sortIndex: 0,
                cellRenderer: p => jDate(p.value)
            },
            {
                headerName: 'مبلغ کل (ریال)',
                field: 'total_amount',
                width: 150,
                type: 'rightAligned',
                // کلیک = کپی مبلغ خام (رقم لاتین بدون جداکننده) — assets/js/ag-grid-fa.js
                cellRenderer: p => AgGridFa.copyCell(Math.round(Number(p.value) || 0), money(p.value), 'مبلغ کل')
            },
            {
                headerName: 'همکار',
                field: 'partner_name',
                width: 130,
                cellRenderer: p => p.data.partner_id ? esc(p.value || '—') : '—'
            },
            {
                headerName: 'درصد سود',
                field: 'partner_percent',
                valueGetter: p => p.data.doc_type === 'proforma' ? null : p.data.partner_percent,
                width: 125,
                type: 'rightAligned',
                cellRenderer: p => p.data.doc_type === 'proforma' ? '' : !p.data.partner_id ? '—' : faDigits(p.value || 0) + '٪' +
                    (p.data.partner_percent_is_override ?
                        '<span class="pct-ov" title="این فاکتور درصد اختصاصی دارد و از درصد ماه پیروی نمی‌کند">اختصاصی</span>' : '')
            },
            {
                headerName: 'مبلغ سود (ریال)',
                field: 'partner_profit_amount',
                valueGetter: p => p.data.doc_type === 'proforma' ? null : p.data.partner_profit_amount,
                width: 140,
                type: 'rightAligned',
                cellRenderer: p => p.data.doc_type === 'proforma' ? '' : p.data.partner_id ?
                    AgGridFa.copyCell(Math.round(Number(p.value) || 0), money(p.value), 'مبلغ سود') : '—'
            },
            {
                // وضعیت ثبت سود همکار در نرم‌افزار حسابداری: ثبت‌نشده → آیکن «ثبت سود» (با تأیید)،
                // ثبت‌شده → متن سبز «ثبت شده». (برگرداندن فقط از سوئیچ مودال تسویه/مودیان.)
                headerName: 'ثبت سود',
                field: 'partner_profit_recorded',
                width: 110,
                valueGetter: p => !hasPartnerProfit(p.data) ? null : (p.data.partner_profit_recorded ? 1 : 0),
                cellRenderer: p => {
                    const d = p.data;
                    if (d.doc_type === 'proforma') return '';
                    if (!d.partner_id) return '—';
                    if (d.partner_profit_recorded) return '<span class="profit-st done">ثبت شده</span>';
                    if (d.status === 'cancelled') return '<span class="profit-st">ثبت نشده</span>';
                    return `<button class="ag-action-btn profit" title="ثبت سود همکار" onclick="markProfitRecorded(${d.id})"><i class="bi bi-journal-plus"></i></button>`;
                }
            },
            {
                headerName: 'تسویه',
                field: 'settlement_status',
                width: 130,
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
                headerName: 'وضعیت',
                field: 'status',
                width: 120,
                cellRenderer: p => p.data.converted_to_id ?
                    '<span class="st-badge" style="background:rgba(37,99,235,.14);color:#2563eb">تبدیل‌شده</span>' :
                    `<span class="st-badge ${p.value}">${ST_LABEL[p.value]||p.value}</span>`
            },
            {
                headerName: 'عملیات',
                width: 80,
                sortable: false,
                filter: false,
                // یک دکمهٔ «⋮» که همهٔ عملیات را به‌صورت منو باز می‌کند (assets/js/ag-grid-fa.js)
                cellRenderer: AgGridFa.actionMenuCell(actionCell)
            },
        ];

        const gridOptions = {
            theme: AgGridFa.theme(), // پایهٔ مشترک در assets/js/ag-grid-fa.js
            columnDefs: colDefs,
            rowData: [],
            enableRtl: true,
            animateRows: true,
            pagination: true,
            paginationPageSize: 20,
            paginationPageSizeSelector: [20, 50, 100],
            onPaginationChanged: () => AgGridFa.persianizePaging(),
            // کلیک = فقط انتخاب ردیف؛ دوبار کلیک = رفتن به حالت چاپ فاکتور (درخواست ۱۴۰۵/۰۷/۲۷).
            // دکمه‌های عملیات، کنترل‌های فرم و کد مؤدیان (کپی) مستثنا هستند.
            rowSelection: { mode: 'singleRow', checkboxes: false, enableClickSelection: true },
            onRowDoubleClicked: (e) => {
                if (e.event && e.event.target.closest && e.event.target.closest('.ag-action-btn, input, .form-check, .moadian-code, .copy-cell')) return;
                // دوبار کلیک یک کلمه را هم انتخاب می‌کند — پاکش کن (جلوی رفتن را نگیرد)
                if (window.getSelection) window.getSelection().removeAllRanges();
                location.href = '/pages/inv-invoice-print.php?id=' + e.data.id;
            },
            defaultColDef: {
                resizable: true,
                sortable: true
            },
            overlayNoRowsTemplate: '<span class="text-muted">فاکتوری یافت نشد</span>',
        };

        async function loadAll() {
            gridApi.showLoadingOverlay();
            try {
                let page = 1,
                    out = [],
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/inv/invoices?per=200&page=' + page);
                    out = out.concat(d.items || []);
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
                allRows = out;
                // ماه‌های موجود (+ ماه جاری حتی اگر هنوز فاکتوری ندارد)؛ بار اول فقط ماه جاری انتخاب
                const cur = MonthFilter.currentKey();
                const keys = allRows.map(r => MonthFilter.keyOf(invDate(r))).concat(cur ? [cur] : []);
                monthFilter.setMonths(keys, monthFilterInit ? null : (cur ? [cur] : []));
                monthFilterInit = true;
                applyFilter();
            } catch (e) {
                showToast(e.message || 'خطا در بارگذاری', 'error');
                gridApi.setGridOption('rowData', []);
            }
        }

        // تاریخ فاکتور برای دسته‌بندی ماهانه — همان ستون «تاریخ صدور» (پیش‌نویس بی‌تاریخ = تاریخ ساخت)
        function invDate(r) {
            return r.issue_date || (r.created_at || '').split(' ')[0];
        }

        function applyFilter() {
            // رقم فارسی/عربی → لاتین تا «۷۰۴» و «704» هر دو پیدا شوند
            const q = String(document.getElementById('crmSearch').value || '')
                .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
                .trim().toLowerCase();
            // مبلغ: جداکنندهٔ هزارگان و فاصله نادیده گرفته می‌شود («۱٬۵۰۰٬۰۰۰» = «1500000»)
            const qAmount = q.replace(/[,٬،\s]/g, '');
            const isAmount = /^\d+$/.test(qAmount);
            const st = document.getElementById('stFilter').value;
            let rows = allRows;
            if (monthFilter) rows = rows.filter(r => monthFilter.matches(invDate(r)));
            if (st) rows = rows.filter(r => r.status === st);
            // جست‌وجو در شماره، نام مشتری، نام همکار و مبلغ کل (درخواست ۱۴۰۵/۰۸/۰۱)
            if (q) rows = rows.filter(r =>
                String(r.number || '').toLowerCase().includes(q) ||
                (r.customer_name || '').toLowerCase().includes(q) ||
                (r.partner_name || '').toLowerCase().includes(q) ||
                (isAmount && String(Math.round(Number(r.total_amount) || 0)).includes(qAmount)));
            gridApi.setGridOption('rowData', rows);
        }

        function approveInv(id) {
            uiConfirm('این فاکتور تأیید و نهایی شود؟ (برای ویرایش بعد از تأیید، باید آن را به پیش‌نویس برگردانید)', async () => {
                try {
                    const d = await apiSend('POST', '/inv/invoices/' + id + '/approve');
                    showToast('صادر شد: ' + faDigits(d.number || ''), 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        // فاکتور تأییدشده → پیش‌نویس (برای ویرایش). موجودی انبار برمی‌گردد و شماره می‌ماند.
        function revertInv(id) {
            const row = allRows.find(r => r.id === id);
            if (row && row.moadian_status === 'registered') {
                showToast('این فاکتور در سامانه‌ی مودیان ثبت شده و قابل ویرایش نیست. برای اصلاح، آن را باطل کنید و فاکتور جدید صادر کنید.', 'error');
                return;
            }
            uiConfirm('این فاکتور برای ویرایش به پیش‌نویس برگردد؟ موجودی کالاهایش به انبار برمی‌گردد، شماره‌ی فاکتور حفظ می‌شود و بعد از ویرایش باید دوباره «تأیید» شود.', async () => {
                try {
                    await apiSend('POST', '/inv/invoices/' + id + '/revert-to-draft');
                    showToast('به پیش‌نویس برگشت', 'success');
                    location.href = '/pages/inv-invoice-edit.php?id=' + id;
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function cancelInv(id) {
            uiConfirm('این فاکتور باطل شود؟ موجودی کالاها به انبار برمی‌گردد.', async () => {
                try {
                    await apiSend('POST', '/inv/invoices/' + id + '/cancel');
                    showToast('باطل شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function convertInv(id) {
            uiConfirm('از این پیش‌فاکتور یک «فاکتور رسمی پیش‌نویس» ساخته شود؟ پیش‌فاکتور بایگانی می‌شود و دیگر موجودی رزرو نمی‌کند.', async () => {
                try {
                    const d = await apiSend('POST', '/inv/invoices/' + id + '/to-official');
                    showToast('فاکتور رسمی ساخته شد', 'success');
                    location.href = '/pages/inv-invoice-edit.php?id=' + d.id;
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        function deleteInv(id) {
            uiConfirm('این پیش‌نویس حذف شود؟', async () => {
                try {
                    await apiSend('DELETE', '/inv/invoices/' + id);
                    showToast('حذف شد', 'success');
                    loadAll();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            });
        }

        // ── ویرایش تسویه/مودیان از مودال (نه <select> داخل سلول — کشویی
        //    آن داخل ردیف AG-Grid کلیپ می‌شد و بخشی از گزینه‌ها دیده نمی‌شد) ──
        let stRow = null;

        function stToggleCode() {
            const reg = document.getElementById('stMoadian').value === 'registered';
            document.getElementById('stCodeWrap').hidden = !reg;
        }

        // آیکن «ثبت سود» در ستون «ثبت سود»: بعد از تأیید، سود همکار «ثبت شده» می‌شود
        // (همان PATCH و فیلدِ partner_profit_recorded که سوئیچ مودال تسویه می‌فرستد).
        function markProfitRecorded(id) {
            const row = allRows.find(r => r.id === id);
            if (!row || !hasPartnerProfit(row) || row.partner_profit_recorded) return;
            const who = row.partner_name ? ' (' + esc(row.partner_name) + ')' : '';
            const no = row.number ? ' ' + faDigits(row.number) : '';
            uiConfirm(`آیا سود همکار${who} برای فاکتور${no} ثبت شود؟`, async () => {
                try {
                    await apiSend('PATCH', '/inv/invoices/' + id + '/partner-status', {
                        partner_profit_recorded: true
                    });
                    row.partner_profit_recorded = true;
                    gridApi.setGridOption('rowData', allRows);
                    showToast('سود همکار ثبت شد', 'success');
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            }, { yesText: 'بله، ثبت شود', noText: 'انصراف' });
        }

        function stEditStatus(id) {
            const row = allRows.find(r => r.id === id);
            if (!row) return;
            stRow = row;
            document.getElementById('stIdLbl').textContent = row.number ? ('#' + faDigits(row.number)) : '';
            document.getElementById('stSettlement').value = row.settlement_status || 'unsettled';
            document.getElementById('stMoadian').value = row.moadian_status || 'unregistered';
            document.getElementById('stCode').value = row.moadian_code || '';
            document.getElementById('stProfitWrap').hidden = !hasPartnerProfit(row);
            document.getElementById('stProfit').checked = !!row.partner_profit_recorded;
            stToggleCode();
            document.getElementById('stAlert').innerHTML = '';
            new bootstrap.Modal(document.getElementById('stModal')).show();
        }

        async function stSaveStatus() {
            if (!stRow) return;
            const settlement = document.getElementById('stSettlement').value;
            const moadian = document.getElementById('stMoadian').value;
            const code = document.getElementById('stCode').value.trim();
            const alertBox = msg => document.getElementById('stAlert').innerHTML =
                msg ? `<div class="alert alert-danger py-2">${msg}</div>` : '';
            if (moadian === 'registered' && !code) {
                alertBox('برای «مودیان ثبت‌شده» کد ثبت در سامانه لازم است.');
                return;
            }
            alertBox('');
            const body = {
                settlement_status: settlement,
                moadian_status: moadian,
                moadian_code: moadian === 'registered' ? code : '',
            };
            // سند سود همکار فقط برای فاکتورِ دارای همکار فرستاده می‌شود
            const profit = document.getElementById('stProfit').checked;
            if (hasPartnerProfit(stRow)) body.partner_profit_recorded = profit;
            try {
                await apiSend('PATCH', '/inv/invoices/' + stRow.id + '/partner-status', body);
                stRow.settlement_status = settlement;
                stRow.moadian_status = moadian;
                stRow.moadian_code = moadian === 'registered' ? code : '';
                if (hasPartnerProfit(stRow)) stRow.partner_profit_recorded = profit;
                gridApi.setGridOption('rowData', allRows);
                bootstrap.Modal.getInstance(document.getElementById('stModal')).hide();
                showToast('ذخیره شد', 'success');
            } catch (e) {
                alertBox(e.message || 'خطا');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            gridApi = agGrid.createGrid(document.getElementById('invGrid'), gridOptions);
            // کلیک راست روی ردیف = منوی همان موارد ستون عملیات (assets/js/ag-grid-fa.js)
            AgGridFa.rowContextMenu(document.getElementById('invGrid'), gridApi);
            document.getElementById('crmSearch').addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(applyFilter, 200);
            });
            document.getElementById('stFilter').addEventListener('change', applyFilter);
            monthFilter = MonthFilter.create({ container: '#monthFilter', onChange: applyFilter });
            document.getElementById('stMoadian').addEventListener('change', stToggleCode);
            document.getElementById('stSaveBtn').addEventListener('click', stSaveStatus);
            document.getElementById('stModal').addEventListener('shown.bs.modal', () => {
                document.getElementById('stSettlement').focus();
            });
            loadAll();
        });
    </script>
</body>

</html>
