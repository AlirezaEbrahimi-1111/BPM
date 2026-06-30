<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کارهای روتین</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/sections-helper.js') ?>"></script>
    <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --surface-3: #f1f5f9;
            --border: #e2e8f0;
            --border-hover: #cbd5e1;
            --text-1: #0f172a;
            --text-2: #475569;
            --text-3: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow: 0 4px 16px rgba(0, 0, 0, 0.07), 0 2px 6px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 12px 40px rgba(0, 0, 0, 0.1), 0 4px 16px rgba(0, 0, 0, 0.06);
        }

        * {
            font-family: 'Vazirmatn', Tahoma, sans-serif !important;
            box-sizing: border-box;
        }

        body {
            background: var(--surface-2);
            color: var(--text-1);
            min-height: 100vh;
        }

        /* ─── Page Layout ─── */
        .page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .page-header-left h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-1);
            margin: 0 0 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header-left h1 i {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        .page-header-left p {
            color: var(--text-3);
            font-size: 0.875rem;
            margin: 0 48px;
        }

        /* ─── Btn Primary ─── */
        .btn-create {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.35);
        }

        .btn-create:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.45);
        }

        /* ─── Template Grid ─── */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.25rem;
        }

        .template-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.22s ease;
            position: relative;
            overflow: hidden;
        }

        .template-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary), var(--primary-light));
            opacity: 0;
            transition: opacity 0.2s;
        }

        .template-card:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .template-card:hover::before {
            opacity: 1;
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-1);
            margin: 0;
            flex: 1;
            padding-left: 12px;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #f1f5f9;
            color: var(--text-3);
        }

        .card-desc {
            font-size: 0.825rem;
            color: var(--text-2);
            margin: 0 0 14px;
            line-height: 1.6;
            min-height: 2.4em;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .card-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: var(--text-3);
        }

        .card-meta i {
            font-size: 0.9rem;
        }

        .card-actions {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            background: var(--surface);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.18s;
            color: var(--text-2);
        }

        .btn-icon:hover {
            background: var(--surface-3);
            border-color: var(--border-hover);
        }

        .btn-icon.edit:hover {
            color: var(--primary);
            border-color: var(--primary-light);
            background: #eef2ff;
        }

        .btn-icon.del:hover {
            color: var(--danger);
            border-color: #fca5a5;
            background: #fef2f2;
        }

        /* ─── Empty State ─── */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 5rem 2rem;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px dashed var(--border);
        }

        .empty-state .empty-icon {
            width: 72px;
            height: 72px;
            background: var(--surface-3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 2rem;
            color: var(--text-3);
        }

        .empty-state h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 0.875rem;
            color: var(--text-3);
        }

        /* ─── Modal ─── */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            max-width: 850px !important;
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-1);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-title i {
            color: var(--primary);
        }

        .modal-body {
            padding: 1.5rem;
            background: var(--surface);
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--surface-2);
            border-radius: 0 0 16px 16px;
            gap: 8px;
        }

        /* حالتِ مشاهده: عناصرِ ویرایش/جابه‌جایی پنهان شوند */
        #templateModal.view-mode .btn-remove-step,
        #templateModal.view-mode .drag-hint,
        #templateModal.view-mode .btn-add-step,
        #templateModal.view-mode .exec-quick {
            display: none !important;
        }

        #templateModal.view-mode .step-item {
            cursor: default;
        }

        #templateModal.view-mode .step-mode-toggle {
            pointer-events: none;
            opacity: .85;
        }

        /* ─── Form Elements ─── */
        .form-label {
            font-size: 0.825rem;
            font-weight: 600;
            color: var(--text-2);
            margin-bottom: 6px;
        }

        .form-control,
        .form-select {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 9px 12px;
            font-size: 0.875rem;
            color: var(--text-1);
            background: var(--surface);
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
            outline: none;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        /* ─── Section Divider ─── */
        .section-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 1.25rem 0 1rem;
        }

        .section-label span {
            font-size: 0.825rem;
            font-weight: 600;
            color: var(--text-2);
            white-space: nowrap;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ─── Steps ─── */
        .steps-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .steps-header h6 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-2);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-add-step {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #eef2ff;
            color: var(--primary);
            border: 1px solid #c7d2fe;
            border-radius: var(--radius-sm);
            padding: 6px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.18s;
        }

        .btn-add-step:hover {
            background: #e0e7ff;
        }

        .step-item {
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px;
            margin-bottom: 10px;
            position: relative;
            transition: border-color 0.18s, box-shadow 0.18s;
            cursor: grab;
        }

        .step-item:active {
            cursor: grabbing;
        }

        .step-item.dragging {
            opacity: 0.45;
            border-style: dashed;
        }

        .step-item:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow-sm);
        }

        .step-item-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .step-num {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .drag-hint {
            color: var(--text-3);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .template-card.locked {
            opacity: .9;
            cursor: not-allowed;
        }

        .badge-status.badge-locked {
            background: #FEF3C7;
            color: #B54708;
        }

        .btn-icon:disabled {
            opacity: .45;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn-remove-step {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #fca5a5;
            background: #fef2f2;
            color: var(--danger);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.18s;
        }

        .btn-remove-step:hover {
            background: #fee2e2;
        }

        .steps-hint {
            font-size: 0.775rem;
            color: var(--text-3);
            background: var(--surface-3);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }

        /* ─── Btn Save / Cancel ─── */
        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 10px 22px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
        }

        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: var(--surface);
            color: var(--text-2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 20px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.18s;
        }

        .btn-cancel:hover {
            background: var(--surface-3);
        }

        /* ─── Skeleton loader ─── */
        .skeleton {
            background: linear-gradient(90deg, var(--surface-3) 25%, var(--border) 50%, var(--surface-3) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 6px;
        }

        .template-card.inactive {
            opacity: .65;
        }

        .btn-icon.view {
            color: #475467;
        }

        .btn-icon.redefine {
            color: #175CD3;
        }

        .btn-icon.toggle-active {
            color: #B54708;
        }

        .btn-icon:hover {
            background: #F2F4F7;
        }

        @keyframes shimmer {
            to {
                background-position: -200% 0;
            }
        }

        .skel-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
        }

        .step-mode-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .step-mode-toggle .sm-label {
            font-size: .8rem;
            color: var(--text-2);
        }

        .sm-btn {
            border: 1px solid #d7dce3;
            background: #fff;
            color: #667085;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: .8rem;
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
        }

        .sm-btn:hover {
            border-color: #744CA4;
            color: #744CA4;
        }

        .sm-btn.active.sm-parallel {
            background: #ECFDF3;
            border-color: #12B76A;
            color: #027A48;
            font-weight: 700;
        }

        .sm-btn.active.sm-cascade {
            background: #EFF8FF;
            border-color: #2E90FA;
            color: #175CD3;
            font-weight: 700;
        }

        .exec-quick {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 6px 0 12px;
        }

        .exec-quick .eq-label {
            font-size: .82rem;
            color: var(--text-2);
        }

        .exec-preview {
            background: #F9FAFB;
            border: 1px solid #EAECF0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-top: 10px;
            font-size: .83rem;
            color: #344054;
        }

        .exec-preview .ep-line {
            margin: 2px 0;
        }

        .exec-preview b {
            color: #101828;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="page-wrapper">
        <!-- Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1><i class="bi bi-diagram-3"></i> کارهای روتین</h1>
                <p>مشاهده و مدیریت فرآیندهای تکرارشونده</p>
            </div>
            <button class="btn-create" onclick="showAddTemplateModal()">
                <i class="bi bi-plus-lg"></i>
                روتین جدید
            </button>
        </div>

        <!-- Grid -->
        <div class="templates-grid" id="templatesList">
            <!-- Skeletons while loading -->
            <div class="skel-card">
                <div class="skeleton" style="height:20px;width:60%;margin-bottom:10px;"></div>
                <div class="skeleton" style="height:14px;width:90%;margin-bottom:6px;"></div>
                <div class="skeleton" style="height:14px;width:70%;"></div>
            </div>
            <div class="skel-card">
                <div class="skeleton" style="height:20px;width:50%;margin-bottom:10px;"></div>
                <div class="skeleton" style="height:14px;width:85%;margin-bottom:6px;"></div>
                <div class="skeleton" style="height:14px;width:60%;"></div>
            </div>
            <div class="skel-card">
                <div class="skeleton" style="height:20px;width:65%;margin-bottom:10px;"></div>
                <div class="skeleton" style="height:14px;width:80%;margin-bottom:6px;"></div>
                <div class="skeleton" style="height:14px;width:55%;"></div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-plus-circle"></i>
                        افزودن کار روتین جدید
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <form id="templateForm" onsubmit="return false;">
                        <input type="hidden" id="templateId">

                        <!-- نام و توضیحات در یک ردیف -->
                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">نام کار روتین <span style="color:var(--danger)">*</span></label>
                                <input type="text" class="form-control" id="templateName"
                                    placeholder="مثال: فروش به مصرف‌کننده" required>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" id="templateDescription" rows="2"
                                    placeholder="توضیح مختصری درباره این فرآیند..."></textarea>
                            </div>
                        </div>

                        <!-- فعال -->
                        <div class="mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="templateActive" checked>
                                <label class="form-check-label" for="templateActive" style="font-size:0.875rem;color:var(--text-2);">فعال</label>
                            </div>
                        </div>

                        <!-- مراحل -->
                        <div class="section-label"><span><i class="bi bi-list-ol me-1"></i>مراحل فرآیند</span></div>

                        <div class="steps-header">
                            <h6><i class="bi bi-grip-vertical"></i> ترتیب مراحل</h6>
                            <button type="button" class="btn-add-step" onclick="addStep()">
                                <i class="bi bi-plus"></i> افزودن مرحله
                            </button>
                        </div>

                        <div class="exec-quick">
                            <span class="eq-label"><i class="bi bi-lightning-charge"></i> تنظیم سریعِ همهٔ مراحل:</span>
                            <button type="button" class="sm-btn" onclick="setAllModes('cascade')">⛓ همه آبشاری</button>
                            <button type="button" class="sm-btn" onclick="setAllModes('parallel')">⚡ همه موازی</button>
                        </div>

                        <div id="stepsList"></div>

                        <div id="execPreview" class="exec-preview"></div>

                        <div class="steps-hint">
                            <i class="bi bi-info-circle"></i>
                            مراحل از بالا به پایین اجرا می‌شوند. برای تغییر ترتیب، بکشید و رها کنید.
                        </div>
                    </form>
                </div>

                <div id="deactivateOldWrap" style="display:none; padding:0 1rem 0.5rem;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:0.9rem; cursor:pointer;">
                        <input type="checkbox" id="deactivateOldChk">
                        قالبِ قبلی غیرفعال شود (دیگر در ساخت کار جدید نمایش داده نشود)
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">
                        <i class="bi bi-x"></i> انصراف
                    </button>
                    <button type="button" class="btn-save" onclick="saveTemplate()">
                        <i class="bi bi-check2"></i> ذخیره
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script>
        let currentTemplateId = null;
        let stepCounter = 0;

        const UNITS = [{
                value: 'sales',
                label: 'فروش'
            },
            {
                value: 'purchase',
                label: 'خرید'
            },
            {
                value: 'warehouse',
                label: 'انبار'
            },
            {
                value: 'technical',
                label: 'فنی'
            },
            {
                value: 'accounting',
                label: 'حسابداری'
            },
            {
                value: 'colleague',
                label: 'همکار'
            },
            {
                value: 'offices',
                label: 'ادارات'
            },
            {
                value: 'virtual',
                label: 'فضای مجازی'
            },
            {
                value: 'public',
                label: 'عمومی'
            },
            {
                value: 'management',
                label: 'مدیریت'
            },
        ];

        // ─── بارگذاری لیست ───────────────────────────────
        async function loadTemplates() {
            try {
                const response = await fetch('../api/workflows/list-all-templates.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (data.success) renderTemplates(data.templates);
                else showToast(data.message, 'danger');
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری لیست', 'danger');
            }
        }

        // ─── رندر کارت‌ها ────────────────────────────────
        function renderTemplates(templates) {
            const container = document.getElementById('templatesList');

            if (!templates || templates.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-diagram-3"></i></div>
                        <h5>هنوز روتینی تعریف نشده</h5>
                        <p>اولین فرآیند روتین خود را اضافه کنید</p>
                    </div>`;
                return;
            }

            container.innerHTML = templates.map(t => {
                const isActive = (t.is_active == 1);
                return `
                <div class="template-card ${isActive ? '' : 'inactive'}"
                     onclick="viewTemplate(${t.id})" style="cursor:pointer;">
                    <div class="card-top">
                        <h3 class="card-title">${escHtml(t.name)}</h3>
                        ${isActive
                            ? '<span class="badge-status badge-active"><i class="bi bi-circle-fill" style="font-size:6px;"></i> فعال</span>'
                            : '<span class="badge-status badge-inactive"><i class="bi bi-circle" style="font-size:6px;"></i> غیرفعال</span>'}
                    </div>
                    <p class="card-desc">${escHtml(t.description || 'بدون توضیحات')}</p>
                    <div class="card-footer">
                        <div class="card-meta">
                            <i class="bi bi-list-check"></i>
                            <span>${t.steps_count || 0} مرحله</span>
                        </div>
                        <div class="card-actions">
                            <button class="btn-icon view" title="مشاهده"
                                onclick="event.stopPropagation(); viewTemplate(${t.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn-icon redefine" title="بازتعریف (ساخت نسخهٔ جدید)"
                                onclick="event.stopPropagation(); redefineTemplate(${t.id})">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <button class="btn-icon toggle-active" title="${isActive ? 'غیرفعال‌کردن' : 'فعال‌کردن'}"
                                onclick="event.stopPropagation(); toggleTemplateActive(${t.id}, ${isActive ? 0 : 1})">
                                <i class="bi bi-${isActive ? 'pause-circle' : 'play-circle'}"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }
        // ─── فعال/غیرفعال‌کردن قالب ───────────────────────
        function toggleTemplateActive(templateId, newActive) {
            const makeActive = (newActive == 1);
            const msg = makeActive ?
                'این قالب دوباره فعال شود و در ساخت کار جدید نمایش داده شود؟' :
                'این قالب غیرفعال شود؟ دیگر در ساخت کار جدید نمایش داده نمی‌شود (کارهای در حال اجرا ادامه می‌یابند).';
            uiConfirm(msg, async function() {
                try {
                    const res = await fetch('../api/workflows/toggle-template-active.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            template_id: templateId,
                            is_active: makeActive ? 1 : 0
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        loadTemplates();
                    } else {
                        showToast(data.message || 'تغییر وضعیت انجام نشد', 'danger');
                    }
                } catch (e) {
                    console.error(e);
                    showToast('خطا در ارتباط با سرور', 'danger');
                }
            });
        }
        // ─── مودال افزودن ────────────────────────────────
        function showAddTemplateModal() {
            currentTemplateId = null;
            redefineSourceId = null;
            setTemplateModalReadonly(false);
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle"></i> افزودن کار روتین جدید';
            document.getElementById('templateForm').reset();
            document.getElementById('templateId').value = '';
            document.getElementById('templateActive').checked = true;
            const wrap = document.getElementById('deactivateOldWrap');
            if (wrap) wrap.style.display = 'none'; // تیک فقط در بازتعریف
            document.getElementById('stepsList').innerHTML = '';
            stepCounter = 0;
            addStep();
            new bootstrap.Modal(document.getElementById('templateModal')).show();
        }

        function notifyLocked() {
            showToast('این قالب یک کار روتینِ در حال اجرا دارد و تا تکمیل‌شدنِ آن قابل ویرایش یا حذف نیست.', 'warning');
        }
        // ─── مشاهدهٔ فقط‌خواندنی ──────────────────────────
        async function viewTemplate(templateId) {
            try {
                const response = await fetch(`../api/workflows/get-template.php?id=${templateId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (!data.success) {
                    showToast(data.message, 'danger');
                    return;
                }

                const t = data.template;
                currentTemplateId = null;
                redefineSourceId = null;

                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-eye"></i> مشاهدهٔ کار روتین';
                document.getElementById('templateId').value = '';
                document.getElementById('templateName').value = t.name || '';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = t.is_active == 1;

                const wrap = document.getElementById('deactivateOldWrap');
                if (wrap) wrap.style.display = 'none';

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;
                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                // فقط‌خواندنی کردن کلِ فرم + پنهان‌کردن دکمهٔ ذخیره
                setTemplateModalReadonly(true);

                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'danger');
            }
        }

        // فعال/غیرفعال‌کردنِ حالتِ فقط‌خواندنیِ مودال
        function setTemplateModalReadonly(readonly) {
            const modal = document.getElementById('templateModal');

            // کلاسِ view-mode برای کنترلِ نمایش با CSS (حذف مرحله، جابه‌جایی، افزودن مرحله، ذخیره)
            modal.classList.toggle('view-mode', !!readonly);

            // ورودی‌ها و دکمه‌های داخلِ فرم را غیرفعال کن
            modal.querySelectorAll('input, select, textarea, button').forEach(el => {
                if (el.classList.contains('btn-close') ||
                    el.classList.contains('btn-cancel') ||
                    el.getAttribute('data-bs-dismiss') === 'modal') return;
                el.disabled = readonly;
            });

            // غیرفعال‌کردنِ کشیدن (drag) روی مراحل در حالت مشاهده
            modal.querySelectorAll('.step-item').forEach(el => {
                if (readonly) el.removeAttribute('draggable');
                else el.setAttribute('draggable', 'true');
            });

            // دکمهٔ ذخیره
            const saveBtn = modal.querySelector('.btn-save');
            if (saveBtn) saveBtn.style.display = readonly ? 'none' : '';
        }
        // ─── ویرایش الگو ─────────────────────────────────
        async function editTemplate(templateId) {
            try {
                const response = await fetch(`../api/workflows/get-template.php?id=${templateId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();

                if (!data.success) {
                    showToast(data.message, 'danger');
                    return;
                }

                const t = data.template;
                currentTemplateId = templateId;

                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> ویرایش کار روتین';
                document.getElementById('templateId').value = templateId;
                document.getElementById('templateName').value = t.name || '';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = t.is_active == 1;

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;

                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'danger');
            }
        }
        // ─── بازتعریف (ساخت نسخهٔ جدید از روی قالب) ──────────
        let redefineSourceId = null; // آیدیِ قالبِ مبدأ برای غیرفعال‌سازی پس از ذخیره

        async function redefineTemplate(templateId) {
            try {
                const response = await fetch(`../api/workflows/get-template.php?id=${templateId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (!data.success) {
                    showToast(data.message, 'danger');
                    return;
                }

                const t = data.template;
                currentTemplateId = null;
                redefineSourceId = templateId; // مبدأ را نگه می‌داریم
                setTemplateModalReadonly(false);

                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-arrow-repeat"></i> بازتعریف کار روتین';
                document.getElementById('templateId').value = ''; // خالی → ساختِ نسخهٔ جدید
                document.getElementById('templateName').value = (t.name || '') + ' (نسخهٔ جدید)';
                document.getElementById('templateDescription').value = t.description || '';
                document.getElementById('templateActive').checked = true;

                // تیکِ «قالب قبلی غیرفعال شود» را نمایش بده و پیش‌فرض خاموش
                const wrap = document.getElementById('deactivateOldWrap');
                if (wrap) wrap.style.display = 'block';
                const chk = document.getElementById('deactivateOldChk');
                if (chk) chk.checked = false;

                document.getElementById('stepsList').innerHTML = '';
                stepCounter = 0;
                if (t.steps && t.steps.length > 0) {
                    t.steps.forEach(step => addStep(step));
                } else {
                    addStep();
                }

                new bootstrap.Modal(document.getElementById('templateModal')).show();
            } catch (e) {
                console.error(e);
                showToast('خطا در بارگذاری اطلاعات', 'danger');
            }
        }
        // ─── افزودن مرحله ────────────────────────────────
        let stepPickers = {}; // stepId -> picker instance
        let stepOriginalData = {}; // stepId -> { type, value } (برای حالت ویرایش‌نشده)

        function addStep(stepData = null) {
            stepCounter++;
            const stepId = stepData ? stepData.id : `new_${stepCounter}`;
            const stepMode = (stepData && stepData.execution_mode === 'parallel') ? 'parallel' : 'cascade';
            stepOriginalData[stepId] = {
                type: stepData ? (stepData.assignee_type || 'section') : 'section',
                value: stepData ?
                    (stepData.assignee_type === 'user' ? stepData.assignee_user_id : stepData.activity_section) : ''
            };

            let assigneePlaceholder = 'جستجوی کاربر یا انتخاب واحد...';
            if (stepData) {
                if (stepData.assignee_type === 'user') {
                    assigneePlaceholder = 'مسئول فعلی: ' + (stepData.assignee_user_name || 'کاربر') + ' — برای تغییر جستجو کنید...';
                } else if (stepData.activity_section) {
                    assigneePlaceholder = 'واحد فعلی: ' + (wfSectionMap[stepData.activity_section] || stepData.activity_section) + ' — برای تغییر جستجو کنید...';
                }
            }

            const html = `
                <div class="step-item" data-step-id="${stepId}" draggable="true">
                    <div class="step-item-header">
                        <span class="step-num">${stepCounter}</span>
                        <span class="drag-hint"><i class="bi bi-grip-horizontal"></i> بکشید</span>
                        <button type="button" class="btn-remove-step" onclick="removeStep(this)" title="حذف مرحله">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label">نام مرحله <span style="color:var(--danger)">*</span></label>
                                <input type="text" class="form-control form-control-sm step-name"
                                   value="${escAttr(stepData ? stepData.step_name : '')}"
                                   placeholder="مثال: بررسی موجودی" oninput="updateExecPreview()" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">مسئول <span style="color:var(--danger)">*</span></label>
                            <div id="step_assignee_${stepId}"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">مهلت (ساعت) <span style="color:var(--danger)">*</span></label>
                            <input type="number" class="form-control form-control-sm step-time"
                                   value="${stepData ? stepData.time_limit_hours : 24}"
                                   min="1" required>
                        </div>
                    </div>
                    <div class="step-mode-toggle" data-mode="${stepMode}">
                        <span class="sm-label">نحوهٔ اجرا:</span>
                        <button type="button" class="sm-btn sm-cascade ${stepMode==='cascade'?'active':''}" onclick="setStepMode(this,'cascade')">⛓ آبشاری</button>
                        <button type="button" class="sm-btn sm-parallel ${stepMode==='parallel'?'active':''}" onclick="setStepMode(this,'parallel')">⚡ موازی</button>
                    </div>
                </div>`;

            document.getElementById('stepsList').insertAdjacentHTML('beforeend', html);

            stepPickers[stepId] = AssigneePicker.create({
                container: '#step_assignee_' + stepId,
                users: wfUsers,
                sections: wfSections,
                sectionMap: wfSectionMap,
                showSections: true,
                placeholder: assigneePlaceholder
            });

            updateStepNumbers();
            initDragAndDrop();
        }

        // ─── حذف مرحله ───────────────────────────────────
        function removeStep(btn) {
            const stepEl = btn.closest('.step-item');
            const stepId = stepEl.dataset.stepId;
            delete stepPickers[stepId];
            delete stepOriginalData[stepId];
            stepEl.remove();
            updateStepNumbers();
        }

        function updateStepNumbers() {
            document.querySelectorAll('.step-item').forEach((el, i) => {
                el.querySelector('.step-num').textContent = i + 1;
            });
            updateExecPreview();
        }

        // ── انتخاب حالتِ یک مرحله ──
        function setStepMode(btn, mode) {
            const wrap = btn.closest('.step-mode-toggle');
            wrap.dataset.mode = mode;
            wrap.querySelectorAll('.sm-btn').forEach(b => b.classList.remove('active'));
            wrap.querySelector(mode === 'parallel' ? '.sm-parallel' : '.sm-cascade').classList.add('active');
            updateExecPreview();
        }

        // ── ست‌کردنِ همهٔ مراحل با یک کلیک ──
        function setAllModes(mode) {
            document.querySelectorAll('.step-mode-toggle').forEach(wrap => {
                wrap.dataset.mode = mode;
                wrap.querySelectorAll('.sm-btn').forEach(b => b.classList.remove('active'));
                wrap.querySelector(mode === 'parallel' ? '.sm-parallel' : '.sm-cascade').classList.add('active');
            });
            updateExecPreview();
        }

        // ── پیش‌نمایشِ زنده ──
        function updateExecPreview() {
            const box = document.getElementById('execPreview');
            if (!box) return;
            const faNum = s => String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]);
            const items = [...document.querySelectorAll('.step-item')];
            if (!items.length) {
                box.innerHTML = '';
                return;
            }

            const modes = items.map(it => it.querySelector('.step-mode-toggle')?.dataset.mode || 'cascade');
            const names = items.map((it, i) => (it.querySelector('.step-name')?.value.trim() || ('مرحله ' + faNum(i + 1))));
            const firstCascadeIdx = modes.findIndex(m => m === 'cascade');

            const activeNow = [],
                waiting = [];
            items.forEach((it, i) => {
                if (modes[i] === 'parallel' || i === firstCascadeIdx) {
                    activeNow.push(names[i]);
                } else {
                    let p = -1;
                    for (let k = i - 1; k >= 0; k--) {
                        if (modes[k] === 'cascade') {
                            p = k;
                            break;
                        }
                    }
                    waiting.push(names[i] + (p >= 0 ? ' (بعد از: ' + names[p] + ')' : ''));
                }
            });

            box.innerHTML =
                '<div class="ep-line"><b>از ابتدا فعال:</b> ' + (activeNow.length ? activeNow.map(escHtml).join('، ') : '—') + '</div>' +
                '<div class="ep-line"><b>منتظر:</b> ' + (waiting.length ? waiting.map(escHtml).join('، ') : '—') + '</div>';
        }

        // ─── Drag & Drop ──────────────────────────────────
        let draggedEl = null;

        function initDragAndDrop() {
            document.querySelectorAll('.step-item').forEach(el => {
                el.ondragstart = function() {
                    draggedEl = this;
                    this.classList.add('dragging');
                };
                el.ondragover = e => e.preventDefault();
                el.ondrop = function(e) {
                    e.stopPropagation();
                    if (draggedEl && draggedEl !== this) {
                        const all = [...document.querySelectorAll('.step-item')];
                        const di = all.indexOf(draggedEl),
                            ti = all.indexOf(this);
                        if (di < ti) this.after(draggedEl);
                        else this.before(draggedEl);
                        updateStepNumbers();
                    }
                };
                el.ondragend = function() {
                    this.classList.remove('dragging');
                    draggedEl = null;
                };
            });
        }

        // ─── ذخیره الگو ──────────────────────────────────
        async function saveTemplate() {
            const name = document.getElementById('templateName').value.trim();
            if (!name) {
                showToast('نام کار روتین الزامی است', 'warning');
                return;
            }

            const stepItems = document.querySelectorAll('.step-item');
            if (stepItems.length === 0) {
                showToast('حداقل یک مرحله تعریف کنید', 'warning');
                return;
            }

            const steps = [];
            let valid = true;

            stepItems.forEach((item, i) => {
                const stepId = item.dataset.stepId;
                const sName = item.querySelector('.step-name').value.trim();
                const sTime = item.querySelector('.step-time').value;

                const picked = stepPickers[stepId] ? stepPickers[stepId].getValue() : null;
                let assignee = (picked && picked.value !== '__all__' && picked.value !== '__all_users__') ? {
                        type: picked.type,
                        value: picked.value
                    } :
                    (stepOriginalData[stepId] || {
                        type: 'section',
                        value: ''
                    });

                if (!sName || !assignee.value || !sTime) {
                    valid = false;
                    return;
                }
                const sMode = item.querySelector('.step-mode-toggle')?.dataset.mode === 'parallel' ? 'parallel' : 'cascade';
                steps.push({
                    step_order: i + 1,
                    step_name: sName,
                    time_limit_hours: parseInt(sTime),
                    assignee_type: assignee.type,
                    assignee_value: assignee.value,
                    execution_mode: sMode
                });
            });

            if (!valid) {
                showToast('تمام فیلدها از جمله مسئولِ هر مرحله را تکمیل کنید (نه «همه واحدها»)', 'warning');
                return;
            }

            const payload = {
                name,
                description: document.getElementById('templateDescription').value.trim(),
                is_active: document.getElementById('templateActive').checked ? 1 : 0,
                steps
            };

            const templateId = document.getElementById('templateId').value;
            if (templateId) payload.template_id = templateId;

            const url = templateId ?
                '../api/workflows/update-template.php' :
                '../api/workflows/create-template.php';

            const saveBtn = document.querySelector('.btn-save');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال ذخیره...';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    // اگر بازتعریف بود و تیکِ غیرفعال‌سازیِ قالب قبلی زده شده بود
                    const deact = document.getElementById('deactivateOldChk');
                    if (redefineSourceId && deact && deact.checked) {
                        try {
                            await fetch('../api/workflows/toggle-template-active.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Authorization': 'Bearer ' + authToken
                                },
                                body: JSON.stringify({
                                    template_id: redefineSourceId,
                                    is_active: 0
                                })
                            });
                        } catch (e) {
                            console.error('deactivate old template:', e);
                        }
                    }
                    redefineSourceId = null; // پاک‌سازی
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
                    loadTemplates();
                } else {
                    showToast(data.message, 'danger');
                }
            } catch (e) {
                showToast('خطا در ارتباط با سرور', 'danger');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check2"></i> ذخیره';
            }
        }

        // ─── حذف الگو ────────────────────────────────────
        function deleteTemplate(templateId) {
            uiConfirm('آیا از حذف این روتین اطمینان دارید؟', async function() {
                try {
                    const res = await fetch('../api/workflows/delete-template.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: JSON.stringify({
                            template_id: templateId
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        loadTemplates();
                    } else showToast(data.message, 'danger');
                } catch (e) {
                    showToast('خطا در حذف', 'danger');
                }
            }, {
                danger: true,
                yesText: 'بله، حذف',
                noText: 'انصراف'
            });
        }

        // ─── Toast ────────────────────────────────────────
        function showToast(msg, type = 'info') {
            const colors = {
                success: '#10b981',
                danger: '#ef4444',
                warning: '#f59e0b',
                info: '#6366f1'
            };
            const icons = {
                success: 'check-circle-fill',
                danger: 'x-circle-fill',
                warning: 'exclamation-triangle-fill',
                info: 'info-circle-fill'
            };

            const el = document.createElement('div');
            el.style.cssText = `
                position:fixed; bottom:24px; left:24px; z-index:9999;
                background:${colors[type]}; color:white;
                padding:12px 18px; border-radius:10px;
                font-size:0.875rem; font-weight:500;
                display:flex; align-items:center; gap:9px;
                box-shadow:0 8px 24px rgba(0,0,0,0.15);
                animation: fadeInUp 0.25s ease;
            `;
            el.innerHTML = `<i class="bi bi-${icons[type]}"></i>${msg}`;

            const style = document.createElement('style');
            style.textContent = `@keyframes fadeInUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }`;
            document.head.appendChild(style);

            document.body.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        }

        // ─── Utils ────────────────────────────────────────
        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function escAttr(str) {
            return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // ─── بارگذاریِ مستقلِ کاربران و واحدها برای Pickerِ مسئولِ هر مرحله ───
        let wfUsers = [];
        let wfSections = [];
        let wfSectionMap = {};
        let stepAssigneeState = {}; // stepId -> { touched, type, value }

        async function loadUsersAndSectionsForPicker() {
            try {
                const [usersRes, sectionsRes] = await Promise.all([
                    fetch('../api/users/list.php', {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    }),
                    fetch('../api/organization/activity-sections.php', {
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        }
                    })
                ]);
                const usersData = await usersRes.json();
                const sectionsData = await sectionsRes.json();
                if (usersData.success) wfUsers = usersData.users;
                if (sectionsData.success) {
                    wfSections = sectionsData.sections;
                    sectionsData.sections.forEach(s => {
                        wfSectionMap[s.section_key] = s.section_label;
                    });
                }
            } catch (e) {
                console.error('loadUsersAndSectionsForPicker error:', e);
            }
        }

        // ─── Init ─────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', async function() {
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            loadTemplates();
            await loadSectionMap(); // در init صفحه
            await loadUsersAndSectionsForPicker();
        });
    </script>
</body>

</html>