<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/version.php';

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
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ایجاد کار جدید - سیستم مدیریت کار</title>

    <link href="<?= asset('../assets/js/cdn/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link href="<?= asset('../assets/js/cdn/fonts/bootstrap-icons.woff2?30af91bf14e37666a085fb8a161ff36d') ?>"
        rel="stylesheet">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/intro.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/introjs.min.css') ?>">
    <!-- تقویم شمسی سفارشی -->
    <link rel="stylesheet" href="<?= asset('../assets/css/persian-datepicker.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/responsive/dashboard-responsive.css') ?>">
    <script src="<?= asset('../assets/js/task-groups.js') ?>"></script>
    <script src="<?= asset('../assets/js/assignee-picker.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../assets/css/deadline-toast.css') ?>">
    <style>
        .step-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            margin-right: 6px;
        }

        .step-mode-badge.mode-parallel {
            background: #ECFDF3;
            color: #027A48;
        }

        .step-mode-badge.mode-cascade {
            background: #EFF8FF;
            color: #175CD3;
        }

        .exec-summary {
            background: #F9FAFB;
            border: 1px solid #EAECF0;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #344054;
        }

        .exec-summary b {
            color: #101828;
        }

        :root[data-theme="dark"] .exec-summary {
            background: var(--surface);
            border-color: var(--border-soft);
            color: var(--text-muted);
        }

        :root[data-theme="dark"] .exec-summary b {
            color: var(--text-strong);
        }
        .form-check-input{
            width: 2rem;
                margin-left: 0.5rem;

        }
        /* چک‌لیست: هر آیتم = ردیف (شماره، عنوان، آیکون‌های توضیحات/حذف در انتها) +
           یک ناحیه‌ی اختیاریِ تمام‌عرض زیرش برای ویرایش توضیحات */
        .cl-item-wrap { margin-bottom: 8px; }
        .cl-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            transition: border-color .15s, background .15s;
        }
        .cl-item:hover { border-color: #c7d2fe; background: #fafaff; }
        .cl-index { color: #9ca3af; font-size: 0.85rem; flex: 0 0 auto; }
        .cl-title-input { flex: 1 1 auto; min-width: 0; border: none; background: transparent; box-shadow: none !important; }
        .cl-title-input:focus { background: #f3f4f6; border-radius: 4px; }
        /* آیکون‌های توضیحات و حذف، گروه‌شده در انتهای هر آیتم (سمت چپ در RTL) */
        .cl-actions { display: flex; align-items: center; gap: 2px; flex: 0 0 auto; margin-inline-start: auto; }
        .cl-icon-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border: none; background: transparent;
            border-radius: 6px; color: #9ca3af; cursor: pointer; transition: background .15s, color .15s;
            padding: 0; font-size: 0.9rem;
        }
        .cl-icon-btn:hover { background: #f3f4f6; }
        .cl-desc-btn:hover { color: #6366f1; }
        .cl-desc-btn.has-desc { color: #6366f1; }
        .cl-delete-btn:hover { color: #dc2626; background: #fee2e2; }
        .cl-desc-zone:empty { display: none; }
        .cl-desc-zone.open { margin-top: 6px; }
        .cl-desc-edit {
            display: flex; flex-direction: column; gap: 6px;
            padding: 8px 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;
        }
        .cl-desc-edit textarea { font-size: 0.82rem; resize: vertical; }

        :root[data-theme="dark"] .cl-item {
            border-color: var(--border-soft);
            background: var(--surface);
        }
        :root[data-theme="dark"] .cl-item:hover {
            border-color: var(--icon-accent);
            background: #232a3a;
        }
        :root[data-theme="dark"] .cl-title-input:focus {
            background: #232a3a;
        }
        :root[data-theme="dark"] .cl-icon-btn:hover {
            background: #2b3242;
        }
        :root[data-theme="dark"] .cl-desc-edit {
            background: #161b27;
            border-color: var(--border-soft);
        }
        .cl-desc-edit-actions { display: flex; gap: 6px; justify-content: flex-end; }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="overview-container">
        <div class="filters-wrapper">
            <!-- عنوان -->
            <h1><i class="bi bi-plus-circle ms-2"></i>ایجاد کار جدید</h1>
            <p class="mb-0">توضیحات کار جدید را مشخص کنید</p>
        </div>


        <div class="filters-wrapper">

            <!-- نمایش پیام‌ها -->
            <div id="alertContainer"></div>
            <!-- انتخاب نوع کار -->
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="isWorkflowTask">
                    <label class="form-check-label" for="isWorkflowTask">
                        <i class="bi bi-diagram-3 me-1"></i>این یک کار روتین است
                    </label>
                </div>
                <small class="text-muted">با انتخاب این گزینه، می‌توانید یک لیست کار بصورت هوشمند برای واحدهای مختلف
                    ایجاد کنید.</small>
            </div>

            <!-- بخش کار روتین -->
            <div id="workflowSection" style="display: none;" class="form-section">
                <h4><i class="bi bi-pencil-square ms-2"></i>جزئیات کار روتین</h4>

                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    کار روتین به صورت خودکار برای واحدهای مختلف ایجاد و مدیریت می‌شود.
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">انتخاب نوع کار روتین *</label>
                            <select class="form-select" id="workflowTemplate">
                                <option value="">در حال بارگذاری...</option>
                            </select>
                            <small class="text-muted">الگوی از پیش تعریف شده را انتخاب کنید</small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">عنوان/موضوع *</label>
                            <input type="text" class="form-control" id="workflowTitle" placeholder="مثال: فاکتور محمد جوادی">
                            <small class="text-muted">عنوانی که به تمام مراحل اضافه می‌شود</small>
                        </div>
                    </div>
                </div>

                <!-- پیش‌نمایش مراحل -->
                <div id="workflowPreview" class="workflow-preview" style="display: none;">
                    <h6 class="mb-3"><i class="bi bi-list-ol me-2"></i>مراحل کار روتین:</h6>
                    <div id="stepsPreview"></div>
                </div>
                <div id="workflowAttachments" style="display:none; margin-top:1.5rem;">
                    <h6><i class="bi bi-paperclip me-2"></i>فایل‌های پیوست روتین</h6>
                    <div class="upload-area" id="workflowUploadArea"
                        style="border:2px dashed #ccc; border-radius:8px; padding:20px; text-align:center; cursor:pointer;">
                        <input type="file" id="workflowFileInput" style="display:none;"
                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.mp3,.m4a,.ogg" multiple>
                        <i class="bi bi-cloud-upload fs-3"></i>
                        <strong> فایل خود را اینجا رها کنید یا کلیک کنید</strong>
                        <p><small>فرمت‌های مجاز: jpg, png, pdf, docx, xlsx, mp3, m4a, ogg (حداکثر 20MB)</small></p>
                    </div>
                    <div id="workflowFilesList" style="margin-top:1rem;"></div>
                </div>
            </div>

            <!-- فرم کار دستی -->
            <div id="manualTaskForm" class="form-section">
                <h4><i class="bi bi-pencil-square ms-2"></i>جزئیات کار دستی</h4>
                <form id="manualForm" method="post" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">عنوان کار *</label>
                                <input type="text" class="form-control" id="manualTitle" required
                                    placeholder="عنوان کار را وارد کنید">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">گروه کار</label>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <select id="taskGroupSelect" class="form-select">
                                        <option value="">بدون گروه</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" style="padding:0.5rem;"
                                        onclick="TaskGroups.openManager()" title="مدیریت گروه‌ها">
                                        <i class="bi bi-gear"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">اولویت</label>
                                <select class="form-select" id="manualPriority">
                                    <option value="medium">متوسط</option>
                                    <option value="high">بالا</option>
                                    <option value="low">پایین</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">توضیحات</label>
                        <textarea class="form-control" id="manualDescription" rows="3"
                            placeholder="توضیحات تکمیلی در مورد کار..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">نوع کار</label>
                                <select class="form-select" id="manualTaskType">
                                    <option value="periodic">مقطعی</option>
                                    <option value="continuous">دوره‌ای</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label class="form-label mb-0">واگذاری به</label>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="multiAssigneeToggle" onchange="toggleMultiAssigneeMode()">
                                        <label class="form-check-label small" for="multiAssigneeToggle">ارجاع به چند نفر</label>
                                    </div>
                                </div>
                                <div id="assigneePicker"></div>
                                <div id="multiAssigneeBox" style="display:none;">
                                    <div id="multiAssigneePicker"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <!-- بخش کار مقطعی -->
                            <div id="manualPeriodicOptions">
                                <div class="mb-3">
                                    <label class="form-label">موعد انجام</label>
                                    <div class="persian-datepicker-wrapper">
                                        <input type="text" class="persian-datepicker-input form-control"
                                            id="manualDueDate" placeholder="انتخاب تاریخ موعد انجام..." readonly>
                                        <div class="persian-datepicker">
                                            <div class="datepicker-header">
                                                <button type="button" class="datepicker-nav"
                                                    data-action="prev">►</button>
                                                <span class="datepicker-current"></span>
                                                <button type="button" class="datepicker-nav"
                                                    data-action="next">◄</button>
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
                            </div>
                        </div>
                    </div>
                    <!-- بخش کار دوره‌ای -->
                    <div id="manualContinuousOptions" style="display: none;">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label class="form-label">تاریخ شروع</label>
                                    <div class="persian-datepicker-wrapper">
                                        <input type="text" class="persian-datepicker-input form-control"
                                            id="manualStartDate" placeholder="انتخاب تاریخ شروع..." readonly>
                                        <div class="persian-datepicker">
                                            <div class="datepicker-header">
                                                <button type="button" class="datepicker-nav"
                                                    data-action="prev">►</button>
                                                <span class="datepicker-current"></span>
                                                <button type="button" class="datepicker-nav"
                                                    data-action="next">◄</button>
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
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label class="form-label">دوره تکرار</label>
                                    <select class="form-select" id="manualPeriod">
                                        <option value="daily">روزانه</option>
                                        <option value="weekly">هفتگی</option>
                                        <option value="monthly">ماهانه</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label class="form-label">تاریخ پایان <small
                                            class="text-muted">(اختیاری)</small></label>
                                    <div class="persian-datepicker-wrapper">
                                        <input type="text" class="persian-datepicker-input form-control"
                                            id="manualEndDate" placeholder="انتخاب تاریخ پایان..." readonly>
                                        <div class="persian-datepicker">
                                            <div class="datepicker-header">
                                                <button type="button" class="datepicker-nav"
                                                    data-action="prev">►</button>
                                                <span class="datepicker-current"></span>
                                                <button type="button" class="datepicker-nav"
                                                    data-action="next">◄</button>
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
                                    <small id="endDateHint" class="text-muted"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- بخش فایل‌های پیوست -->
            </div>
            <div class="row mt-4">
                <div class="col-md-6">
                    <!-- اشتراک‌گذاری تاریخچه با ارجاع‌شوندگان -->
                    <div class="form-check form-switch mt-4" id="shareHistorySection">
                        <input class="form-check-input" type="checkbox" id="shareHistoryToggle" checked>
                        <label class="form-check-label" for="shareHistoryToggle">
                            تاریخچهٔ کار برای کاربران ارجاع‌شونده قابل نمایش باشد
                        </label>
                        <small class="form-text text-muted d-block">
                            اگر غیرفعال شود، هر کاربری که کار به او ارجاع می‌شود فقط از زمان ورود خودش به بعد را می‌بیند.
                        </small>
                    </div>

                    <!-- 🆕 بخش چک‌لیست (فقط کار عادی) -->
                    <div class=" mt-4" id="checklistSection">
                        <div style="display:flex; align-items:center;">
                            <h5 style="display:flex; align-items:center; gap:8px; margin:0;">
                                <i class="bi bi-check2-square"></i> چک‌لیست
                            </h5>
                        </div>

                        <div id="checklistItems" class="mt-2"></div>

                        <div style="display:flex; gap:8px; margin-top:8px;">
                            <input type="text" id="newChecklistItem" class="form-control form-control-sm"
                                placeholder="افزودن آیتم جدید..."
                                onkeydown="if(event.key==='Enter'){event.preventDefault();addChecklistItemCreate();}">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addChecklistItemCreate()">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <!-- بخش فایل‌های پیوست -->
                    <div class="attachments-section mt-4">
                        <h5 style="display:flex; align-items:center; gap:8px;">
                            <i class="bi bi-paperclip"></i>
                            فایل‌های پیوست
                            <span id="attachmentsCountBadge" class="badge bg-secondary" style="display:none;">0</span>
                        </h5>

                        <div class="upload-area" id="uploadArea" style="border:2px dashed #ccc; border-radius:8px; padding:30px;
    text-align:center; cursor:pointer; margin-top:10px;">
                            <input type="file" id="fileInput" style="display:none;"
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.mp3,.m4a,.ogg" multiple>
                            <i class="bi bi-cloud-upload fs-3"></i>
                            &nbsp;<strong>فایل خود را اینجا رها کنید یا کلیک کنید</strong>
                            <p><small>فرمت‌های مجاز: jpg, png, pdf, docx, xlsx, mp3, m4a, ogg (حداکثر 20MB)</small></p>
                        </div>
                        <div id="selectedFilesList" style="margin-top:1rem;"></div>

                    </div>
                </div>
            </div>
            </form>
        </div>
        <!-- دکمه‌های عمل -->
        <div class="d-flex justify-content-between mt-4">
            <div></div>
            <div>
                <button type="button" class="btn btn-primary" onclick="saveTask()" id="saveBtn">
                    <i class="bi bi-check-circle ms-2"></i>ذخیره کار
                </button>
            </div>
        </div>
    </div>
    </div>


    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">در حال پردازش...</span>
            </div>
            <p>در حال ایجاد کار...</p>
        </div>
    </div>
    <?php include 'footer.php'; ?>

    <!-- Scripts -->
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('../../assets/js/table-utils.js') ?>"></script>
    <!-- تقویم شمسی سفارشی -->
    <script src="<?= asset('../assets/js/persian-datepicker.js') ?>"></script>

    <script>
        let currentTaskType = 'manual';
        let users = [];
        let sections = []; // لیست واحدها
        let mainAssigneePickerInst = null;
        let multiAssigneePickerInst = null;
        let workflowTemplates = [];
        let selectedTemplate = null;
        let userRoutines = [];
        let selectedRoutineData = null;

        // متغیرهای جدید برای روتین
        let availableRoutines = [];
        let canCreateRoutine = false;
        let myRole = '';

        let acticity_section = {
            'management': 'مدیریت',
            'supervisor': 'سرپرست'
        };
        // 🆕 چک‌لیست در فرم ساخت کار
        let checklistItemsCreate = []; // {tempId, title}

        function addChecklistItemCreate() {
            const input = document.getElementById('newChecklistItem');
            const title = input.value.trim();
            if (!title) return;
            checklistItemsCreate.push({
                tempId: Date.now(),
                title,
                description: ''
            });
            renderChecklistCreate();
            input.value = '';
            input.focus();
        }

        function removeChecklistItemCreate(tempId) {
            checklistItemsCreate = checklistItemsCreate.filter(i => i.tempId !== tempId);
            renderChecklistCreate();
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function escapeAttr(str) {
            return escapeHtml(str).replace(/"/g, '&quot;');
        }

        // تولتیپ‌های بوت‌استرپ برای دکمه‌های توضیحات را (دوباره) مقداردهی می‌کند
        function initChecklistTooltips() {
            document.querySelectorAll('#checklistItems [data-bs-toggle="tooltip"]').forEach(el => {
                bootstrap.Tooltip.getInstance(el)?.dispose();
                new bootstrap.Tooltip(el);
            });
        }

        function renderChecklistCreate() {
            const c = document.getElementById('checklistItems');
            if (!c) return;
            c.innerHTML = checklistItemsCreate.map((item, idx) => {
                const hasDesc = !!(item.description && item.description.trim());
                const descTooltip = hasDesc ? escapeAttr(item.description) : 'افزودن توضیحات';

                return `
                <div class="cl-item-wrap">
                    <div class="cl-item">
                        <span class="cl-index">${enTofaNumber(idx + 1)}.</span>
                        <input type="text" class="form-control form-control-sm cl-title-input" value="${escapeAttr(item.title)}"
                            placeholder="عنوان آیتم..."
                            onchange="updateChecklistTitleCreate(${item.tempId}, this.value)">
                        <div class="cl-actions">
                            <button type="button" class="cl-icon-btn cl-desc-btn ${hasDesc ? 'has-desc' : ''}"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="${descTooltip}"
                                onclick="startEditDescCreate(${item.tempId})">
                                <i class="bi ${hasDesc ? 'bi-chat-left-text-fill' : 'bi-chat-left-text'}"></i>
                            </button>
                            <button type="button" class="cl-icon-btn cl-delete-btn" title="حذف آیتم"
                                onclick="removeChecklistItemCreate(${item.tempId})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="cl-desc-zone" id="cl-desc-zone-${item.tempId}"></div>
                </div>`;
            }).join('');
            initChecklistTooltips();
        }

        function updateChecklistTitleCreate(tempId, val) {
            const it = checklistItemsCreate.find(i => i.tempId === tempId);
            if (it) it.title = val.trim();
        }

        // ═══════════════════════════════════════════════
        //  ویرایش درجای توضیحات آیتم (نسخه‌ی create-task، فقط حافظه)
        //  ناحیه‌ی ویرایش، تمام‌عرض و زیرِ ردیف آیتم باز می‌شود
        // ═══════════════════════════════════════════════

        function startEditDescCreate(tempId) {
            const zone = document.getElementById('cl-desc-zone-' + tempId);
            if (!zone) return;

            const it = checklistItemsCreate.find(i => i.tempId === tempId);
            const currentDesc = (it && it.description) ? it.description : '';

            zone.classList.add('open');
            zone.innerHTML = `
                <div class="cl-desc-edit">
                    <textarea id="cl-desc-input-${tempId}" class="form-control form-control-sm" rows="2"
                        placeholder="توضیحات..."
                        onkeydown="if(event.key==='Escape'){cancelEditDescCreate(${tempId});} else if(event.ctrlKey && event.key==='Enter'){saveEditDescCreate(${tempId});}"
                    >${escapeHtml(currentDesc)}</textarea>
                    <div class="cl-desc-edit-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelEditDescCreate(${tempId})">
                            <i class="bi bi-x-lg"></i> انصراف
                        </button>
                        <button type="button" class="btn btn-sm btn-success" onclick="saveEditDescCreate(${tempId})">
                            <i class="bi bi-check-lg"></i> ثبت توضیحات
                        </button>
                    </div>
                </div>
            `;

            const ta = document.getElementById('cl-desc-input-' + tempId);
            if (ta) {
                ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
            }
        }

        // ذخیره‌ی توضیحات (فقط در حافظه، بدون سرور) و بازرسم کل چک‌لیست
        // (برای به‌روزرسانیِ آیکون و تولتیپ همان آیتم)
        function saveEditDescCreate(tempId) {
            const ta = document.getElementById('cl-desc-input-' + tempId);
            if (!ta) return;
            const it = checklistItemsCreate.find(i => i.tempId === tempId);
            if (it) it.description = ta.value.trim();
            renderChecklistCreate();
        }

        // انصراف از ویرایش (بدون ذخیره)
        function cancelEditDescCreate(tempId) {
            const zone = document.getElementById('cl-desc-zone-' + tempId);
            if (zone) {
                zone.innerHTML = '';
                zone.classList.remove('open');
            }
        }
        async function loadSections() {
            try {
                const res = await fetch('../api/organization/activity-sections.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await res.json();
                if (data.success) {
                    sections = data.sections; // ← آرایه sections را پر کن
                    data.sections.forEach(s => {
                        acticity_section[s.section_key] = s.section_label;
                    });
                }
            } catch {}
        }


        // بارگذاری الگوهای workflow
        async function loadWorkflowTemplates() {
            try {
                const response = await fetch('../api/workflows/list-templates.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();

                if (data.success) {
                    workflowTemplates = data.templates;
                    renderWorkflowTemplatesDropdown();
                }
            } catch (error) {
                console.error('Error loading workflow templates:', error);
            }
        }

        // نمایش الگوها در dropdown
        function renderWorkflowTemplatesDropdown() {
            const select = document.getElementById('workflowTemplate');

            if (workflowTemplates.length === 0) {
                select.innerHTML = '<option value="">هیچ کار روتینی تعریف نشده است</option>';
                return;
            }

            let html = '<option value="">انتخاب کنید...</option>';
            workflowTemplates.forEach(template => {
                html += `<option value="${template.id}">${template.name}</option>`;
            });

            select.innerHTML = html;
        }

        // تغییر نوع کار (عادی یا روتین)
        document.getElementById('isWorkflowTask').addEventListener('change', function() {
            const isWorkflow = this.checked;
            document.getElementById('workflowSection').style.display = isWorkflow ? 'block' : 'none';
            document.getElementById('manualTaskForm').style.display = isWorkflow ? 'none' : 'block';
            // 🆕 چک‌لیست فقط برای کار عادی
            const cs = document.getElementById('checklistSection');
            if (cs) cs.style.display = isWorkflow ? 'none' : 'block';
            if (isWorkflow && workflowTemplates.length === 0) {
                loadWorkflowTemplates();
            }
        });

        // انتخاب الگوی workflow
        document.getElementById('workflowTemplate').addEventListener('change', async function() {
            const templateId = this.value;

            if (!templateId) {
                document.getElementById('workflowPreview').style.display = 'none';
                return;
            }

            try {
                const response = await fetch(`../api/workflows/get-template.php?id=${templateId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();

                if (data.success) {
                    selectedTemplate = data.template;

                    showWorkflowPreview(data.template);
                }
            } catch (error) {
                console.error('Error loading template details:', error);
            }
        });

        function enTofaNumber(numb) {
            const persianNumbers = "۰۱۲۳۴۵۶۷۸۹";
            const englishNumbers = "0123456789";
            return String(numb).replace(/[0-9]/g, d => persianNumbers[englishNumbers.indexOf(d)]);
        }

        // نمایش پیش‌نمایش مراحل
        function showWorkflowPreview(template) {
            const previewContainer = document.getElementById('stepsPreview');
            console.log('steps:', JSON.stringify(template.steps.map(s => ({
                id: s.id,
                name: s.step_name
            }))));

            let html = '';
            template.steps.forEach((step, index) => {
                html += `
    <div class="step-preview-item">
        <div class="step-number">${String(enTofaNumber(index + 1))}</div>
        <div class="step-info">
            <strong>${step.step_name}</strong>
            <div>
                <span class="step-unit">${
                    step.assignee_type === 'user'
                        ? ('مسئول: ' + (step.assignee_user_name || 'نامشخص'))
                    : step.assignee_type === 'creator'
                        ? '↩ ایجادکنندهٔ روتین'
                    : ('واحد: ' + (acticity_section[step.activity_section] || step.activity_section))
                }</span>
                <span class="step-time"><i class="bi bi-clock me-1"></i> ${String(enTofaNumber(step.time_limit_hours))} ساعت</span>
                ${(step.execution_mode === 'parallel')
                    ? '<span class="step-mode-badge mode-parallel">⚡ موازی</span>'
                    : '<span class="step-mode-badge mode-cascade">⛓ آبشاری</span>'}
            </div>
            <div class="mt-2">
                <textarea class="form-control form-control-sm"
                    id="step_desc_${index}"
                    placeholder="توضیح اختصاصی این مرحله (اختیاری)..."
                    rows="2">${step.step_description || ''}</textarea>
            </div>
        </div>
        ${index < template.steps.length - 1 ? '<i class="bi bi-arrow-left text-muted"></i>' : ''}
    </div>
`;
            });

            // خلاصهٔ ترتیب اجرا
            const fa = s => String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹' [d]);
            const modes = template.steps.map(s => (s.execution_mode === 'parallel') ? 'parallel' : 'cascade');
            const firstCascade = modes.indexOf('cascade');
            const activeNow = [],
                waiting = [];
            template.steps.forEach((s, i) => {
                const nm = s.step_name || ('مرحله ' + fa(i + 1));
                if (modes[i] === 'parallel' || i === firstCascade) activeNow.push(nm);
                else {
                    let p = -1;
                    for (let k = i - 1; k >= 0; k--) {
                        if (modes[k] === 'cascade') {
                            p = k;
                            break;
                        }
                    }
                    waiting.push(nm + (p >= 0 ? ' (بعد از: ' + template.steps[p].step_name + ')' : ''));
                }
            });
            const summary = `<div class="exec-summary">
                <div><b>از ابتدا فعال:</b> ${activeNow.join('، ') || '—'}</div>
                <div><b>منتظر:</b> ${waiting.join('، ') || '—'}</div>
            </div>`;

            previewContainer.innerHTML = summary + html;
            document.getElementById('workflowPreview').style.display = 'block';
            document.getElementById('workflowAttachments').style.display = 'block';
            setupWorkflowUpload();
        }


        // بارگذاری اولیه
        document.addEventListener('DOMContentLoaded', function() {
            authToken = localStorage.getItem('auth_token');
            if (!authToken) {
                window.location.href = '../index.php';
                return;
            }
            initializePage();
        });

        async function initializePage() {
            await loadSections(); // ← await — باید قبل از loadUsers تمام شود
            await checkUserPermissions();
            setupTaskTypeSelection();
            setupFormHandlers();
            await loadUsers(); // ← sections و acticity_section آماده‌اند
            setupCreateTaskUpload();
        }

        // بررسی دسترسی کاربر
        async function checkUserPermissions() {
            try {
                const response = await fetch('../api/auth/profile.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });

                const data = await response.json();
                if (data.success) {
                    canCreateRoutine = data.user.can_create_routine == 1;
                    myRole = data.user.role || '';

                    // 🆕 راه‌اندازی گروه‌ها (گروه سازمانی فقط برای management+supervisor)
                    const isOrgAdmin = (data.user.activity_section === 'management' &&
                        data.user.role === 'supervisor');
                    await TaskGroups.init({
                        isOrgAdmin,
                        onChange: () => TaskGroups.fill(document.getElementById('taskGroupSelect'))
                    });
                    TaskGroups.fill(document.getElementById('taskGroupSelect'));
                    // اگر دسترسی ندارد، گزینه روتین را مخفی کن
                    if (!canCreateRoutine) {
                        const workflowSection = document.querySelector('[data-type="routine"]');
                        if (workflowSection) {
                            workflowSection.style.display = 'none';
                        }
                    }
                }
            } catch (error) {
                console.error('Error checking permissions:', error);
            }


            const token = localStorage.getItem('auth_token');
            if (!token) {
                console.error('❌ توکن احراز هویت یافت نشد');
                return;
            }

        }

        // راه‌اندازی انتخاب نوع کار
        function setupTaskTypeSelection() {
            document.getElementById('manualTaskForm').classList.add('active');
        }

        // راه‌اندازی رویدادهای فرم
        function setupFormHandlers() {
            // تغییر نوع کار دستی
            document.getElementById('manualTaskType').addEventListener('change', function() {
                const isPeriodicTask = this.value === 'periodic';
                document.getElementById('manualPeriodicOptions').style.display = isPeriodicTask ? 'block' : 'none';
                document.getElementById('manualContinuousOptions').style.display = isPeriodicTask ? 'none' : 'block';

                function updateEndDateHint() {
                    const period = document.getElementById('manualPeriodicOptions').value;
                    const startDate = document.getElementById('manualStartDate').getAttribute('data-date');
                    const hint = document.getElementById('endDateHint');
                    const labels = {
                        daily: 'روز',
                        weekly: 'هفته',
                        monthly: 'ماه'
                    };
                    if (startDate) {
                        hint.textContent = `حداقل یک ${labels[period] || 'دوره'} بعد از تاریخ شروع`;
                    }
                }
                if (!isPeriodicTask && typeof window.reinitPersianDatepickers === 'function') {
                    setTimeout(() => {
                        window.reinitPersianDatepickers();
                    }, 50);
                }
            });

            // تغییر روتین انتخابی
            document.getElementById('isWorkflowTask').addEventListener('change', async function() {
                const routineId = this.value;

                if (!routineId) {
                    document.getElementById('routineDetails').style.display = 'none';
                    return;
                }
            });
        }

        async function loadUsers() {
            try {
                const response = await fetch('../api/users/list.php', {
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    }
                });
                const data = await response.json();
                if (data.success) {
                    users = data.users;
                    mainAssigneePickerInst = AssigneePicker.init({
                        container: '#assigneePicker',
                        users,
                        sections, // آرایه [{ section_key, section_label }]
                        sectionMap: acticity_section, // object { key: label } — ترجمه نام واحد
                        showSections: true,
                        allowAll: (myRole === 'supervisor'), // 🆕 «همه کاربران/همه واحدها» فقط برای سرپرست
                        onSelect: (type, value, label) => {
                            /* getValue() کافی است */
                        }
                    });
                    multiAssigneePickerInst = AssigneePicker.create({
                        container: '#multiAssigneePicker',
                        users,
                        sections,
                        sectionMap: acticity_section,
                        multiSelect: true,
                        onSelect: () => { /* getValue() کافی است */ }
                    });
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        // ذخیره کار
        async function saveTask() {

            // دکمه ذخیره را پیدا کن
            const saveBtn = document.getElementById('saveBtn');
            if (!saveBtn) {
                // اگر دکمه پیدا نشد، تابع مثل قبل کار کند
                const isWorkflow = document.getElementById('isWorkflowTask').checked;
                return isWorkflow ? await saveWorkflowTask() : await saveNormalTask();
            }

            // متن اصلی دکمه را نگه دار
            const originalHTML = saveBtn.innerHTML;

            // دکمه را غیرفعال و در حالت لودینگ قرار بده
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>ذخیره کار';

            try {
                const isWorkflow = document.getElementById('isWorkflowTask').checked;
                let taskId = null;

                if (isWorkflow) {
                    taskId = await saveWorkflowTask();
                } else {
                    taskId = await saveNormalTask();
                }

                if (taskId) {
                    // تسک با موفقیت ساخته شده، صفحه را رفرش کن
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                } else {
                    // ساخت تسک ناموفق، دکمه را به حالت قبل برگردان
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalHTML;
                }
            } catch (e) {
                // خطای غیرمنتظره، دکمه را به حالت قبل برگردان
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalHTML;
            }
        }



        // ذخیره کار روتین
        async function saveWorkflowTask() {
            const templateId = document.getElementById('workflowTemplate').value;
            const title = document.getElementById('workflowTitle').value.trim();

            if (!templateId) {
                const t = showToast('لطفاً نوع کار روتین را انتخاب کنید', 'warning');
                t.close();
                return;
            }

            if (!title) {
                const t = showToast('عنوان کار الزامی است', 'warning');
                t.close();
                return;
            }

            // نمایش لودینگ
            const btn = document.querySelector('button[onclick="saveTask()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>در حال ایجاد...';
            btn.disabled = true;
            console.log('11');
            try {
                const response = await fetch('../api/workflows/start-workflow.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        template_id: parseInt(templateId),
                        title: title,
                        share_history: document.getElementById('shareHistoryToggle')?.checked ? 1 : 0
                    })
                });
                const data = await response.json();
                console.log(data);
                if (data.success) {
                    const t = showToast('کار روتین با موفقیت ایجاد شد', 'success');
                    t.close();

                    // پاک کردن فرم
                    document.getElementById('isWorkflowTask').checked = false;
                    document.getElementById('workflowTemplate').value = '';
                    document.getElementById('workflowTitle').value = '';
                    document.getElementById('workflowSection').style.display = 'none';
                    document.getElementById('manualTaskForm').style.display = 'block';
                    document.getElementById('workflowPreview').style.display = 'none';

                    // 🆕 توضیحات مراحل را همیشه ذخیره کن (حتی بدون فایل)
                    await saveStepDescriptions(data.instance_id);

                    if (workflowPendingFiles.length > 0 && data.instance_id) {
                        await uploadWorkflowFiles(data.instance_id); // 🆕 instance_id
                    }
                    return data.task_id || data.instance_id;

                    // انتقال به صفحه کارها
                    // setTimeout(() => {
                    //     window.location.href = 'my-tasks.php';
                    // }, 1500);
                } else {
                    const t = showToast('خطا در ایجاد کار روتین', 'error');
                    t.close();
                }
            } catch (error) {
                const t = showToast('خطا در ارتباط با سرور', 'error');
                t.close();
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        // توابع کمکی
        // function showAlert(message, type = 'info') {
        //     const alertContainer = document.getElementById('alertContainer');
        //     const alertId = 'alert-' + Date.now();

        //     const icons = {
        //         'success': 'check-circle-fill',
        //         'danger': 'exclamation-triangle-fill',
        //         'warning': 'exclamation-triangle-fill',
        //         'info': 'info-circle-fill'
        //     };

        //     const alertHTML = `
        //     <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}" role="alert">
        //         <i class="bi bi-${icons[type]} ms-2"></i>
        //         ${message}
        //         <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        //     </div>
        // `;

        //     alertContainer.innerHTML = alertHTML;

        //     setTimeout(() => {
        //         const alertElement = document.getElementById(alertId);
        //         if (alertElement) {
        //             const bsAlert = new bootstrap.Alert(alertElement);
        //             bsAlert.close();
        //         }
        //     }, 5000);

        //     document.querySelector('.overview-container').scrollIntoView({ behavior: 'smooth' });
        // }

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            const saveBtn = document.getElementById('saveBtn');

            if (show) {
                overlay.style.display = 'flex';
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm ms-2"></span>در حال ذخیره...';
            } else {
                overlay.style.display = 'none';
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check-circle ms-2"></i>ذخیره کار';
            }
        }

        function goBack() {
            uiConfirm('آیا مطمئن هستید که می‌خواهید بدون ذخیره خارج شوید؟', function() {
                window.location.href = 'dashboard-manager.php';
            }, {
                danger: true,
                yesText: 'بله، خروج',
                noText: 'بمان در صفحه'
            });
        }

        // ذخیره کار عادی (کد قبلی)
        async function saveNormalTask() {

            const taskType = document.getElementById('manualTaskType').value;
            const dueDateInput = document.getElementById('manualDueDate');
            const startDateInput = document.getElementById('manualStartDate');

            if (document.getElementById('multiAssigneeToggle').checked) {
                return await saveTaskForMultipleUsers();
            }

            // خواندن مقدار از کامپوننت
            const sel = mainAssigneePickerInst ? mainAssigneePickerInst.getValue() : null;

            if (sel && sel.type === 'section') {
                if (!sel.value) {
                    showToast('لطفاً یک واحد انتخاب کنید', 'warning');
                    return null;
                }
                return await saveTaskForSection(sel.value);
            }
            if (sel && sel.type === 'user' && sel.value === '__all_users__') {
                return await saveTaskForSection('__all__');
            }

            let assigneeId = null;
            if (sel && sel.type === 'user') {
                if (sel.value === '' || sel.value === null) {
                    // «خودم» انتخاب شده
                    const me = JSON.parse(localStorage.getItem('user_info'));
                    assigneeId = me ? me.id : null;
                } else if (sel.value !== '__all_users__') {
                    assigneeId = sel.value;
                }
            }

            const taskData = {
                title: document.getElementById('manualTitle').value.trim(),
                description: document.getElementById('manualDescription').value.trim(),
                task_type: taskType,
                priority: document.getElementById('manualPriority').value,
                assignee_id: assigneeId,
                group_id: document.getElementById('taskGroupSelect')?.value || null, // ✅ اضافه شد
                share_history: document.getElementById('shareHistoryToggle')?.checked ? 1 : 0
            };
            console.log('111');
            // شرطی کردن فیلدهای تاریخ و دوره بر اساس نوع تسک
            if (taskType === 'periodic') {
                taskData.due_date = dueDateInput.getAttribute('data-date') || null; // فرمت میلادی از data-date
                taskData.start_date = null;
                taskData.period_type = null;
            } else if (taskType === 'continuous') {
                taskData.start_date = startDateInput.getAttribute('data-date') || null; // فرمت میلادی
                taskData.period_type = document.getElementById('manualPeriod').value || null;
                taskData.due_date = null;
                taskData.end_date = document.getElementById('manualEndDate').getAttribute('data-date') || null;
            }

            if (!taskData.title) {
                showToast('عنوان کار الزامی است', 'error');

                return;
            }
            const __me = JSON.parse(localStorage.getItem('user_info') || 'null');
            const __isSelfTask = !taskData.assignee_id || (__me && String(taskData.assignee_id) === String(__me.id));
            if (taskData.task_type === 'periodic' && !taskData.due_date && !__isSelfTask) {
                showToast('تاریخ انجام برای کارهای مقطعیِ ارجاع‌داده‌شده الزامی است', 'error');

                return;
            }
            if (taskData.task_type === 'continuous' && !taskData.start_date) {
                showToast('تاریخ شروع برای کارهای دوره‌ای الزامی است', 'error');

                return;
            }
            // چک تاریخ پایان
            if (taskData.task_type === 'continuous' && taskData.end_date && taskData.start_date) {
                const periodDays = {
                    daily: 1,
                    weekly: 7,
                    monthly: 30
                };
                const minDays = periodDays[taskData.period_type] || 1;
                const startMs = new Date(taskData.start_date).getTime();
                const endMs = new Date(taskData.end_date).getTime();
                const diffDays = (endMs - startMs) / 864e5;
                if (diffDays < minDays) {
                    showToast(`تاریخ پایان باید حداقل ${enTofaNumber(minDays)} روز بعد از تاریخ شروع باشد`, 'error');

                    return;
                }
            }
            try {
                const response = await fetch('../api/tasks/create.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify(taskData)
                });

                // ✅ اول متن خام را بگیرید
                const responseText = await response.text();

                // ✅ حالا JSON را parse کنید
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    throw new Error('پاسخ سرور JSON معتبر نیست: ' + responseText.substring(0, 100));
                }

                if (data.success) {
                    const taskId = data.taskId || data.task_id || data.id || null;

                    if (taskId && pendingFiles.length > 0) {
                        await uploadPendingFiles(taskId);
                    }

                    // 🆕 ذخیره چک‌لیست (اگر آیتمی هست)
                    if (taskId && checklistItemsCreate.length > 0) {
                        await saveChecklistItems(taskId);
                    }

                    return taskId;
                } else {
                    showToast('خطا در ایجاد کار', 'error');
                    return null; // برای حالت خطا
                }


            } catch (error) {
                showToast('خطا در ارتباط با سرور: ' + error.message, 'error');

            }
        }
        // 🆕 ذخیره دسته‌ای آیتم‌های چک‌لیست بعد از ساخت کار
        async function saveChecklistItems(taskId) {
            try {
                await fetch('../api/checklist/save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        items: checklistItemsCreate.map((it, i) => ({
                            title: it.title,
                            description: it.description || '',
                            sort_order: i
                        }))
                    })
                });
            } catch (e) {
                console.error('saveChecklistItems error:', e);
            }
        }

        // ─────────────── واگذاری به «چند نفرِ خاص» — از همون AssigneePicker،
        // فقط با یک نمونهٔ دومِ multiSelect:true (زیبایی/رفتارِ یکسان با بالا) ───────────────
        function toggleMultiAssigneeMode() {
            const on = document.getElementById('multiAssigneeToggle').checked;
            document.getElementById('assigneePicker').style.display = on ? 'none' : '';
            document.getElementById('multiAssigneeBox').style.display = on ? '' : 'none';
        }

        async function saveTaskForMultipleUsers() {
            const taskType = document.getElementById('manualTaskType').value;
            const dueDateInput = document.getElementById('manualDueDate');
            const startDateInput = document.getElementById('manualStartDate');

            const multiVal = multiAssigneePickerInst ? multiAssigneePickerInst.getValue() : null;
            const assigneeIds = multiVal ? multiVal.value : [];
            if (!assigneeIds.length) {
                showToast('لطفاً حداقل یک نفر را انتخاب کنید', 'warning');
                return null;
            }

            const baseTask = {
                title: document.getElementById('manualTitle').value.trim(),
                description: document.getElementById('manualDescription').value.trim(),
                task_type: taskType,
                priority: document.getElementById('manualPriority').value,
                group_id: document.getElementById('taskGroupSelect')?.value || null,
                share_history: document.getElementById('shareHistoryToggle')?.checked ? 1 : 0
            };

            if (!baseTask.title) {
                showToast('عنوان کار الزامی است', 'error');
                return null;
            }

            if (taskType === 'periodic') {
                baseTask.due_date = dueDateInput.getAttribute('data-date') || null;
                baseTask.start_date = null;
                baseTask.period_type = null;
                if (!baseTask.due_date) {
                    showToast('تاریخ انجام برای کارهای مقطعیِ ارجاع‌داده‌شده الزامی است', 'error');
                    return null;
                }
            } else {
                baseTask.start_date = startDateInput.getAttribute('data-date') || null;
                baseTask.period_type = document.getElementById('manualPeriod').value || null;
                baseTask.due_date = null;
                baseTask.end_date = document.getElementById('manualEndDate').getAttribute('data-date') || null;
                if (!baseTask.start_date) {
                    showToast('تاریخ شروع برای کارهای دوره‌ای الزامی است', 'error');
                    return null;
                }
            }

            try {
                const response = await fetch('../api/tasks/create-bulk.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({ base_task: baseTask, assignee_ids: assigneeIds })
                });

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch {
                    throw new Error('پاسخ سرور JSON معتبر نیست');
                }

                if (data.success) {
                    showToast(`${data.created_count} تسک با موفقیت ایجاد شد`, 'success');
                    return data.first_task_id || 1;
                } else {
                    showToast(data.message || 'خطا در ایجاد تسک‌ها', 'error');
                    return null;
                }
            } catch (err) {
                showToast('خطا در ارتباط با سرور: ' + err.message, 'error');
                return null;
            }
        }

        // ذخیره تسک برای یک واحد یا همه واحدها
        async function saveTaskForSection(sectionKey) {
            const taskType = document.getElementById('manualTaskType').value;
            const dueDateInput = document.getElementById('manualDueDate');
            const startDateInput = document.getElementById('manualStartDate');

            // ساخت پایه taskData (بدون assignee_id)
            const baseTask = {
                title: document.getElementById('manualTitle').value.trim(),
                description: document.getElementById('manualDescription').value.trim(),
                task_type: taskType,
                priority: document.getElementById('manualPriority').value,
                group_id: document.getElementById('taskGroupSelect')?.value || null,
                share_history: document.getElementById('shareHistoryToggle')?.checked ? 1 : 0
            };

            if (!baseTask.title) {
                showToast('عنوان کار الزامی است', 'error');
                return null;
            }

            if (taskType === 'periodic') {
                baseTask.due_date = dueDateInput.getAttribute('data-date') || null;
                baseTask.start_date = null;
                baseTask.period_type = null;
                if (!baseTask.due_date) {
                    showToast('تاریخ انجام برای کارهای مقطعی الزامی است', 'error');
                    return null;
                }
            } else {
                baseTask.start_date = startDateInput.getAttribute('data-date') || null;
                baseTask.period_type = document.getElementById('manualPeriod').value || null;
                baseTask.due_date = null;
                baseTask.end_date = document.getElementById('manualEndDate').getAttribute('data-date') || null;
                if (!baseTask.start_date) {
                    showToast('تاریخ شروع برای کارهای دوره‌ای الزامی است', 'error');
                    return null;
                }
            }

          // تعیین لیست assignee ها
            //  • «همه واحدها» → لیست را فرانت می‌فرستد
            //  • یک واحد خاص → فقط section_key؛ بک‌اند کاربرانِ همهٔ واحدها را پیدا می‌کند
            //    (تا کاربرِ چندواحدی که این واحد، واحدِ دومش است هم بیفتد)
            let bulkBody = { base_task: baseTask };

            if (sectionKey === '__all__') {
                const targetUsers = users.map(u => u.id);
                if (targetUsers.length === 0) {
                    showToast('هیچ کاربری یافت نشد', 'warning');
                    return null;
                }
                bulkBody.assignee_ids = targetUsers;
            } else {
                bulkBody.section_key = sectionKey;
            }

            try {
                const response = await fetch('../api/tasks/create-bulk.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify(bulkBody)
                });

                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch {
                    throw new Error('پاسخ سرور JSON معتبر نیست');
                }

                if (data.success) {
                    const label = sectionKey === '__all__' ?
                        'همه واحدها' :
                        (sections.find(s => s.section_key === sectionKey)?.section_label || sectionKey);
                    showToast(`${data.created_count} تسک برای ${label} با موفقیت ایجاد شد`, 'success');
                    return data.first_task_id || 1; // مقدار truthy برای saveTask
                } else {
                    showToast(data.message || 'خطا در ایجاد تسک‌ها', 'error');
                    return null;
                }
            } catch (err) {
                showToast('خطا در ارتباط با سرور: ' + err.message, 'error');
                return null;
            }
        }
        // تغییر حالت بین دستی و روتین
        function toggleTaskMode(mode) {
            const manualSection = document.getElementById('manualSection');
            const routineSection = document.getElementById('routineSection');
            const routineHint = document.getElementById('routineHint');

            if (mode === 'routine') {
                manualSection.style.display = 'none';
                routineSection.style.display = 'block';
                routineHint.style.display = 'block';
            } else {
                manualSection.style.display = 'block';
                routineSection.style.display = 'none';
                routineHint.style.display = 'none';
            }
        }




        // نمایش مراحل روتین هنگام انتخاب
        document.addEventListener('DOMContentLoaded', function() {
            const isWorkflowTask = document.getElementById('isWorkflowTask');
            loadWorkflowTemplates();

            isWorkflowTask.addEventListener('change', async function() {
                const routineId = this.value;
                const previewDiv = document.getElementById('routineStepsPreview');
                const stepsList = document.getElementById('stepsList');

                if (!routineId) {
                    previewDiv.style.display = 'none';
                    return;
                }

                try {
                    const response = await fetch(`../api/admin/get-routine-steps.php?routine_id=${routineId}`);
                    const data = await response.json();

                    if (data.success && data.steps.length > 0) {
                        stepsList.innerHTML = '';
                        data.steps.forEach(step => {
                            const li = document.createElement('li');
                            li.textContent = `${step.title} - واحد: ${step.activity_section} (${enTofaNumber(step.duration_days)} روز)`;
                            stepsList.appendChild(li);
                        });
                        previewDiv.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Error loading steps:', error);
                }
            });
        });


        // ذخیره کار روتین
        async function saveRoutineTask() {
            const routineId = document.getElementById('isWorkflowTask').value;
            const title = document.getElementById('routineTitle').value.trim();


            if (!routineId) {
                const t = showToast('لطفاً یک کار روتین انتخاب کنید', 'info');
                t.close();
                return;
            }

            if (!title) {
                const t = showToast('عنوان/موضوع کار الزامی است', 'error');
                t.close();
                return;
            }

            const saveBtn = document.querySelector('#newTaskModal .btn-primary');
            const btnText = saveBtn.querySelector('.btn-text');
            const originalText = btnText.textContent;

            btnText.textContent = 'در حال ذخیره...';
            saveBtn.disabled = true;

            try {
                const apiUrl = getApiUrl('/routines/create-instance.php');

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        routine_id: routineId,
                        title: title
                    })
                });


                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }

                const data = await response.json();

                if (data.success) {
                    const t = showToast(`✅ کار روتین ایجاد شد! ${data.tasks_created} وظیفه برای واحدهای مختلف ثبت شد.`, 'success');
                    t.close();

                    // بستن مودال
                    const modal = bootstrap.Modal.getInstance(document.getElementById('newTaskModal'));
                    modal.hide();

                    // ریست فرم
                    document.getElementById('newTaskForm').reset();
                    document.getElementById('taskTypeSelect').value = 'manual';
                    toggleRoutineFields();
                    document.getElementById('routineStepsPreview').style.display = 'none';

                    // بروزرسانی داده‌ها
                    setTimeout(() => {
                        loadDashboardData();
                    }, 500);
                } else {
                    const t = showToast('خطا در ایجاد کار روتین', 'error');
                    t.close();
                }
            } catch (error) {
                const t = showToast('خطا در ارتباط با سرور', 'error');
                t.close();
            } finally {
                btnText.textContent = originalText;
                saveBtn.disabled = false;
            }
        }
        // تغییر نمایش فیلدها بین روتین و دستی
        function toggleRoutineFields() {
            const taskType = document.getElementById('taskTypeSelect').value;
            const routineContainer = document.getElementById('routineFieldsContainer');
            const manualContainer = document.getElementById('manualFieldsContainer');


            if (taskType === 'routine') {
                routineContainer.style.display = 'block';
                manualContainer.style.display = 'none';
            } else {
                routineContainer.style.display = 'none';
                manualContainer.style.display = 'block';
            }
        }

        let pendingFiles = [];

        function setupCreateTaskUpload() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            if (!uploadArea || !fileInput) return;

            uploadArea.addEventListener('click', (e) => {
                if (e.target.id !== 'fileInput') fileInput.click();
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    addPendingFiles(fileInput.files);
                    fileInput.value = '';
                }
            });

            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#0d6efd';
                uploadArea.style.background = '#f0f4ff';
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.style.borderColor = '#ccc';
                uploadArea.style.background = '';
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#ccc';
                uploadArea.style.background = '';
                addPendingFiles(e.dataTransfer.files);
            });
        }

        function addPendingFiles(files) {
            const maxSize = 20 * 1024 * 1024;
            for (let file of files) {
                if (file.size > maxSize) {
                    showToast(`فایل "${file.name}" بیش از ${enTofaNumber(20)}MB است و اضافه نشد.`, 'warning');
                    continue;
                }
                pendingFiles.push(file);
            }
            renderPendingFiles();
        }

        function renderPendingFiles() {
            const list = document.getElementById('selectedFilesList');
            if (!list) return;

            if (pendingFiles.length === 0) {
                list.innerHTML = '';
                return;
            }

            let html = '';
            pendingFiles.forEach((file, index) => {
                html += `
            <div class="d-flex justify-content-between align-items-center mb-1 p-2 border rounded">
                <span><i class="bi bi-paperclip me-1"></i>${file.name}</span>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="removePendingFile(${index})">حذف</button>
            </div>`;
            });
            list.innerHTML = html;
        }

        function removePendingFile(index) {
            pendingFiles.splice(index, 1);
            renderPendingFiles();
        }

        async function uploadPendingFiles(taskId) {
            for (let file of pendingFiles) {
                const formData = new FormData();
                formData.append('task_id', taskId);
                formData.append('file', file);

                try {
                    const response = await fetch('../api/tasks/upload-attachment.php', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + authToken
                        },
                        body: formData
                    });
                    const data = await response.json();
                    if (!data.success) {
                        console.error(`خطا در آپلود222222 ${file.name}:`, data.message);
                    }
                } catch (err) {
                    // try {
                    //     await fetch('log.php', {
                    //         method: 'POST',
                    //         headers: { 'Content-Type': 'application/json' },
                    //         body: JSON.stringify({ myVar: err })
                    //     });
                    // } catch (err) {
                    //     console.error('Failed to log taskId:', err);
                    // }
                    console.error(`خطا در آپلود ${file.name}:`, err);
                }
            }
            pendingFiles = [];
        }

        let workflowPendingFiles = [];

        function setupWorkflowUpload() {
            const uploadArea = document.getElementById('workflowUploadArea');
            const fileInput = document.getElementById('workflowFileInput');
            if (!uploadArea || !fileInput || uploadArea._initialized) return;
            uploadArea._initialized = true;

            uploadArea.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    addWorkflowFiles(fileInput.files);
                    fileInput.value = '';
                }
            });
            uploadArea.addEventListener('dragover', e => {
                e.preventDefault();
                uploadArea.style.borderColor = '#0d6efd';
            });
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.style.borderColor = '#ccc';
            });
            uploadArea.addEventListener('drop', e => {
                e.preventDefault();
                uploadArea.style.borderColor = '#ccc';
                addWorkflowFiles(e.dataTransfer.files);
            });
        }

        function addWorkflowFiles(files) {
            const maxSize = 20 * 1024 * 1024;
            for (let file of files) {
                if (file.size > maxSize) {
                    showToast(`فایل "${file.name}" بیش از ${enTofaNumber(20)}MB است.`, 'warning');
                    continue;
                }
                workflowPendingFiles.push({
                    file,
                    visible_to_steps: []
                });
            }
            renderWorkflowFiles();
        }

        function renderWorkflowFiles() {
            const list = document.getElementById('workflowFilesList');
            if (!list) return;
            if (workflowPendingFiles.length === 0) {
                list.innerHTML = '';
                return;
            }

            const sections = Object.entries(acticity_section);
            let html = '';
            workflowPendingFiles.forEach((item, index) => {
                const checksHtml = (selectedTemplate?.steps || []).map((step, si) => `
    <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox"
            id="vis_${index}_${step.id}" value="${step.id}"
            onchange="toggleFileVisibility(${index}, ${step.id}, this.checked)">
        <label class="form-check-label" for="vis_${index}_${step.id}">
            مرحله ${si + 1}: ${step.step_name}
        </label>
    </div>`).join('');

                html += `
            <div class="border rounded p-2 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-paperclip me-1"></i>${item.file.name}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="removeWorkflowFile(${index})">حذف</button>
                </div>
                <div class="mt-2">
                    <small class="text-muted d-block mb-1">نمایش برای:</small>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="vis_${index}_public"
                            ${item.visible_to_steps.length === 0 ? 'checked' : ''}
                            onchange="togglePublicVisibility(${index}, this.checked)">
                        <label class="form-check-label" for="vis_${index}_public">همه (عمومی)</label>
                    </div>
                    ${checksHtml}
                </div>
            </div>`;
            });
            list.innerHTML = html;
        }

        function toggleFileVisibility(index, stepId, checked) {
            const item = workflowPendingFiles[index];
            if (checked) {
                if (!item.visible_to_steps.includes(stepId)) item.visible_to_steps.push(stepId);
                const pub = document.getElementById(`vis_${index}_public`);
                if (pub) pub.checked = false;
            } else {
                item.visible_to_steps = item.visible_to_steps.filter(s => s !== stepId);
                if (item.visible_to_steps.length === 0) {
                    const pub = document.getElementById(`vis_${index}_public`);
                    if (pub) pub.checked = true;
                }
            }
        }

        function togglePublicVisibility(index, checked) {
            if (checked) {
                workflowPendingFiles[index].visible_to_steps = [];
                (selectedTemplate?.steps || []).forEach(step => {
                    const cb = document.getElementById(`vis_${index}_${step.id}`);
                    if (cb) cb.checked = false;
                });
            }
        }

        function removeWorkflowFile(index) {
            workflowPendingFiles.splice(index, 1);
            renderWorkflowFiles();
        }

        // 🆕 تابع مستقل برای ذخیره توضیحات مراحل
        async function saveStepDescriptions(instanceId) {
            if (!instanceId || !selectedTemplate || !selectedTemplate.steps) return;

            const stepDescs = selectedTemplate.steps.map((step, index) => ({
                step_id: step.id,
                description: (document.getElementById(`step_desc_${index}`)?.value || '').trim()
            })).filter(s => s.description);

            if (stepDescs.length > 0) {
                await fetch('../api/workflows/save-step-descriptions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: JSON.stringify({
                        instance_id: instanceId,
                        steps: stepDescs
                    })
                });
            }
        }

        async function uploadWorkflowFiles(instanceId) {
            for (let item of workflowPendingFiles) {
                const formData = new FormData();
                formData.append('task_id', instanceId);
                formData.append('file', item.file);
                formData.append('step_ids', item.visible_to_steps.length === 0 ? '' : JSON.stringify(item.visible_to_steps));
                console.log('instanceId:', instanceId, '| step_ids:', item.visible_to_steps, '| visible_to_steps raw:', JSON.stringify(item.visible_to_steps));

                await fetch('../api/tasks/upload-attachment.php', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + authToken
                    },
                    body: formData
                });
            }
            workflowPendingFiles = [];
        }
    </script>
    <script src="<?= asset('../assets/js/deadline-toast.js') ?>"></script>

</body>

</html>