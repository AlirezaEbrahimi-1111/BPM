<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once '../includes/version.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات حساب کاربری</title>
    <link href="<?= asset('../assets/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('../assets/js/cdn/bootstrap-icons.css') ?>">
    <link rel="stylesheet" href="<?= asset('../../assets/css/custom.css') ?>">
    <style>
        * { font-family: 'Vazirmatn', 'Vazir', sans-serif !important; }
        body { background: #f4f6f9; padding-top: 20px; padding-bottom: 2rem; }

        /* افزایش عرض کلی */
        .page-wrap {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        /* چیدمان دو ستونی */
        .two-col-layout {
            display: flex;
            gap: 1.8rem;
            align-items: flex-start;
        }
        .right-col {
            flex: 0 0 20%;
            min-width: 0;
        }
        .left-col {
            flex: 0 0 80%;
            min-width: 0;
        }

        /* ریسپانسیو */
        @media (max-width: 768px) {
            .two-col-layout {
                flex-direction: column;
            }
            .right-col, .left-col {
                flex: 0 0 100%;
            }
        }

        /* استیکر برای کارت پروفایل در دسکتاپ */
        @media (min-width: 769px) {
            .right-col .s-card {
                position: sticky;
                top: 1rem;
            }
        }

        /* کارت‌ها */
        .s-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 1.6rem 1.8rem;
            margin-bottom: 1.1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
        }
        .right-col .s-card {
            margin-bottom: 0;
        }
        .s-card-title {
            font-size: .82rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 1.1rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        /* آواتار */
        .avatar-ring {
            width: 68px; height: 68px; border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.6rem; font-weight: 700;
            flex-shrink: 0;
            overflow: hidden;
        }
        .avatar-ring img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .user-meta { font-size: .82rem; color: #6b7280; }
        .user-role {
            display: inline-block;
            background: #ede9fe; color: #5b21b6;
            font-size: .7rem; font-weight: 600;
            border-radius: 20px; padding: .15em .6em;
        }

        /* فرم */
        .form-label {
            font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .4rem;
        }
        .form-control {
            border-radius: 9px;
            border: 1.5px solid #e5e7eb;
            padding: .6rem .85rem;
            font-size: .875rem;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .form-control[readonly] {
            background: #f9fafb; color: #9ca3af; cursor: default;
        }
        .form-hint { font-size: .75rem; color: #9ca3af; margin-top: .3rem; }

        /* دکمه ذخیره */
        .btn-save {
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            color: #fff; border: none; border-radius: 9px;
            padding: .6rem 1.6rem; font-size: .875rem; font-weight: 600;
            transition: opacity .15s, transform .15s;
        }
        .btn-save:hover { opacity: .92; transform: translateY(-1px); color: #fff; }
        .btn-save:disabled { opacity: .6; transform: none; cursor: not-allowed; }

        /* strength bar */
        .strength-bar {
            height: 4px; border-radius: 2px; background: #e5e7eb;
            margin-top: .45rem; overflow: hidden;
        }
        .strength-fill {
            height: 100%; border-radius: 2px;
            transition: width .25s, background .25s;
            width: 0;
        }
        .strength-label { font-size: .72rem; margin-top: .25rem; }

        /* password wrap */
        .pw-wrap { position: relative; }
        .pw-wrap .form-control { padding-left: 2.5rem; }
        .pw-eye {
            position: absolute; left: .7rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af; cursor: pointer; padding: 0;
            font-size: 1rem;
        }
        .pw-eye:hover { color: #6366f1; }

        .s-divider { border: none; border-top: 1px solid #f3f4f6; margin: 1.2rem 0; }

        @media (max-width: 576px) {
            .s-card { padding: 1.2rem 1.1rem; }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-wrap">
    <div class="two-col-layout">
        <!-- ستون راست (20%) : فقط کارت پروفایل -->
        <div class="right-col">
            <div class="s-card">
                <div class="d-flex flex-column align-items-center text-center gap-2">
                    <div class="avatar-ring" id="avatarInitials" onclick="document.getElementById('avatarFileInput').click()" style="cursor:pointer; position:relative;" title="تغییرِ عکسِ پروفایل">؟</div>
                    <input type="file" id="avatarFileInput" accept="image/*" style="display:none;" onchange="uploadAvatar(this.files[0])">
                    <div class="fw-semibold" id="headerFullName" style="font-size:.95rem">در حال بارگذاری...</div>
                    <div class="user-meta" id="headerPhone">—</div>
                    <div>
                        <span class="user-role" id="headerRole">—</span>
                        <span class="user-meta me-2" id="headerSection"></span>
                    </div>
                    <div class="user-meta mt-1" id="headerLastLogin" style="display:none">
                        <i class="bi bi-clock-history ms-1"></i>آخرین ورود: <span id="lastLoginVal">—</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ستون چپ (80%) : دو کارت فرم‌ها -->
        <div class="left-col">
            <!-- اطلاعات شخصی -->
            <div class="s-card">
                <div class="s-card-title"><i class="bi bi-person"></i>اطلاعات شخصی</div>
                <div id="profileAlert"></div>

                <form id="profileForm" novalidate>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">نام</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">نام خانوادگی</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">شماره موبایل</label>
                            <input type="tel" class="form-control" id="phone" readonly>
                            <div class="form-hint">توسط ادمین قابل تغییر است</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="email" placeholder="example@email.com">
                        </div>
                    </div>

                    <hr class="s-divider">

                    <div class="s-card-title" style="margin-bottom:.75rem"><i class="bi bi-building"></i>اطلاعات سازمانی</div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">کد رسمی</label>
                            <input type="text" class="form-control" id="officialCode" readonly>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">بخش فعالیت</label>
                            <input type="text" class="form-control" id="activitySection" readonly>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-save" id="profileSaveBtn">
                            <i class="bi bi-check2 ms-1"></i>ذخیره اطلاعات
                        </button>
                    </div>
                </form>
            </div>

            <!-- تغییر رمز عبور -->
            <div class="s-card">
                <div class="s-card-title"><i class="bi bi-shield-lock"></i>تغییر رمز عبور</div>
                <div id="passwordAlert"></div>

                <form id="passwordForm" novalidate>
                    <div class="mb-3">
                        <label class="form-label">رمز عبور فعلی</label>
                        <div class="pw-wrap">
                            <input type="password" class="form-control" id="currentPassword"
                                   autocomplete="current-password" placeholder="رمز عبور فعلی را وارد کنید">
                            <button type="button" class="pw-eye" onclick="togglePw('currentPassword',this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">رمز عبور جدید</label>
                        <div class="pw-wrap">
                            <input type="password" class="form-control" id="newPassword"
                                   autocomplete="new-password" placeholder="حداقل ۴ کاراکتر"
                                   oninput="checkStrength(this.value)">
                            <button type="button" class="pw-eye" onclick="togglePw('newPassword',this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                        <div class="strength-label text-muted" id="strengthLabel"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">تکرار رمز عبور جدید</label>
                        <div class="pw-wrap">
                            <input type="password" class="form-control" id="confirmPassword"
                                   autocomplete="new-password" placeholder="رمز عبور جدید را تکرار کنید">
                            <button type="button" class="pw-eye" onclick="togglePw('confirmPassword',this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-save" id="passwordSaveBtn">
                            <i class="bi bi-lock ms-1"></i>تغییر رمز عبور
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="<?= asset('../assets/js/cdn/bootstrap.bundle.min.js') ?>"></script>
<script>
'use strict';

let currentUser = null;

function tok() { return authToken || localStorage.getItem('auth_token'); }
function ah()  { return { 'Authorization': 'Bearer ' + tok() }; }
function ahj() { return { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + tok() }; }

const ROLE_NAMES    = { employee:'کارمند', manager:'مدیر', supervisor:'سوپروایزر', admin:'ادمین' };
const SECTION_NAMES = { sales:'فروش', purchase:'خرید', warehouse:'انبار', technical:'فنی',
    accounting:'حسابداری', colleague:'همکار', offices:'ادارات', virtual:'فضای مجازی',
    public:'بازرگانی', management:'مدیریت', Supervision:'نظارتی' };

document.addEventListener('DOMContentLoaded', () => {
    if (!tok()) { location.href = '../index.php'; return; }
    loadProfile();
    setupRealtime();

    document.getElementById('profileForm').addEventListener('submit', saveProfile);
    document.getElementById('passwordForm').addEventListener('submit', changePassword);
});

async function uploadAvatar(file) {
    if (!file) return;
    const fd = new FormData();
    fd.append('avatar', file);
    try {
        const r = await fetch('../api/profile/upload-avatar.php', { method: 'POST', headers: ah(), body: fd });
        const d = await r.json();
        document.getElementById('avatarFileInput').value = '';
        if (d.success) {
            currentUser.avatar_path = d.avatar_path;
            fillForm();
            var info = JSON.parse(localStorage.getItem('user_info') || '{}');
            info.avatar_path = d.avatar_path;
            localStorage.setItem('user_info', JSON.stringify(info));
            showToast('عکسِ پروفایل بروزرسانی شد', 'success');
        } else {
            showToast(d.message || 'خطا در آپلودِ عکس', 'danger');
        }
    } catch { showToast('خطا در ارتباط با سرور', 'danger'); }
}

async function loadProfile() {
    try {
        const r = await fetch('../api/auth/profile.php', { headers: ah() });
        if (r.status === 401) { localStorage.removeItem('auth_token'); location.href = '../index.php'; return; }
        const d = await r.json();
        if (d.success) { currentUser = d.user; fillForm(); }
        else showToast(d.message || 'خطا در بارگذاری', 'danger');
    } catch { showToast('خطا در ارتباط با سرور', 'danger'); }
}

function fillForm() {
    const u = currentUser;
    const fullName = [u.first_name, u.last_name].filter(Boolean).join(' ') || '—';
    const initials = fullName.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();

    var avatarEl = document.getElementById('avatarInitials');
    avatarEl.innerHTML = u.avatar_path ? '<img src="../' + u.avatar_path + '" alt="">' : (initials || '؟');
    document.getElementById('headerFullName').textContent = fullName;
    document.getElementById('headerPhone').textContent    = u.phone || '—';
    document.getElementById('headerRole').textContent     = ROLE_NAMES[u.role] || u.role || '—';
    document.getElementById('headerSection').textContent  = SECTION_NAMES[u.activity_section] || u.activity_section || '';

    if (u.last_login) {
        const ll = document.getElementById('headerLastLogin');
        ll.style.display = '';
        document.getElementById('lastLoginVal').textContent =
            new Date(u.last_login).toLocaleString('fa-IR', {
                year:'numeric', month:'2-digit', day:'2-digit',
                hour:'2-digit', minute:'2-digit'
            });
    }

    document.getElementById('firstName').value      = u.first_name || '';
    document.getElementById('lastName').value       = u.last_name  || '';
    document.getElementById('phone').value          = u.phone      || '';
    document.getElementById('email').value          = u.email      || '';
    document.getElementById('officialCode').value   = u.official_code || '—';
    document.getElementById('activitySection').value = SECTION_NAMES[u.activity_section] || u.activity_section || '—';
}

async function saveProfile(e) {
    e.preventDefault();
    const fn = document.getElementById('firstName').value.trim();
    const ln = document.getElementById('lastName').value.trim();
    if (!fn || !ln) { showFormAlert('profileAlert', 'نام و نام خانوادگی الزامی است', 'danger'); return; }

    const btn = document.getElementById('profileSaveBtn');
    btn.disabled = true;
    clearFormAlert('profileAlert');

    try {
        const r = await fetch('../api/auth/profile.php', {
            method: 'PUT', headers: ahj(),
            body: JSON.stringify({
                first_name: fn, last_name: ln,
                email: document.getElementById('email').value.trim() || null
            })
        });
        const d = await r.json();
        if (d.success) {
            currentUser = { ...currentUser, first_name: fn, last_name: ln, email: document.getElementById('email').value.trim() };
            const info = JSON.parse(localStorage.getItem('user_info') || '{}');
            localStorage.setItem('user_info', JSON.stringify({ ...info, first_name: fn, last_name: ln }));
            fillForm();
            showToast('اطلاعات با موفقیت ذخیره شد', 'success');
        } else {
            showFormAlert('profileAlert', d.message || 'خطا در ذخیره', 'danger');
        }
    } catch { showFormAlert('profileAlert', 'خطا در ارتباط با سرور', 'danger'); }
    finally { btn.disabled = false; }
}

async function changePassword(e) {
    e.preventDefault();
    const cur  = document.getElementById('currentPassword').value;
    const nw   = document.getElementById('newPassword').value;
    const conf = document.getElementById('confirmPassword').value;

    clearFormAlert('passwordAlert');

    if (!cur)        { showFormAlert('passwordAlert', 'رمز عبور فعلی را وارد کنید', 'danger'); return; }
    if (nw.length < 4) { showFormAlert('passwordAlert', 'رمز عبور جدید حداقل ۴ کاراکتر باشد', 'danger'); return; }
    if (nw !== conf)   { showFormAlert('passwordAlert', 'رمز عبور جدید و تکرار آن یکسان نیستند', 'danger'); return; }

    const btn = document.getElementById('passwordSaveBtn');
    btn.disabled = true;

    try {
        const r = await fetch('../api/auth/change-password.php', {
            method: 'POST', headers: ahj(),
            body: JSON.stringify({ current_password: cur, new_password: nw })
        });
        const d = await r.json();
        if (d.success) {
            document.getElementById('passwordForm').reset();
            document.getElementById('strengthFill').style.width = '0';
            document.getElementById('strengthLabel').textContent = '';
            showToast('رمز عبور با موفقیت تغییر کرد', 'success');
        } else {
            showFormAlert('passwordAlert', d.message || 'خطا در تغییر رمز', 'danger');
        }
    } catch { showFormAlert('passwordAlert', 'خطا در ارتباط با سرور', 'danger'); }
    finally { btn.disabled = false; }
}

function checkStrength(pw) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (pw.length >= 4)  score++;
    if (pw.length >= 8)  score++;
    if (/[A-Z]/.test(pw) || /[a-z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const cfg = [
        { w:'0%',   bg:'#e5e7eb', t:'' },
        { w:'25%',  bg:'#ef4444', t:'ضعیف' },
        { w:'50%',  bg:'#f59e0b', t:'متوسط' },
        { w:'75%',  bg:'#3b82f6', t:'خوب' },
        { w:'100%', bg:'#10b981', t:'قوی' },
    ];
    const c = cfg[Math.min(score, 4)];
    fill.style.width      = c.w;
    fill.style.background = c.bg;
    label.textContent     = c.t;
    label.style.color     = c.bg;
    validateConfirm();
}

function setupRealtime() {
    document.getElementById('email').addEventListener('input', function() {
        const v  = this.value.trim();
        const ok = !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        setFieldState(this, v ? (ok ? 'ok' : 'err') : 'reset');
    });
    ['firstName','lastName'].forEach(id => {
        document.getElementById(id).addEventListener('input', function() {
            setFieldState(this, this.value.trim() ? 'ok' : 'err');
        });
    });
    document.getElementById('confirmPassword').addEventListener('input', validateConfirm);
}

function setFieldState(el, state) {
    if (state === 'ok')    { el.style.borderColor='#10b981'; el.style.boxShadow='0 0 0 3px rgba(16,185,129,.1)'; }
    else if (state==='err'){ el.style.borderColor='#ef4444'; el.style.boxShadow='0 0 0 3px rgba(239,68,68,.1)'; }
    else                   { el.style.borderColor=''; el.style.boxShadow=''; }
}

function validateConfirm() {
    const nw   = document.getElementById('newPassword').value;
    const conf = document.getElementById('confirmPassword').value;
    const inp  = document.getElementById('confirmPassword');
    if (!conf) { setFieldState(inp,'reset'); return; }
    setFieldState(inp, nw === conf ? 'ok' : 'err');
}

function togglePw(id, btn) {
    const inp  = document.getElementById(id);
    const icon = btn.querySelector('i');
    inp.type        = inp.type === 'password' ? 'text' : 'password';
    icon.className  = inp.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

function showFormAlert(containerId, msg, type) {
    document.getElementById(containerId).innerHTML =
        `<div class="alert alert-${type} alert-dismissible small py-2 mb-3" role="alert">
            <i class="bi bi-${type==='danger'?'exclamation-triangle':'check-circle'} ms-2"></i>${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
}
function clearFormAlert(id) { document.getElementById(id).innerHTML = ''; }

// تابع showToast در صورت نیاز (در کد اصلی شما باید وجود داشته باشد، ولی در اینجا تعریف می‌کنیم)
function showToast(msg, type) {
    // یک toast ساده با alert موقت (می‌توانید با bootstrap toast جایگزین کنید)
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} fixed-bottom m-3 p-2 small`;
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.left = '20px';
    toast.style.zIndex = '9999';
    toast.style.backgroundColor = type === 'success' ? '#10b981' : '#ef4444';
    toast.style.color = 'white';
    toast.style.borderRadius = '8px';
    toast.innerText = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
</body>
</html>