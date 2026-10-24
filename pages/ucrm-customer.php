<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// جزئیات مشتری CRM (حسابداری غیررسمی) — طرح ۱۴۰۵/۰۸/۰۲:
// سربرگ (نام، وضعیت، کارشناس، دکمه‌ها) + ۵ تب. فعلا تب «اطلاعات پایه» کامل است؛
// ویرایش اطلاعات مشتری فعال درجاست و به‌صورت «درخواست تغییر» برای تأیید حسابداری می‌رود
// (crm-service/ucrm_changes.go)؛
// «برنامهٔ پیگیری»، «یادداشت‌های تماس»، «پیامک‌ها» و «پیش‌فاکتورها» فقط نمایشی‌اند
// تا مرحله‌های بعدی CRM (پیگیری، پیامک، فاکتور) ساخته شوند — دکمه‌های مربوطشان «به‌زودی».
// هر جای حسابداری غیررسمی که روی نام مشتری کلیک شود، این صفحه باز می‌شود.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id)) {
    header('Location: ../pages/dashboard.php');
    exit;
}
$__cid = (int) ($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات مشتری CRM - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/ucrm.css') ?>">
    <script src="<?= asset('../../assets/js/iran-provinces.js') ?>"></script>
    <script src="<?= asset('../../assets/js/iran-id-validate.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- چیدمان و اندازهٔ کادر صفحه هم‌شکل pages/tasks.php (همهٔ صفحه‌های CRM) -->
    <div class="overview-container">
        <div class="uc-crumb"><i class="bi bi-people ms-1"></i><a href="ucrm-customers.php">مشتریان CRM</a> ‹ <span id="crumbName">…</span></div>
        <div id="ucBody">
            <div class="uc-card text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> در حال بارگذاری…</div>
        </div>
    </div>

    <!-- دکمهٔ شناور «پیگیری جدید» (همان دکمهٔ سربرگ، برای وقتی صفحه پایین رفته) -->
    <button type="button" class="uc-fab" id="ucFab" title="پیگیری جدید" hidden><i class="bi bi-plus-lg"></i></button>

    <!-- «ثبت پیگیری جدید» / ویرایش پیگیری (طرح ۱۴۰۵/۰۸/۰۲) — crm-service/ucrm_followups.go -->
    <div class="modal fade uc-fu-modal" id="fuModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="fuTitleHd">ثبت پیگیری جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div id="fuAlert"></div>
                    <label class="form-label">عنوان پیگیری <span class="text-danger">*</span></label>
                    <div class="fu-ico mb-3"><i class="bi bi-file-earmark-text"></i>
                        <input type="text" class="form-control" id="fuTitle" maxlength="255" placeholder="عنوان پیگیری را وارد کنید" autocomplete="off"></div>
                    <label class="form-label">نوع پیگیری <span class="text-danger">*</span></label>
                    <div class="mb-3">
                        <select class="form-select" id="fuType">
                            <option value="">نوع پیگیری را انتخاب کنید</option>
                            <option value="call">تماس تلفنی</option>
                            <option value="sms">پیامک</option>
                            <option value="meeting">جلسهٔ حضوری</option>
                            <option value="email">ایمیل</option>
                            <option value="proforma">پیگیری پیش‌فاکتور</option>
                            <option value="other">سایر</option>
                        </select>
                    </div>
                    <label class="form-label">تاریخ و زمان <span class="text-danger">*</span></label>
                    <div class="fu-when mb-3">
                        <div class="persian-datepicker-wrapper fu-date">
                                <input type="text" class="persian-datepicker-input form-control" id="fuDate" placeholder="تاریخ" readonly>
                                <i class="bi bi-calendar3 fu-cal"></i>
                                <div class="persian-datepicker">
                                    <div class="datepicker-header">
                                        <button type="button" class="datepicker-nav" data-action="prev">►</button>
                                        <span class="datepicker-current"></span>
                                        <button type="button" class="datepicker-nav" data-action="next">◄</button>
                                    </div>
                                    <div class="datepicker-weekdays">
                                        <div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div>
                                        <div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div>
                                        <div class="datepicker-weekday">ج</div>
                                    </div>
                                    <div class="datepicker-days"></div>
                                    <button type="button" class="datepicker-today-btn">امروز</button>
                                </div>
                            </div>
                        <input type="time" class="form-control fu-time" id="fuTime">
                    </div>
                    <label class="form-label">یادداشت‌ها</label>
                    <div class="fu-ico"><i class="bi bi-chat-square-dots"></i>
                        <textarea class="form-control" id="fuNote" rows="3" placeholder="یادداشت خود را اینجا بنویسید…"></textarea></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-primary w-100 fu-save" id="fuSave">ثبت پیگیری</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const CID = <?= $__cid ?>;
        let DATA = null, EDIT = false, phones = [];
        // پیگیری‌های مشتری (تب «برنامهٔ پیگیری») — crm-service/ucrm_followups.go
        let FU = { items: [], can_manage: false, loaded: false }, fuModal = null, fuEditId = 0;
        const FU_TYPE = { call: 'تماس تلفنی', sms: 'پیامک', meeting: 'جلسهٔ حضوری', email: 'ایمیل', proforma: 'پیگیری پیش‌فاکتور', other: 'سایر' };

        const TABS = [
            { key: 'info', label: 'اطلاعات پایه', icon: 'bi-info-circle' },
            { key: 'plan', label: 'برنامهٔ پیگیری', icon: 'bi-calendar-check' },
            { key: 'history', label: 'تاریخچهٔ پیگیری', icon: 'bi-clock-history' },
            { key: 'sms', label: 'پیامک‌ها', icon: 'bi-chat-left-text' },
            { key: 'proformas', label: 'پیش‌فاکتورها', icon: 'bi-file-earmark-text' },
        ];
        // تب‌های نمایشی (تا مرحلهٔ مربوط ساخته شود)
        const SOON = {
            sms: { icon: 'bi-chat-left-text', title: 'پیامک‌ها', text: 'پیامک‌هایی که برای این مشتری فرستاده شده، با قالب و تاریخ ارسال، این‌جا نمایش داده می‌شود.' },
        };

        function row(icon, k, v, extra) {
            return `<div class="uc-info-row"><span class="ic"><i class="bi ${icon}"></i></span>
                <div class="tx"><div class="k">${k}${extra || ''}</div><div class="v">${v}</div></div></div>`;
        }

        function statusPill(c) {
            if (c.status === 'active') return '<span class="uc-pill ok"><span class="dot"></span>مشتری فعال</span>';
            return UCRM.statusBadge(c);
        }

        function salesBlock(c, month) {
            const sales = c.sales_amount || 0;
            const target = c.target_amount || 0;
            const pct = target > 0 ? Math.round(sales * 100 / target) : null;
            const monthPct = month && month.days ? Math.round(month.day * 100 / month.days) : 0;
            const suspended = c.target_suspended && target > 0;
            return `
                <div class="uc-goal">
                    <div class="top">
                        <span class="ic"><i class="bi bi-cash-stack"></i></span>
                        <div class="tx"><div class="t">فروش فعلی</div><div class="a">${UCRM.money(sales)} تومان</div></div>
                        ${pct != null ? `<div class="pct">${faDigits(pct)}٪</div>` : ''}
                    </div>
                    <div class="bar"><span class="sales" style="width:${Math.min(100, pct || 0)}%"></span></div>
                    <div class="cap">${c.sales_amount == null ? 'فروش از فاکتورهای CRM حساب می‌شود (این بخش هنوز ساخته نشده).' : ''}</div>
                </div>
                <div class="uc-goal">
                    <div class="top">
                        <span class="ic"><i class="bi bi-bullseye"></i></span>
                        <div class="tx"><div class="t">تارگت فروش${c.target_period ? ' <span class="per">(' + esc(c.target_period) + ')</span>' : ''}</div>
                            <div class="a">${target > 0 ? UCRM.money(target) + ' تومان' : '<span class="text-muted">تعیین نشده</span>'}${suspended ? ' <span class="uc-target gray">معلق</span>' : ''}</div></div>
                    </div>
                    <div class="bar"><span class="time" style="width:${monthPct}%"></span></div>
                    <div class="cap">${month ? faDigits(month.day) + ' روز از ' + faDigits(month.days) + ' روز ماه گذشته (' + faDigits(monthPct) + '٪)' : ''}</div>
                </div>`;
        }

        function infoTab(d) {
            const c = d.customer, ro = d.roles || {};
            const none = '<span class="text-muted">ثبت نشده</span>';
            const legal = c.type !== 'individual';
            const phones = (c.phones || []).map(p => `<span class="num-ltr">${faDigits(p)}</span>`).join('، ') || none;
            const cr = d.change_request;
            const pending = cr && cr.status === 'pending';
            // مداد: مشتری فعال → ویرایش درجا (درخواست تغییر)؛ هنوز تأییدنشده → فرم کامل (مسیر تأیید قبلی)
            let editBtn = `<span class="uc-head-ico"><i class="bi bi-pencil-square"></i></span>`;
            if (d.can_request_change && pending) {
                editBtn = `<span class="uc-head-ico" title="یک درخواست تغییر در انتظار تأیید حسابداری است"><i class="bi bi-hourglass-split"></i></span>`;
            } else if (d.can_request_change) {
                editBtn = `<button type="button" class="uc-head-ico" onclick="startEdit()" title="ویرایش اطلاعات (با تأیید حسابداری)"><i class="bi bi-pencil-square"></i></button>`;
            } else if (c.can_edit && c.status !== 'active') {
                editBtn = `<a class="uc-head-ico" href="ucrm-customers.php?edit=${c.id}&back=detail" title="ویرایش مشتری"><i class="bi bi-pencil-square"></i></a>`;
            }
            const targetBtns = ro.manager ?
                `<a class="uc-head-ico" href="ucrm-targets.php" title="تنظیمات هدفگذاری"><i class="bi bi-gear"></i></a>
                 <a class="uc-head-ico" href="ucrm-targets.php" title="ویرایش تارگت"><i class="bi bi-pencil-square"></i></a>` :
                `<span class="uc-head-ico"><i class="bi bi-graph-up-arrow"></i></span>`;
            const events = (d.events || []).map(ev => `
                <li class="${/_rejected$/.test(ev.action) || ev.action === 'deleted' ? 'rej' : (/_approved$/.test(ev.action) ? 'ok' : '')}">
                    <div class="n">${esc(ev.note)}</div>
                    <div class="t">${TimeSync.formatJalali(ev.created_at)} ساعت ${TimeSync.formatTimeOnly(ev.created_at)}</div>
                </li>`).join('');
            return `
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="uc-card uc-panel h-100">
                            <div class="uc-panel-hd">${EDIT ? '<span class="uc-head-ico"><i class="bi bi-pencil-square"></i></span>' : editBtn}<h3>اطلاعات پایه و ارتباطی</h3></div>
                            ${changeBanner(cr)}
                            ${EDIT ? editForm(c) : `
                            ${row(legal ? 'bi-building' : 'bi-person', legal ? 'نام شرکت' : 'نام', esc(c.name))}
                            ${row('bi-journal-text', 'نام ' + (legal ? 'شرکت' : 'مشتری') + ' در نرم‌افزار حسابداری', c.accounting_name ? esc(c.accounting_name) : none)}
                            ${row('bi-telephone', 'شماره تماس', phones)}
                            ${row('bi-geo-alt', 'آدرس', esc(UCRM.fullAddress(c)) || none)}
                            ${row('bi-upc-scan', 'کد اقتصادی', c.economic_code ? faDigits(c.economic_code) : none)}
                            ${row('bi-card-text', legal ? 'شناسهٔ ملی' : 'کد ملی', c.national_id ? faDigits(c.national_id) : none)}`}
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="uc-card uc-panel h-100">
                            <div class="uc-panel-hd">${targetBtns}<h3>وضعیت فروش و اهداف</h3></div>
                            ${salesBlock(c, d.month)}
                        </div>
                    </div>
                </div>
                ${c.note ? `<div class="uc-card"><h3><i class="bi bi-sticky ms-1"></i> توضیحات</h3><div>${esc(c.note)}</div></div>` : ''}
                <div class="uc-card">
                    <h3><i class="bi bi-clock-history ms-1"></i> تاریخچه</h3>
                    ${events ? `<ul class="uc-timeline">${events}</ul>` : '<div class="text-muted small">سابقه‌ای ثبت نشده است.</div>'}
                </div>`;
        }

        // بنر درخواست تغییر: در انتظار (با جدول تغییرات و «لغو درخواست») یا آخرین ردشده (با دلیل)
        function changeBanner(cr) {
            if (!cr) return '';
            const val = v => Array.isArray(v) ? v.map(x => faDigits(x)).join('، ') : (v ? esc(faDigits(String(v))) : '—');
            const rows = (cr.changes || []).map(ch => `<tr><td class="lbl">${esc(ch.label)}</td><td>${val(ch.old)}</td><td class="new">${val(ch.new)}</td></tr>`).join('');
            const when = TimeSync.formatJalali(cr.created_at);
            if (cr.status === 'pending') {
                return `<div class="uc-chg-banner wait">
                    <div class="t"><i class="bi bi-hourglass-split ms-1"></i>درخواست تغییر اطلاعات (${esc(cr.requester_name)}، ${when}) در انتظار تأیید حسابداری است.
                    ${cr.can_cancel ? '<button type="button" class="btn btn-sm btn-link text-danger p-0 me-2" onclick="cancelChange()">لغو درخواست</button>' : ''}</div>
                    <table class="uc-chg-table"><thead><tr><th>فیلد</th><th>مقدار فعلی</th><th>مقدار جدید</th></tr></thead><tbody>${rows}</tbody></table></div>`;
            }
            if (cr.status === 'rejected') {
                return `<div class="uc-chg-banner rej"><div class="t"><i class="bi bi-x-circle ms-1"></i>آخرین درخواست تغییر اطلاعات (${when}) توسط حسابداری${cr.decided_by_name ? ' (' + esc(cr.decided_by_name) + ')' : ''} رد شد.
                    دلیل: «${esc(cr.reject_reason)}»</div></div>`;
            }
            return '';
        }

        function field(icon, label, input) {
            return `<div class="uc-info-row edit"><span class="ic"><i class="bi ${icon}"></i></span>
                <div class="tx"><label class="k">${label}</label>${input}</div></div>`;
        }

        function editForm(c) {
            const legal = c.type !== 'individual';
            return `<div id="edAlert" class="px-4 pt-3"></div>
                ${field(legal ? 'bi-building' : 'bi-person', legal ? 'نام شرکت' : 'نام', `<input type="text" class="form-control" id="ed_name" value="${esc(c.name)}">`)}
                ${field('bi-journal-text', 'نام ' + (legal ? 'شرکت' : 'مشتری') + ' در نرم‌افزار حسابداری', `<input type="text" class="form-control" id="ed_acc" maxlength="255" value="${esc(c.accounting_name || '')}">`)}
                ${field('bi-telephone', 'شماره تماس', `<div class="input-group"><input type="text" class="form-control" id="ed_phone_in" inputmode="tel" autocomplete="off" placeholder="شماره را بنویسید و Enter بزنید">
                    <button type="button" class="btn btn-outline-secondary" onclick="addPhone()"><i class="bi bi-plus-lg"></i></button></div><div class="uc-chips" id="edChips"></div>`)}
                ${field('bi-geo-alt', 'آدرس', `<div class="row g-2 mb-2"><div class="col-6"><select class="form-select" id="ed_province"><option value="">استان</option></select></div>
                    <div class="col-6"><input type="text" class="form-control" id="ed_city" placeholder="شهر" autocomplete="off"></div></div>
                    <input type="text" class="form-control" id="ed_address" value="${esc(c.address || '')}" placeholder="آدرس کامل">`)}
                ${field('bi-upc-scan', 'کد اقتصادی', `<input type="text" class="form-control" id="ed_eco" inputmode="numeric" autocomplete="off" value="${esc(faDigits(c.economic_code || ''))}" placeholder="اختیاری — ۱۰ تا ۱۲ رقم">`)}
                ${field('bi-card-text', legal ? 'شناسهٔ ملی' : 'کد ملی', `<input type="text" class="form-control" id="ed_nid" inputmode="numeric" autocomplete="off" value="${esc(faDigits(c.national_id || ''))}" placeholder="اختیاری">`)}
                <div class="uc-edit-acts">
                    <button type="button" class="btn btn-primary" id="edSend" onclick="sendChange()"><i class="bi bi-check2 ms-1"></i>درخواست تأیید حسابداری</button>
                    <button type="button" class="btn uc-btn-soft" onclick="stopEdit()"><i class="bi bi-x-lg ms-1"></i>انصراف</button>
                </div>`;
        }

        function renderChips() {
            document.getElementById('edChips').innerHTML = phones.map((p, i) =>
                `<span class="uc-chip"><span class="num-ltr">${faDigits(p)}</span><button type="button" title="حذف" onclick="removePhone(${i})">×</button></span>`).join('');
        }

        function addPhone() {
            const el = document.getElementById('ed_phone_in');
            const v = UCRM.toEn(el.value).replace(/\D/g, '');
            if (!v) return false;
            if (v.length < 4 || v.length > 15) {
                showToast('شماره تماس معتبر نیست.', 'warning');
                return false;
            }
            if (!phones.includes(v)) phones.push(v);
            el.value = '';
            renderChips();
            el.focus();
            return true;
        }

        function removePhone(i) {
            phones.splice(i, 1);
            renderChips();
        }

        function startEdit() {
            EDIT = true;
            phones = (DATA.customer.phones || []).slice();
            showTab('info');
            const c = DATA.customer;
            const ps = document.getElementById('ed_province'), ci = document.getElementById('ed_city');
            IranProvinces.bind(ps, ci);
            IranProvinces.setValue(ps, ci, c.province || '', c.city || '');
            renderChips();
            document.getElementById('ed_phone_in').addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); addPhone(); }
            });
            if (window.IranId) IranId.attachLive({ national: document.getElementById('ed_nid'), economic: document.getElementById('ed_eco'),
                type: { value: c.type, dataset: {}, addEventListener() {} } });
            const n = document.getElementById('ed_name');
            n.focus();
            n.select();
        }

        function stopEdit() {
            EDIT = false;
            showTab('info');
        }

        async function sendChange() {
            if (document.getElementById('ed_phone_in').value.trim() && !addPhone()) return;
            const c = DATA.customer;
            const body = {
                name: document.getElementById('ed_name').value.trim(),
                accounting_name: document.getElementById('ed_acc').value.trim(),
                phones: phones.slice(),
                province: document.getElementById('ed_province').value,
                city: document.getElementById('ed_city').value.trim(),
                address: document.getElementById('ed_address').value.trim(),
                economic_code: UCRM.toEn(document.getElementById('ed_eco').value.trim()),
                national_id: UCRM.toEn(document.getElementById('ed_nid').value.trim()),
            };
            const alertBox = m => document.getElementById('edAlert').innerHTML = m ? `<div class="alert alert-danger py-2 mb-0">${m}</div>` : '';
            const miss = [];
            if (!body.name) miss.push('نام');
            if (!body.phones.length) miss.push('شماره تماس');
            if (miss.length) return alertBox('این فیلدها الزامی‌اند: ' + miss.join('، '));
            if (window.IranId) {
                const chk = { type: c.type };
                if (body.national_id !== (c.national_id || '')) chk.national_id = body.national_id;
                if (body.economic_code !== (c.economic_code || '')) chk.economic_code = body.economic_code;
                const errs = IranId.checkCustomer(chk);
                if (errs.length) return alertBox(errs.join('<br>'));
            }
            const btn = document.getElementById('edSend');
            btn.disabled = true;
            try {
                const r = await UCRM.send('POST', '/customers/' + CID + '/change-request', body);
                showToast(r.message || 'درخواست فرستاده شد', 'success');
                EDIT = false;
                render(await UCRM.get('/customers/' + CID));
            } catch (e) {
                alertBox(esc(e.message || 'خطا'));
                btn.disabled = false;
            }
        }

        function cancelChange() {
            const cr = DATA.change_request;
            uiConfirm('درخواست تغییر اطلاعات این مشتری لغو شود؟', async () => {
                try {
                    const r = await UCRM.send('POST', '/change-requests/' + cr.id + '/cancel', {});
                    showToast(r.message || 'لغو شد', 'success');
                    render(await UCRM.get('/customers/' + CID));
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            }, { danger: true, yesText: 'لغو درخواست', noText: 'انصراف' });
        }

        // تب «برنامهٔ پیگیری» (طرح ۱۴۰۵/۰۸/۰۲): پیگیری‌های ثبت‌شده. ستون‌ها: توضیحات | تاریخ پیگیری | وضعیت | عملیات
        // (جای «وضعیت» و «توضیحات» نسبت به طرح عوض شده). عقب‌افتاده = «در انتظار» با زمان گذشته (از سرور).
        function fuStatus(f) {
            if (f.status === 'done') return '<span class="uc-st done"><span class="dot"></span>انجام شده</span>';
            if (f.status === 'cancelled') return '<span class="uc-st cancel"><span class="dot"></span>لغو شده</span>';
            if (f.overdue) return '<span class="uc-st late"><span class="dot"></span>عقب‌افتاده</span>';
            return '<span class="uc-st wait"><span class="dot"></span>در انتظار</span>';
        }

        function planTab() {
            const rows = FU.items.map(f => {
                const ops = FU.can_manage ? `<div class="uc-fu-ops">
                        <div class="dropdown">
                            <button type="button" class="ic" data-bs-toggle="dropdown" title="بیشتر"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                ${f.status !== 'done' ? `<li><button class="dropdown-item" onclick="fuSetStatus(${f.id}, 'done')"><i class="bi bi-check2-circle ms-2 text-success"></i>انجام شد</button></li>` : ''}
                                ${f.status !== 'cancelled' ? `<li><button class="dropdown-item" onclick="fuSetStatus(${f.id}, 'cancelled')"><i class="bi bi-x-circle ms-2 text-danger"></i>لغو</button></li>` : ''}
                                ${f.status !== 'pending' ? `<li><button class="dropdown-item" onclick="fuSetStatus(${f.id}, 'pending')"><i class="bi bi-arrow-counterclockwise ms-2"></i>بازگشت به «در انتظار»</button></li>` : ''}
                            </ul>
                        </div>
                        <button type="button" class="ic edit" title="ویرایش" onclick="openFollowup(${f.id})"><i class="bi bi-pencil-square"></i></button>
                        <button type="button" class="ic del" title="حذف" onclick="fuDelete(${f.id})"><i class="bi bi-trash"></i></button>
                    </div>` : '<span class="text-muted small">—</span>';
                return `<tr>
                    <td><div class="fu-t">${esc(f.title)}</div>
                        <div class="fu-m"><span class="uc-fu-type">${FU_TYPE[f.type] || esc(f.type)}</span>${f.note ? ' ' + esc(f.note) : ''}</div></td>
                    <td class="fu-d"><b>${TimeSync.formatJalali(f.due_at)}</b><span>${TimeSync.formatTimeOnly(f.due_at)}</span></td>
                    <td>${fuStatus(f)}</td>
                    <td>${ops}</td>
                </tr>`;
            }).join('');
            const empty = !FU.loaded ? '<div class="spinner-border spinner-border-sm"></div> در حال بارگذاری…' :
                '<i class="bi bi-calendar-check"></i>هنوز پیگیری‌ای برای این مشتری ثبت نشده است.' +
                (FU.can_manage ? ' <button type="button" class="btn btn-sm btn-link p-0 me-1" onclick="openFollowup()">ثبت پیگیری جدید</button>' : '');
            return `<div class="uc-card uc-plan-card">
                <table class="uc-plan-table">
                    <thead><tr><th>توضیحات</th><th>تاریخ پیگیری</th><th>وضعیت</th><th class="ops">عملیات</th></tr></thead>
                    <tbody>${rows || `<tr><td colspan="4" class="empty">${empty}</td></tr>`}</tbody>
                </table>
            </div>`;
        }

        async function loadFollowups() {
            try {
                const d = await UCRM.get('/customers/' + CID + '/followups');
                FU = { items: d.items || [], can_manage: !!d.can_manage, loaded: true };
            } catch (e) {
                FU = { items: [], can_manage: false, loaded: true };
                showToast(e.message || 'خطا در دریافت پیگیری‌ها', 'error');
            }
            syncFollowupButtons();
            if (document.querySelector('.uc-tabs .tb.active[data-tab="plan"]')) showTab('plan');
        }

        // «پیگیری جدید» (سربرگ) و دکمهٔ گرد «+»: فقط کارشناس همان مشتری یا مدیر
        function syncFollowupButtons() {
            const btn = document.getElementById('btnNewFu'), fab = document.getElementById('ucFab');
            if (btn) {
                btn.disabled = !FU.can_manage;
                btn.title = FU.can_manage ? 'ثبت پیگیری جدید' : 'فقط کارشناس همین مشتری یا مدیر می‌تواند پیگیری ثبت کند';
            }
            if (fab) fab.hidden = !FU.can_manage;
        }

        function pad2(n) { return String(n).padStart(2, '0'); }

        function openFollowup(id) {
            const f = id ? FU.items.find(x => x.id === id) : null;
            fuEditId = f ? f.id : 0;
            document.getElementById('fuTitleHd').textContent = f ? 'ویرایش پیگیری' : 'ثبت پیگیری جدید';
            document.getElementById('fuSave').textContent = f ? 'ذخیرهٔ تغییرات' : 'ثبت پیگیری';
            document.getElementById('fuAlert').innerHTML = '';
            document.getElementById('fuTitle').value = f ? f.title : '';
            const ty = document.getElementById('fuType');
            ty.value = f ? f.type : '';
            ty.dispatchEvent(new Event('change'));
            document.getElementById('fuNote').value = f ? (f.note || '') : '';
            // پیش‌فرض: امروزِ سرور، ساعت کامل بعدی (نه ساعت دستگاه)
            let g, hm;
            if (f) {
                g = f.due_at.slice(0, 10);
                hm = f.due_at.slice(11, 16);
            } else {
                const p = TimeSync.serverParts();
                g = TimeSync.serverToday();
                hm = pad2(Math.min(23, p.h + 1)) + ':00';
            }
            const di = document.getElementById('fuDate');
            di.setAttribute('data-date', g);
            di.value = convertToJalali(g);
            document.getElementById('fuTime').value = hm;
            fuModal.show();
        }

        async function saveFollowup() {
            const di = document.getElementById('fuDate');
            let g = di.getAttribute('data-date') || '';
            if (!g && di.value && typeof convertToGregorian === 'function') g = convertToGregorian(di.value) || '';
            const body = {
                title: document.getElementById('fuTitle').value.trim(),
                type: document.getElementById('fuType').value,
                due_at: g + ' ' + document.getElementById('fuTime').value,
                note: document.getElementById('fuNote').value.trim(),
            };
            const alertBox = m => document.getElementById('fuAlert').innerHTML = m ? `<div class="alert alert-danger py-2">${m}</div>` : '';
            const miss = [];
            if (!body.title) miss.push('عنوان پیگیری');
            if (!body.type) miss.push('نوع پیگیری');
            if (!g || !document.getElementById('fuTime').value) miss.push('تاریخ و زمان');
            if (miss.length) return alertBox('این فیلدها الزامی‌اند: ' + miss.join('، '));
            const btn = document.getElementById('fuSave');
            btn.disabled = true;
            try {
                const r = fuEditId ? await UCRM.send('PUT', '/followups/' + fuEditId, body) :
                    await UCRM.send('POST', '/customers/' + CID + '/followups', body);
                fuModal.hide();
                showToast(r.message || 'ذخیره شد', 'success');
                EDIT = false;
                showTab('plan');
                await loadFollowups();
            } catch (e) {
                alertBox(esc(e.message || 'خطا'));
            } finally {
                btn.disabled = false;
            }
        }

        async function fuSetStatus(id, status) {
            try {
                const r = await UCRM.send('POST', '/followups/' + id + '/status', { status });
                showToast(r.message || 'ذخیره شد', 'success');
                await loadFollowups();
            } catch (e) {
                showToast(e.message || 'خطا', 'error');
            }
        }

        function fuDelete(id) {
            const f = FU.items.find(x => x.id === id);
            uiConfirm('پیگیری «' + esc(f ? f.title : '') + '» حذف شود؟', async () => {
                try {
                    const r = await UCRM.send('DELETE', '/followups/' + id, {});
                    showToast(r.message || 'حذف شد', 'success');
                    await loadFollowups();
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            }, { danger: true, yesText: 'حذف', noText: 'انصراف' });
        }

        // تب «تاریخچهٔ پیگیری» (طرح ۱۴۰۵/۰۸/۰۲): زیرتب «تماس‌ها» و «پیامک‌ها» + جستجو؛ نمایش مثل
        // تاریخچهٔ جزئیات کار (minimal-list / ml-item در custom.css). فعلا نمایشی — هنوز تماس/پیامکی ثبت نمی‌شود.
        let HIST_SUB = 'calls';
        const HIST_EMPTY = {
            calls: 'هنوز تماسی برای این مشتری ثبت نشده است.',
            sms: 'هنوز پیامکی برای این مشتری فرستاده نشده است.',
        };

        function historyTab() {
            return `<div class="uc-card uc-hist-card">
                <div class="uc-hist-bar">
                    <div class="uc-hist-search"><i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="histQ" placeholder="${HIST_SUB === 'sms' ? 'جستجو در پیامک‌ها…' : 'جستجو در رخدادهای تماس…'}" autocomplete="off"></div>
                    <div class="spacer"></div>
                    <div class="uc-hist-seg">
                        <button type="button" class="${HIST_SUB === 'calls' ? 'active' : ''}" data-sub="calls"><i class="bi bi-person ms-1"></i>تماس‌ها</button>
                        <button type="button" class="${HIST_SUB === 'sms' ? 'active' : ''}" data-sub="sms"><i class="bi bi-chat-left-text ms-1"></i>پیامک‌ها</button>
                    </div>
                </div>
                <div class="uc-hist-list">
                    <div class="minimal-list" id="histList"></div>
                    <div class="uc-hist-empty"><i class="bi bi-clock-history"></i>${HIST_EMPTY[HIST_SUB]}
                        <span class="uc-soon-tag sm">${HIST_SUB === 'sms' ? 'ارسال پیامک' : 'ثبت تماس'} به‌زودی</span></div>
                </div>
            </div>`;
        }

        function bindHistory() {
            document.querySelectorAll('.uc-hist-seg button').forEach(b => b.addEventListener('click', () => {
                HIST_SUB = b.dataset.sub;
                showTab('history');
            }));
        }

        // تب «پیش‌فاکتورها» (طرح ۱۴۰۵/۰۸/۰۲): جستجو + همه / امروز / عقب‌افتاده + جدول. فعلا نمایشی —
        // پیش‌فاکتور CRM هنوز ساخته نشده؛ کاربرد «عقب‌افتاده» بعدا مشخص می‌شود. مبلغ به تومان (مثل بقیهٔ CRM).
        let PF_FILTER = 'all';

        function proformaTab() {
            const f = (k, l) => `<button type="button" class="${PF_FILTER === k ? 'active' : ''}" data-pf="${k}">${l}</button>`;
            return `<div class="uc-card uc-hist-card">
                <div class="uc-hist-bar">
                    <div class="uc-hist-search"><i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="pfQ" placeholder="جستجو در پیش‌فاکتورها…" autocomplete="off"></div>
                    <div class="uc-hist-seg uc-pf-seg">${f('all', 'همه')}${f('today', 'امروز')}${f('overdue', 'عقب‌افتاده')}</div>
                </div>
                <table class="uc-plan-table">
                    <thead><tr><th>شمارهٔ پیش‌فاکتور</th><th>تاریخ صدور</th><th>مبلغ (تومان)</th><th>وضعیت</th><th class="ops">عملیات</th></tr></thead>
                    <tbody><tr><td colspan="5" class="empty"><i class="bi bi-file-earmark-text"></i>
                        هنوز پیش‌فاکتوری برای این مشتری صادر نشده است.<span class="uc-soon-tag sm">صدور پیش‌فاکتور به‌زودی</span></td></tr></tbody>
                </table>
            </div>`;
        }

        function bindProformas() {
            document.querySelectorAll('.uc-pf-seg button').forEach(b => b.addEventListener('click', () => {
                PF_FILTER = b.dataset.pf;
                showTab('proformas');
            }));
        }

        function soonTab(key) {
            const s = SOON[key];
            return `<div class="uc-card uc-soon-box"><i class="bi ${s.icon}"></i><h3>${s.title}</h3><p>${s.text}</p>
                <span class="uc-soon-tag">در مرحلهٔ بعد فعال می‌شود</span></div>`;
        }

        function showTab(key) {
            document.querySelectorAll('.uc-tabs .tb').forEach(b => b.classList.toggle('active', b.dataset.tab === key));
            document.getElementById('ucTab').innerHTML = key === 'info' ? infoTab(DATA) :
                (key === 'plan' ? planTab() : (key === 'history' ? historyTab() : (key === 'proformas' ? proformaTab() : soonTab(key))));
            if (key === 'history') bindHistory();
            if (key === 'proformas') bindProformas();
            try { sessionStorage.setItem('ucrm-cust-tab', key); } catch (e) {}
        }

        function render(d) {
            DATA = d;
            const c = d.customer;
            document.getElementById('crumbName').textContent = c.name;
            document.title = c.name + ' - مشتریان CRM';
            const legal = c.type !== 'individual';
            const soonBtn = (cls, icon, label, what) =>
                `<button type="button" class="btn ${cls} uc-soon" data-soon="${what}" title="${label} — به‌زودی"><i class="bi ${icon} ms-1"></i>${label}</button>`;
            document.getElementById('ucBody').innerHTML = `
                <div class="uc-card uc-cust-head">
                    <div class="av"><i class="bi ${legal ? 'bi-building' : 'bi-person'}"></i></div>
                    <div class="nm">
                        <h2>${esc(c.name)}</h2>
                        <div class="meta">${statusPill(c)}</div>
                        <div class="rep"><i class="bi bi-person ms-1"></i>کارشناس: ${c.rep_name ? esc(c.rep_name) : '<span class="text-muted">تعیین نشده</span>'}</div>
                    </div>
                    <div class="spacer"></div>
                    <div class="acts">
                        ${soonBtn('btn-light uc-btn-soft', 'bi-chat-left-text', 'ارسال پیامک', 'ارسال پیامک به مشتری')}
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-primary uc-more-btn" data-bs-toggle="dropdown" aria-expanded="false" title="بیشتر"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button class="dropdown-item uc-soon" data-soon="صدور پیش‌فاکتور"><i class="bi bi-file-earmark-text ms-2"></i>پیش‌فاکتور <span class="uc-soon-tag sm">به‌زودی</span></button></li>
                                <li><button class="dropdown-item uc-soon" data-soon="ثبت سفارش خرید"><i class="bi bi-cart ms-2"></i>سفارش خرید <span class="uc-soon-tag sm">به‌زودی</span></button></li>
                                <li><button class="dropdown-item uc-soon" data-soon="ثبت سفارش فروش"><i class="bi bi-cart-check ms-2"></i>سفارش فروش <span class="uc-soon-tag sm">به‌زودی</span></button></li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-primary" id="btnNewFu" onclick="openFollowup()"><i class="bi bi-plus-lg ms-1"></i>پیگیری جدید</button>
                    </div>
                </div>
                <div class="uc-card uc-tabs">${TABS.map(t =>
                    `<button type="button" class="tb" data-tab="${t.key}"><i class="bi ${t.icon} ms-1"></i>${t.label}</button>`).join('')}</div>
                <div id="ucTab"></div>`;
            document.querySelectorAll('.uc-tabs .tb').forEach(b => b.addEventListener('click', () => { EDIT = false; showTab(b.dataset.tab); }));
            let tab = 'info';
            try { tab = sessionStorage.getItem('ucrm-cust-tab') || 'info'; } catch (e) {}
            showTab(TABS.some(t => t.key === tab) ? tab : 'info');
            syncFollowupButtons();
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) {
                location.href = '../index.php';
                return;
            }
            fuModal = new bootstrap.Modal(document.getElementById('fuModal'));
            document.getElementById('fuSave').addEventListener('click', saveFollowup);
            document.getElementById('ucFab').addEventListener('click', () => openFollowup());
            // اولین فیلد قابل‌ویرایش فوکوس بگیرد (قاعدهٔ مودال‌های سایت)
            document.getElementById('fuModal').addEventListener('shown.bs.modal', () => document.getElementById('fuTitle').focus());
            // دکمه‌های «به‌زودی» (پیامک، پیش‌فاکتور، سفارش‌ها): فقط پیام
            document.addEventListener('click', e => {
                const b = e.target.closest('.uc-soon');
                if (!b) return;
                e.preventDefault();
                showToast('«' + (b.dataset.soon || 'این بخش') + '» در مرحله‌های بعدی CRM فعال می‌شود.', 'info');
            });
            try {
                render(await UCRM.get('/customers/' + CID));
                loadFollowups();
            } catch (e) {
                document.getElementById('ucBody').innerHTML =
                    `<div class="uc-card text-center text-muted py-5">${esc(e.message || 'خطا در بارگذاری')}<br><a class="btn btn-outline-secondary mt-3" href="ucrm-customers.php">بازگشت به فهرست</a></div>`;
            }
        });
    </script>
</body>

</html>
