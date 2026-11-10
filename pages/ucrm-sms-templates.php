<?php
require_once __DIR__ . '/../includes/page-bootstrap.php';

// CRM جدید — قالب‌های پیامک به مشتری (بخش ۹ طرح CRM، ۱۴۰۵/۰۸/۱۹). فقط مدیر.
// هر قالب = الگوی ملی‌پیامک + متنِ همان الگو با {0} {1}… + برچسبِ هر متغیر. قالبِ تازه بدونِ کد
// مستقیم در ملی‌پیامک ساخته و برای تأیید فرستاده می‌شود (۱۴۰۵/۰۸/۱۹)؛ با کد = الگویی که قبلا در پنل
// ساخته شده. ستون «وضعیت تأیید» + توضیحِ کارشناس از ملی‌پیامک؛ فقط «تأییدشده» قابل ارسال است.
// API: crm-service/ucrm_sms.go. ارسال از صفحهٔ مشتری (دکمهٔ «ارسال پیامک»).
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/ucrm_access.php';
if (!ucrmAllowed($db, (int) $user_id) || !ucrmRoles($db, (int) $user_id)['manager']) {
    header('Location: ../pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>قالب‌های پیامک - سامانه مدیریت فرآیندها</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/ucrm.css') ?>">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container uc-top-gap">
        <div class="uc-card uc-pf-head">
            <h1><span class="uc-tg-ico"><i class="bi bi-chat-left-text"></i></span>قالب‌های پیامک به مشتری</h1>
            <div class="spacer"></div>
            <button type="button" class="btn btn-outline-primary" id="btnRefresh"><i class="bi bi-arrow-repeat ms-1"></i>به‌روزرسانی وضعیت</button>
            <button type="button" class="btn btn-primary" id="btnAdd"><i class="bi bi-plus-lg ms-1"></i>قالب جدید</button>
        </div>
        <div class="uc-card">
            <p class="small text-muted mb-3">
                قالب تازه مستقیم برای <b>تأیید به ملی‌پیامک</b> فرستاده می‌شود؛ پس از تأیید کارشناسان ملی‌پیامک، برای ارسال به مشتری در دسترس است.
                جای هر متغیر در متن را با <span class="num-ltr">{0}</span>، <span class="num-ltr">{1}</span> و… (رقم انگلیسی) مشخص کنید و برای هر کدام یک برچسب بنویسید
                (برچسبی که کلمهٔ «نام» دارد، هنگام ارسال با نام مشتری پر می‌شود).
                آدرس <span class="num-ltr">itmalek.com</span>، اگر در متن نباشد، خودکار به خط آخرِ قالب اضافه می‌شود (الزامِ تأیید ملی‌پیامک).
            </p>
            <div id="tplGrid" class="ag-theme-alpine inv-grid grid-fill" style="width:100%;"></div>
        </div>
    </div>

    <div class="modal fade" id="tplModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tplModalTitle">قالب جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="fTitle">عنوان قالب <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mb-3" id="fTitle" maxlength="50" placeholder="مثلا: یادآوری پیش‌فاکتور">
                    <label class="form-label" for="fPreview">متن الگو <span class="text-danger">*</span></label>
                    <textarea class="form-control mb-1" id="fPreview" rows="3" placeholder="مثلا: {0} عزیز، پیش‌فاکتور {1} برای شما صادر شد."></textarea>
                    <div class="small text-warning mb-2" id="fLockNote" hidden></div>
                    <div class="small text-muted mb-3" id="fVarHint"></div>
                    <div id="fVars"></div>
                    <div id="fBodyWrap">
                        <label class="form-label small mt-2 mb-1" for="fBody">کد الگو <span class="text-muted">(اختیاری — فقط اگر این الگو را قبلا خودتان در پنل ملی‌پیامک ساخته‌اید)</span></label>
                        <input type="text" class="form-control" id="fBody" inputmode="numeric" dir="ltr" placeholder="خالی = ارسال برای تأیید به ملی‌پیامک">
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="fActive" checked>
                        <label class="form-check-label" for="fActive">فعال (در فهرست ارسال دیده شود)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="button" class="btn btn-primary" id="tplSave"><i class="bi bi-check-lg ms-1"></i>ذخیره</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= asset('../../assets/js/ucrm-common.js') ?>"></script>
    <script>
        let gridApi = null, ROWS = [], tplModal = null, editId = 0;
        // BodyStatus ملی‌پیامک
        const APPROVAL = {
            0: ['wait', 'bi-hourglass-split', 'در انتظار تأیید'],
            1: ['ok', 'bi-check-lg', 'تأیید شده'],
            2: ['stop', 'bi-x-circle-fill', 'رد شده'],
            3: ['wait', 'bi-hourglass-split', 'اصلاح‌شده، در انتظار'],
            4: ['late', 'bi-pencil', 'نیاز به اصلاح'],
            5: ['stop', 'bi-trash', 'حذف‌شده در پنل'],
            6: ['stop', 'bi-x-circle-fill', 'رد شده'],
        };

        function actionCell(p) {
            const r = p.data;
            return `<button class="ag-action-btn edit" title="ویرایش" onclick="openTpl(${r.id})"><i class="bi bi-pencil-square"></i></button>
                <button class="ag-action-btn del" title="حذف" onclick="delTpl(${r.id})"><i class="bi bi-trash"></i></button>`;
        }

        const colDefs = [
            { headerName: 'عنوان', field: 'title', flex: 1, minWidth: 150, cellRenderer: p => esc(p.value) },
            { headerName: 'کد الگو', field: 'body_id', width: 110, cellRenderer: p => faDigits(p.value) },
            { headerName: 'متن الگو', field: 'preview', flex: 2, minWidth: 220, cellRenderer: p => esc(p.value) },
            { headerName: 'متغیرها', field: 'var_labels', flex: 1, minWidth: 140, cellRenderer: p => esc((p.value || []).join('، ')) || '—' },
            { headerName: 'وضعیت تأیید', field: 'approval_status', width: 170,
              cellRenderer: p => {
                  const a = APPROVAL[p.value];
                  const badge = a ? `<span class="uc-st ${a[0]}"><i class="bi ${a[1]}"></i>${a[2]}</span>` : '<span class="text-muted small">نامشخص</span>';
                  return p.data.review_note ? `<span title="توضیح ملی‌پیامک: ${esc(p.data.review_note)}" style="cursor:help">${badge} <i class="bi bi-info-circle"></i></span>` : badge;
              } },
            { headerName: 'فعال', field: 'is_active', width: 80,
              cellRenderer: p => p.value ? '<i class="bi bi-toggle-on text-success fs-5"></i>' : '<i class="bi bi-toggle-off text-muted fs-5"></i>' },
            { headerName: 'عملیات', colId: 'actions', width: 80, sortable: false, filter: false, cellRenderer: AgGridFa.actionMenuCell(actionCell, { insideGrid: true }) },
        ];

        // وضعیت تأیید از ملی‌پیامک؛ اگر ملی‌پیامک در دسترس نبود، فهرستِ ذخیره‌شده نمایش داده می‌شود
        async function refreshStatus(showMsg) {
            const btn = document.getElementById('btnRefresh');
            btn.disabled = true;
            try {
                const res = await UCRM.send('POST', '/sms-templates/refresh');
                if (showMsg) showToast(res.message || 'به‌روز شد.', 'success');
            } catch (e) {
                showToast(e.message || 'خواندن وضعیت از ملی‌پیامک ناموفق بود', showMsg ? 'error' : 'warning');
            } finally {
                btn.disabled = false;
            }
            await load();
        }

        async function load() {
            gridApi.showLoadingOverlay();
            try {
                ROWS = (await UCRM.get('/sms-templates?all=1')).items || [];
                gridApi.setGridOption('rowData', ROWS);
                if (!ROWS.length) gridApi.showNoRowsOverlay(); else gridApi.hideOverlay();
            } catch (e) {
                showToast(e.message || 'خطا در دریافت قالب‌ها', 'error');
            }
        }

        // یک فیلد برچسب برای هر {n} در متن
        function syncVarFields(keep) {
            const prev = keep || [...document.querySelectorAll('#fVars input')].map(el => el.value);
            let max = -1;
            (document.getElementById('fPreview').value.match(/\{(\d+)\}/g) || []).forEach(m => { max = Math.max(max, +m.slice(1, -1)); });
            const n = max + 1;
            document.getElementById('fVarHint').textContent = n ? 'این متن ' + faDigits(n) + ' متغیر دارد؛ برای هر کدام یک برچسب بنویسید.' : 'این متن متغیری ندارد.';
            document.getElementById('fVars').innerHTML = Array.from({ length: n }, (_, i) =>
                `<div class="input-group input-group-sm mb-2"><span class="input-group-text num-ltr">{${i}}</span>
                 <input type="text" class="form-control" placeholder="برچسب — مثلا «نام مشتری»" value="${esc(prev[i] || '')}"></div>`).join('');
        }

        function openTpl(id) {
            editId = id || 0;
            const t = ROWS.find(x => x.id === editId);
            document.getElementById('tplModalTitle').textContent = t ? 'ویرایش قالب' : 'قالب جدید';
            document.getElementById('fTitle').value = t ? t.title : '';
            document.getElementById('fBody').value = '';
            document.getElementById('fBodyWrap').hidden = !!t; // کد فقط هنگام افزودنِ الگوی موجود در پنل
            document.getElementById('fPreview').value = t ? t.preview : '';
            // متنِ الگو فقط وقتی ملی‌پیامک «نیاز به اصلاح» گفته قابل تغییر است
            const locked = !!t && t.approval_status !== 4;
            document.getElementById('fPreview').readOnly = locked;
            const ln = document.getElementById('fLockNote');
            ln.hidden = !t;
            ln.textContent = !t ? '' : (locked
                ? 'متن الگو قابل تغییر نیست (فقط وقتی ملی‌پیامک «نیاز به اصلاح» بگوید). برای متن تازه، قالب جدید بسازید.'
                : 'ملی‌پیامک گفته این الگو نیاز به اصلاح دارد' + (t.review_note ? ': «' + t.review_note + '»' : '') + ' — با ذخیره، اصلاحیه برای تأیید فرستاده می‌شود.');
            document.getElementById('fActive').checked = t ? t.is_active : true;
            syncVarFields(t ? t.var_labels : []);
            tplModal.show();
        }

        async function saveTpl() {
            const body = {
                title: document.getElementById('fTitle').value.trim(),
                body_id: parseInt(UCRM.toEn(document.getElementById('fBody').value).replace(/\D/g, ''), 10) || 0,
                preview: document.getElementById('fPreview').value.trim(),
                var_labels: [...document.querySelectorAll('#fVars input')].map(el => el.value.trim()),
                is_active: document.getElementById('fActive').checked,
            };
            const btn = document.getElementById('tplSave');
            btn.disabled = true;
            try {
                const res = editId ? await UCRM.send('PUT', '/sms-templates/' + editId, body) : await UCRM.send('POST', '/sms-templates', body);
                tplModal.hide();
                showToast(res.message || 'ذخیره شد.', 'success');
                load();
            } catch (e) {
                showToast(e.message || 'خطا در ذخیره', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function delTpl(id) {
            const t = ROWS.find(x => x.id === id);
            uiConfirm('قالب «' + esc(t ? t.title : '') + '» حذف شود؟ پیامک‌های قبلی در تاریخچه می‌مانند.', async () => {
                try {
                    const res = await UCRM.send('DELETE', '/sms-templates/' + id);
                    showToast(res.message || 'حذف شد.', 'success');
                    load();
                } catch (e) {
                    showToast(e.message || 'خطا در حذف', 'error');
                }
            }, { danger: true, yesText: 'بله، حذف کن', noText: 'انصراف' });
        }

        // جدول تا پایین صفحه، بدون اسکرول
        function fitGrid() {
            const el = document.getElementById('tplGrid');
            el.style.height = '';
            const over = document.documentElement.scrollHeight - window.innerHeight;
            if (over > 0) el.style.height = Math.max(300, Math.floor(el.getBoundingClientRect().height - over)) + 'px';
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!UCRM.tok()) { location.href = '../index.php'; return; }
            gridApi = agGrid.createGrid(document.getElementById('tplGrid'), {
                theme: AgGridFa.theme(), columnDefs: colDefs, rowData: [], enableRtl: true,
                pagination: true, paginationPageSize: 20, paginationPageSizeSelector: [20, 50, 100],
                onPaginationChanged: () => AgGridFa.persianizePaging(),
                defaultColDef: { resizable: true, sortable: true },
                overlayNoRowsTemplate: '<span class="text-muted">هنوز قالبی تعریف نشده است.</span>',
            });
            AgGridFa.rowContextMenu(document.getElementById('tplGrid'), gridApi, null, { insideGrid: true });
            tplModal = new bootstrap.Modal(document.getElementById('tplModal'));
            document.getElementById('tplModal').addEventListener('shown.bs.modal', () => document.getElementById('fTitle').focus());
            document.getElementById('btnAdd').addEventListener('click', () => openTpl(0));
            document.getElementById('btnRefresh').addEventListener('click', () => refreshStatus(true));
            document.getElementById('tplSave').addEventListener('click', saveTpl);
            document.getElementById('fPreview').addEventListener('input', () => syncVarFields());
            fitGrid();
            window.addEventListener('resize', fitGrid);
            window.addEventListener('load', fitGrid);
            refreshStatus(false);
        });
    </script>
</body>

</html>
