/* ============================================================
   task-groups.js — واحد مستقل مدیریت گروه‌های کار
   استفاده: این فایل را در assets/js بگذار و در صفحه include کن:
   <script src="../assets/js/task-groups.js"></script>

   نیازمندی: متغیر سراسری authToken (همان توکن موجود در صفحات تو)
   و کتابخانه Bootstrap (برای مودال) که در پروژه هست.
   ============================================================ */

(function () {
    'use strict';

    // پالت رنگ ثابت
    const GROUP_COLORS = [
        '#6366f1', '#ef4444', '#f59e0b', '#10b981',
        '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6',
        '#64748b', '#0ea5e9'
    ];
    // آیکون‌های آماده (Bootstrap Icons)
    const GROUP_ICONS = [
        'bi-tag', 'bi-briefcase', 'bi-house', 'bi-heart',
        'bi-star', 'bi-flag', 'bi-bullseye', 'bi-people',
        'bi-cart', 'bi-tools', 'bi-book', 'bi-lightning'
    ];

    let _groups = [];
    let _isOrgAdmin = false;   // آیا کاربر می‌تواند گروه سازمانی بسازد
    let _editingId = null;
    let _selColor = GROUP_COLORS[0];
    let _selIcon = GROUP_ICONS[0];
    let _selScope = 'personal';
    let _onChange = null;      // callback بعد از هر تغییر (برای refresh select)

    function api(path) { return '../api/task-groups/' + path; }
    function toFa(n) { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

    // ── ضدِ XSS: نامِ گروه خام داخلِ innerHTML می‌رفت؛ رنگ داخلِ style و آیکن
    //    داخلِ class attribute ── همه باید پاک‌سازی/اعتبارسنجی شوند.
    function tgEsc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function tgSafeColor(c) {
        return /^#[0-9a-fA-F]{3,8}$/.test(String(c || '')) ? String(c) : '#8e57fe';
    }
    function tgSafeIcon(i) {
        return /^[a-zA-Z0-9 _-]{1,40}$/.test(String(i || '')) ? String(i) : 'bi bi-tag';
    }

    // ---- بارگذاری گروه‌ها از سرور ----
    async function loadGroups() {
        try {
            const res = await fetch(api('list.php'), {
                headers: { 'Authorization': 'Bearer ' + authToken }
            });
            const data = await res.json();
            if (data.success) _groups = data.groups || [];
        } catch (e) {
            console.error('loadGroups error:', e);
            _groups = [];
        }
        return _groups;
    }

    // ---- پر کردن یک <select> با گروه‌ها ----
    function fillSelect(selectEl, selectedValue) {
        if (!selectEl) return;
        const orgGroups = _groups.filter(g => g.scope === 'org');
        const myGroups = _groups.filter(g => g.scope === 'personal');

        let html = '<option value="">بدون گروه</option>';
        if (orgGroups.length) {
            html += '<optgroup label="گروه‌های سازمانی">';
            orgGroups.forEach(g => {
                html += `<option value="${g.id}" ${g.id == selectedValue ? "selected" : ""}>${tgEsc(g.name)}</option>`;
            });
            html += '</optgroup>';
        }
        if (myGroups.length) {
            html += '<optgroup label="گروه‌های من">';
            myGroups.forEach(g => {
                html += `<option value="${g.id}" ${g.id == selectedValue ? "selected" : ""}>${tgEsc(g.name)}</option>`;
            });
            html += '</optgroup>';
        }
        selectEl.innerHTML = html;
    }

    // ---- ساخت مودال (یک‌بار به body اضافه می‌شود) ----
    function ensureModal() {
        if (document.getElementById('groupManagerModal')) return;

        const colorsHtml = GROUP_COLORS.map(c =>
            `<span class="gm-color" data-color="${c}" style="background:${c}"></span>`
        ).join('');
        const iconsHtml = GROUP_ICONS.map(ic =>
            `<span class="gm-icon" data-icon="${ic}"><i class="${ic}"></i></span>`
        ).join('');

        const modalHtml = `
        <div class="modal fade" id="groupManagerModal" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tags me-2"></i>مدیریت گروه‌ها</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <!-- لیست گروه‌ها -->
                <div id="gmList" style="max-height:240px; overflow:auto; margin-bottom:1rem;"></div>
                <hr>
                <!-- فرم ساخت/ویرایش -->
                <div id="gmForm">
                  <input type="hidden" id="gmEditId">
                  <div class="mb-2">
                    <label class="form-label">نام گروه</label>
                    <input type="text" class="form-control form-control-sm" id="gmName" placeholder="مثلاً: کاری">
                  </div>
                  <div class="mb-2">
                    <label class="form-label d-block">رنگ</label>
                    <div id="gmColors" class="gm-colors">${colorsHtml}</div>
                  </div>
                  <div class="mb-2">
                    <label class="form-label d-block">آیکون</label>
                    <div id="gmIcons" class="gm-icons">${iconsHtml}</div>
                  </div>
                  <div class="mb-2" id="gmScopeRow" style="display:none;">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="gmScopeOrg">
                      <label class="form-check-label" for="gmScopeOrg">گروه سازمانی (برای همه اعضا)</label>
                    </div>
                  </div>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="gmSaveBtn">ذخیره گروه</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="gmCancelBtn" style="display:none;">انصراف ویرایش</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>`;

        const style = `
        <style>
          .gm-colors,.gm-icons{display:flex;flex-wrap:wrap;gap:6px}
          .gm-color{width:26px;height:26px;border-radius:50%;cursor:pointer;border:2px solid transparent}
          .gm-color.active{border-color:#000}
          .gm-icon{width:30px;height:30px;display:flex;align-items:center;justify-content:center;border:1px solid #ddd;border-radius:6px;cursor:pointer}
          .gm-icon.active{background:rgba(142,87,254,0.12);border-color:#8e57fe}
          .gm-row{display:flex;align-items:center;gap:8px;padding:6px 4px;border-bottom:1px solid #f0f0f0}
          .gm-row .gm-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:12px;font-size:.8rem}
        </style>`;

        document.body.insertAdjacentHTML('beforeend', style + modalHtml);

        // رویدادهای انتخاب رنگ/آیکون
        document.querySelectorAll('#gmColors .gm-color').forEach(el => {
            el.addEventListener('click', () => {
                document.querySelectorAll('#gmColors .gm-color').forEach(c => c.classList.remove('active'));
                el.classList.add('active');
                _selColor = el.dataset.color;
            });
        });
        document.querySelectorAll('#gmIcons .gm-icon').forEach(el => {
            el.addEventListener('click', () => {
                document.querySelectorAll('#gmIcons .gm-icon').forEach(c => c.classList.remove('active'));
                el.classList.add('active');
                _selIcon = el.dataset.icon;
            });
        });

        document.getElementById('gmSaveBtn').addEventListener('click', saveGroup);
        document.getElementById('gmCancelBtn').addEventListener('click', resetForm);
    }

    // ---- رندر لیست داخل مودال ----
    function renderList() {
        const c = document.getElementById('gmList');
        if (!c) return;
        if (_groups.length === 0) {
            c.innerHTML = '<p class="text-muted">هنوز گروهی ساخته نشده.</p>';
            return;
        }
        c.innerHTML = _groups.map(g => {
            const isOrg = g.scope === 'org';
            const col = tgSafeColor(g.color);
            const ico = tgSafeIcon(g.icon);
            return `
            <div class="gm-row">
              <span class="gm-badge" style="background:${col}20;color:${col}">
                <i class="${ico}"></i>${tgEsc(g.name)}
              </span>
              ${isOrg ? '<small class="text-muted">سازمانی</small>' : ''}
              <span class="ms-auto"></span>
              <button class="btn btn-link btn-sm p-0" data-edit="${tgEsc(g.id)}"><i class="bi bi-pencil"></i></button>
              <button class="btn btn-link btn-sm text-danger p-0" data-del="${tgEsc(g.id)}"><i class="bi bi-trash"></i></button>
            </div>`;
        }).join('');

        c.querySelectorAll('[data-edit]').forEach(b =>
            b.addEventListener('click', () => startEdit(parseInt(b.dataset.edit))));
        c.querySelectorAll('[data-del]').forEach(b =>
            b.addEventListener('click', () => deleteGroup(parseInt(b.dataset.del))));
    }

    function resetForm() {
        _editingId = null;
        document.getElementById('gmEditId').value = '';
        document.getElementById('gmName').value = '';
        document.getElementById('gmCancelBtn').style.display = 'none';
        document.getElementById('gmSaveBtn').textContent = 'ذخیره گروه';
        if (_isOrgAdmin) document.getElementById('gmScopeOrg').checked = false;
    }

    function startEdit(id) {
        const g = _groups.find(x => x.id == id);
        if (!g) return;
        _editingId = id;
        document.getElementById('gmEditId').value = id;
        document.getElementById('gmName').value = g.name;
        _selColor = g.color; _selIcon = g.icon;
        // فعال‌سازی نمایشی رنگ/آیکون
        document.querySelectorAll('#gmColors .gm-color').forEach(c =>
            c.classList.toggle('active', c.dataset.color === g.color));
        document.querySelectorAll('#gmIcons .gm-icon').forEach(c =>
            c.classList.toggle('active', c.dataset.icon === g.icon));
        document.getElementById('gmCancelBtn').style.display = 'inline-block';
        document.getElementById('gmSaveBtn').textContent = 'به‌روزرسانی';
    }

    async function saveGroup() {
        const name = document.getElementById('gmName').value.trim();
        if (!name) { showToast('نام گروه را وارد کنید', 'warning'); return; }

        const scope = (_isOrgAdmin && document.getElementById('gmScopeOrg').checked) ? 'org' : 'personal';
        const isEdit = !!_editingId;
        const url = isEdit ? api('update.php') : api('create.php');
        const body = isEdit
            ? { id: _editingId, name, color: _selColor, icon: _selIcon }
            : { name, color: _selColor, icon: _selIcon, scope };

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.success) {
                await loadGroups();
                renderList();
                resetForm();
                if (_onChange) _onChange(_groups);
            } else {
                showToast(data.message || 'خطا در ذخیره گروه', 'error');
            }
        } catch (e) { showToast('خطا در ارتباط با سرور', 'error'); }
    }

    function deleteGroup(id) {
        uiConfirm('این گروه حذف شود؟ کارهای داخل آن بدون گروه می‌شوند.', async function () {
            try {
                const res = await fetch(api('delete.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken },
                    body: JSON.stringify({ id })
                });
                const data = await res.json();
                if (data.success) {
                    await loadGroups();
                    renderList();
                    if (_onChange) _onChange(_groups);
                } else {
                    showToast(data.message || 'خطا در حذف', 'error');
                }
            } catch (e) { showToast('خطا در ارتباط با سرور', 'error'); }
        }, { danger: true, yesText: 'بله، حذف', noText: 'انصراف' });
    }

    // ---- API عمومی این واحد ----
    window.TaskGroups = {
        // مقداردهی اولیه: گروه‌ها را لود می‌کند. isOrgAdmin را از صفحه پاس بده.
        async init(opts = {}) {
            _isOrgAdmin = !!opts.isOrgAdmin;
            _onChange = opts.onChange || null;
            await loadGroups();
            return _groups;
        },
        // پر کردن یک select
        fill(selectEl, selectedValue) { fillSelect(selectEl, selectedValue); },
        // باز کردن مودال مدیریت
        openManager() {
            ensureModal();
            // نمایش گزینه سازمانی فقط برای ادمین
            const scopeRow = document.getElementById('gmScopeRow');
            if (scopeRow) scopeRow.style.display = _isOrgAdmin ? 'block' : 'none';
            renderList();
            resetForm();
            new bootstrap.Modal(document.getElementById('groupManagerModal')).show();
        },
        // دسترسی به لیست فعلی
        all() { return _groups; }
    };
})();
