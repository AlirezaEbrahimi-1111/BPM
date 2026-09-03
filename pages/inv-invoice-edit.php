<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if (!isset($db)) {
    $database = new Database();
    $db = $database->getConnection();
}
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}
$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me) {
    header('Location: ../index.php');
    exit;
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/crm_access.php';
if (!crmModuleAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$invId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $invId ? 'ویرایش فاکتور' : 'فاکتور جدید' ?> - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/entity-picker.js') ?>"></script>

    <style>
        .inv-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .inv-head-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 1rem;
        }

        table.inv-items {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
        }

        table.inv-items th,
        table.inv-items td {
            border: 1px solid var(--border-soft, #e5e7eb);
            padding: 4px 6px;
            vertical-align: middle;
        }

        table.inv-items th {
            background: rgba(142, 87, 254, .08);
            font-weight: 600;
            white-space: nowrap;
        }

        table.inv-items input:not(.ep-input) {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 4px;
            color: inherit;
        }

        table.inv-items input:not(.ep-input):focus {
            outline: 2px solid rgba(142, 87, 254, .35);
            border-radius: 4px;
        }

        /* انتخابگرِ کالا داخلِ سلولِ جدول */
        table.inv-items .it-prod-pick {
            min-width: 210px;
        }

        table.inv-items .ep-dropdown {
            font-size: 12px;
        }

        .col-qty {
            width: 90px;
        }

        .col-price,
        .col-disc {
            width: 130px;
        }

        .col-exempt {
            width: 60px;
            text-align: center;
        }

        .col-stock {
            width: 120px;
        }

        .col-total {
            width: 130px;
            text-align: left;
            white-space: nowrap;
        }

        .col-del {
            width: 40px;
            text-align: center;
        }

        tr.row-lowstock td {
            background: rgba(220, 38, 38, .09);
        }

        .stock-warn {
            color: #dc2626;
            font-weight: 600;
        }

        .totals-box {
            margin-top: 1rem;
            margin-inline-start: auto;
            width: 320px;
            font-size: .9rem;
        }

        .totals-box .tl {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dashed var(--border-soft, #e5e7eb);
        }

        .totals-box .tl.grand {
            font-weight: 700;
            font-size: 1.05rem;
            border-bottom: 0;
        }

        .inv-actions {
            display: flex;
            gap: 10px;
            margin-top: 1.5rem;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container" style="margin-top:70px">
        <div class="inv-wrap">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 style="font-size:1.3rem;margin:0"><i class="bi bi-receipt ms-2"></i><span id="pageTitle"><?= $invId ? 'ویرایش فاکتور' : 'فاکتور جدید' ?></span></h1>
                <a href="/pages/inv-invoices.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right ms-1"></i> بازگشت به فهرست</a>
            </div>

            <div id="formAlert"></div>

            <div class="inv-head-grid">
                <div>
                    <label class="form-label">نوعِ سند</label>
                    <select class="form-select" id="f_doc_type">
                        <option value="official">فاکتور رسمی</option>
                        <option value="proforma">پیش‌فاکتور</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">مشتری <span class="text-danger">*</span></label>
                    <div id="customerPicker"></div>
                </div>
                <div>
                    <label class="form-label">تاریخِ صدور</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control" id="f_issue_date" placeholder="۱۴۰۵/۰۶/۱۱" readonly>
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
                </div>
                <div>
                    <label class="form-label">نحوه‌ی فروش</label>
                    <select class="form-select" id="f_payment_type">
                        <option value="">—</option>
                        <option value="cash">نقدی</option>
                        <option value="credit">غیرنقدی</option>
                    </select>
                </div>
            </div>

            <table class="inv-items">
                <thead>
                    <tr>
                        <th style="width:34px">#</th>
                        <th style="min-width:220px">کالا (کد / نام)</th>
                        <th>شرح</th>
                        <th class="col-qty">تعداد</th>
                        <th class="col-price">قیمت واحد</th>
                        <th class="col-disc">تخفیف</th>
                        <th class="col-exempt">معاف</th>
                        <th class="col-stock">قابل‌فروش</th>
                        <th class="col-total">جمع ردیف (با مالیات)</th>
                        <th class="col-del"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody"></tbody>
            </table>

            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnAddRow"><i class="bi bi-plus-lg ms-1"></i> افزودن ردیف</button>

            <div class="totals-box">
                <div class="tl"><span>جمعِ کل</span><span id="t_subtotal">۰</span></div>
                <div class="tl"><span>تخفیف</span><span id="t_discount">۰</span></div>
                <div class="tl"><span>مالیات بر ارزش افزوده (<span id="t_vatrate">۰</span>٪)</span><span id="t_tax">۰</span></div>
                <div class="tl grand"><span>مبلغِ قابل پرداخت</span><span id="t_total">۰</span></div>
            </div>

            <div class="mt-3">
                <label class="form-label">توضیحات</label>
                <textarea class="form-control" id="f_note" rows="2"></textarea>
            </div>

            <div class="inv-actions">
                <button type="button" class="btn btn-primary" id="btnSaveBack">ذخیره و بازگشت</button>
                <button type="button" class="btn btn-outline-primary" id="btnSave">ذخیره‌ی پیش‌نویس</button>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const API = '/crm/api';
        const INV_ID = <?= $invId ?>;

        function tok() {
            return localStorage.getItem('auth_token');
        }

        function ahj() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + tok()
            };
        }

        function toEn(s) {
            return String(s == null ? '' : s)
                .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
                .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
        }

        function faDigits(s) {
            return String(s == null ? '' : s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [+d]);
        }

        function num(v) {
            const n = parseFloat(toEn(v).replace(/[,٬\s]/g, '').replace(/[^\d.-]/g, ''));
            return isNaN(n) ? 0 : n;
        }

        function money(n) {
            return faDigits(Math.round(n || 0).toLocaleString('en-US'));
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
                body: JSON.stringify(body)
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || d.success === false) throw new Error(d.message || ('خطای ' + r.status));
            return d;
        }

        let VAT = 0;
        let products = [],
            customers = [];
        let customerPicker = null,
            prodItems = [];

        function alertBox(msg, kind = 'danger') {
            document.getElementById('formAlert').innerHTML =
                msg ? `<div class="alert alert-${kind} py-2">${msg}</div>` : '';
        }

        function firstWords(s, n) {
            const w = String(s || '').trim().split(/\s+/);
            return w.slice(0, n).join(' ') + (w.length > n ? '…' : '');
        }

        // هر کالا → یک موردِ انتخابگر: برچسب «کد — چند کلمه‌ی اولِ نام»
        function buildProdItems() {
            prodItems = products.map(p => {
                const av = (p.available != null ? p.available : p.stock);
                const shortName = firstWords(p.name, 5);
                return {
                    id: p.id,
                    label: p.code ? (faDigits(p.code) + ' — ' + shortName) : shortName,
                    meta: 'قابل‌فروش ' + faDigits(av) + (p.unit ? ' • ' + p.unit : ''),
                    search: (p.code || '') + ' ' + p.name,
                };
            });
        }

        function custItems() {
            return customers.map(c => ({
                id: c.id,
                label: c.name,
                meta: [c.mobile, c.phone].filter(Boolean).map(faDigits).join(' • '),
                search: c.name + ' ' + (c.mobile || '') + ' ' + (c.phone || '') + ' ' + (c.national_id || ''),
            }));
        }

        function rowTemplate() {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="idx text-center"></td>
                <td><div class="it-prod-pick"></div></td>
                <td><input class="it-title" placeholder="شرح ردیف"></td>
                <td class="col-qty"><input class="it-qty" inputmode="decimal" value="1"></td>
                <td class="col-price"><input class="it-price" inputmode="numeric" value="0"></td>
                <td class="col-disc"><input class="it-disc" inputmode="numeric" value="0"></td>
                <td class="col-exempt"><input type="checkbox" class="it-exempt"></td>
                <td class="col-stock it-stock small text-muted"></td>
                <td class="col-total it-linetotal">۰</td>
                <td class="col-del"><button type="button" class="btn btn-sm btn-link text-danger p-0 it-del">✕</button></td>`;
            tr._picker = EntityPicker.create({
                container: tr.querySelector('.it-prod-pick'),
                items: prodItems,
                placeholder: 'کد یا نامِ کالا…',
                onSelect: it => onRowProduct(tr, it),
            });
            tr.querySelectorAll('.it-qty,.it-price,.it-disc').forEach(el => el.addEventListener('input', recalc));
            tr.querySelector('.it-exempt').addEventListener('change', recalc);
            tr.querySelector('.it-del').addEventListener('click', () => {
                tr.remove();
                renumber();
                recalc();
            });
            return tr;
        }

        function addRow(data) {
            const tr = rowTemplate();
            document.getElementById('itemsBody').appendChild(tr);
            if (data) {
                if (data.product_id) tr._picker.setValue(data.product_id); // onSelect پرِ فیلدها را می‌کند
                // مقادیرِ ذخیره‌شده روی پیش‌فرضِ کالا اولویت دارند
                tr.querySelector('.it-title').value = data.title || '';
                tr.querySelector('.it-qty').value = data.qty ?? 1;
                tr.querySelector('.it-price').value = data.unit_price ?? 0;
                tr.querySelector('.it-disc').value = data.discount ?? 0;
                tr.querySelector('.it-exempt').checked = !!data.is_tax_exempt;
            }
            renumber();
            updateStockHint(tr);
            recalc();
        }

        function renumber() {
            document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
                tr.querySelector('.idx').textContent = faDigits(i + 1);
            });
        }

        // انتخابِ کالا از انتخابگر: کد/قیمت/معاف/شرح را پر می‌کند.
        function onRowProduct(tr, item) {
            if (item) {
                const p = products.find(x => x.id === item.id);
                tr.dataset.productId = item.id;
                if (p) {
                    const t = tr.querySelector('.it-title');
                    if (!t.value.trim()) t.value = p.name;
                    tr.querySelector('.it-price').value = p.unit_price;
                    tr.querySelector('.it-exempt').checked = !!p.is_tax_exempt;
                }
            } else {
                delete tr.dataset.productId;
            }
            updateStockHint(tr);
            recalc();
        }

        function updateStockHint(tr) {
            const cell = tr.querySelector('.it-stock');
            const pid = tr.dataset.productId ? +tr.dataset.productId : 0;
            if (!pid) {
                cell.textContent = '';
                tr.classList.remove('row-lowstock');
                return;
            }
            const p = products.find(x => x.id === pid);
            const qty = num(tr.querySelector('.it-qty').value);
            const avail = p ? Number(p.available != null ? p.available : (p.stock || 0)) : 0;
            if (qty > avail) {
                cell.innerHTML = `<span class="stock-warn">${faDigits(avail)} (کمبود)</span>`;
                tr.classList.add('row-lowstock');
            } else {
                cell.textContent = faDigits(avail);
                tr.classList.remove('row-lowstock');
            }
        }

        function recalc() {
            let subtotal = 0,
                discount = 0,
                tax = 0;
            document.querySelectorAll('#itemsBody tr').forEach(tr => {
                const qty = num(tr.querySelector('.it-qty').value);
                const price = num(tr.querySelector('.it-price').value);
                const disc = num(tr.querySelector('.it-disc').value);
                const exempt = tr.querySelector('.it-exempt').checked;
                const gross = Math.round(qty * price);
                let after = gross - disc;
                if (after < 0) after = 0;
                const t = exempt ? 0 : Math.round(after * VAT / 100);
                tr.querySelector('.it-linetotal').textContent = money(after + t);
                subtotal += gross;
                discount += disc;
                tax += t;
                updateStockHint(tr);
            });
            document.getElementById('t_subtotal').textContent = money(subtotal);
            document.getElementById('t_discount').textContent = money(discount);
            document.getElementById('t_tax').textContent = money(tax);
            document.getElementById('t_total').textContent = money(subtotal - discount + tax);
        }

        function collect() {
            const picked = customerPicker ? customerPicker.getValue() : null;
            const cust = picked ? customers.find(c => c.id === picked.id) : null;
            const items = [];
            document.querySelectorAll('#itemsBody tr').forEach(tr => {
                const title = tr.querySelector('.it-title').value.trim();
                if (!title) return;
                items.push({
                    product_id: tr.dataset.productId ? +tr.dataset.productId : null,
                    title,
                    qty: num(tr.querySelector('.it-qty').value),
                    unit_price: Math.round(num(tr.querySelector('.it-price').value)),
                    discount: Math.round(num(tr.querySelector('.it-disc').value)),
                    is_tax_exempt: tr.querySelector('.it-exempt').checked,
                });
            });
            const dateInput = document.getElementById('f_issue_date');
            let issue = dateInput.getAttribute('data-date') || '';
            if (!issue && dateInput.value && typeof convertToGregorian === 'function') {
                issue = convertToGregorian(dateInput.value) || '';
            }
            return {
                cust,
                body: {
                    doc_type: document.getElementById('f_doc_type').value,
                    customer_id: cust ? cust.id : 0,
                    issue_date: issue,
                    payment_type: document.getElementById('f_payment_type').value,
                    note: document.getElementById('f_note').value.trim(),
                    items,
                }
            };
        }

        async function save(goBack) {
            const {
                cust,
                body
            } = collect();
            if (!cust) {
                alertBox('یک مشتری از فهرست انتخاب کنید. اگر نیست، با دکمه‌ی + بسازیدش.');
                return;
            }
            if (!body.items.length) {
                alertBox('حداقل یک ردیف با «شرح» لازم است.');
                return;
            }
            alertBox('');
            try {
                let id = INV_ID;
                if (INV_ID) await apiSend('PUT', '/inv/invoices/' + INV_ID, body);
                else {
                    const d = await apiSend('POST', '/inv/invoices', body);
                    id = d.id;
                }
                showToast('ذخیره شد', 'success');
                if (goBack) location.href = '/pages/inv-invoices.php';
                else if (!INV_ID) location.href = '/pages/inv-invoice-edit.php?id=' + id;
            } catch (e) {
                alertBox(e.message || 'خطا در ذخیره');
            }
        }

        async function reloadCustomers() {
            const out = [];
            try {
                let page = 1,
                    total = Infinity;
                while (out.length < total) {
                    const d = await apiGet('/customers?per=200&page=' + page);
                    out.push(...(d.items || []));
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
                customers = out;
            } catch (e) {}
        }

        async function init() {
            if (!tok()) {
                location.href = '../index.php';
                return;
            }
            try {
                const st = await apiGet('/inv/settings');
                VAT = Number(st.vat_rate || 0);
                document.getElementById('t_vatrate').textContent = faDigits(VAT);
            } catch (e) {
                /* ادامه با VAT=0 */
            }

            // مشتری‌ها
            await reloadCustomers();
            customerPicker = EntityPicker.create({
                container: '#customerPicker',
                items: custItems(),
                placeholder: 'کد/نام مشتری…',
                addTitle: 'افزودن مشتری جدید',
                onAdd: () => {
                    window.open('/pages/crm-customers.php', '_blank');
                    showToast('بعد از افزودنِ مشتری، به همین صفحه برگرد؛ فهرست خودکار تازه می‌شود.', 'info');
                },
            });
            // وقتی از تبِ «مشتریان» برگشتی، فهرست را تازه کن
            window.addEventListener('focus', async () => {
                await reloadCustomers();
                if (customerPicker) customerPicker.updateItems(custItems());
            });

            // کالاها — در حالتِ ویرایش، خودِ این فاکتور از محاسبه‌ی رزرو کنار می‌رود.
            const exParam = INV_ID ? '&exclude_invoice=' + INV_ID : '';
            try {
                let page = 1,
                    total = Infinity;
                while (products.length < total) {
                    const d = await apiGet('/inv/products?per=200&page=' + page + exParam);
                    products = products.concat(d.items || []);
                    total = d.total || 0;
                    if (!d.items || !d.items.length) break;
                    page++;
                }
            } catch (e) {}
            buildProdItems();

            if (INV_ID) {
                try {
                    const d = await apiGet('/inv/invoices/' + INV_ID);
                    const inv = d.invoice;
                    if (inv.status !== 'draft') {
                        location.href = '/pages/inv-invoice-print.php?id=' + INV_ID;
                        return;
                    }
                    document.getElementById('f_doc_type').value = inv.doc_type;
                    document.getElementById('f_payment_type').value = inv.payment_type || '';
                    if (customerPicker) customerPicker.setValue(inv.customer_id);
                    document.getElementById('f_note').value = inv.note || '';
                    if (inv.issue_date) {
                        const di = document.getElementById('f_issue_date');
                        di.setAttribute('data-date', inv.issue_date);
                        di.value = (typeof convertToJalali === 'function') ? convertToJalali(inv.issue_date) : inv.issue_date;
                    }
                    (inv.items || []).forEach(addRow);
                } catch (e) {
                    alertBox('بارگذاریِ فاکتور ناموفق بود: ' + (e.message || ''));
                }
            }
            if (!document.querySelectorAll('#itemsBody tr').length) {
                addRow();
                addRow();
            }
            recalc();
            if (typeof window.reinitPersianDatepickers === 'function') window.reinitPersianDatepickers();

            document.getElementById('btnAddRow').addEventListener('click', () => addRow());
            document.getElementById('btnSave').addEventListener('click', () => save(false));
            document.getElementById('btnSaveBack').addEventListener('click', () => save(true));
        }

        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>

</html>
