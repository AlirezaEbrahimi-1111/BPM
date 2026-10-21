<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// جزئیات مشتری CRM جدید — فعلا اطلاعات پایه + تاریخچهٔ تأیید/رد؛ تب‌های دیگر (تارگت،
// پیگیری، یادداشت تماس، پیامک، پیش‌فاکتور) با طرح‌های بعدی اضافه می‌شوند.
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
</head>

<body>
    <?php include 'header.php'; ?>

    <!-- چیدمان و اندازهٔ کادر صفحه هم‌شکل pages/tasks.php (همهٔ صفحه‌های CRM) -->
    <div class="overview-container">
        <div class="filters-wrapper two-col">
            <div class="filters-title-col">
                <h1><i class="bi bi-person-vcard"></i> جزئیات مشتری</h1>
                <p><a href="ucrm-customers.php">مشتریان CRM</a> › <span id="crumbName">…</span></p>
            </div>
        </div>

        <div id="ucBody">
            <div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> در حال بارگذاری…</div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        const CID = <?= $__cid ?>;

        function kv(k, v) {
            return `<div><div class="k">${k}</div><div class="v">${v}</div></div>`;
        }

        function evClass(a) {
            if (/_rejected$/.test(a) || a === 'deleted') return 'rej';
            if (/_approved$/.test(a)) return 'ok';
            return '';
        }

        function render(d) {
            const c = d.customer;
            document.getElementById('crumbName').textContent = c.name;
            document.title = c.name + ' - مشتریان CRM';
            const none = '<span class="text-muted">ثبت نشده</span>';
            const phones = (c.phones || []).map(p => `<span class="num-ltr">${faDigits(p)}</span>`).join('، ') || none;
            const reject = c.status === 'rejected' && c.reject_reason ?
                `<div class="alert alert-warning py-2">دلیل رد ${c.rejected_stage === 'accounting' ? 'حسابداری' : 'مدیر'}: «${esc(c.reject_reason)}»</div>` : '';
            const events = (d.events || []).map(ev => `
                <li class="${evClass(ev.action)}">
                    <div class="n">${esc(ev.note)}</div>
                    <div class="t">${TimeSync.formatJalaliTime ? TimeSync.formatJalaliTime(ev.created_at) : faDigits(ev.created_at)}</div>
                </li>`).join('');

            document.getElementById('ucBody').innerHTML = `
                <div class="uc-detail-head">
                    <h2>${esc(c.name)}</h2>
                    ${UCRM.typeBadge(c.type)}
                    ${UCRM.statusBadge(c)}
                    <div class="spacer"></div>
                    <a class="btn btn-outline-secondary" href="ucrm-customers.php"><i class="bi bi-arrow-right ms-1"></i> بازگشت به فهرست</a>
                </div>
                ${reject}
                <div class="uc-card">
                    <h3><i class="bi bi-info-circle ms-1"></i> اطلاعات پایه</h3>
                    <div class="uc-kv">
                        ${kv('نام', esc(c.name))}
                        ${kv(c.type === 'individual' ? 'کد ملی' : 'شناسهٔ ملی', c.national_id ? faDigits(c.national_id) : none)}
                        ${kv('شماره تماس', phones)}
                        ${kv('کارشناس فروش', c.rep_name ? esc(c.rep_name) : none)}
                        ${kv('استان / شهر', [c.province, c.city].filter(Boolean).map(esc).join(' / ') || none)}
                        ${kv('آدرس', c.address ? esc(c.address) : none)}
                        ${kv('تارگت ماهانه', c.target_amount ? UCRM.money(c.target_amount) + ' تومان' :
                            (c.proposed_target ? 'پیشنهاد کارشناس: ' + UCRM.money(c.proposed_target) + ' تومان' : none))}
                        ${kv('وضعیت تارگت ماه', UCRM.targetBadge(c, d.settings))}
                        ${kv('آخرین پیگیری', UCRM.lastFollowup(c.last_followup_at))}
                        ${kv('توضیحات', c.note ? esc(c.note) : none)}
                    </div>
                </div>
                <div class="uc-card">
                    <h3><i class="bi bi-clock-history ms-1"></i> تاریخچه</h3>
                    ${events ? `<ul class="uc-timeline">${events}</ul>` : '<div class="text-muted small">سابقه‌ای ثبت نشده است.</div>'}
                </div>`;
        }

        document.addEventListener('DOMContentLoaded', async () => {
            if (!UCRM.tok()) {
                location.href = '../index.php';
                return;
            }
            try {
                render(await UCRM.get('/customers/' + CID));
            } catch (e) {
                document.getElementById('ucBody').innerHTML =
                    `<div class="uc-card text-center text-muted py-5">${esc(e.message || 'خطا در بارگذاری')}<br><a class="btn btn-outline-secondary mt-3" href="ucrm-customers.php">بازگشت به فهرست</a></div>`;
            }
        });
    </script>
</body>

</html>
