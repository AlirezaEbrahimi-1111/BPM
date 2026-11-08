<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — صدور/ویرایش پیش‌فاکتور فروش، سفارش فروش و سفارش خرید (۱۴۰۵/۰۸/۱۷؛ بدون طرح — هم‌شکل
// فرم فاکتور رسمی). ?type=proforma|sales_order|purchase_order (پیش‌فرض پیش‌فاکتور)؛ ?id=شناسه = ویرایش/نمایش
// (نوع از خود سند)؛ ?customer=شناسه = سند تازه برای همان مشتری. سفارش خرید: طرف = تفصیلیِ شایگان
// (تأمین‌کننده)، فعلا فقط مدیر/سوپروایزر. پیش‌فاکتورِ ذخیره‌شده → «تبدیل به سفارش فروش».
// کالا فقط از کالاهای شایگان (فهرست پیش‌بارگذاری‌شده در crm-service — بدون انتظار)؛ قیمت دستی، تومان.
// API: crm-service/ucrm_proforma_docs.go. پس از ارسال به حسابداری (مرحلهٔ ۲) فقط‌خواندنی.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$__isManager = ucrmRoles($db, (int) $user_id)['manager'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌فاکتور فروش - سامانه مدیریت فرآیندها</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/entity-picker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/money-shortcut.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/ucrm.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container uc-top-gap">
        <div class="uc-card uc-pf-head">
            <h1><span class="uc-tg-ico"><i class="bi bi-file-earmark-plus"></i></span><span id="pfTitle">پیش‌فاکتور فروش جدید</span></h1>
            <div class="spacer"></div>
            <a class="btn btn-light btn-sm" href="ucrm-proformas.php" id="btnBack"><i class="bi bi-arrow-right ms-1"></i>بازگشت به فهرست</a>
        </div>

        <div id="pfAlert"></div>

        <div class="uc-card">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label small"><span id="partyLbl">مشتری</span> <span class="text-danger">*</span></label>
                    <div id="custPicker"></div>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small">تاریخ صدور <span class="text-danger">*</span></label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control" id="fIssue" readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                            <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                            <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small" id="date2Lbl">تاریخ پیگیری بعدی</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control" id="fFollow" readonly placeholder="اختیاری">
                        <div class="persian-datepicker">
                            <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                            <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                            <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="uc-card">
            <div class="d-flex align-items-center gap-2 mb-2">
                <h3 class="m-0"><i class="bi bi-box-seam ms-1"></i>کالاها</h3>
                <span class="small text-muted" id="itemsHint"></span>
            </div>
            <div class="table-responsive">
                <table class="uc-pf-items">
                    <thead>
                        <tr>
                            <th style="width:34px">#</th>
                            <th>کالا (کد / نام در حسابداری)</th>
                            <th class="c-unit">واحد</th>
                            <th class="c-qty">تعداد</th>
                            <th class="c-money">فی (تومان)</th>
                            <th class="c-money">تخفیف (تومان)</th>
                            <th class="c-money">جمع ردیف</th>
                            <th class="c-del"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                </table>
            </div>
            <datalist id="shygunItemList"></datalist>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnAddRow"><i class="bi bi-plus-lg ms-1"></i>افزودن ردیف</button>

            <div class="uc-pf-totals">
                <div class="tl"><span>جمع کل</span><span id="tSub">۰</span></div>
                <div class="tl"><span>تخفیف</span><span id="tDisc">۰</span></div>
                <div class="tl grand"><span>مبلغ قابل پرداخت (تومان)</span><span id="tTotal">۰</span></div>
            </div>
        </div>

        <div class="uc-card">
            <label class="form-label small" for="fNote">توضیحات</label>
            <textarea class="form-control" id="fNote" rows="2" placeholder="اختیاری — مثلا شرایط پرداخت یا اعتبار پیش‌فاکتور"></textarea>
        </div>

        <div class="uc-pf-actions">
            <button type="button" class="btn btn-primary" id="btnSave"><i class="bi bi-check-lg ms-1"></i>ذخیره</button>
            <button type="button" class="btn btn-outline-primary" id="btnPrint" hidden><i class="bi bi-printer ms-1"></i>چاپ</button>
            <button type="button" class="btn btn-outline-success" id="btnToOrder" hidden><i class="bi bi-arrow-left-right ms-1"></i>تبدیل به سفارش فروش</button>
            <div class="spacer"></div>
            <button type="button" class="btn btn-outline-danger" id="btnDelete" hidden><i class="bi bi-trash ms-1"></i>حذف پیش‌فاکتور</button>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const qs = new URLSearchParams(location.search);
        let DOC_ID = parseInt(qs.get('id'), 10) || 0;
        const IS_MANAGER = <?= $__isManager ? 'true' : 'false' ?>;
        const DOC = {
            proforma: { title: 'پیش‌فاکتور فروش', party: 'مشتری', date2: 'تاریخ پیگیری بعدی', date2Key: 'next_followup', back: 'ucrm-proformas.php', icon: 'bi-file-earmark-plus' },
            sales_order: { title: 'سفارش فروش', party: 'مشتری', date2: 'تاریخ تحویل مورد انتظار', date2Key: 'expected_date', back: 'ucrm-orders.php?type=sales', icon: 'bi-cart-check' },
            purchase_order: { title: 'سفارش خرید', party: 'تأمین‌کننده', date2: 'تاریخ تحویل مورد انتظار', date2Key: 'expected_date', back: 'ucrm-orders.php?type=purchase', icon: 'bi-cart' },
        };
        let TYPE = DOC[qs.get('type')] ? qs.get('type') : 'proforma';
        let SALES_ORDER_ID = 0;
        let ITEMS = [], BY_CODE = new Map(), custPicker = null, CAN_EDIT = true;

        const fa = s => faDigits(String(s));
        const num = el => parseFloat(UCRM.toEn(el.value).replace(/[^\d.]/g, '')) || 0;
        const itemLabel = it => it.code + ' — ' + it.name;

        // برچسب‌ها و مسیر بازگشت بر اساس نوع سند
        function applyType() {
            const d = DOC[TYPE];
            document.getElementById('pfTitle').textContent = d.title + ' جدید';
            document.getElementById('partyLbl').textContent = d.party;
            document.getElementById('date2Lbl').textContent = d.date2;
            document.querySelector('.uc-pf-head .uc-tg-ico i').className = 'bi ' + d.icon;
            document.getElementById('btnBack').href = d.back;
            document.title = d.title + ' - سامانه مدیریت فرآیندها';
        }

        function alertBox(msg, kind = 'danger') {
            document.getElementById('pfAlert').innerHTML = msg ? `<div class="alert alert-${kind} py-2">${esc(msg)}</div>` : '';
        }

        function setDate(id, g) {
            const el = document.getElementById(id);
            if (g) { el.setAttribute('data-date', g); el.value = convertToJalali(g); }
            else { el.removeAttribute('data-date'); el.value = ''; }
        }
        const getDate = id => document.getElementById(id).getAttribute('data-date') || '';

        // ── ردیف کالا ──
        function addRow(d) {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="idx text-center"></td>
                <td><input class="it-name" list="shygunItemList" dir="auto" placeholder="کد یا نام کالا را تایپ کنید"></td>
                <td class="c-unit it-unit text-muted"></td>
                <td class="c-qty"><input class="it-qty" inputmode="decimal" value="${fa(1)}"></td>
                <td class="c-money"><input class="it-price"></td>
                <td class="c-money"><input class="it-disc"></td>
                <td class="c-money it-total">۰</td>
                <td class="c-del"><button type="button" class="btn btn-sm btn-link text-danger p-0 it-del" title="حذف ردیف">✕</button></td>`;
            const name = tr.querySelector('.it-name');
            UCRM.bindMoney(tr.querySelector('.it-price'));
            UCRM.bindMoney(tr.querySelector('.it-disc'));
            name.addEventListener('change', () => syncItem(tr));
            tr.querySelectorAll('.it-qty,.it-price,.it-disc').forEach(el => el.addEventListener('input', recalc));
            tr.querySelector('.it-qty').addEventListener('blur', e => { e.target.value = fa(num(e.target) || 1); recalc(); });
            tr.querySelector('.it-del').addEventListener('click', () => { tr.remove(); renumber(); recalc(); });
            tr.querySelectorAll('input').forEach(el => el.addEventListener('keydown', e => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const next = tr.nextElementSibling || (tr.querySelector('.it-name').value.trim() ? addRow() : null);
                (next ? next.querySelector('.it-name') : el).focus();
            }));
            document.getElementById('itemsBody').appendChild(tr);
            if (d) {
                tr.dataset.code = d.item_code;
                name.value = d.item_code + ' — ' + d.item_name;
                tr.querySelector('.it-unit').textContent = d.unit || '';
                tr.querySelector('.it-qty').value = fa(d.qty);
                UCRM.setMoney(tr.querySelector('.it-price'), d.unit_price);
                UCRM.setMoney(tr.querySelector('.it-disc'), d.discount);
            }
            renumber();
            recalc();
            return tr;
        }

        // متن فیلد کالا → کالای شایگان (بر اساس کدِ ابتدای متن، یا تطبیق دقیق نام)
        function syncItem(tr) {
            const v = tr.querySelector('.it-name').value.trim();
            const code = v.split(' — ')[0].trim();
            const it = BY_CODE.get(code) || ITEMS.find(x => x.name === v);
            if (it) {
                tr.dataset.code = it.code;
                tr.querySelector('.it-name').value = itemLabel(it);
                tr.querySelector('.it-unit').textContent = it.unit || '';
                tr.classList.remove('uc-row-bad');
            } else {
                delete tr.dataset.code;
                tr.querySelector('.it-unit').textContent = '';
                tr.classList.toggle('uc-row-bad', !!v);
            }
        }

        function renumber() {
            document.querySelectorAll('#itemsBody tr').forEach((tr, i) => tr.querySelector('.idx').textContent = fa(i + 1));
        }

        function recalc() {
            let sub = 0, disc = 0;
            document.querySelectorAll('#itemsBody tr').forEach(tr => {
                const gross = Math.round(num(tr.querySelector('.it-qty')) * UCRM.moneyVal(tr.querySelector('.it-price')));
                const d = UCRM.moneyVal(tr.querySelector('.it-disc'));
                tr.querySelector('.it-total').textContent = UCRM.money(gross - d);
                sub += gross;
                disc += d;
            });
            document.getElementById('tSub').textContent = UCRM.money(sub);
            document.getElementById('tDisc').textContent = UCRM.money(disc);
            document.getElementById('tTotal').textContent = UCRM.money(sub - disc);
        }

        function collect() {
            const c = custPicker.getValue();
            const rows = [...document.querySelectorAll('#itemsBody tr')].filter(tr => tr.querySelector('.it-name').value.trim());
            for (let i = 0; i < rows.length; i++) {
                if (!rows[i].dataset.code) throw new Error('ردیف ' + fa(i + 1) + ': کالا را از فهرست کالاهای حسابداری انتخاب کنید.');
            }
            return {
                doc_type: TYPE,
                customer_id: TYPE !== 'purchase_order' && c ? c.id : 0,
                party_job_no: TYPE === 'purchase_order' && c ? String(c.id) : '',
                issue_date: getDate('fIssue'),
                [DOC[TYPE].date2Key]: getDate('fFollow'),
                note: document.getElementById('fNote').value,
                items: rows.map(tr => {
                    const it = BY_CODE.get(tr.dataset.code) || {};
                    return {
                        item_code: tr.dataset.code, item_guid: it.guid || '', item_name: it.name || '', unit: it.unit || '',
                        qty: num(tr.querySelector('.it-qty')) || 1,
                        unit_price: UCRM.moneyVal(tr.querySelector('.it-price')),
                        discount: UCRM.moneyVal(tr.querySelector('.it-disc')),
                    };
                }),
            };
        }

        async function save() {
            alertBox('');
            let body;
            try { body = collect(); } catch (e) { return alertBox(e.message, 'warning'); }
            const btn = document.getElementById('btnSave');
            btn.disabled = true;
            try {
                const res = DOC_ID
                    ? await UCRM.send('PUT', '/proforma-docs/' + DOC_ID, body)
                    : await UCRM.send('POST', '/proforma-docs', body);
                showToast(res.message || 'ذخیره شد.', 'success');
                if (!DOC_ID) {
                    DOC_ID = res.id;
                    history.replaceState(null, '', 'ucrm-proforma-edit.php?id=' + DOC_ID);
                    document.getElementById('pfTitle').textContent = DOC[TYPE].title + ' ' + fa(res.number);
                    document.getElementById('btnPrint').hidden = false;
                    document.getElementById('btnDelete').hidden = false;
                    document.getElementById('btnToOrder').hidden = TYPE !== 'proforma';
                }
            } catch (e) {
                alertBox(e.message || 'خطا در ذخیره');
            } finally {
                btn.disabled = false;
            }
        }

        function del() {
            uiConfirm('این ' + DOC[TYPE].title + ' حذف شود؟', async () => {
                try {
                    const res = await UCRM.send('DELETE', '/proforma-docs/' + DOC_ID);
                    showToast(res.message || 'حذف شد.', 'success');
                    setTimeout(() => location.href = DOC[TYPE].back, 800);
                } catch (e) {
                    showToast(e.message || 'خطا در حذف', 'error');
                }
            }, { danger: true, yesText: 'بله، حذف کن', noText: 'انصراف' });
        }

        // پیش‌فاکتور → سفارش فروشِ تازه (همان مشتری و کالاها)؛ اگر قبلا ساخته شده، باز کردن همان
        async function toSalesOrder() {
            if (SALES_ORDER_ID) { location.href = 'ucrm-proforma-edit.php?id=' + SALES_ORDER_ID; return; }
            uiConfirm('از این پیش‌فاکتور سفارش فروش ساخته شود؟ پیش‌فاکتور «تأیید شده» می‌شود.', async () => {
                try {
                    const res = await UCRM.send('POST', '/proforma-docs/' + DOC_ID + '/to-sales-order');
                    showToast(res.message || 'سفارش فروش ساخته شد.', 'success');
                    setTimeout(() => location.href = 'ucrm-proforma-edit.php?id=' + res.id, 800);
                } catch (e) {
                    showToast(e.message || 'خطا در ساخت سفارش فروش', 'error');
                }
            }, { yesText: 'بله، بساز', noText: 'انصراف' });
        }

        function lockForm() {
            CAN_EDIT = false;
            document.querySelectorAll('.overview-container input, .overview-container textarea').forEach(el => el.disabled = true);
            ['btnSave', 'btnAddRow', 'btnDelete'].forEach(id => document.getElementById(id).hidden = true);
            document.querySelectorAll('.it-del').forEach(b => b.hidden = true);
            alertBox('این پیش‌فاکتور برای حسابداری ارسال شده و دیگر قابل ویرایش نیست.', 'info');
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) { location.href = '../index.php'; return; }

            // کالاهای شایگان — از کشِ سرویس (بدون انتظار برای حسابداری)
            const itemsP = UCRM.get('/shygun/items').then(r => {
                ITEMS = r.items || [];
                BY_CODE = new Map(ITEMS.map(x => [x.code, x]));
                document.getElementById('shygunItemList').innerHTML = ITEMS.map(x =>
                    `<option value="${esc(itemLabel(x))}">`).join('');
                document.getElementById('itemsHint').textContent = r.loading
                    ? 'فهرست کالاها هنوز از حسابداری خوانده نشده؛ چند لحظهٔ دیگر صفحه را تازه کنید.'
                    : fa(ITEMS.length) + ' کالا از حسابداری' + (r.fetched_at ? ' — به‌روز شده ' + fa(r.fetched_at) : '');
            }).catch(e => alertBox(e.message || 'خطا در دریافت کالاها'));

            // سند موجود: نوعش از خود سند
            let doc = null;
            if (DOC_ID) {
                try { doc = await UCRM.get('/proforma-docs/' + DOC_ID); TYPE = doc.doc_type || 'proforma'; }
                catch (e) { alertBox(e.message || 'سند یافت نشد'); }
            }
            if (TYPE === 'purchase_order' && !IS_MANAGER) {
                alertBox('ثبت سفارش خرید فعلا فقط برای مدیران است.', 'warning');
                ['btnSave', 'btnAddRow'].forEach(id => document.getElementById(id).hidden = true);
                return;
            }
            applyType();

            // طرف سند: مشتری‌های فعال (کارشناس فقط مال خودش — سرور)، یا تأمین‌کننده = تفصیلی‌های شایگان
            let custs = [];
            try {
                custs = TYPE === 'purchase_order'
                    ? ((await UCRM.get('/shygun/parties')).items || []).map(p => ({ id: p.job_no, name: p.name, meta: 'تفصیلی ' + faDigits(p.job_no) }))
                    : ((await UCRM.get('/customers')).items || []).filter(c => c.status === 'active')
                        .map(c => ({ id: c.id, name: c.name, meta: c.rep_name ? 'کارشناس: ' + c.rep_name : '' }));
            } catch (e) {
                alertBox(e.message || 'خطا در دریافت فهرست ' + DOC[TYPE].party);
            }
            custPicker = EntityPicker.create({
                container: '#custPicker',
                items: custs.map(c => ({ id: c.id, label: c.name, meta: c.meta })),
                placeholder: TYPE === 'purchase_order' ? 'نام یا شمارهٔ تأمین‌کننده…' : 'نام مشتری…',
                onSelect: () => { const f = document.querySelector('#itemsBody .it-name'); if (f) f.focus(); },
            });

            document.getElementById('btnAddRow').addEventListener('click', () => addRow().querySelector('.it-name').focus());
            document.getElementById('btnSave').addEventListener('click', save);
            document.getElementById('btnDelete').addEventListener('click', del);
            document.getElementById('btnPrint').addEventListener('click', () => window.open('ucrm-proforma-print.php?id=' + DOC_ID, '_blank'));
            document.getElementById('btnToOrder').addEventListener('click', toSalesOrder);

            await itemsP;
            if (DOC_ID) {
                if (!doc) {
                    ['btnSave', 'btnAddRow'].forEach(id => document.getElementById(id).hidden = true);
                    return;
                }
                const d = doc;
                document.getElementById('pfTitle').textContent = DOC[TYPE].title + ' ' + fa(d.number);
                document.title = DOC[TYPE].title + ' ' + d.number + ' - سامانه مدیریت فرآیندها';
                const pid = TYPE === 'purchase_order' ? d.party_job_no : d.customer_id;
                const pname = TYPE === 'purchase_order' ? d.party_name : d.customer_name;
                if (!custs.some(c => String(c.id) === String(pid))) {
                    custPicker.updateItems(custs.map(c => ({ id: c.id, label: c.name, meta: c.meta })).concat([{ id: pid, label: pname }]));
                }
                custPicker.setValue(pid);
                setDate('fIssue', d.issue_date);
                setDate('fFollow', d[DOC[TYPE].date2Key]);
                document.getElementById('fNote').value = d.note || '';
                (d.items || []).forEach(addRow);
                document.getElementById('btnPrint').hidden = false;
                document.getElementById('btnDelete').hidden = !d.can_edit;
                if (TYPE === 'proforma') {
                    SALES_ORDER_ID = d.sales_order_id || 0;
                    const b = document.getElementById('btnToOrder');
                    b.hidden = false;
                    if (SALES_ORDER_ID) b.innerHTML = '<i class="bi bi-cart-check ms-1"></i>مشاهدهٔ سفارش فروش';
                }
                if (d.source_doc_id) {
                    document.getElementById('itemsHint').insertAdjacentHTML('afterend',
                        ' <a class="small" href="ucrm-proforma-edit.php?id=' + d.source_doc_id + '">(ساخته‌شده از پیش‌فاکتور)</a>');
                }
                if (!d.can_edit) lockForm();
                return;
            }
            setDate('fIssue', TimeSync.serverToday());
            const cust = TYPE === 'purchase_order' ? 0 : (parseInt(qs.get('customer'), 10) || 0);
            if (cust) {
                custPicker.setValue(cust);
                document.getElementById('btnBack').href = DOC[TYPE].back + (DOC[TYPE].back.includes('?') ? '&' : '?') + 'customer=' + cust;
            }
            addRow();
            if (cust && custPicker.getValue()) document.querySelector('#itemsBody .it-name').focus();
            else custPicker.focus();
        });
    </script>
</body>

</html>
