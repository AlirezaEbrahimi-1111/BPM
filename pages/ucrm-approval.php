<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// صفحهٔ تسک‌های CRM: «تأیید انتساب مشتری» (مدیر)، «تأیید حسابداری مشتری»، «اصلاح مشتری» (کارشناس).
// task-detail.php برای این تسک‌ها خودکار به این‌جا می‌آید. تصمیم از همین صفحه به
// crm-service می‌رود و تسک‌های همان مرحله (برای همهٔ نسخه‌ها) خودکار بسته می‌شوند.
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_tasks.php';

$__taskId = (int) ($_GET['task'] ?? 0);
$__link = $__taskId ? ucrmTaskLink($db, $__taskId) : null;
if (!$__link) {
    header('Location: task-detail.php?id=' . $__taskId);
    exit;
}
$__st = $db->prepare("
    SELECT t.id, t.title, t.status, t.assignee_id, t.creator_id, t.created_at,
           TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS creator_name,
           (SELECT h.notes FROM task_history h WHERE h.task_id = t.id ORDER BY h.id DESC LIMIT 1) AS last_note
    FROM tasks t LEFT JOIN users c ON c.id = t.creator_id
    WHERE t.id = ? AND t.is_deleted = 0
");
$__st->execute([$__taskId]);
$__task = $__st->fetch(PDO::FETCH_ASSOC);
// فقط مسئول همین تسک یا کسی که به CRM دسترسی دارد
if (!$__task || ((int) $__task['assignee_id'] !== (int) $user_id && !ucrmAllowed($db, (int) $user_id))) {
    header('Location: my-tasks.php');
    exit;
}
$__page = [
    'task_id'      => (int) $__task['id'],
    'customer_id'  => (int) $__link['customer_id'],
    'stage'        => $__link['stage'],
    'closed'       => $__link['closed_at'] !== null,
    'mine'         => (int) $__task['assignee_id'] === (int) $user_id,
    'creator_name' => $__task['creator_name'],
    'created_at'   => $__task['created_at'],
    'last_note'    => (string) $__task['last_note'],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درخواست تأیید مشتری - سامانه مدیریت فرآیندها</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/ucrm.css') ?>">
    <script src="<?= asset('../../assets/js/money-shortcut.js') ?>"></script>
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- چیدمان و اندازهٔ کادر صفحه هم‌شکل pages/tasks.php (همهٔ صفحه‌های CRM) -->
    <div class="overview-container">
        <div class="uc-crumb"><i class="bi bi-people ms-1"></i><a href="my-tasks.php">کارهای من</a> ‹ <span id="crumbTitle">درخواست تأیید مشتری</span></div>

        <div id="apBody" class="uc-card">
            <div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> در حال بارگذاری…</div>
        </div>
    </div>

    <!-- رد با دلیل -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header-custom modal-header border-0">
                    <h5 class="modal-title text-white"><i class="bi bi-x-circle ms-2"></i>رد درخواست</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">دلیل رد <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rjReason" rows="4" placeholder="دلیل را بنویسید تا کارشناس فروش اصلاح کند"></textarea>
                    <div id="rjAlert" class="mt-2"></div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-danger" id="rjDo">رد و برگشت به کارشناس</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const PAGE = <?= json_encode($__page, JSON_UNESCAPED_UNICODE) ?>;
        const STAGE = {
            manager: { title: 'درخواست تأیید و انتساب مشتری', wait: 'در انتظار اقدام مدیر', status: 'pending_manager', icon: 'bi-file-earmark-text' },
            accounting: { title: 'درخواست تأیید حسابداری مشتری', wait: 'در انتظار اقدام حسابداری', status: 'pending_accounting', icon: 'bi-calculator' },
            rep_fix: { title: 'اصلاح مشتری', wait: 'در انتظار اصلاح کارشناس', status: 'rejected', icon: 'bi-pencil-square' },
        };
        let cust = null, reps = [], rejectModal;

        function row(k, v) {
            return `<div class="uc-ap-row"><div class="k">${k}</div><div class="v">${v}</div></div>`;
        }

        function render(d) {
            cust = d.customer;
            const sg = STAGE[PAGE.stage] || STAGE.manager;
            // تسک هنوز باز است و مشتری هم واقعا در همین مرحله است → اقدام ممکن
            const open = !PAGE.closed && cust.status === sg.status;
            const none = '<span class="text-muted">ثبت نشده</span>';
            document.getElementById('crumbTitle').textContent = sg.title;
            document.title = sg.title + ': ' + cust.name;

            const badge = open ?
                `<span class="uc-badge st-pending_manager">${sg.wait}</span>` :
                `<span class="uc-badge st-active">انجام شده</span>`;
            const canDecide = open && PAGE.stage !== 'rep_fix' && cust.can_approve;
            const isRep = open && PAGE.stage === 'rep_fix' && cust.can_resubmit;

            let repCell = cust.rep_name ? `<b>${esc(cust.rep_name)}</b>` : none;
            if (canDecide && PAGE.stage === 'manager') {
                const list = reps.slice();
                if (cust.rep_id && !list.some(r => r.id === cust.rep_id)) list.unshift({ id: cust.rep_id, name: cust.rep_name });
                repCell = `<select class="form-select form-select-sm" id="apRep">${list.map(r =>
                    `<option value="${r.id}" ${r.id === cust.rep_id ? 'selected' : ''}>${esc(r.name)}</option>`).join('')}</select>
                    <div class="form-text">در صورت نیاز کارشناس را عوض کنید.</div>`;
            }

            // تارگت ماهانه: مرحلهٔ مدیر → قابل ویرایش (پیش‌فرض پیشنهاد کارشناس)؛ بقیه → نمایش
            const tVal = cust.target_amount || cust.proposed_target || 0;
            let targetCell = tVal ? `<b>${UCRM.money(tVal)} تومان</b>` : none;
            if (canDecide && PAGE.stage === 'manager') {
                targetCell = `<div class="input-group input-group-sm uc-ap-target"><input type="text" class="form-control" id="apTarget" placeholder="بدون تارگت">
                    <span class="input-group-text">تومان</span></div>
                    <div class="form-text">${cust.proposed_target ? 'پیشنهاد کارشناس: ' + UCRM.money(cust.proposed_target) + ' تومان' : 'کارشناس تارگتی پیشنهاد نداده است.'}</div>`;
            }
            const targetLabel = cust.target_amount ? 'تارگت ماهانه:' : 'تارگت ماهانهٔ پیشنهادی:';
            const reject = cust.status === 'rejected' && cust.reject_reason ?
                `<div class="alert alert-warning py-2 mb-3">دلیل رد ${cust.rejected_stage === 'accounting' ? 'حسابداری' : 'مدیر'}: «${esc(cust.reject_reason)}»</div>` : '';
            const doneNote = !open ?
                `<div class="alert alert-secondary py-2 mb-0">${esc(PAGE.last_note || 'این درخواست قبلا انجام شده است.')} <a href="ucrm-customer.php?id=${cust.id}">مشاهدهٔ مشتری</a></div>` : '';

            let actions = '';
            if (canDecide) {
                actions = `<div class="uc-ap-actions">
                    <button class="btn btn-success uc-ap-btn" id="btnOk"><i class="bi bi-check-lg ms-1"></i> تأیید</button>
                    <button class="btn btn-outline-danger uc-ap-btn" id="btnNo"><i class="bi bi-x-lg ms-1"></i> رد</button>
                </div>`;
            } else if (isRep) {
                actions = `<div class="uc-ap-actions">
                    <a class="btn btn-primary uc-ap-btn" href="ucrm-customers.php?edit=${cust.id}&resubmit=1"><i class="bi bi-arrow-repeat ms-1"></i> اصلاح و ارسال دوباره</a>
                    <button class="btn btn-outline-danger uc-ap-btn" id="btnWithdraw"><i class="bi bi-trash ms-1"></i> پس گرفتن درخواست</button>
                </div>`;
            } else if (open) {
                actions = `<div class="text-muted small">این مرحله منتظر اقدام ${PAGE.stage === 'accounting' ? 'حسابداری' : (PAGE.stage === 'manager' ? 'مدیر' : 'کارشناس فروش')} است.</div>`;
            }

            document.getElementById('apBody').innerHTML = `
                <div class="uc-ap-title"><span class="uc-ap-ico"><i class="bi ${sg.icon}"></i></span>${sg.title}: ${esc(cust.name)}</div>
                <div class="uc-ap-strip">
                    <div class="it"><span class="ic"><i class="bi bi-person"></i></span><div><div class="k">درخواست‌دهنده:</div><div class="v">${esc(PAGE.creator_name || '')}</div></div></div>
                    <div class="it"><span class="ic"><i class="bi bi-calendar3"></i></span><div><div class="k">تاریخ درخواست:</div><div class="v">${TimeSync.formatJalali(PAGE.created_at)} ساعت ${TimeSync.formatTimeOnly(PAGE.created_at)}</div></div></div>
                    <div class="it"><span class="ic"><i class="bi bi-clock"></i></span><div><div class="k">وضعیت:</div><div class="v">${badge}</div></div></div>
                </div>
                ${reject}
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="uc-ap-box">
                            <div class="hd"><i class="bi bi-person ms-1"></i> مشخصات مشتری</div>
                            ${row('نوع مشتری:', UCRM.typeBadge(cust.type))}
                            ${row('نام:', esc(cust.name))}
                            ${row(cust.type === 'individual' ? 'کد ملی:' : 'شناسهٔ ملی:', cust.national_id ? faDigits(cust.national_id) : none)}
                            ${row('شماره تماس:', (cust.phones || []).map(p => `<span class="num-ltr">${faDigits(p)}</span>`).join('، ') || none)}
                            ${row('استان / شهر:', [cust.province, cust.city].filter(Boolean).map(esc).join(' / ') || none)}
                            ${row('آدرس:', cust.address ? esc(cust.address) : none)}
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="uc-ap-box">
                            <div class="hd"><i class="bi bi-bullseye ms-1"></i> پیشنهاد انتساب</div>
                            ${row('کارشناس پیشنهادی:', repCell)}
                            ${row(targetLabel, targetCell)}
                            ${row('توضیحات کارشناس:', cust.note ? esc(cust.note) : none)}
                        </div>
                    </div>
                </div>
                <div class="uc-ap-foot">${actions || doneNote}</div>`;

            if (canDecide) {
                const ti = document.getElementById('apTarget');
                if (ti) { UCRM.bindMoney(ti); UCRM.setMoney(ti, cust.proposed_target || 0); }
                document.getElementById('btnOk').addEventListener('click', approve);
                document.getElementById('btnNo').addEventListener('click', () => {
                    document.getElementById('rjReason').value = '';
                    document.getElementById('rjAlert').innerHTML = '';
                    rejectModal.show();
                });
            }
            if (isRep) document.getElementById('btnWithdraw').addEventListener('click', withdraw);
        }

        function done(msg) {
            showToast(msg, 'success');
            setTimeout(() => location.href = 'my-tasks.php', 1300);
        }

        function approve() {
            const sel = document.getElementById('apRep');
            const rep = sel ? +sel.value : 0;
            const ti = document.getElementById('apTarget');
            const target = ti ? UCRM.moneyVal(ti) : null;
            const body = {};
            if (rep) body.rep_id = rep;
            if (ti) body.target = target;
            const txt = PAGE.stage === 'manager' ?
                'مشتری «' + esc(cust.name) + '» با کارشناس «' + esc(sel ? sel.selectedOptions[0].text : cust.rep_name) + '» و ' +
                (target ? 'تارگت ماهانهٔ ' + UCRM.money(target) + ' تومان' : 'بدون تارگت ماهانه') + ' تأیید و برای حسابداری فرستاده شود؟' :
                'مشتری «' + esc(cust.name) + '» تأیید و فعال شود؟';
            uiConfirm(txt, async () => {
                try {
                    const d = await UCRM.send('POST', '/customers/' + cust.id + '/approve', body);
                    done(d.message || 'تأیید شد');
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            }, { yesText: 'تأیید', noText: 'انصراف' });
        }

        async function doReject() {
            const reason = document.getElementById('rjReason').value.trim();
            if (reason.length < 3) {
                document.getElementById('rjAlert').innerHTML = '<div class="alert alert-warning py-2 mb-0">دلیل رد را بنویسید.</div>';
                document.getElementById('rjReason').focus();
                return;
            }
            const btn = document.getElementById('rjDo');
            btn.disabled = true;
            try {
                const d = await UCRM.send('POST', '/customers/' + cust.id + '/reject', { reason });
                rejectModal.hide();
                done(d.message || 'رد شد');
            } catch (e) {
                document.getElementById('rjAlert').innerHTML = '<div class="alert alert-danger py-2 mb-0">' + esc(e.message || 'خطا') + '</div>';
            } finally {
                btn.disabled = false;
            }
        }

        function withdraw() {
            uiConfirm('درخواست ثبت مشتری «' + esc(cust.name) + '» پس گرفته و حذف شود؟', async () => {
                try {
                    const d = await UCRM.send('DELETE', '/customers/' + cust.id, {});
                    done(d.message || 'حذف شد');
                } catch (e) {
                    showToast(e.message || 'خطا', 'error');
                }
            }, { danger: true, yesText: 'حذف', noText: 'انصراف' });
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) {
                location.href = '../index.php';
                return;
            }
            rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
            document.getElementById('rejectModal').addEventListener('shown.bs.modal', () => document.getElementById('rjReason').focus());
            document.getElementById('rjDo').addEventListener('click', doReject);
            try {
                const [d, rp] = await Promise.all([
                    UCRM.get('/customers/' + PAGE.customer_id),
                    PAGE.stage === 'manager' && !PAGE.closed ? UCRM.get('/reps') : Promise.resolve({ items: [] }),
                ]);
                reps = rp.items || [];
                render(d);
            } catch (e) {
                document.getElementById('apBody').innerHTML =
                    `<div class="text-center text-muted py-5">${esc(e.message || 'خطا در بارگذاری')}<br>
                     <div class="small mt-2">${esc(PAGE.last_note || '')}</div>
                     <a class="btn btn-outline-secondary mt-3" href="my-tasks.php">بازگشت به کارهای من</a></div>`;
            }
        });
    </script>
</body>

</html>
