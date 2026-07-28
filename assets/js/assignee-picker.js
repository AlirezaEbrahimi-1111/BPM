/**
 * AssigneePicker — کامپوننت انتخاب کاربر / واحد با جستجو
 * نسخه ۲.۱ — multi-instance + حالت filter (placeholder)
 *
 * ─── ایجاد instance ───────────────────────────────────────────────────────
 *
 * حالت واگذاری  (create-task.php — هم کاربر هم واحد):
 *   const picker = AssigneePicker.create({
 *     container:    '#assigneePicker',
 *     users,
 *     sections,                      // [{ section_key, section_label }]
 *     sectionMap:   acticity_section, // { key: label } — همان متغیر موجود
 *     showSections: true,
 *     onSelect: (type, value, label) => { }
 *   });
 *
 * حالت واگذاری  (سایر فرم‌ها — فقط کاربر):
 *   const picker = AssigneePicker.create({
 *     container:    '#assigneePicker',
 *     users,
 *     sectionMap:   acticity_section,
 *     showSections: false,
 *     onSelect: (type, value, label) => { }
 *   });
 *
 * حالت فیلتر  (tasks.php, my-tasks.php, delegated-tasks.php, tasks-overview.php):
 *   const picker = AssigneePicker.create({
 *     container:    '#filterAssigneePicker',
 *     users,
 *     sectionMap:   acticity_section,
 *     showSections: false,
 *     placeholder:  'همه پرسنل',      // وجود این پارامتر = حالت فیلتر
 *     onSelect: (type, value, label) => {
 *       filterAssigneeId = value || '';
 *       applyFilters();
 *     }
 *   });
 *
 * ─── API هر instance ───────────────────────────────────────────────────────
 *   picker.getValue()                            → { type, value, label } | null
 *   picker.reset()                               → پاک کردن انتخاب
 *   picker.updateData(users?, sections?, map?)   → به‌روزرسانی داده بدون reinit
 *
 * ─── سازگاری با نسخه قبل (single-instance) ────────────────────────────────
 *   AssigneePicker.init(cfg)    → معادل AssigneePicker.create(cfg)
 *   AssigneePicker.getValue()   → آخرین instance ساخته‌شده
 *   AssigneePicker.reset()      → آخرین instance
 *   AssigneePicker.resetAll()   → همه instance‌های فعال (برای «پاک‌کردن فیلترها»)
 */

