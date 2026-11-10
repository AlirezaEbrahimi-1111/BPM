<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// جزئیات مشتری CRM (حسابداری غیررسمی) — طرح ۱۴۰۵/۰۸/۰۲:
// سربرگ (نام، وضعیت، کارشناس، دکمه‌ها) + ۵ تب. فعلا تب «اطلاعات پایه» کامل است؛
// ویرایش اطلاعات مشتری فعال درجاست و به‌صورت «درخواست تغییر» برای تأیید حسابداری می‌رود
// (crm-service/ucrm_changes.go)؛
// «برنامهٔ پیگیری»، «یادداشت‌های تماس» و «پیش‌فاکتورها» فقط نمایشی‌اند
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
    <script src="<?= asset('../../assets/js/iran-id-validate.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <script src="<?= asset('../../assets/js/persian-date-utils.js') ?>"></script>
    <script src="<?= asset('../../assets/js/persian-datepicker.js') ?>"></script>
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
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
                        <input type="text" class="form-control fu-time" id="fuTime" dir="ltr" inputmode="numeric" maxlength="5" autocomplete="off" placeholder="۱۴:۳۰" title="ساعت، مثلا ۱۴:۳۰" oninput="fuTimeInput(this)" onblur="fuTimeBlur(this)">
                    </div>
                    <label class="form-label">یادداشت‌ها</label>
                    <div class="fu-ico"><i class="bi bi-chat-square-dots"></i>
                        <textarea class="form-control" id="fuNote" rows="3" placeholder="یادداشت خود را اینجا بنویسید…"></textarea></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-primary" id="fuSave">ثبت پیگیری</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ارسال پیامک به مشتری (قالب = الگوی تأییدشدهٔ ملی‌پیامک) -->
    <div class="modal fade" id="smsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-chat-left-text ms-2"></i>ارسال پیامک به مشتری</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="smsPhone">شمارهٔ موبایل <span class="text-danger">*</span></label>
                    <select class="form-select mb-3" id="smsPhone"></select>
                    <label class="form-label" for="smsTpl">قالب پیامک <span class="text-danger">*</span></label>
                    <select class="form-select mb-3" id="smsTpl"></select>
                    <div id="smsVars"></div>
                    <label class="form-label small text-muted mb-1">پیش‌نمایش</label>
                    <div class="uc-sms-preview" id="smsPreview">—</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="smsSend"><i class="bi bi-send ms-1"></i>ارسال پیامک</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ثبت گزارش تماس (تب «تاریخچهٔ پیگیری ← تماس‌ها»، و «انجام شد»ِ پیگیریِ تماس) -->
    <div class="modal fade" id="callModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-telephone ms-2"></i>ثبت گزارش تماس</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="callFor"></div>
                    <label class="form-label">نتیجهٔ تماس <span class="text-danger">*</span></label>
                    <div class="uc-call-results mb-3" id="callResults">
                        <input type="radio" class="btn-check" name="callResult" id="cr_answered" value="answered" checked><label class="btn btn-sm btn-outline-success" for="cr_answered">پاسخ داد</label>
                        <input type="radio" class="btn-check" name="callResult" id="cr_no_answer" value="no_answer"><label class="btn btn-sm btn-outline-warning" for="cr_no_answer">پاسخ نداد</label>
                        <input type="radio" class="btn-check" name="callResult" id="cr_busy" value="busy"><label class="btn btn-sm btn-outline-warning" for="cr_busy">مشغول بود</label>
                        <input type="radio" class="btn-check" name="callResult" id="cr_wrong_number" value="wrong_number"><label class="btn btn-sm btn-outline-danger" for="cr_wrong_number">شماره اشتباه</label>
                    </div>
                    <label class="form-label" for="callNote">گزارش تماس <span class="text-danger" id="callNoteReq">*</span></label>
                    <textarea class="form-control mb-3" id="callNote" rows="3" placeholder="خلاصهٔ صحبت با مشتری…"></textarea>
                    <label class="form-label" for="callFiles">پیوست <span class="text-muted small">(اختیاری — حداکثر ۵ فایل، هر کدام ۱۰ مگابایت)</span></label>
                    <input type="file" class="form-control mb-3" id="callFiles" multiple
                        accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.mp3,.m4a,.ogg,.wav,.amr">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="callNextOn">
                        <label class="form-check-label" for="callNextOn">ثبت پیگیری بعدی</label>
                    </div>
                    <div id="callNextBox" hidden>
                        <div class="row g-2">
                            <div class="col-7">
                                <div class="persian-datepicker-wrapper">
                                    <input type="text" class="persian-datepicker-input form-control" id="callNextDate" placeholder="تاریخ" readonly>
                                    <div class="persian-datepicker">
                                <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                                <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                                <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                            </div>
                                </div>
                            </div>
                            <div class="col-5"><input type="time" class="form-control" id="callNextTime" value="10:00" aria-label="ساعت"></div>
                        </div>
                        <input type="text" class="form-control mt-2" id="callNextTitle" placeholder="عنوان پیگیری بعدی (اختیاری)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="callSave"><i class="bi bi-check-lg ms-1"></i>ثبت گزارش</button>
                </div>
            </div>
        </div>
    </div>

    <!-- تغییر تاریخ پیگیریِ پیش‌فاکتور (تب «پیش‌فاکتورها») -->
    <div class="modal fade" id="pfFuModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-event ms-2"></i>تغییر تاریخ پیگیری</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="pfFuFor"></div>
                    <label class="form-label small mb-1">تاریخ پیگیری بعدی</label>
                    <div class="persian-datepicker-wrapper">
                        <input type="text" class="persian-datepicker-input form-control" id="pfFuDate" readonly>
                        <div class="persian-datepicker">
                            <div class="datepicker-header"><button type="button" class="datepicker-nav" data-action="prev">►</button><span class="datepicker-current"></span><button type="button" class="datepicker-nav" data-action="next">◄</button></div>
                            <div class="datepicker-weekdays"><div class="datepicker-weekday">ش</div><div class="datepicker-weekday">ی</div><div class="datepicker-weekday">د</div><div class="datepicker-weekday">س</div><div class="datepicker-weekday">چ</div><div class="datepicker-weekday">پ</div><div class="datepicker-weekday">ج</div></div>
                            <div class="datepicker-days"></div><button type="button" class="datepicker-today-btn">امروز</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="pfFuSave"><i class="bi bi-check-lg ms-1"></i>ثبت</button>
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
            { key: 'proformas', label: 'پیش‌فاکتورها', icon: 'bi-file-earmark-text' },
        ];
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
                        <div class="tx"><div class="t">${target > 0 ? UCRM.basisLabel(c.target_basis) : 'تارگت فروش'}${c.target_period ? ' <span class="per">(' + esc(c.target_period) + ')</span>' : ''}</div>
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
                `<a class="uc-head-ico" href="ucrm-targets.php" title="تنظیمات تارگت‌گذاری"><i class="bi bi-gear"></i></a>
                 <a class="uc-head-ico" href="ucrm-targets.php" title="ویرایش تارگت"><i class="bi bi-pencil-square"></i></a>` :
                `<span class="uc-head-ico"><i class="bi bi-graph-up-arrow"></i></span>`;
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
                            ${row('bi-geo-alt', 'آدرس', esc(c.address || '') || none)}
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
                ${c.note ? `<div class="uc-card"><h3><i class="bi bi-sticky ms-1"></i> توضیحات</h3><div>${esc(c.note)}</div></div>` : ''}`;
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
                ${field('bi-geo-alt', 'آدرس', `<input type="text" class="form-control" id="ed_address" value="${esc(c.address || '')}" placeholder="آدرس کامل">`)}
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
            renderChips();
            document.getElementById('ed_phone_in').addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); addPhone(); }
            });
            if (window.IranId) IranId.attachLive({ national: document.getElementById('ed_nid'),
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
                // استان، شهر و کد اقتصادی در فرم نیستند؛ مقدار ثبت‌شده حفظ می‌شود تا درخواست تغییرِ «پاک شدن» ساخته نشود
                province: c.province || '',
                city: c.city || '',
                address: document.getElementById('ed_address').value.trim(),
                economic_code: c.economic_code || '',
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
            // «ارسال پیامک» هم مثل پیگیری: کارشناسِ همین مشتری یا مدیر
            const sms = document.getElementById('btnSms');
            if (sms) sms.hidden = !FU.can_manage;
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
            document.getElementById('fuTime').value = faDigits(hm);
            fuModal.show();
        }

        // کادر ساعت متنی است: input type="time" اعدادش را از زبان مرورگر می‌گیرد و فارسی نمی‌شود.
        // نمایش فارسی؛ مقدار ارسالی به سرور همان HH:MM انگلیسی
        function fuTimeDigits(raw) {
            return UCRM.toEn(raw).replace(/\D/g, '').slice(0, 4);
        }

        function fuTimeInput(el) {
            const d = fuTimeDigits(el.value);
            const v = faDigits(d.length > 2 ? d.slice(0, 2) + ':' + d.slice(2) : d);
            if (el.value !== v) el.value = v;
        }

        // «۹» → 09:00، «۹۳۰» → 09:30، «۱۴۳۰» → 14:30؛ نامعتبر → ''
        function fuTimeValue(raw) {
            const d = fuTimeDigits(raw);
            if (!d) return '';
            const h = d.length <= 2 ? d : d.slice(0, d.length - 2);
            const m = d.length <= 2 ? '00' : d.slice(-2);
            if (+h > 23 || +m > 59) return '';
            return pad2(+h) + ':' + m;
        }

        function fuTimeBlur(el) {
            const v = fuTimeValue(el.value);
            if (v) el.value = faDigits(v);
        }

        async function saveFollowup() {
            const di = document.getElementById('fuDate');
            let g = di.getAttribute('data-date') || '';
            if (!g && di.value && typeof convertToGregorian === 'function') g = convertToGregorian(di.value) || '';
            const timeRaw = document.getElementById('fuTime').value;
            const time = fuTimeValue(timeRaw);
            const body = {
                title: document.getElementById('fuTitle').value.trim(),
                type: document.getElementById('fuType').value,
                due_at: g + ' ' + time,
                note: document.getElementById('fuNote').value.trim(),
            };
            const alertBox = m => document.getElementById('fuAlert').innerHTML = m ? `<div class="alert alert-danger py-2">${m}</div>` : '';
            const miss = [];
            if (!body.title) miss.push('عنوان پیگیری');
            if (!body.type) miss.push('نوع پیگیری');
            if (!g || !timeRaw.trim()) miss.push('تاریخ و زمان');
            if (miss.length) return alertBox('این فیلدها الزامی‌اند: ' + miss.join('، '));
            if (!time) return alertBox('ساعت معتبر نیست؛ آن را به‌شکل ۱۴:۳۰ بنویسید.');
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
            // پیگیریِ نوعِ «تماس» با گزارش «انجام شد» می‌شود (همان گزارش در تاریخچهٔ تماس‌ها می‌نشیند)
            const fu = FU.items.find(x => x.id === id);
            if (status === 'done' && fu && fu.type === 'call') { openCallModal(id); return; }
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

        // ── تماس‌ها (۱۴۰۵/۰۸/۱۹): گزارش‌های تماس مثل تاریخچهٔ تسک (crm-service/ucrm_calls.go) ──
        let CALLS = null, CALL_Q = '', callModal = null, callFollowupId = 0;
        const CALL_BADGE = { answered: 'ab-approved', no_answer: 'ab-pending', busy: 'ab-deadline', wrong_number: 'ab-rejected' };

        async function loadCalls() {
            try {
                CALLS = (await UCRM.get('/customers/' + CID + '/calls')).items || [];
            } catch (e) {
                CALLS = [];
                showToast(e.message || 'خطا در دریافت تماس‌ها', 'error');
            }
            if (document.querySelector('.uc-tabs .tb.active')?.dataset.tab === 'history') renderHistoryList();
        }

        function callHtml(x) {
            const files = (x.files || []).map(f =>
                `<button type="button" class="uc-call-file" onclick="downloadCallFile(${f.id}, this)" data-name="${esc(f.name)}"><i class="bi bi-paperclip"></i>${esc(f.name)}</button>`).join('');
            return `<div class="ml-item">
                <div class="ml-left">
                    <div class="ml-time">${TimeSync.formatJalali(x.created_at)}<br>${TimeSync.formatTimeOnly(x.created_at)}</div>
                    <span class="ml-action-badge ${CALL_BADGE[x.result] || ''}">${esc(x.result_label)}</span>
                </div>
                <div class="ml-right">
                    <div class="ml-user"><i class="bi bi-person"></i> ${esc(x.created_by || '')}</div>
                    ${x.followup_title ? `<div class="ml-reason-mini">پیگیری انجام‌شده: ${esc(x.followup_title)}</div>` : ''}
                    ${x.note ? `<div class="ml-notes">${esc(x.note).replace(/\n/g, '<br>')}</div>` : ''}
                    ${x.next_due_at ? `<div class="ml-reason-mini"><i class="bi bi-calendar-event"></i> پیگیری بعدی: ${TimeSync.formatJalali(x.next_due_at)} ساعت ${TimeSync.formatTimeOnly(x.next_due_at)}${x.next_title ? ' — ' + esc(x.next_title) : ''}</div>` : ''}
                    ${files ? `<div class="uc-call-files">${files}</div>` : ''}
                </div>
            </div>`;
        }

        function renderHistoryList() {
            const list = document.getElementById('histList');
            const empty = document.getElementById('histEmpty');
            if (!list) return;
            if (HIST_SUB === 'sms') {
                if (SMS === null) {
                    list.innerHTML = '<div class="uc-hist-empty"><span class="spinner-border spinner-border-sm ms-2"></span>در حال بارگذاری…</div>';
                    empty.hidden = true;
                    return;
                }
                const q = UCRM.toEn(SMS_Q).trim().toLowerCase();
                const rows = SMS.filter(x => !q || UCRM.toEn([x.template, x.text, x.phone, x.sent_by_name].join(' ')).toLowerCase().includes(q));
                list.innerHTML = rows.map(smsHtml).join('');
                empty.hidden = rows.length > 0;
                if (!rows.length) empty.querySelector('.msg').textContent = SMS.length ? 'پیامکی با این جستجو پیدا نشد.' : HIST_EMPTY.sms;
                return;
            }
            if (CALLS === null) {
                list.innerHTML = '<div class="uc-hist-empty"><span class="spinner-border spinner-border-sm ms-2"></span>در حال بارگذاری…</div>';
                empty.hidden = true;
                return;
            }
            const q = UCRM.toEn(CALL_Q).trim().toLowerCase();
            const rows = CALLS.filter(x => !q || UCRM.toEn([x.result_label, x.note, x.created_by, x.followup_title,
                (x.files || []).map(f => f.name).join(' ')].join(' ')).toLowerCase().includes(q));
            list.innerHTML = rows.map(callHtml).join('');
            empty.hidden = rows.length > 0;
            if (!rows.length) empty.querySelector('.msg').textContent = CALLS.length ? 'تماسی با این جستجو پیدا نشد.' : HIST_EMPTY.calls;
        }

        // ── پیامک‌ها (۱۴۰۵/۰۸/۱۹): تاریخچه + ارسال با قالب (crm-service/ucrm_sms.go) ──
        let SMS = null, SMS_TPL = null, smsModal = null, SMS_Q = '';
        const SMS_BADGE = { sent: ['ab-approved', 'فرستاده شد'], failed: ['ab-rejected', 'ناموفق'], queued: ['ab-pending', 'در صف'], sending: ['ab-pending', 'در حال ارسال'] };

        async function loadSms() {
            try {
                SMS = (await UCRM.get('/customers/' + CID + '/sms')).items || [];
            } catch (e) {
                SMS = [];
                showToast(e.message || 'خطا در دریافت پیامک‌ها', 'error');
            }
            if (document.querySelector('.uc-tabs .tb.active')?.dataset.tab === 'history') renderHistoryList();
        }

        function smsHtml(x) {
            const b = SMS_BADGE[x.status] || ['', x.status];
            return `<div class="ml-item">
                <div class="ml-left">
                    <div class="ml-time">${TimeSync.formatJalali(x.created_at)}<br>${TimeSync.formatTimeOnly(x.created_at)}</div>
                    <span class="ml-action-badge ${b[0]}">${b[1]}</span>
                </div>
                <div class="ml-right">
                    <div class="ml-user"><i class="bi bi-person"></i> ${esc(x.sent_by_name || '')}</div>
                    <div class="ml-reason-mini">${esc(x.template || 'قالب حذف‌شده')} — به <span class="num-ltr">${faDigits(esc(x.phone))}</span></div>
                    ${x.text ? `<div class="ml-notes">${esc(x.text)}</div>` : ''}
                    ${x.status === 'failed' && x.error ? `<div class="ml-reason-mini text-danger">علت: ${esc(x.error)}</div>` : ''}
                </div>
            </div>`;
        }

        function smsRender(preview, vals) {
            return String(preview || '').replace(/\{(\d+)\}/g, (m, i) => (vals[+i] || '').trim() || m);
        }

        async function openSmsModal() {
            const c = DATA.customer;
            const phoneSel = document.getElementById('smsPhone');
            phoneSel.innerHTML = (c.phones || []).map(p => `<option value="${esc(p)}">${faDigits(esc(p))}</option>`).join('');
            if (!(c.phones || []).length) return showToast('برای این مشتری شمارهٔ موبایلی ثبت نشده است.', 'warning');
            if (SMS_TPL === null) {
                try { SMS_TPL = (await UCRM.get('/sms-templates')).items || []; } catch (e) { SMS_TPL = []; }
            }
            if (!SMS_TPL.length) return showToast('هنوز قالب پیامکی تعریف نشده است؛ مدیر از «CRM ← قالب‌های پیامک» اضافه می‌کند.', 'warning');
            document.getElementById('smsTpl').innerHTML = SMS_TPL.map(t => `<option value="${t.id}">${esc(t.title)}</option>`).join('');
            renderSmsVars();
            smsModal.show();
        }

        // برای هر متغیرِ قالب یک فیلد؛ اگر برچسب «نام» دارد، نام مشتری از پیش پر می‌شود
        function renderSmsVars() {
            const t = SMS_TPL.find(x => x.id === +document.getElementById('smsTpl').value);
            const box = document.getElementById('smsVars');
            box.innerHTML = (t ? t.var_labels : []).map((l, i) =>
                `<label class="form-label small mb-1" for="smsV${i}">${esc(l)} <span class="text-danger">*</span></label>
                 <input type="text" class="form-control mb-2 sms-var" id="smsV${i}" value="${/نام/.test(l) ? esc(DATA.customer.name) : ''}">`).join('');
            box.querySelectorAll('.sms-var').forEach(el => el.addEventListener('input', renderSmsPreview));
            renderSmsPreview();
        }

        function renderSmsPreview() {
            const t = SMS_TPL.find(x => x.id === +document.getElementById('smsTpl').value);
            const vals = [...document.querySelectorAll('#smsVars .sms-var')].map(el => el.value);
            document.getElementById('smsPreview').textContent = t ? smsRender(t.preview, vals) : '—';
        }

        async function sendSms() {
            const t = SMS_TPL.find(x => x.id === +document.getElementById('smsTpl').value);
            const vals = [...document.querySelectorAll('#smsVars .sms-var')].map(el => el.value.trim());
            const miss = (t ? t.var_labels : []).find((l, i) => !vals[i]);
            if (miss) return showToast('مقدار «' + miss + '» را وارد کنید.', 'warning');
            const btn = document.getElementById('smsSend');
            btn.disabled = true;
            try {
                const res = await UCRM.send('POST', '/customers/' + CID + '/sms',
                    { template_id: t.id, phone: document.getElementById('smsPhone').value, args: vals });
                smsModal.hide();
                showToast(res.message || 'پیامک فرستاده شد.', 'success');
            } catch (e) {
                showToast(e.message || 'خطا در ارسال پیامک', 'error');
            } finally {
                btn.disabled = false;
                await loadSms(); // ناموفق هم در تاریخچه ثبت می‌شود
            }
        }

        function historyTab() {
            const canAdd = HIST_SUB === 'calls' && FU && FU.can_manage;
            const canSms = HIST_SUB === 'sms' && FU && FU.can_manage;
            return `<div class="uc-card uc-hist-card">
                <div class="uc-hist-bar">
                    <div class="uc-hist-search"><i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="histQ" placeholder="${HIST_SUB === 'sms' ? 'جستجو در پیامک‌ها…' : 'جستجو در گزارش تماس‌ها…'}" autocomplete="off" value="${esc(HIST_SUB === 'calls' ? CALL_Q : SMS_Q)}"></div>
                    ${canAdd ? '<button type="button" class="btn btn-primary btn-sm" onclick="openCallModal(0)"><i class="bi bi-telephone-plus ms-1"></i>ثبت گزارش تماس</button>' : ''}
                    ${canSms ? '<button type="button" class="btn btn-primary btn-sm" onclick="openSmsModal()"><i class="bi bi-send ms-1"></i>ارسال پیامک</button>' : ''}
                    <div class="spacer"></div>
                    <div class="uc-hist-seg">
                        <button type="button" class="${HIST_SUB === 'calls' ? 'active' : ''}" data-sub="calls"><i class="bi bi-person ms-1"></i>تماس‌ها</button>
                        <button type="button" class="${HIST_SUB === 'sms' ? 'active' : ''}" data-sub="sms"><i class="bi bi-chat-left-text ms-1"></i>پیامک‌ها</button>
                    </div>
                </div>
                <div class="uc-hist-list">
                    <div class="minimal-list" id="histList"></div>
                    <div class="uc-hist-empty" id="histEmpty"><i class="bi bi-clock-history"></i><span class="msg">${HIST_EMPTY[HIST_SUB]}</span></div>
                </div>
            </div>`;
        }

        function bindHistory() {
            document.querySelectorAll('.uc-hist-seg button').forEach(b => b.addEventListener('click', () => {
                HIST_SUB = b.dataset.sub;
                showTab('history');
            }));
            document.getElementById('histQ').addEventListener('input', e => {
                if (HIST_SUB === 'calls') CALL_Q = e.target.value; else SMS_Q = e.target.value;
                renderHistoryList();
            });
            renderHistoryList();
            if (HIST_SUB === 'calls' && CALLS === null) loadCalls();
            if (HIST_SUB === 'sms' && SMS === null) loadSms();
        }

        function openCallModal(followupId) {
            callFollowupId = followupId || 0;
            const fu = callFollowupId ? FU.items.find(x => x.id === callFollowupId) : null;
            document.getElementById('callFor').textContent = fu ? 'انجامِ پیگیری: ' + fu.title : '';
            document.getElementById('cr_answered').checked = true;
            document.getElementById('callNote').value = '';
            document.getElementById('callFiles').value = '';
            document.getElementById('callNextOn').checked = false;
            document.getElementById('callNextBox').hidden = true;
            document.getElementById('callNextTitle').value = '';
            const d = document.getElementById('callNextDate');
            d.removeAttribute('data-date');
            d.value = '';
            syncCallNoteReq();
            callModal.show();
        }

        function syncCallNoteReq() {
            const r = document.querySelector('input[name="callResult"]:checked');
            document.getElementById('callNoteReq').hidden = !(r && r.value === 'answered');
        }

        async function saveCall() {
            const result = (document.querySelector('input[name="callResult"]:checked') || {}).value || '';
            const note = document.getElementById('callNote').value.trim();
            if (result === 'answered' && !note) return showToast('برای تماسی که پاسخ داده شده، متن گزارش را بنویسید.', 'warning');
            const fd = new FormData();
            fd.append('result', result);
            fd.append('note', note);
            if (callFollowupId) fd.append('followup_id', callFollowupId);
            const files = document.getElementById('callFiles').files;
            if (files.length > 5) return showToast('حداکثر ۵ فایل می‌شود پیوست کرد.', 'warning');
            for (const f of files) {
                if (f.size > 10 * 1024 * 1024) return showToast('فایل «' + f.name + '» بیش از ۱۰ مگابایت است.', 'warning');
                fd.append('files', f);
            }
            if (document.getElementById('callNextOn').checked) {
                const date = document.getElementById('callNextDate').getAttribute('data-date') || '';
                if (!date) return showToast('تاریخ پیگیری بعدی را انتخاب کنید.', 'warning');
                fd.append('next_due_at', date + ' ' + (document.getElementById('callNextTime').value || '10:00'));
                fd.append('next_title', document.getElementById('callNextTitle').value.trim());
            }
            const btn = document.getElementById('callSave');
            btn.disabled = true;
            try {
                const res = await fetch('/crm/api/ucrm/customers/' + CID + '/calls', {
                    method: 'POST', headers: { 'Authorization': 'Bearer ' + UCRM.tok() }, body: fd,
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || ('خطای ' + res.status));
                callModal.hide();
                showToast(d.message || 'گزارش تماس ثبت شد.', 'success');
                await Promise.all([loadCalls(), loadFollowups()]);
            } catch (e) {
                showToast(e.message || 'خطا در ثبت گزارش', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        // دانلود پیوست: با توکن (لینکِ ساده توکن نمی‌فرستد)
        async function downloadCallFile(id, el) {
            try {
                const res = await fetch('/crm/api/ucrm/call-files/' + id, { headers: { 'Authorization': 'Bearer ' + UCRM.tok() } });
                if (!res.ok) {
                    const d = await res.json().catch(() => ({}));
                    throw new Error(d.message || 'دانلود ناموفق بود');
                }
                const url = URL.createObjectURL(await res.blob());
                const a = document.createElement('a');
                a.href = url;
                a.download = el.dataset.name || 'file';
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(url), 2000);
            } catch (e) {
                showToast(e.message || 'دانلود ناموفق بود', 'error');
            }
        }

        // تب «پیش‌فاکتورها» (طرح ۱۴۰۵/۰۸/۰۲؛ دادهٔ واقعی ۱۴۰۵/۰۸/۱۸): همان پیش‌فاکتورهای کل CRM
        // (شایگان + صادرشده در CRM — GET /ucrm/proformas، crm-service/ucrm_proformas.go) با فیلترِ ثابتِ
        // همین مشتری و همهٔ تاریخ‌ها. «امروز» = صادرشده امروز؛ «عقب‌افتاده» = وضعیت عقب افتاده (باز و
        // پیگیریِ گذشته). عملیات = همان فهرست اصلی (تکمیل / لغو / بازگشایی / تغییر تاریخ پیگیری).
        // جدول = AG Grid با همان ستون‌ها و تنظیماتِ صفحهٔ ucrm-proformas.php (۱۴۰۵/۰۸/۱۸).
        let PF_FILTER = 'all', PF_ROWS = null, PF_Q = '', pfFuModal = null, pfFuGuid = '', pfGridApi = null;
        const PF_STATUS = {
            approved: ['ok', 'bi-check-lg', 'تأیید شده'],
            stopped: ['stop', 'bi-dash-circle-fill', 'متوقف شده'],
            not_started: ['wait', 'bi-hourglass-split', 'شروع نشده'],
            overdue: ['late', 'bi-exclamation-circle-fill', 'عقب افتاده'],
        };

        async function loadProformas() {
            try {
                PF_ROWS = (await UCRM.get('/proformas?customer=' + CID)).items || [];
            } catch (e) {
                PF_ROWS = [];
                showToast(e.message || 'خطا در دریافت پیش‌فاکتورها', 'error');
            }
            if (document.querySelector('.uc-tabs .tb.active')?.dataset.tab === 'proformas') renderPfRows();
        }

        function pfVisibleRows() {
            const today = TimeSync.serverToday();
            const q = UCRM.toEn(PF_Q).trim().toLowerCase();
            return (PF_ROWS || []).filter(r => {
                if (PF_FILTER === 'today' && r.issue_date !== today) return false;
                if (PF_FILTER === 'overdue' && r.status !== 'overdue') return false;
                if (!q) return true;
                const hay = [r.number, String(r.amount), (PF_STATUS[r.status] || [])[2] || '', convertToJalali(r.issue_date || '')]
                    .join(' ').toLowerCase();
                return UCRM.toEn(hay).includes(q);
            });
        }

        function pfActionCell(p) {
            const r = p.data;
            if (!r || !r.can_act) return '';
            const g = esc(r.guid);
            const fu = `<button class="ag-action-btn" style="color:#8e57fe" title="تغییر تاریخ پیگیری" onclick="pfFollowup('${g}')"><i class="bi bi-calendar-event"></i></button>`;
            if (r.status === 'approved' || r.status === 'stopped') {
                return `<button class="ag-action-btn" style="color:#b45309" title="بازگشایی (شروع نشده)" onclick="pfSetStatus('${g}','not_started')"><i class="bi bi-arrow-counterclockwise"></i></button>` + fu;
            }
            return `<button class="ag-action-btn" style="color:#0f7a57" title="تکمیل" onclick="pfSetStatus('${g}','approved')"><i class="bi bi-check-circle-fill"></i></button>
                <button class="ag-action-btn del" title="لغو" onclick="pfSetStatus('${g}','stopped')"><i class="bi bi-x-circle-fill"></i></button>` + fu;
        }

        function pfColDefs() {
            const ro = (DATA && DATA.roles) || {};
            const seesAll = !!(ro.manager || ro.accounting);
            const jd = v => v ? TimeSync.formatJalali(v) : '—';
            return [
                { headerName: 'شمارهٔ پیش‌فاکتور', field: 'number', width: 150,
                  cellRenderer: p => p.data.source === 'crm'
                      ? `<a class="uc-name-link" href="ucrm-proforma-edit.php?id=${p.data.doc_id}" title="صادرشده در CRM — هنوز به حسابداری نرفته">${faDigits(p.value || '')}</a>`
                      : faDigits(p.value || '') },
                { headerName: 'نام مشتری', field: 'customer_name', flex: 1.4, minWidth: 130, cellRenderer: p => esc(p.value) },
                { headerName: 'کارشناس فروش', field: 'rep_name', flex: 1, minWidth: 110, hide: !seesAll },
                { headerName: 'مبلغ (تومان)', field: 'amount', width: 130, cellRenderer: p => UCRM.money(p.value) },
                { headerName: 'تاریخ صدور', field: 'issue_date', width: 105, cellRenderer: p => jd(p.value) },
                { headerName: 'پیگیری بعدی', field: 'next_followup', width: 115, cellRenderer: p => jd(p.value) },
                { headerName: 'وضعیت', field: 'status', width: 130, cellRenderer: p => {
                    const st = PF_STATUS[p.value];
                    return st ? `<span class="uc-st ${st[0]}"><i class="bi ${st[1]}"></i>${st[2]}</span>` : '';
                } },
                { headerName: 'عملیات', colId: 'actions', width: 80, sortable: false, filter: false, cellRenderer: AgGridFa.actionMenuCell(pfActionCell, { insideGrid: true }) },
            ];
        }

        function renderPfRows() {
            if (!pfGridApi) return;
            if (PF_ROWS === null) { pfGridApi.showLoadingOverlay(); return; }
            const rows = pfVisibleRows();
            pfGridApi.setGridOption('overlayNoRowsTemplate', PF_ROWS.length
                ? '<span class="text-muted">پیش‌فاکتوری با این فیلتر پیدا نشد.</span>'
                : '<span class="text-muted">هنوز پیش‌فاکتوری برای این مشتری صادر نشده است.</span>');
            pfGridApi.setGridOption('rowData', rows);
            if (!rows.length) pfGridApi.showNoRowsOverlay(); else pfGridApi.hideOverlay();
        }

        function proformaTab() {
            const f = (k, l) => `<button type="button" class="${PF_FILTER === k ? 'active' : ''}" data-pf="${k}">${l}</button>`;
            return `<div class="uc-card uc-hist-card">
                <div class="uc-hist-bar">
                    <div class="uc-hist-search"><i class="bi bi-search"></i>
                        <input type="text" class="form-control" id="pfQ" placeholder="جستجو در پیش‌فاکتورها…" autocomplete="off" value="${esc(PF_Q)}"></div>
                    <div class="uc-hist-seg uc-pf-seg">${f('all', 'همه')}${f('today', 'امروز')}${f('overdue', 'عقب‌افتاده')}</div>
                </div>
                <div id="pfGrid" class="ag-theme-alpine inv-grid" style="width:100%;height:520px;"></div>
            </div>`;
        }

        function bindProformas() {
            document.querySelectorAll('.uc-pf-seg button').forEach(b => b.addEventListener('click', () => {
                PF_FILTER = b.dataset.pf;
                document.querySelectorAll('.uc-pf-seg button').forEach(x => x.classList.toggle('active', x === b));
                renderPfRows();
            }));
            document.getElementById('pfQ').addEventListener('input', e => { PF_Q = e.target.value; renderPfRows(); });
            // تب هر بار از نو ساخته می‌شود → جدولِ قبلی آزاد و جدولِ تازه با تنظیماتِ استانداردِ پروژه
            if (pfGridApi) { try { pfGridApi.destroy(); } catch (e) {} }
            const el = document.getElementById('pfGrid');
            pfGridApi = agGrid.createGrid(el, {
                theme: AgGridFa.theme(), columnDefs: pfColDefs(), rowData: [], enableRtl: true,
                pagination: true, paginationPageSize: 20, paginationPageSizeSelector: [20, 50, 100],
                onPaginationChanged: () => AgGridFa.persianizePaging(),
                defaultColDef: { resizable: true, sortable: true },
            });
            // کلیک راست روی ردیف = همان منوی عملیات (قاعدهٔ جدول‌های حسابداری)
            AgGridFa.rowContextMenu(el, pfGridApi, null, { insideGrid: true });
            renderPfRows();
            if (PF_ROWS === null) loadProformas();
        }

        async function pfSetStatus(guid, status) {
            try {
                const res = await UCRM.send('POST', '/proformas/' + guid + '/status', { status });
                showToast(res.message || 'ثبت شد.', 'success');
                loadProformas();
            } catch (e) {
                showToast(e.message || 'خطا در ثبت وضعیت', 'error');
            }
        }

        function pfFollowup(guid) {
            const r = (PF_ROWS || []).find(x => x.guid === guid);
            if (!r) return;
            pfFuGuid = guid;
            document.getElementById('pfFuFor').textContent = faDigits(r.number);
            const el = document.getElementById('pfFuDate');
            const g = r.next_followup || TimeSync.serverToday();
            el.setAttribute('data-date', g);
            el.value = convertToJalali(g);
            pfFuModal.show();
        }

        async function pfSaveFollowup() {
            const date = document.getElementById('pfFuDate').getAttribute('data-date') || '';
            if (!date) return showToast('تاریخ پیگیری را انتخاب کنید.', 'warning');
            const btn = document.getElementById('pfFuSave');
            btn.disabled = true;
            try {
                const res = await UCRM.send('PUT', '/proformas/' + pfFuGuid + '/followup', { date });
                pfFuModal.hide();
                showToast(res.message || 'ثبت شد.', 'success');
                loadProformas();
            } catch (e) {
                showToast(e.message || 'خطا در ثبت تاریخ پیگیری', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function showTab(key) {
            document.querySelectorAll('.uc-tabs .tb').forEach(b => b.classList.toggle('active', b.dataset.tab === key));
            document.getElementById('ucTab').innerHTML = key === 'info' ? infoTab(DATA) :
                (key === 'plan' ? planTab() : (key === 'history' ? historyTab() : proformaTab()));
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
                        <button type="button" class="btn btn-light uc-btn-soft" id="btnSms" onclick="openSmsModal()" hidden><i class="bi bi-chat-left-text ms-1"></i>ارسال پیامک</button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-primary uc-more-btn" data-bs-toggle="dropdown" aria-expanded="false" title="بیشتر"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="ucrm-proforma-edit.php?customer=${c.id}"><i class="bi bi-file-earmark-plus ms-2"></i>پیش‌فاکتور جدید</a></li>
                                <li><a class="dropdown-item" href="ucrm-proforma-edit.php?type=sales_order&amp;customer=${c.id}"><i class="bi bi-cart-check ms-2"></i>سفارش فروش جدید</a></li>
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
            pfFuModal = new bootstrap.Modal(document.getElementById('pfFuModal'));
            callModal = new bootstrap.Modal(document.getElementById('callModal'));
            smsModal = new bootstrap.Modal(document.getElementById('smsModal'));
            document.getElementById('smsTpl').addEventListener('change', renderSmsVars);
            document.getElementById('smsSend').addEventListener('click', sendSms);
            document.getElementById('smsModal').addEventListener('shown.bs.modal', () => document.getElementById('smsPhone').focus());
            document.getElementById('callSave').addEventListener('click', saveCall);
            document.querySelectorAll('input[name="callResult"]').forEach(r => r.addEventListener('change', syncCallNoteReq));
            document.getElementById('callNextOn').addEventListener('change', e => { document.getElementById('callNextBox').hidden = !e.target.checked; });
            // اولین فیلدِ قابل‌ویرایش فوکوس بگیرد (قاعدهٔ مودال‌های سایت)
            document.getElementById('callModal').addEventListener('shown.bs.modal', () => document.getElementById('callNote').focus());
            document.getElementById('pfFuSave').addEventListener('click', pfSaveFollowup);
            document.getElementById('fuSave').addEventListener('click', saveFollowup);
            document.getElementById('ucFab').addEventListener('click', () => openFollowup());
            // اولین فیلد قابل‌ویرایش فوکوس بگیرد (قاعدهٔ مودال‌های سایت)
            document.getElementById('fuModal').addEventListener('shown.bs.modal', () => document.getElementById('fuTitle').focus());
            // دکمه‌های «به‌زودی» (پیامک): فقط پیام
            document.addEventListener('click', e => {
                const b = e.target.closest('.uc-soon');
                if (!b) return;
                e.preventDefault();
                showToast('«' + (b.dataset.soon || 'این بخش') + '» در مرحله‌های بعدی CRM فعال می‌شود.', 'info');
            });
            try {
                render(await UCRM.get('/customers/' + CID));
                await loadFollowups();
                // «ویرایش پیگیری» از کارت «کارهای کارشناس فروش» داشبورد: ?fu=شناسهٔ پیگیری
                const fuId = parseInt(new URLSearchParams(location.search).get('fu'), 10) || 0;
                if (fuId && FU.items.some(x => x.id === fuId)) {
                    showTab('plan');
                    if (FU.can_manage) openFollowup(fuId);
                    history.replaceState(null, '', 'ucrm-customer.php?id=' + CID);
                }
            } catch (e) {
                document.getElementById('ucBody').innerHTML =
                    `<div class="uc-card text-center text-muted py-5">${esc(e.message || 'خطا در بارگذاری')}<br><a class="btn btn-outline-secondary mt-3" href="ucrm-customers.php">بازگشت به فهرست</a></div>`;
            }
        });
    </script>
</body>

</html>
