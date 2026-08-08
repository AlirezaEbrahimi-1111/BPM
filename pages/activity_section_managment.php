<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) $user_id = $auth->getUserFromToken();
if (!$user_id && isset($_COOKIE['auth_token'])) $user_id = $auth->validateToken($_COOKIE['auth_token']);

if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

$__me = loadUserForPermissions($db, (int) $user_id);
if (!$__me || !hasPermission($__me, 'manage_activity_sections')) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>مدیریت واحدهای فعالیت</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <script src="<?= asset('../assets/js/config.js') ?>"></script>
    <script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
    <style>
        :root {
            --bg:        #edf1ff;
            --surface:   #ffffff;
            --border:    #d9d9d9;
            --accent:    #4f8ef7;
            --accent-dim:#d4e3ff;
            --danger:    #e05252;
            --success:   #3ecf8e;
            --text:      #3a3c3f;
            --muted:     #6b7490;
            --radius:    12px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            min-height: 100vh;
        }

        .page-wrap {
            max-width: 680px;
            margin: 0 auto;
            padding: 24px 16px 60px;
        }

        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        .page-title i { color: var(--accent); font-size: 1.4rem; }

        /* add card */
        .add-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            margin-top:20px;
        }
        .add-card h6 {
            font-size: .85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 14px;
        }
        .add-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }
        @media (max-width: 520px) {
            .add-row { grid-template-columns: 1fr 1fr; }
            .add-row .btn-add { grid-column: 1 / -1; }
        }

        .form-group label {
            display: block;
            font-size: .78rem;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 9px 12px;
            font-size: .9rem;
            transition: border-color .2s;
            font-family: inherit;
        }
        .form-input:focus { outline: none; border-color: var(--accent); }

        .btn-add {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font-size: .9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: opacity .2s, transform .15s;
            white-space: nowrap;
            font-family: inherit;
        }
        .btn-add:hover { opacity: .85; transform: translateY(-1px); }
        .btn-add:active { transform: translateY(0); }

        /* list */
        .list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .list-header h6 {
            font-size: .85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .count-badge {
            background: var(--accent-dim);
            color: var(--accent);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: .78rem;
            font-weight: 600;
        }

        .sections-list { display: flex; flex-direction: column; gap: 8px; }

        .section-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: border-color .2s;
            animation: fadeIn .25s ease;
        }
        .section-item:hover { border-color: #bbbbbb; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .item-key {
            background: var(--accent-dim);
            color: var(--accent);
            border-radius: 6px;
            padding: 3px 8px;
            font-size: .75rem;
            font-family: monospace;
            flex-shrink: 0;
            min-width: 80px;
            text-align: center;
        }

        .item-label { flex: 1; font-size: .95rem; }
        .item-label-input {
            flex: 1;
            background: var(--bg);
            border: 1px solid var(--accent);
            border-radius: 6px;
            color: var(--text);
            padding: 5px 10px;
            font-size: .9rem;
            font-family: inherit;
        }
        .item-label-input:focus { outline: none; }

        .item-actions { display: flex; gap: 6px; flex-shrink: 0; }

        .btn-icon {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--muted);
            padding: 5px 9px;
            cursor: pointer;
            font-size: .85rem;
            transition: all .2s;
            display: flex;
            align-items: center;
            font-family: inherit;
        }
        .btn-icon:hover { color: var(--text); border-color: #3d4460; }
        .btn-icon.save   { border-color: var(--success); color: var(--success); }
        .btn-icon.cancel { border-color: var(--muted);   color: var(--muted); }
        .btn-icon.del { 
            border-color: #fca5a5; 
            color: #dc2626;
            background: #fff5f5;
        }
        .btn-icon.del:hover { 
            background: #fee2e2; 
            border-color: #dc2626; 
            color: #b91c1c;
        }

        .order-input {
            width: 52px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--muted);
            padding: 5px 8px;
            font-size: .8rem;
            text-align: center;
            flex-shrink: 0;
            font-family: inherit;
        }
        .order-input:focus { outline: none; border-color: var(--accent); color: var(--text); }

        /* empty / skeleton */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 10px; display: block; }

        .skeleton {
            background: linear-gradient(90deg, var(--surface) 25%, var(--border) 50%, var(--surface) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: var(--radius);
            height: 52px;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* toast */
        .toast-wrap {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }
        .toast-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 20px;
            font-size: .88rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 8px 30px rgba(0,0,0,.4);
            animation: toastIn .3s ease;
            pointer-events: all;
        }
        .toast-item.success { border-color: var(--success); }
        .toast-item.error   { border-color: var(--danger);  }
        .toast-item.success i { color: var(--success); }
        .toast-item.error   i { color: var(--danger);  }
        @keyframes toastIn {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* delete overlay */
        .overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.6);
            z-index: 1000;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
            animation: fadeIn .2s ease;
        }
        .confirm-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 24px;
            max-width: 420px;
            width: 100%;
        }
        .confirm-box h5 { margin-bottom: 8px; font-size: 1rem; }
        .confirm-box p  { color: var(--muted); font-size: .9rem; margin-bottom: 16px; line-height: 1.6; }

        .transfer-row { margin-bottom: 16px; }
        .transfer-row label { font-size: .85rem; color: var(--muted); display: block; margin-bottom: 6px; }
        .transfer-select {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            padding: 9px 12px;
            font-size: .9rem;
            font-family: inherit;
        }
        .transfer-select:focus { outline: none; border-color: var(--accent); }

        .confirm-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-confirm-cancel {
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--muted);
            padding: 8px 18px;
            cursor: pointer;
            font-family: inherit;
            transition: all .2s;
        }
        .btn-confirm-cancel:hover { color: var(--text); border-color: #3d4460; }
        .btn-confirm-del {
            background: #dc2626;
            border: 1px solid #dc2626;
            border-radius: 8px;
            color: #fff;
            padding: 8px 18px;
            cursor: pointer;
            font-family: inherit;
            transition: all .2s;
        }
        .btn-confirm-del:hover { background: #b91c1c; border-color: #b91c1c; }

        /* error hint */
        .add-error {
            color: var(--danger);
            font-size: .82rem;
            margin-top: 8px;
            display: none;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="page-wrap">

    <div class="page-title">
        <i class="bi bi-diagram-3"></i>
        مدیریت واحدهای فعالیت
    </div>

    <!-- فرم افزودن -->
    <div class="add-card">
        <h6>افزودن واحد جدید</h6>
        <div class="add-row">
            <div class="form-group">
                <label>کلید انگلیسی <span style="color:var(--danger)">*</span></label>
                <input class="form-input" id="newKey"
                       placeholder="مثال: logistics"
                       autocomplete="off" spellcheck="false">
            </div>
            <div class="form-group">
                <label>نام فارسی <span style="color:var(--danger)">*</span></label>
                <input class="form-input" id="newLabel" placeholder="مثال: لجستیک">
            </div>
            <button class="btn-add" onclick="addSection()">
                <i class="bi bi-plus-lg"></i>
                <span>افزودن</span>
            </button>
        </div>
        <div class="add-error" id="addError"></div>
    </div>

    <!-- لیست -->
    <div class="list-header">
        <h6>واحدهای تعریف‌شده</h6>
        <span class="count-badge" id="countBadge">—</span>
    </div>

    <div class="sections-list" id="sectionsList">
        <div class="skeleton"></div>
        <div class="skeleton"></div>
        <div class="skeleton"></div>
    </div>

</div>
<?php include 'footer.php'; ?>
<!-- Toast -->
<div class="toast-wrap" id="toastWrap"></div>

<!-- Delete confirm -->
<div class="overlay" id="deleteOverlay" style="display:none;">
    <div class="confirm-box">
        <h5><i class="bi bi-exclamation-triangle" style="color:var(--danger);margin-left:8px"></i>حذف واحد فعالیت</h5>
        <p id="deleteMsg"></p>

        <div class="transfer-row" id="transferRow">
            <label>کاربران این واحد به کجا منتقل شوند؟</label>
            <select class="transfer-select" id="transferSelect"></select>
        </div>

        <div class="confirm-actions">
            <button class="btn-confirm-cancel" onclick="closeDeleteOverlay()">انصراف</button>
            <button class="btn-confirm-del" onclick="confirmDelete()">
                <i class="bi bi-trash"></i> حذف
            </button>
        </div>
    </div>
</div>

<script>
// ─── State ────────────────────────────────────────────────────────────────────
let sections  = [];
let deleteKey = null;

const API = '../api/organization/activity-sections.php';

function authHeaders() {
    return {
        'Authorization': 'Bearer ' + authToken,
        'Content-Type': 'application/json'
    };
}

// ─── API calls ────────────────────────────────────────────────────────────────
async function fetchSections() {
    const [secRes, usrRes] = await Promise.all([
        fetch(API, { headers: authHeaders() }),
        fetch('../api/admin/users.php', { headers: authHeaders() })
    ]);
    const secData = await secRes.json();
    const usrData = await usrRes.json();
    if (!secData.success) throw new Error(secData.message || 'خطا');

    // شمارش کاربران هر واحد
    const counts = {};
    if (usrData.success) {
        usrData.users.forEach(u => {
            const k = u.activity_section || '';
            counts[k] = (counts[k] || 0) + 1;
        });
    }
    return secData.sections.map(s => ({
        ...s,
        user_count: counts[s.section_key] || 0
    }));
}

async function apiPost(body) {
    const res = await fetch(API, {
        method: 'POST',
        headers: authHeaders(),
        body: JSON.stringify(body)
    });
    return res.json();
}

async function apiDelete(section_key, transfer_to) {
    const res = await fetch(API, {
        method: 'DELETE',
        headers: authHeaders(),
        body: JSON.stringify({ section_key, transfer_to })
    });
    return res.json();
}

// ─── Load & Render ────────────────────────────────────────────────────────────
async function loadSections() {
    try {
        sections = await fetchSections();
        renderList();
    } catch {
        document.getElementById('sectionsList').innerHTML =
            `<div class="empty-state"><i class="bi bi-wifi-off"></i>خطا در دریافت اطلاعات</div>`;
    }
}

function renderList() {
    const list = document.getElementById('sectionsList');
    document.getElementById('countBadge').textContent = sections.length;

    if (!sections.length) {
        list.innerHTML = `<div class="empty-state">
            <i class="bi bi-inbox"></i>هنوز واحدی تعریف نشده است
        </div>`;
        return;
    }

list.innerHTML = sections.map(s => `
    <div class="section-item" id="item-${s.section_key}">

        <span class="item-key">${s.section_key}</span>

        <span class="item-label" id="label-${s.section_key}">
            ${s.section_label}
            <small style="color:#6b7280;font-size:.75rem;margin-right:6px">(${toFa(s.user_count)} نفر)</small>
        </span>
        <input class="item-label-input" id="input-${s.section_key}"
               value="${s.section_label}" style="display:none;"
               onkeydown="handleEditKey(event,'${s.section_key}')">

        <div class="item-actions" id="actions-${s.section_key}">
            <button class="btn-icon" onclick="startEdit('${s.section_key}')" title="ویرایش">
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn-icon del" onclick="askDelete('${s.section_key}','${s.section_label}')" title="حذف">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <div class="item-actions" id="edit-actions-${s.section_key}" style="display:none;">
            <button class="btn-icon save" onclick="saveEdit('${s.section_key}')">
                <i class="bi bi-check-lg"></i>
            </button>
            <button class="btn-icon cancel" onclick="cancelEdit('${s.section_key}')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

    </div>
`).join('');
}
function toFa(n) {
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}


// ─── Add ──────────────────────────────────────────────────────────────────────
async function addSection() {
    const key   = document.getElementById('newKey').value.trim().toLowerCase();
    const label = document.getElementById('newLabel').value.trim();
    const errEl = document.getElementById('addError');

    errEl.style.display = 'none';

    if (!key || !label)                          return showAddErr('کلید انگلیسی و نام فارسی الزامی است');
    if (!/^[a-z0-9_]+$/.test(key))              return showAddErr('کلید باید فقط شامل حروف کوچک، عدد و _ باشد');
    if (['management','supervisor'].includes(key)) return showAddErr('این کلید رزرو شده است');
    if (sections.find(s => s.section_key === key)) return showAddErr('این کلید قبلاً وجود دارد');

    const data = await apiPost({ section_key: key, section_label: label, sort_order: sections.length });

    if (data.success) {
        document.getElementById('newKey').value   = '';
        document.getElementById('newLabel').value = '';
        toast('واحد با موفقیت اضافه شد', 'success');
        await loadSections();
    } else {
        showAddErr(data.message || 'خطا در افزودن');
    }
}

function showAddErr(msg) {
    const el = document.getElementById('addError');
    el.textContent  = msg;
    el.style.display = 'block';
}

// ─── Edit ─────────────────────────────────────────────────────────────────────
function startEdit(key) {
    document.getElementById(`label-${key}`).style.display        = 'none';
    document.getElementById(`input-${key}`).style.display        = 'block';
    document.getElementById(`actions-${key}`).style.display      = 'none';
    document.getElementById(`edit-actions-${key}`).style.display = 'flex';
    document.getElementById(`input-${key}`).focus();
}

function cancelEdit(key) {
    const s = sections.find(x => x.section_key === key);
    document.getElementById(`input-${key}`).value                = s?.section_label || '';
    document.getElementById(`label-${key}`).style.display        = 'block';
    document.getElementById(`input-${key}`).style.display        = 'none';
    document.getElementById(`actions-${key}`).style.display      = 'flex';
    document.getElementById(`edit-actions-${key}`).style.display = 'none';
}

async function saveEdit(key) {
    const newLabel = document.getElementById(`input-${key}`).value.trim();
    if (!newLabel) { toast('نام نمی‌تواند خالی باشد', 'error'); return; }

    const s    = sections.find(x => x.section_key === key);
    const data = await apiPost({ section_key: key, section_label: newLabel, sort_order: s?.sort_order ?? 0 });

    if (data.success) {
        toast('نام واحد بروزرسانی شد', 'success');
        await loadSections();
    } else {
        toast(data.message || 'خطا', 'error');
    }
}

function handleEditKey(e, key) {
    if (e.key === 'Enter')  saveEdit(key);
    if (e.key === 'Escape') cancelEdit(key);
}


// ─── Delete ───────────────────────────────────────────────────────────────────
function askDelete(key, label) {
    deleteKey = key;

    const others = sections.filter(s => s.section_key !== key);
    const sel    = document.getElementById('transferSelect');
    const row    = document.getElementById('transferRow');

    document.getElementById('deleteMsg').textContent =
        `واحد «${label}» حذف خواهد شد. اگر کاربری در این واحد باشد، به واحد زیر منتقل می‌شود.`;

    sel.innerHTML = `<option value="management">مدیریت (management)</option>` +
        others.map(s => `<option value="${s.section_key}">${s.section_label} (${s.section_key})</option>`).join('');

    row.style.display = others.length ? 'block' : 'none';
    document.getElementById('deleteOverlay').style.display = 'flex';
}

function closeDeleteOverlay() {
    document.getElementById('deleteOverlay').style.display = 'none';
    deleteKey = null;
}

async function confirmDelete() {
    if (!deleteKey) return;
    const transferTo = document.getElementById('transferSelect').value || 'management';
    const data = await apiDelete(deleteKey, transferTo);

    if (data.success) {
        toast(data.message || 'واحد حذف شد', 'success');
        closeDeleteOverlay();
        await loadSections();
    } else {
        toast(data.message || 'خطا در حذف', 'error');
    }
}

// ─── Toast ────────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    el.className = `toast-item ${type}`;
    el.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>${msg}`;
    wrap.appendChild(el);
    setTimeout(() => el.remove(), 3200);
}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (!authToken) { window.location.href = '../index.php'; return; }

    // Enter برای افزودن
    ['newKey','newLabel'].forEach(id => {
        document.getElementById(id).addEventListener('keydown', e => {
            if (e.key === 'Enter') addSection();
        });
    });

    // بستن با کلیک بیرون از modal
    document.getElementById('deleteOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeDeleteOverlay();
    });

    loadSections();
});
</script>

</body>
</html>