const AssigneePicker = (() => {

    /* ── استایل — یک‌بار inject ─────────────────────────────────────── */
    function _injectStyle() {
        if (document.getElementById('ap-style')) return;
        const s = document.createElement('style');
        s.id = 'ap-style';
        s.textContent = `
.ap-wrap{position:relative;width:100%}
.ap-input-row{display:flex;align-items:center;position:relative}
.ap-input{
  width:100%;padding:7px 36px 7px 32px;
  border:1px solid var(--bs-border-color,#dee2e6);
  border-radius:6px;font-size:13px;font-family:inherit;outline:none;
  background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);
  direction:rtl;transition:border-color .15s;cursor:pointer}
.ap-input:focus{border-color:#0d6efd;box-shadow:0 0 0 .2rem rgba(13,110,253,.15)}
.ap-icon{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  color:#9ca3af;font-size:14px;pointer-events:none}
.ap-clear{
  position:absolute;left:8px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:#9ca3af;font-size:15px;
  line-height:1;padding:2px;display:none;border-radius:3px}
.ap-clear:hover{color:#495057;background:var(--bs-secondary-bg,#e9ecef)}
.ap-clear.visible{display:flex;align-items:center;justify-content:center}
.ap-dropdown{
  position:absolute;top:calc(100% + 4px);
  min-width:300px;width:max-content;max-width:460px;
  right:0;
  background:var(--bs-body-bg,#fff);
  border:1px solid var(--bs-border-color,#dee2e6);
  border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.13);
  z-index:1055;max-height:280px;overflow-y:auto;display:none}
.ap-dropdown.open{display:block}
.ap-group-header{
  padding:5px 12px;font-size:11px;font-weight:600;color:#6c757d;
  letter-spacing:.4px;
  background:var(--bs-tertiary-bg,#f8f9fa);
  border-bottom:1px solid var(--bs-border-color,#dee2e6);
  border-top:1px solid var(--bs-border-color,#dee2e6);
  position:sticky;top:0;z-index:1}
.ap-group-header:first-child{border-top:none}
.ap-item{
  display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;
  font-size:13px;border-bottom:1px solid var(--bs-border-color-translucent,rgba(0,0,0,.07));
  transition:background .1s;white-space:nowrap}
.ap-item:last-child{border-bottom:none}
.ap-item:hover,.ap-item.ap-focused{background:var(--bs-secondary-bg,#f0f4ff)}
.ap-item.ap-selected{background:#e7f0ff}
.ap-avatar{
  width:28px;height:28px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:11px;font-weight:700;flex-shrink:0}
.ap-avatar.user   {background:#dbeafe;color:#1d4ed8}
.ap-avatar.section{background:#fef9c3;color:#854d0e}
.ap-avatar.all    {background:#f3e8ff;color:#6b21a8}
.ap-item-body{flex:1}
.ap-item-name{font-weight:500}
.ap-item-meta{font-size:11px;color:#9ca3af;margin-top:1px}
.ap-empty{padding:14px;text-align:center;color:#9ca3af;font-size:13px}
.ap-hint{font-size:12px;color:#6c757d;margin-top:5px;min-height:18px;display:block}
.ap-hint.ap-info   {color:#0d6efd}
.ap-hint.ap-warning{color:#fd7e14}

/* تم تاریک — این استایل‌ها از متغیرهای bootstrap (--bs-body-bg و ...) استفاده می‌کنند
   که با تاگل تمِ اپ (data-theme) هماهنگ نیستند و همیشه مقدار روشن دارند؛ اینجا override می‌شوند */
:root[data-theme="dark"] .ap-input{
  background:var(--surface);border-color:var(--border-soft);color:var(--text-strong)}
:root[data-theme="dark"] .ap-clear:hover{background:#2b3242;color:var(--text-strong)}
:root[data-theme="dark"] .ap-dropdown{
  background:var(--surface);border-color:var(--border-soft);box-shadow:0 6px 24px rgba(0,0,0,.4)}
:root[data-theme="dark"] .ap-group-header{
  background:#161b27;color:var(--text-muted);
  border-bottom-color:var(--border-soft);border-top-color:var(--border-soft)}
:root[data-theme="dark"] .ap-item{border-bottom-color:var(--border-soft)}
:root[data-theme="dark"] .ap-item:hover,
:root[data-theme="dark"] .ap-item.ap-focused{background:#232a3a}
:root[data-theme="dark"] .ap-item.ap-selected{background:rgba(205,184,255,.15)}
:root[data-theme="dark"] .ap-item-name{color:var(--text-strong)}
:root[data-theme="dark"] .ap-hint{color:var(--text-muted)}
        `;
        document.head.appendChild(s);
    }

    /* ── ثبت همه instance‌ها برای resetAll ─────────────────────────── */
    const _instances = [];

    /* ══════════════════════════════════════════════════════════════════
       Instance
    ══════════════════════════════════════════════════════════════════ */
    function _Instance(cfg) {

        let _cfg        = cfg;
        let _selected   = null;
        let _open       = false;
        let _filterText = '';
        let _root, _input, _dropdown, _hint;

        /* آیتم‌های ثابت */
        const SELF_ITEM    = { id: '',              label: 'خودم',        type: 'user',    avatarCls: 'user' };
        const ALL_USERS    = { id: '__all_users__', label: 'همه کاربران', type: 'user',    avatarCls: 'all'  };
        const ALL_SECTIONS = { id: '__all__',       label: 'همه واحدها',  type: 'section', avatarCls: 'all'  };

        /* وجود cfg.placeholder = حالت فیلتر */
        const _isFilterMode = !!cfg.placeholder;

        /* ── ساخت DOM ──────────────────────────────────────────────── */
        function _build() {
            _root = typeof _cfg.container === 'string'
                ? document.querySelector(_cfg.container) : _cfg.container;
            if (!_root) {
                console.error('AssigneePicker: container not found:', _cfg.container);
                return false;
            }

            const ph = _isFilterMode
                ? _cfg.placeholder
                : `جستجو در ${_cfg.showSections ? 'کاربران و واحدها' : 'کاربران'}...`;

            _root.innerHTML = `
<div class="ap-wrap">
  <div class="ap-input-row">
    <span class="ap-icon bi bi-search"></span>
    <input class="ap-input" type="text" placeholder="${ph}" autocomplete="off" readonly>
    <button class="ap-clear bi bi-x-lg" type="button" title="پاک کردن"></button>
  </div>
  <div class="ap-dropdown"></div>
</div>
${_isFilterMode ? '' : '<small class="ap-hint"></small>'}`;

            _input    = _root.querySelector('.ap-input');
            _dropdown = _root.querySelector('.ap-dropdown');
            _hint     = _root.querySelector('.ap-hint') || null;

            _bindEvents();
            _setInitialValue();
            return true;
        }

        /* ── رویدادها ──────────────────────────────────────────────── */
        function _bindEvents() {
            _input.addEventListener('click', () => {
                _input.removeAttribute('readonly');
                _filterText = '';
                _input.value = '';
                _renderDropdown('');
                _openDropdown();
            });

            _input.addEventListener('input', e => {
                _filterText = e.target.value;
                _renderDropdown(_filterText);
                if (!_open) _openDropdown();
            });

            _root.querySelector('.ap-clear').addEventListener('click', e => {
                e.stopPropagation();
                _resetSelection();
            });

            document.addEventListener('click', _onOutsideClick);
            _input.addEventListener('keydown', _onKeyDown);
        }

        function _onOutsideClick(e) {
            if (_root && !_root.contains(e.target)) _closeDropdown();
        }

        function _onKeyDown(e) {
            const items   = [..._dropdown.querySelectorAll('.ap-item')];
            const focused = _dropdown.querySelector('.ap-item.ap-focused');
            let idx       = items.indexOf(focused);

            if      (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, items.length - 1); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); idx = Math.max(idx - 1, 0); }
            else if (e.key === 'Enter')     { if (focused) { focused.click(); return; } }
            else if (e.key === 'Escape')    { _closeDropdown(); return; }
            else return;

            items.forEach(it => it.classList.remove('ap-focused'));
            if (items[idx]) {
                items[idx].classList.add('ap-focused');
                items[idx].scrollIntoView({ block: 'nearest' });
            }
        }

        /* ── ترجمه section_key → label ─────────────────────────────── */
        function _sectionLabel(key) {
            const map = _cfg.sectionMap || {};
            if (map[key]) return map[key];
            const f = (_cfg.sections || []).find(x => x.section_key === key);
            return f ? f.section_label : null;
        }

        /* ── ساخت لیست واحدها (از sections آرایه + sectionMap object) ── */
        function _buildSectionList() {
            const result = [], seen = new Set();
            (_cfg.sections || []).forEach(s => {
                if (s.section_key && s.section_label && !seen.has(s.section_key)) {
                    seen.add(s.section_key);
                    result.push({ key: s.section_key, label: s.section_label });
                }
            });
            Object.entries(_cfg.sectionMap || {}).forEach(([key, label]) => {
                if (!seen.has(key)) { seen.add(key); result.push({ key, label }); }
            });
            return result;
        }

        /* ── رندر dropdown ─────────────────────────────────────────── */
        function _renderDropdown(q) {
            const users   = _cfg.users || [];
            const secList = _buildSectionList();
            const lq      = q.trim().toLowerCase();
            const match   = str => !lq || (str || '').toLowerCase().includes(lq);

            let html = '';

            /* واحدها — فقط حالت واگذاری با showSections:true */
            if (_cfg.showSections) {
                const secItems = [];
                if (_cfg.allowAll && match(ALL_SECTIONS.label))
                    secItems.push(_itemHTML(ALL_SECTIONS.id, ALL_SECTIONS.label,
                        `${users.length} نفر — یک تسک برای هر نفر`, 'section', 'all'));
                secList.forEach(s => {
                    if (!match(s.label)) return;
                    const count = users.filter(u => u.activity_section === s.key).length;
                    secItems.push(_itemHTML(s.key, s.label,
                        count > 0 ? `${count} نفر — ${count} تسک ایجاد می‌شود` : 'بدون عضو',
                        'section', 'section'));
                });
                if (secItems.length)
                    html += `<div class="ap-group-header">📁 واحدها</div>` + secItems.join('');
            }

            /* کاربران */
            const userItems = [];

            /* «خودم» و «همه کاربران» فقط در حالت واگذاری نشان داده می‌شوند */
            if (!_isFilterMode) {
                if (match(SELF_ITEM.label))
                    userItems.push(_itemHTML(SELF_ITEM.id, SELF_ITEM.label, '', 'user', 'user'));
                if (_cfg.allowAll && match(ALL_USERS.label))
                    userItems.push(_itemHTML(ALL_USERS.id, ALL_USERS.label,
                        `${users.length} نفر — یک تسک برای هر نفر`, 'user', 'all'));
            }

            users.forEach(u => {
                const name = (u.full_name ||
                    `${u.first_name || ''} ${u.last_name || ''}`.trim() ||
                    u.phone || '').trim();
                if (!match(name)) return;
                const secLabel = u.activity_section
                    ? (_sectionLabel(u.activity_section) || u.activity_section) : '';
                userItems.push(_itemHTML(u.id, name, secLabel, 'user', 'user'));
            });

            if (userItems.length)
                html += `<div class="ap-group-header">👤 کاربران</div>` + userItems.join('');

            _dropdown.innerHTML = html || `<div class="ap-empty">نتیجه‌ای یافت نشد</div>`;

            /* علامت‌گذاری انتخاب فعلی */
            if (_selected) {
                const active = _dropdown.querySelector(
                    `.ap-item[data-type="${_selected.type}"][data-value="${CSS.escape(_selected.value)}"]`);
                if (active) active.classList.add('ap-selected');
            }

            _dropdown.querySelectorAll('.ap-item').forEach(el => {
                el.addEventListener('mousedown', e => {
                    e.preventDefault(); // جلوگیری از blur روی input
                    _pick(el.dataset.type, el.dataset.value, el.dataset.label);
                });
            });
        }

        function _itemHTML(value, label, meta, type, avatarCls) {
            const esc = str => String(str).replace(/"/g, '&quot;').replace(/</g, '&lt;');
            return `<div class="ap-item" data-type="${type}" data-value="${esc(value)}" data-label="${esc(label)}">
  <div class="ap-avatar ${avatarCls}">${label.charAt(0)}</div>
  <div class="ap-item-body">
    <div class="ap-item-name">${label}</div>
    ${meta ? `<div class="ap-item-meta">${meta}</div>` : ''}
  </div>
</div>`;
        }

        /* ── انتخاب آیتم ───────────────────────────────────────────── */
        function _pick(type, value, label) {
            _selected = { type, value, label };
            _input.value = label;
            _input.setAttribute('readonly', true);
            _root.querySelector('.ap-clear').classList.add('visible');
            _closeDropdown();
            _updateHint();
            if (typeof _cfg.onSelect === 'function') _cfg.onSelect(type, value, label);
        }

        /* ── hint زیر فیلد (فقط حالت واگذاری) ─────────────────────── */
        function _updateHint() {
            if (!_hint || !_selected) {
                if (_hint) { _hint.textContent = ''; _hint.className = 'ap-hint'; }
                return;
            }
            const { type, value } = _selected;
            if (type === 'section') {
                if (value === '__all__') {
                    _hint.textContent = 'برای همه کاربران سازمان تسک ایجاد می‌شود';
                    _hint.className   = 'ap-hint ap-warning';
                } else {
                    const count = (_cfg.users || []).filter(u => u.activity_section === value).length;
                    const lbl   = _sectionLabel(value) || value;
                    _hint.textContent = count > 0
                        ? `${count} نفر در واحد "${lbl}" — ${count} تسک ایجاد می‌شود`
                        : 'هیچ کاربری در این واحد یافت نشد';
                    _hint.className = count > 0 ? 'ap-hint ap-info' : 'ap-hint ap-warning';
                }
            } else if (value === '__all_users__') {
                const n = (_cfg.users || []).length;
                _hint.textContent = `${n} نفر — ${n} تسک ایجاد می‌شود`;
                _hint.className   = 'ap-hint ap-warning';
            } else {
                _hint.textContent = '';
                _hint.className   = 'ap-hint';
            }
        }

        /* ── باز / بسته ────────────────────────────────────────────── */
        function _openDropdown()  { _dropdown.classList.add('open'); _open = true; }
        function _closeDropdown() {
            _dropdown.classList.remove('open');
            _open = false;
            _input.value = _selected ? _selected.label : '';
            _input.setAttribute('readonly', true);
        }

        /* ── ریست ──────────────────────────────────────────────────── */
        function _resetSelection() {
            _selected   = null;
            _filterText = '';
            _input.value = '';
            _input.setAttribute('readonly', true);
            _root.querySelector('.ap-clear').classList.remove('visible');
            if (_hint) { _hint.textContent = ''; _hint.className = 'ap-hint'; }
            _closeDropdown();
            if (typeof _cfg.onSelect === 'function') _cfg.onSelect(null, null, null);
        }

        /* ── مقدار اولیه ────────────────────────────────────────────── */
        function _setInitialValue() {
            if (_isFilterMode) {
                /* حالت فیلتر — بدون انتخاب؛ placeholder نشان می‌دهد */
                _selected = null;
                return;
            }
            /* حالت واگذاری — پیش‌فرض «خودم» */
            _pick(SELF_ITEM.type, SELF_ITEM.id, SELF_ITEM.label);
            _root.querySelector('.ap-clear').classList.remove('visible');
        }

        /* ── API عمومی instance ─────────────────────────────────────── */
        function getValue() { return _selected; }

        function reset() { if (_input) _resetSelection(); }

        function updateData(users, sections, sectionMap) {
            if (users      !== undefined) _cfg.users      = users;
            if (sections   !== undefined) _cfg.sections   = sections;
            if (sectionMap !== undefined) _cfg.sectionMap = sectionMap;
            if (_open) _renderDropdown(_filterText);
        }

        /* راه‌اندازی */
        _build();

        return { getValue, reset, updateData };
    }

    /* ══════════════════════════════════════════════════════════════════
       API ماژول
    ══════════════════════════════════════════════════════════════════ */

    let _lastInstance = null;

    /**
     * create — ساخت instance جدید و برگرداندن آن
     */
    function create(cfg) {
        _injectStyle();
        const inst = _Instance(cfg);
        _instances.push(inst);
        _lastInstance = inst;
        return inst;
    }

    /**
     * init — سازگاری با نسخه قبل؛ معادل create()
     */
    function init(cfg) { return create(cfg); }

    /** getValue — آخرین instance */
    function getValue() { return _lastInstance ? _lastInstance.getValue() : null; }

    /** reset — آخرین instance */
    function reset() { if (_lastInstance) _lastInstance.reset(); }

    /**
     * resetAll — ریست همه instance‌های فعال
     * برای دکمه «پاک‌کردن همه فیلترها» استفاده کنید
     */
    function resetAll() { _instances.forEach(i => i.reset()); }

    return { create, init, getValue, reset, resetAll };

})();
