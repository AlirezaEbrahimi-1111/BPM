<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../config/config.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران</title>

    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../../assets/js/ag-grid-community.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <script src="<?= asset('../../assets/js/sections-helper.js') ?>"></script> 
    <style>
        /* ── صفحه ── */
        body { background: #f4f6f9; }

        .users-wrap {
            padding: 1.2rem 1.4rem;
        }

        /* ── هدر صفحه ── */
        .page-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .6rem;
            margin-bottom: 1rem;
        }
        .page-header-bar h1 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #3d3d6b;
            margin: 0;
        }
        .page-header-bar h1 i { color: #7c3aed; }

        /* ── نوار فیلتر ── */
        .filters-bar {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: .6rem .85rem;
            margin-bottom: .85rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }
        .filters-bar .form-control,
        .filters-bar .form-select {
            font-size: .82rem;
            border-radius: 7px;
            max-width: 160px;
            height: 34px;
            padding: .25rem .55rem;
        }
        .filters-bar .btn { height: 34px; font-size: .82rem; border-radius: 7px; }
        .filters-bar .users-count {
            margin-right: auto;
            font-size: .8rem;
            color: #6b7280;
        }

        /* ── badge‌های جدول ── */
        .role-badge {
            display: inline-block;
            font-size: .7rem;
            padding: .18em .55em;
            border-radius: 20px;
            font-weight: 700;
            white-space: nowrap;
        }
        .role-employee   { color:#166534; }
        .role-manager    { color:#1e40af; }
        .role-supervisor { color:#5b21b6; }
        .role-admin      { color:#991b1b; }

        .status-badge {
            display: inline-block;
            font-size: .7rem;
            padding: .18em .55em;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-active   { color:#065f46;padding:0 !important; }
        .status-inactive { color:#6b7280;padding:0 !important; }

        .unit-badge {
            display: inline-block;
            font-size: .68rem;
            padding: .15em .45em;
            border-radius: .25rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
            margin: 1px;
            font-weight: 600;
        }
        .unit-badge.primary { background:#fef9c3; border-color:#fbbf24; color:#78350f; }

        /* ── مودال چندتب ── */
        .modal-header-custom {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #fff;
            border-radius: .4rem .4rem 0 0;
            padding: .9rem 1.1rem;
        }
        .modal-tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
            padding: 0 1rem;
        }
        .modal-tab {
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            padding: .6rem .85rem;
            font-size: .82rem;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: .3rem;
            transition: color .15s, border-color .15s;
            margin-bottom: -2px;
            white-space: nowrap;
        }
        .modal-tab:hover { color: #4f46e5; }
        .modal-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; font-weight: 600; }

        .unit-card {
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            padding: .6rem .8rem;
            background: #fafafa;
            transition: border-color .15s, background .15s;
        }
        .unit-card.selected { border-color: #4f46e5; background: #eef2ff; }

        .access-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: .7rem .9rem;
            background: #fff;
        }
        .form-check-input:checked { background-color: #4f46e5; border-color: #4f46e5; }

        .hierarchy-node {
            display: flex;
            align-items: center;
            padding: .45rem .8rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            font-size: .84rem;
            margin-bottom: .25rem;
            gap: .5rem;
        }
        .h-manager { background:#eef2ff; border-color:#c7d2fe; }
        .h-current  { background:#dbeafe; border-color:#93c5fd; font-weight:600; }
        .h-sub      { background:#f0fdf4; border-color:#86efac; }
        .hierarchy-line { width: 2px; height: 12px; background: #c7d2fe; margin: 0 1.3rem; }

        /* ── آلرت ── */
        #alertContainer .alert { border-radius: 8px; font-size: .875rem; }

        /* ── ag-Grid action cell ── */
        .ag-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #fff;
            cursor: pointer;
            font-size: .8rem;
            transition: background .12s, border-color .12s;
            margin: 0 2px;
        }
        .ag-action-btn:hover { background: #f3f4f6; border-color: #9ca3af; }
        .ag-action-btn.edit   { color: #4f46e5; }
        .ag-action-btn.toggle-on  { color: #d97706; }
        .ag-action-btn.toggle-off { color: #16a34a; }

        @media (max-width: 768px) {
            .filters-bar .form-control,
            .filters-bar .form-select { max-width: 130px; }
            .users-wrap { padding: .8rem; }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="overview-container" style="margin-top:70px">

    <div id="alertContainer" class="mb-3"></div>

    <!-- هدر صفحه -->
    <div class="page-header-bar">
        <h1><i class="bi bi-people"></i> مدیریت کاربران</h1>
        <div class="d-flex gap-2">
            <button class="btn btn-success btn-sm" onclick="openCreateModal()">
                <i class="bi bi-person-plus ms-1"></i>کاربر جدید
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="loadUsers()">
                <i class="bi bi-arrow-clockwise ms-1"></i>بروزرسانی
            </button>
        </div>
    </div>

    <!-- فیلترها -->
    <div class="filters-bar">
        <input type="text" class="form-control" id="searchInput" placeholder="جستجو: نام، موبایل...">
        <select class="form-select" id="filterRole">
            <option value="">همه نقش‌ها</option>
            <option value="employee">کارمند</option>
            <option value="supervisor">سوپروایزر</option>
        </select>
        <select class="form-select" id="filterSection">
            <option value="">همه بخش‌ها</option>
        </select>
        <select class="form-select" id="filterStatus">
            <option value="">همه وضعیت‌ها</option>
            <option value="1">فعال</option>
            <option value="0">غیرفعال</option>
        </select>
        <button class="btn btn-primary" onclick="applyFilters()">
            <i class="bi bi-funnel"></i>
        </button>
        <button class="btn btn-outline-secondary" onclick="resetFilters()">
            <i class="bi bi-x-lg"></i>
        </button>
        <span class="users-count" id="usersCount"></span>
    </div>

    <!-- جدول -->
    <div id="usersGrid" class="ag-theme-alpine" style="height:600px; width:100%;"></div>
</div>

<!-- ════ MODAL: کاربر جدید ════ -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header-custom modal-header border-0">
                <h5 class="modal-title text-white"><i class="bi bi-person-plus ms-2"></i>کاربر جدید</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="createModalAlert"></div>
                <form id="createUserForm" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">نام <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="c_first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نام خانوادگی <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="c_last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">موبایل / نام کاربری <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="c_phone" required maxlength="11" placeholder="09xxxxxxxxx">
                            <div class="form-text">این مقدار هم به عنوان موبایل و هم نام کاربری ثبت می‌شود</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">رمز عبور <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="c_password" required minlength="4">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">تکرار رمز <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="c_password_confirm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="c_email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">بخش فعالیت <span class="text-danger">*</span></label>
                            <select class="form-select" id="c_activity_section" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">نقش <span class="text-danger">*</span></label>
                            <select class="form-select" id="c_role" required>
                                <option value="employee" selected>کارمند</option>
                                <option value="supervisor">سوپروایزر</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0 small">
                        <i class="bi bi-info-circle ms-1"></i>
                        پس از ایجاد کاربر می‌توانید شیفت و دسترسی‌ها را از دکمه ویرایش تنظیم کنید.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="createUser()">
                    <i class="bi bi-save ms-1"></i>ایجاد کاربر
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════ MODAL: ویرایش کاربر (چند تب) ════ -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header-custom modal-header border-0">
                <h5 class="modal-title text-white">
                    <i class="bi bi-pencil-square ms-2"></i>
                    ویرایش: <span id="editModalName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-tabs">
                <button class="modal-tab active" onclick="switchTab('info',this)"><i class="bi bi-person"></i> اطلاعات پایه</button>
<button class="modal-tab" onclick="switchTab('shift',this)"><i class="bi bi-clock"></i> شیفت کاری</button>
<button class="modal-tab" onclick="switchTab('hierarchy',this)"><i class="bi bi-diagram-3"></i> سلسله مراتب و دسترسی‌ها</button>
            </div>

            <input type="hidden" id="editingUserId">
            <div class="modal-body pt-0">

                <!-- آلرت داخل مودال -->
                <div id="modalAlertContainer"></div>
                <!-- تب ۱ -->
                <div id="tab-info" class="tab-pane">
                    <div class="row g-3 pt-3">
                        <div class="col-md-6"><label class="form-label">نام *</label><input type="text" class="form-control" id="e_first_name"></div>
                        <div class="col-md-6"><label class="form-label">نام خانوادگی *</label><input type="text" class="form-control" id="e_last_name"></div>
                        <div class="col-md-6">
                        <label class="form-label">موبایل / نام کاربری *</label>
                        <input type="text" class="form-control" id="e_phone" maxlength="11" placeholder="09xxxxxxxxx">
                        <div class="form-text">این مقدار هم به عنوان موبایل و هم نام کاربری ذخیره می‌شود</div>
                    </div>
                    <div class="col-md-6"><label class="form-label">ایمیل</label><input type="email" class="form-control" id="e_email"></div>
                        <div class="col-md-6">
                            <label class="form-label">وضعیت</label>
                            <select class="form-select" id="e_is_active">
                                <option value="1">فعال</option><option value="0">غیرفعال</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">آخرین ورود</label>
                            <input type="text" class="form-control bg-light" id="e_last_login" readonly style="direction:ltr; text-align:left;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">بخش فعالیت</label>
                            <select class="form-select" id="e_activity_section"></select>
                        </div>
                        <div class="col-12"><hr class="my-1"><small class="text-muted">تغییر رمز — خالی بگذارید تا تغییر نکند</small></div>
                        <div class="col-md-6"><label class="form-label">رمز جدید</label><input type="password" class="form-control" id="e_password" minlength="4" placeholder="خالی = بدون تغییر"></div>
                        <div class="col-md-6"><label class="form-label">تکرار رمز</label><input type="password" class="form-control" id="e_password_confirm" placeholder="خالی = بدون تغییر"></div>
                    </div>
                </div>

                

<!-- تب شیفت -->
<div id="tab-shift" class="tab-pane" style="display:none">
    <div class="row g-3 pt-3">
        <div class="col-md-6">
            <label class="form-label">نوع شیفت</label>
            <select class="form-select" id="e_shift_type" onchange="toggleShift2()">
                <option value="single">تک شیفت</option>
                <option value="double">دو شیفت</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">ساعت کاری روزانه</label>
            <input type="number" class="form-control bg-light" id="e_daily_work_hours" readonly>
            <div class="form-text">محاسبه خودکار از ساعات شیفت</div>
        </div>

        <div class="col-12"><hr class="my-1"><strong class="small">شیفت اول</strong></div>
        <div class="col-md-6">
            <label class="form-label">شروع</label>
            <input type="time" class="form-control" id="e_shift1_start" oninput="calcDailyHours()">
        </div>
        <div class="col-md-6">
            <label class="form-label">پایان</label>
            <input type="time" class="form-control" id="e_shift1_end" oninput="calcDailyHours()">
        </div>

        <div id="shift2Block" style="display:none" class="col-12">
            <div class="row g-3">
                <div class="col-12"><hr class="my-1"><strong class="small">شیفت دوم</strong></div>
                <div class="col-md-6">
                    <label class="form-label">شروع</label>
                    <input type="time" class="form-control" id="e_shift2_start" oninput="calcDailyHours()">
                </div>
                <div class="col-md-6">
                    <label class="form-label">پایان</label>
                    <input type="time" class="form-control" id="e_shift2_end" oninput="calcDailyHours()">
                </div>
            </div>
        </div>

        <div class="col-12"><hr class="my-1"><strong class="small">اطلاعات مالی</strong></div>
        <div class="col-md-6">
            <label class="form-label">حقوق ماهانه (تومان)</label>
            <input type="text" class="form-control bg-light" id="e_monthly_salary" readonly>
        </div>
    </div>
</div>

                

              <div id="tab-hierarchy" class="tab-pane" style="display:none">
    <div class="pt-3">
        <!-- بخش مدیر مستقیم -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">مدیر مستقیم</label>
                <select class="form-select" id="e_manager_id"></select>
            </div>
            <div class="col-md-6">
                <label class="form-label">نقش</label>
                <select class="form-select" id="e_role">
                    <option value="employee">کارمند</option>
                    <option value="manager">مدیر</option>
                    <option value="supervisor">سوپروایزر</option>
                </select>
            </div>
        </div>
        <div id="hierarchyTree" class="mb-4"></div>

        <hr>

        <!-- بخش دسترسی‌ها -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="access-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold small">دسترسی ایجاد قالب روتین</div>
                            <div class="text-muted" style="font-size:.75rem">کاربر می‌تواند قالب کار روتین ایجاد کند</div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="e_can_create_routine" role="switch">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="access-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold small">دسترسی تعریف کار روتین</div>
                            <div class="text-muted" style="font-size:.75rem">کاربر می‌تواند کار روتین تعریف کند</div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="e_can_create_workflow" role="switch">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">انصراف</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveUser()">
                    <i class="bi bi-save ms-1"></i>ذخیره تغییرات
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('../../assets/js/table-utils.js') ?>"></script>
<script src="<?= asset('/assets/js/undo-toast.js') ?>"></script>

<script>

'use strict';
// ── بخش‌های فعالیت ──
let orgSections = [];

// جدید:
function buildSectionFilterOptions() {
    fillSectionFilter('filterSection');
}
/* ── ثابت‌ها ── */
const PAGE_SIZE  = 20;
const UNIT_LIST  = ['RS','ATM','AM','AC','PR','HE'];
const UNIT_NAMES = { RS:'کامپیوتر', ATM:'فضای مجازی', AM:'نوجوانان', AC:'حسابداری', PR:'روابط عمومی', HE:'تربیتی' };
const ROLE_NAMES = { employee:'کارمند', manager:'مدیر', supervisor:'سوپروایزر', admin:'ادمین' };

/* ── state ── */
let allUsers = [], filteredUsers = [], managers = [];
let editModalInst, createModalInst;
let gridApi = null;
let searchTimeout;
/* ── helpers ── */
function tok()  { return localStorage.getItem('auth_token'); }
function ah()   { return { 'Authorization': 'Bearer ' + tok() }; }
function ahj()  { return { 'Content-Type':'application/json', 'Authorization':'Bearer ' + tok() }; }

/* ── init ── */
document.addEventListener('DOMContentLoaded', async () => {
    if (!tok()) return;

    try {
        const r = await fetch('/api/admin/me.php', { headers: ah() });
        const d = await r.json();
        
        if (!d.success) return;
        if (!['admin','supervisor'].includes(d.user.role)) return;

        editModalInst   = new bootstrap.Modal(document.getElementById('editUserModal'));
        createModalInst = new bootstrap.Modal(document.getElementById('createUserModal'));

        await loadSections();        // ← الان درست کار می‌کنه
        await loadSectionMap();        // ← اضافه کن

        buildSectionFilterOptions();
        buildSectionOptionsForCreate();
        initGrid();
        loadUsers();

    } catch (err) {
        console.error('init error:', err);
    }

    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilters, 300);
    });
});
function buildSectionOptionsForCreate() {
    fillSectionSelect('c_activity_section');
}

async function loadSections() {
    try {
        const res = await fetch('/api/organization/activity-sections.php', {
            headers: { 'Authorization': 'Bearer ' + authToken }
        });
        const data = await res.json();
        if (data.success) orgSections = data.sections;
    } catch {}
}
function buildSectionOptions(selectedValue = '') {
    let opts = '<option value="">انتخاب کنید</option>';
    opts += `<option value="management" ${selectedValue==='management'?'selected':''}>مدیریت</option>`;
    orgSections.forEach(s => {
        opts += `<option value="${s.section_key}" ${selectedValue===s.section_key?'selected':''}>${s.section_label}</option>`;
    });
    return opts;
}
function showCreateAlert(msg, type = 'danger') {
    const c = document.getElementById('createModalAlert');
    if (!c) return;
    c.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show small mt-2" role="alert">
        <i class="bi bi-exclamation-triangle ms-2"></i>${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    setTimeout(() => c.querySelector('.alert')?.remove(), 5000);
}
function showModalAlert(msg, type = 'danger') {
    const c = document.getElementById('modalAlertContainer');
    if (!c) return;
    c.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show small mt-2" role="alert">
        <i class="bi bi-exclamation-triangle ms-2"></i>${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    setTimeout(() => c.querySelector('.alert')?.remove(), 4000);
}
function calcDailyHours() {
    function toMin(t) {
        if (!t) return 0;
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }
    const s1 = document.getElementById('e_shift1_start').value;
    const e1 = document.getElementById('e_shift1_end').value;
    const s2 = document.getElementById('e_shift2_start').value;
    const e2 = document.getElementById('e_shift2_end').value;

    let total = 0;
    if (s1 && e1) total += Math.max(0, toMin(e1) - toMin(s1));
    if (s2 && e2) total += Math.max(0, toMin(e2) - toMin(s2));

    document.getElementById('e_daily_work_hours').value = total > 0 ? Math.round(total / 60) : '';
}
/* ── ag-Grid setup ── */
function initGrid() {
    const colDefs = [
        {
            headerName:'ردیف', width:65, sortable:false,
            valueGetter: p => p.node.rowIndex + 1, 
            cellRenderer: p => toFa(p.value),
        },
        { field:'first_name', headerName:'نام', width:180, sortable:true },
        { field:'last_name',  headerName:'نام خانوادگی',width:180, sortable:true },
        { field:'phone', headerName:'موبایل', width:180, cellRenderer: p => toFa(p.value) },
        {
            field:'role', headerName:'نقش', width:130, sortable:true,
            cellRenderer: p => `<span class="role-badge role-${p.value}">${ROLE_NAMES[p.value]||p.value}</span>`
        },
        {
            field:'activity_section', headerName:'بخش', width:130,
            cellRenderer: p => `<small>${getSectionLabel(p.value)}</small>`
        },
        {
            field:'created_at', headerName:'ایجاد', width:105, sortable:true,
            cellRenderer: p => p.value ? `<small>${toFa(new Date(p.value).toLocaleDateString('fa-IR'))}</small>` : '—'
        },
        {
            field:'is_active', headerName:'وضعیت', width:90,
            cellRenderer: p => p.value==1
                ? '<span class="status-badge status-active">فعال</span>'
                : '<span class="status-badge status-inactive">غیرفعال</span>'
        },
        {
            field:'last_login', headerName:'آخرین ورود', width:130, sortable:true,
            cellRenderer: p => p.value
                ? `<small>${toFa(new Date(p.value).toLocaleDateString('fa-IR', { timeZone:'Asia/Tehran' }))}</small>`
                : '<small style="color:#9ca3af">—</small>'
        },
        {
 headerName:'عملیات', width:150, sortable:false,
cellRenderer: p => `
    <button class="ag-action-btn edit" title="ویرایش" onclick="openEditModal(${p.data.id})">
        <i class="bi bi-pencil"></i>
    </button>
    <button class="ag-action-btn ${p.data.is_active==1?'toggle-on':'toggle-off'}"
            title="${p.data.is_active==1?'غیرفعال کردن':'فعال کردن'}"
            onclick="toggleStatus(${p.data.id},${p.data.is_active})">
        <i class="bi bi-${p.data.is_active==1?'pause':'play'}-circle"></i>
    </button>
    <button class="ag-action-btn" style="color:#dc2626" title="حذف کاربر"
            onclick="deleteUser(${p.data.id})">
        <i class="bi bi-trash"></i>
    </button>`
        },
    ];

    const opts = {
        theme: agGrid.themeQuartz.withParams({
            fontFamily: "'Vazirmatn', Tahoma, sans-serif",
            fontSize: 13,
            rowHoverColor: '#f5f3ff',
            headerBackgroundColor: '#f9fafb',
        }),
        columnDefs: colDefs,
        rowData: [],
        enableRtl: true,
        animateRows: true,
        pagination: true,
        paginationPageSize: PAGE_SIZE,
        paginationPageSizeSelector: [10, 20, 50, 100],
        defaultColDef: { resizable: true },
        overlayNoRowsTemplate: '<span class="text-muted">کاربری یافت نشد</span>',
        overlayLoadingTemplate: '<span class="text-muted">در حال بارگذاری...</span>',
        onPaginationChanged: () => {
                    setTimeout(() => {
                        // فارسی کردن اعداد و متن‌ها
                        document.querySelectorAll('.ag-paging-panel span, .ag-paging-panel button').forEach(el => {
                            if (el.childElementCount === 0 && !el.classList.contains('injected-az')) {
                                el.textContent = el.textContent
                                    .replace(/Page/g, 'صفحه')
                                    .replace(/\bof\b/g, 'از')
                                    .replace(/\bto\b/g, 'تا')
                                    .replace(/\d+/g, n => n.replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]));
                            }
                        });

                        // حذف span های تنها «از» که بیرون از summary پنل هستند
                        document.querySelectorAll('.ag-paging-panel > span, .ag-paging-panel > div:not(.ag-paging-row-summary-panel):not(.ag-paging-page-size):not(.ag-paging-button-wrapper):not(.ag-paging-page-summary-panel)').forEach(el => {
                            if (el.textContent.trim() === 'از') el.remove();
                        });

                        // اضافه کردن «از» به ابتدای summary
                        const summary = document.querySelector('.ag-paging-row-summary-panel');
                        if (summary) {
                            summary.querySelectorAll('.injected-az').forEach(el => el.remove());
                            const azSpan = document.createElement('span');
                            azSpan.textContent = 'از ';
                            azSpan.className = 'injected-az';
                            summary.insertBefore(azSpan, summary.firstChild);
                        }
                    }, 100); // ← از 0 به 100 تغییر کرد تا AG Grid اول رندر کنه
                },
    };

    gridApi = agGrid.createGrid(document.getElementById('usersGrid'), opts);
    // 🆕 با تغییر اندازه پنجره، ستون‌ها دوباره تنظیم شوند
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(applyResponsiveColumns, 200);
});
}
function deleteUser(userId) {
    uiConfirm('آیا مطمئنید؟ این کاربر حذف می‌شود.', async function () {
        try {
            const r = await fetch('/api/admin/delete-user.php', {
                method: 'POST', headers: ahj(),
                body: JSON.stringify({ user_id: userId })
            });
            const d = await r.json();
            if (d.success) {
                loadUsers();
                showUndoToast({
                    title: 'حذف کاربر',
                    message: 'کاربر حذف شد',
                    duration: 6000,
                    onUndo: () => restoreUser(userId)
                });
            }
            else showAlert(d.message || 'خطا', 'danger');
        } catch { showAlert('خطا در سرور', 'danger'); }
    }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
}

async function restoreUser(userId) {
    try {
        const r = await fetch('/api/admin/restore-user.php', {
            method: 'POST', headers: ahj(),
            body: JSON.stringify({ user_id: userId })
        });
        const d = await r.json();
        if (d.success) loadUsers();
        else showAlert(d.message || 'خطا در بازگرداندن', 'danger');
    } catch { showAlert('خطا در سرور', 'danger'); }
}
/* ── load ── */
async function loadUsers() {
    if (gridApi) gridApi.showLoadingOverlay();
    try {
        const r = await fetch('/api/admin/users.php', { headers: ah() });
        const d = await r.json();
        if (d.success) {
            allUsers = d.users || [];
            managers = allUsers.filter(u => u.role === 'management' || ['manager','supervisor','admin'].includes(u.role));
            applyFilters();
        } else {
            showAlert(d.message || 'خطا در بارگذاری', 'danger');
        }
    } catch { showAlert('خطا در ارتباط بییییییا سرور', 'danger'); }
    finally { if (gridApi) gridApi.hideOverlay(); }
}

/* ── filters ── */
function applyFilters() {
    const s  = document.getElementById('searchInput').value.trim().toLowerCase();
    const r  = document.getElementById('filterRole').value;
    const sc = document.getElementById('filterSection').value;
    const st = document.getElementById('filterStatus').value;

    filteredUsers = allUsers.filter(x => {
        if (s) {
            const nm = `${x.first_name||''} ${x.last_name||''}`.toLowerCase();
            if (!nm.includes(s) && !(x.phone||'').includes(s) && !(x.username||'').toLowerCase().includes(s)) return false;
        }
        if (r  && x.role !== r)              return false;
        if (sc && x.activity_section !== sc) return false;
        if (st !== '' && String(x.is_active) !== st) return false;
        return true;
    });

    document.getElementById('usersCount').textContent = filteredUsers.length + ' کاربر';
    if (gridApi) gridApi.setGridOption('rowData', filteredUsers);
}

function resetFilters() {
    ['searchInput','filterUnit','filterRole','filterSection','filterStatus']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    applyFilters();
}


/* ── tab switch ── */
function switchTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.modal-tab').forEach(b => b.classList.remove('active'));
    const pane = document.getElementById(`tab-${id}`);
    if (pane) pane.style.display = '';
    if (btn)  btn.classList.add('active');
}

/* ── shift toggle ── */
function toggleShift2() {
    const d = document.getElementById('e_shift_type').value === 'double';
    document.getElementById('shift2Block').style.display = d ? '' : 'none';
}

/* ── create user ── */
async function openCreateModal() {
    // بررسی سقف کاربران قبل از باز کردن مودال
    try {
        const r = await fetch('/api/admin/check-user-limit.php', { headers: ah() });
        const d = await r.json();
        if (!d.allowed) {
            showAlert(d.message || 'سقف تعداد کاربران تکمیل شده است', 'danger');
            return;
        }
    } catch (e) {
        console.error('خطا در بررسی سقف کاربران:', e);
    }

    document.getElementById('createUserForm').reset();
    buildSectionOptionsForCreate();
    createModalInst.show();
}
// 🆕 نمایش ستون‌های کمتر در موبایل
function applyResponsiveColumns() {
    if (!gridApi) return;
    const isMobile = window.innerWidth <= 576;

    // در موبایل پنهان شوند (فقط عنوان، وضعیت، موعد بماند)
    // 🆕 ستون «مهلت» با colId یکتا، تا با «موعد» قاطی نشود
    const hideOnMobile = [
        'id', 'creator_name', 'assignee_name',
        'task_type', 'created_at', 'col_mohlat'
    ];

    hideOnMobile.forEach(col => {
        gridApi.setColumnsVisible([col], !isMobile);
    });
}
async function createUser() {
    const phone = document.getElementById('c_phone').value.trim();
    const pw    = document.getElementById('c_password').value;
    const pw2   = document.getElementById('c_password_confirm').value;
    const fn    = document.getElementById('c_first_name').value.trim();
    const ln    = document.getElementById('c_last_name').value.trim();

    if (!fn || !ln)                  { showCreateAlert('نام و نام خانوادگی اجباری است'); return; }
    if (!/^09[0-9]{9}$/.test(phone)) { showCreateAlert('موبایل باید 09xxxxxxxxx باشد'); return; }
    if (pw.length < 8) { showCreateAlert('رمز عبور حداقل ۸ کاراکتر'); return; }
    if (!/[A-Za-z]/.test(pw)) { showCreateAlert('رمز عبور باید حداقل یک حرف انگلیسی داشته باشد'); return; }
    if (!/[0-9]/.test(pw))    { showCreateAlert('رمز عبور باید حداقل یک عدد داشته باشد'); return; }
    if (pw !== pw2)                  { showCreateAlert('رمز عبور و تکرار یکسان نیستند'); return; }

    try {
        const r = await fetch('/api/admin/create-user.php', {
            method:'POST', headers: ahj(),
            body: JSON.stringify({
                username: phone,
                phone:    phone,
                password: pw,
                first_name: fn,
                last_name:  ln,
                email: document.getElementById('c_email').value.trim() || null,
                activity_section: document.getElementById('c_activity_section').value,
                role: document.getElementById('c_role').value,
            })
        });
        const d = await r.json();
        if (d.success) {
            showAlert('کاربر ایجاد شد', 'success');
            createModalInst.hide();
            loadUsers();
        } else {
            showCreateAlert(d.message || 'خطا در ایجاد کاربر');
        }
    } catch {
        showCreateAlert('خطا در ارتباط با سرور');
    }
}

/* ── edit user ── */
async function openEditModal(userId) {
    const u = allUsers.find(x => x.id === userId);
    if (!u) return;
    if (!orgSections.length) await loadSections();

    document.getElementById('editingUserId').value = userId;
    document.getElementById('editModalName').textContent =
        [u.first_name, u.last_name].filter(Boolean).join(' ') || u.phone;

    // تب ۱
    document.getElementById('e_first_name').value       = u.first_name || '';
    document.getElementById('e_last_name').value        = u.last_name  || '';
    document.getElementById('e_phone').value            = u.phone      || '';
    document.getElementById('e_email').value            = u.email      || '';

    document.getElementById('e_is_active').value = String(u.is_active ?? 1);
    buildSectionOptionsForEdit(u.activity_section || '');
    document.getElementById('e_last_login').value = u.last_login
        ? new Date(u.last_login).toLocaleString('fa-IR', { timeZone: 'Asia/Tehran' })
        : 'هرگز وارد نشده';
    document.getElementById('e_password').value        = '';
    document.getElementById('e_password_confirm').value = '';

    // شیفت
    document.getElementById('e_shift_type').value    = u.shift_type || 'single';
    document.getElementById('e_shift1_start').value  = (u.shift_1_start || '08:00').slice(0,5);
    document.getElementById('e_shift1_end').value    = (u.shift_1_end   || '17:00').slice(0,5);
    document.getElementById('e_shift2_start').value  = (u.shift_2_start || '').slice(0,5);
    document.getElementById('e_shift2_end').value    = (u.shift_2_end   || '').slice(0,5);
    document.getElementById('e_monthly_salary').value = formatSalary(u.monthly_salary);
    toggleShift2();
    calcDailyHours();
    
    // دسترسی‌ها
    document.getElementById('e_can_create_routine').checked  = u.can_create_routine  == 1;
    document.getElementById('e_can_create_workflow').checked = u.can_create_workflow == 1;

    // نقش
    document.getElementById('e_role').value = u.role || 'employee';

    // تب ۵
    buildManagerDropdown(userId, u.manager_id);
    buildHierarchyTree(u);

    switchTab('info', document.querySelector('.modal-tab'));
    // ذخیره مقادیر اولیه برای مقایسه
    originalUserData = {
        first_name:          u.first_name || '',
        last_name:           u.last_name  || '',
        phone:               u.phone      || '',
        email:               u.email      || '',
        is_active:           String(u.is_active ?? 1),
        shift_type:          u.shift_type || 'single',
        shift_1_start:       (u.shift_1_start || '08:00').slice(0,5),
        shift_1_end:         (u.shift_1_end   || '17:00').slice(0,5),
        shift_2_start:       (u.shift_2_start || '').slice(0,5),
        shift_2_end:         (u.shift_2_end   || '').slice(0,5),
        monthly_salary: formatSalary(u.monthly_salary),
        can_create_routine:  u.can_create_routine  == 1,
        can_create_workflow: u.can_create_workflow == 1,
        manager_id:          String(u.manager_id || ''),
        activity_section: u.activity_section || '',
        role:                u.role || 'employee',
        password:            '',
    };
    editModalInst.show();
}
let originalUserData = {};
/* ── save user ── */
async function saveUser() {
    const uid  = document.getElementById('editingUserId').value;
    const pw   = document.getElementById('e_password').value;
    const pw2  = document.getElementById('e_password_confirm').value;
    const ph   = document.getElementById('e_phone').value.trim();

if (!/^09[0-9]{9}$/.test(ph)) { switchTab('info', document.querySelector('.modal-tab')); showModalAlert('موبایل نامعتبر است'); return; }
if (pw && pw !== pw2)  { showModalAlert('رمزها یکسان نیستند'); return; }
if (pw && pw.length<4) { showModalAlert('رمز حداقل ۴ کاراکتر'); return; }

    const units = UNIT_LIST
        .filter(c => document.getElementById(`unit_${c}`)?.checked)
        .map(c => ({ activity_unit: c, is_primary: document.getElementById(`primary_${c}`)?.checked ? 1 : 0 }));
    if (units.length && !units.some(u => u.is_primary===1)) units[0].is_primary = 1;

    const mgr  = allUsers.find(x => x.id == document.getElementById('e_manager_id').value);
    // مقایسه با مقادیر اولیه
    const currentData = {
        first_name:          document.getElementById('e_first_name').value.trim(),
        last_name:           document.getElementById('e_last_name').value.trim(),
        phone:               ph,
        email:               document.getElementById('e_email').value.trim(),
        is_active:           document.getElementById('e_is_active').value,
        shift_type:          document.getElementById('e_shift_type').value,
        shift_1_start:       document.getElementById('e_shift1_start').value,
        shift_1_end:         document.getElementById('e_shift1_end').value,
        shift_2_start:       document.getElementById('e_shift2_start').value,
        shift_2_end:         document.getElementById('e_shift2_end').value,
        monthly_salary: document.getElementById('e_monthly_salary').value,
        can_create_routine:  document.getElementById('e_can_create_routine').checked,
        can_create_workflow: document.getElementById('e_can_create_workflow').checked,
        manager_id:          String(document.getElementById('e_manager_id').value || ''),
        activity_section: document.getElementById('e_activity_section').value,
        role:                document.getElementById('e_role').value,
        password:            pw,
    };
    
    const hasChanged = Object.keys(currentData).some(k => {
        if (k === 'password') return currentData[k] !== '';
        return String(currentData[k]) !== String(originalUserData[k]);
    });
    
    if (!hasChanged) {
        showModalAlert('هیچ تغییری اعمال نشده است', 'warning');
        return;
    }
    const payload = {
        user_id: uid,
        first_name: document.getElementById('e_first_name').value.trim(),
        last_name:  document.getElementById('e_last_name').value.trim(),
        phone: ph,
        username: ph,
        email: document.getElementById('e_email').value.trim() || null,
        is_active: parseInt(document.getElementById('e_is_active').value),
        password: pw || null,
        shift_type: document.getElementById('e_shift_type').value,
        daily_work_hours: parseFloat(document.getElementById('e_daily_work_hours').value) || 0,
        shift_1_start: document.getElementById('e_shift1_start').value || null,
        shift_1_end:   document.getElementById('e_shift1_end').value   || null,
        shift_2_start: document.getElementById('e_shift2_start').value || null,
        shift_2_end:   document.getElementById('e_shift2_end').value   || null,
        monthly_salary: parseFloat(document.getElementById('e_monthly_salary').value) || 0,
        activity_section: document.getElementById('e_activity_section').value || null,
        activity_section: document.getElementById('e_activity_section').value || null,
        can_create_routine:  document.getElementById('e_can_create_routine').checked  ? 1 : 0,
        can_create_workflow: document.getElementById('e_can_create_workflow').checked ? 1 : 0,
        role: document.getElementById('e_role').value,
        manager_id: mgr ? parseInt(mgr.id) : null,
        manager_name:     mgr?.first_name || null,
        manager_lastname: mgr?.last_name  || null,
    };

    try {
        const r = await fetch('/api/admin/update-user.php', { method:'POST', headers: ahj(), body: JSON.stringify(payload) });
        const d = await r.json();
        if (d.success) { showAlert('ذخیره شد','success'); editModalInst.hide(); loadUsers(); }
        else showModalAlert(d.message || 'خطا در ذخیره');
    } catch { showAlert('خطا در سرور','danger'); }
}
function toFa(n) {
    if (n === null || n === undefined || n === '') return '—';
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}
function buildSectionOptionsForEdit(currentSection) {
    fillSectionSelect('e_activity_section', currentSection);
}
function formatSalary(val) {
    if (!val) return '';
    const toman = Math.round(val / 10);
    return toFa(toman.toLocaleString('en-US'));
}
/* ── toggle status ── */
function toggleStatus(userId, current) {
    const newVal = current == 1 ? 0 : 1;
    const user = allUsers.find(x => x.id === userId);
    const name = user ? `${user.first_name || ''} ${user.last_name || ''}`.trim() : 'این کاربر';

    const msg = (newVal === 0)
        ? `⚠️ غیرفعال کردن «${name}» — این کاربر دیگر نمی‌تواند وارد سیستم شود و تمام دسترسی‌هایش قطع می‌شود. مطمئن هستید؟`
        : `فعال کردن «${name}»؟`;

    uiConfirm(msg, async function () {
        try {
            const r = await fetch('/api/admin/toggle-user-status.php', {
                method:'POST', headers: ahj(),
                body: JSON.stringify({ user_id: userId, is_active: newVal })
            });
            const d = await r.json();
            if (d.success) loadUsers();
            else showAlert(d.message||'خطا','danger');
        } catch { showAlert('خطا در سرور','danger'); }
    }, { danger: (newVal === 0), yesText: (newVal === 0 ? 'بله، غیرفعال کن' : 'بله، فعال کن'), noText: 'انصراف' });
}

/* ── manager dropdown ── */
function buildManagerDropdown(excludeId, currentMgrId) {
    const sel = document.getElementById('e_manager_id');
    sel.innerHTML = '<option value="">— بدون مدیر —</option>';
allUsers.filter(m => m.id != excludeId && (m.role === 'management' || ['manager','supervisor','admin'].includes(m.role))).forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = `${[m.first_name,m.last_name].filter(Boolean).join(' ')||m.phone} (${ROLE_NAMES[m.role]||m.role})`;
        if (m.id == currentMgrId) opt.selected = true;
        sel.appendChild(opt);
    });
    sel.onchange = () => {
        const m = managers.find(x => x.id == sel.value);
        document.getElementById('e_manager_code').value = m?.official_code || '';
    };
}

/* ── hierarchy tree ── */
function buildHierarchyTree(u) {
    const mgr  = allUsers.find(x => x.id == u.manager_id);
    const subs = allUsers.filter(x => x.manager_id == u.id);
    let html   = '';
    if (mgr) {
        html += `<div class="hierarchy-node h-manager"><i class="bi bi-person-badge"></i>
            <strong>مدیر:</strong> ${[mgr.first_name,mgr.last_name].filter(Boolean).join(' ')||mgr.phone}
            <span class="role-badge role-${mgr.role} ms-2">${ROLE_NAMES[mgr.role]||mgr.role}</span></div>
            <div class="hierarchy-line"></div>`;
    }
    html += `<div class="hierarchy-node h-current"><i class="bi bi-person-circle text-primary"></i>
        <strong>${[u.first_name,u.last_name].filter(Boolean).join(' ')||u.phone}</strong>
        <span class="role-badge role-${u.role} ms-2">${ROLE_NAMES[u.role]||u.role}</span></div>`;
    if (subs.length) {
        html += `<div class="hierarchy-line"></div><div class="ps-3">
            <small class="text-muted d-block mb-1"><i class="bi bi-people ms-1"></i>زیردستان (${subs.length}):</small>
            ${subs.map(s=>`<div class="hierarchy-node h-sub"><i class="bi bi-person"></i>
                ${[s.first_name,s.last_name].filter(Boolean).join(' ')||s.phone}
                <span class="role-badge role-${s.role} ms-2">${ROLE_NAMES[s.role]||s.role}</span></div>`).join('')}
        </div>`;
    }
    document.getElementById('hierarchyTree').innerHTML = html;
}

/* ── alert ── */
function showAlert(msg, type='info') {
    const map = { danger: 'warning', error: 'warning' };
    showToast(msg, map[type] || type);
}
</script>
</body>
</html>