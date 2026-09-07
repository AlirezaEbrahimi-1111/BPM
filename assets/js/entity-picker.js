/**
 * EntityPicker — یک انتخابگرِ تک‌موردیِ جست‌وجوپذیر، مستقل از دامنه.
 * ظاهرش عمداً شبیهِ AssigneePicker است، ولی هیچ وابستگی‌ای به کاربر/واحد ندارد؛
 * فقط یک آرایه‌ی { id, label, meta } می‌گیرد.
 *
 *   const p = EntityPicker.create({
 *     container:   '#el',                 // سلکتور یا المان
 *     items:       [{ id, label, meta }], // meta = خطِ دومِ اختیاری
 *     placeholder: 'جست‌وجو…',
 *     value:       id,                    // انتخابِ اولیه (اختیاری)
 *     addTitle:    'افزودن مورد جدید',    // اگر ست شود، دکمه‌ی + کنارِ فیلد می‌آید
 *     onAdd:       () => {},              // کلیکِ +
 *     onSelect:    (item | null) => {},   // انتخاب یا پاک‌کردن
 *     freeText:    false,                 // اگر true: متنِ تایپ‌شده که با هیچ موردی
 *                                         //   نمی‌خواند هم مجاز است و در فیلد می‌مانَد
 *   });
 *
 *   p.getValue()        → { id, label, meta } | null
 *   p.getText()         → متنِ کنونیِ فیلد (برای حالتِ freeText)
 *   p.setValue(id)      → انتخابِ برنامه‌ای (اگر id در items باشد)
 *   p.setText(str)      → قراردادنِ متنِ آزاد بدونِ انتخاب (حالتِ freeText)
 *   p.reset()
 *   p.updateItems(arr)  → جایگزینیِ داده؛ انتخابِ فعلی اگر هنوز معتبر باشد می‌ماند
 *   p.focus()
 */
const EntityPicker = (() => {

    function _injectStyle() {
        if (document.getElementById('ep-style')) return;
        const s = document.createElement('style');
        s.id = 'ep-style';
        s.textContent = `
.ep-wrap{position:relative;width:100%}
.ep-row{display:flex;align-items:center;position:relative}
.ep-input{
  width:100%;padding:7px 34px 7px 30px;
  border:1px solid var(--bs-border-color,#dee2e6);border-radius:6px;
  font-size:13px;font-family:inherit;outline:none;direction:rtl;cursor:pointer;
  background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);transition:border-color .15s}
.ep-input:focus{border-color:#8e57fe;box-shadow:0 0 0 .2rem #8e57fe15}
.ep-icon{position:absolute;right:9px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:13px;pointer-events:none}
.ep-clear,.ep-add{
  position:absolute;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;font-size:14px;line-height:1;padding:2px;
  border-radius:4px;display:flex;align-items:center;justify-content:center}
.ep-clear{left:6px;color:#9ca3af;display:none}
.ep-clear.visible{display:flex}
.ep-clear:hover{color:#495057;background:var(--bs-secondary-bg,#e9ecef)}
.ep-add{left:6px;color:#0f7a57}
.ep-add:hover{background:rgba(15,122,87,.12)}
.ep-has-add .ep-input{padding-left:52px}
.ep-has-add .ep-clear{left:28px}
.ep-dropdown{
  position:absolute;top:calc(100% + 4px);right:0;
  min-width:260px;width:max-content;max-width:440px;
  background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);
  border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.13);
  z-index:1055;max-height:260px;overflow-y:auto;display:none}
.ep-dropdown.open{display:block}
.ep-item{
  padding:7px 12px;cursor:pointer;font-size:13px;
  border-bottom:1px solid var(--bs-border-color-translucent,rgba(0,0,0,.07));transition:background .1s}
.ep-item:last-child{border-bottom:none}
.ep-item:hover,.ep-item.ep-focused{background:rgba(142,87,254,.12)}
.ep-item.ep-selected{background:#e7f0ff}
.ep-item-name{font-weight:500}
.ep-item-meta{font-size:11px;color:#9ca3af;margin-top:1px}
.ep-empty{padding:14px;text-align:center;color:#9ca3af;font-size:13px}
:root[data-theme="dark"] .ep-input{background:var(--surface);border-color:var(--border-soft);color:var(--text-strong)}
:root[data-theme="dark"] .ep-clear:hover{background:#2b3242;color:var(--text-strong)}
:root[data-theme="dark"] .ep-dropdown{background:var(--surface);border-color:var(--border-soft);box-shadow:0 6px 24px rgba(0,0,0,.4)}
:root[data-theme="dark"] .ep-item{border-bottom-color:var(--border-soft)}
:root[data-theme="dark"] .ep-item:hover,:root[data-theme="dark"] .ep-item.ep-focused{background:rgba(142,87,254,.18)}
:root[data-theme="dark"] .ep-item.ep-selected{background:rgba(205,184,255,.15)}
:root[data-theme="dark"] .ep-item-name{color:var(--text-strong)}
        `;
        document.head.appendChild(s);
    }

    const _esc = s => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    // نرمال‌سازیِ رقم‌ها (فارسی/عربی → لاتین) تا جست‌وجوی کد با هر صفحه‌کلیدی کار کند.
    const _norm = s => String(s == null ? '' : s).toLowerCase()
        .replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
        .replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));

    function _Instance(cfg) {
        let _items = Array.isArray(cfg.items) ? cfg.items.slice() : [];
        let _selected = null;
        let _open = false;
        let _root, _input, _dd;

        _root = typeof cfg.container === 'string'
            ? document.querySelector(cfg.container) : cfg.container;
        if (!_root) {
            console.error('EntityPicker: container not found', cfg.container);
            return { getValue() { return null; }, setValue() {}, reset() {}, updateItems() {}, focus() {} };
        }

        const hasAdd = typeof cfg.onAdd === 'function';
        _root.innerHTML = `
<div class="ep-wrap${hasAdd ? ' ep-has-add' : ''}">
  <div class="ep-row">
    <span class="ep-icon bi bi-search"></span>
    <input class="ep-input" type="text" placeholder="${_esc(cfg.placeholder || 'جست‌وجو…')}" autocomplete="off" readonly>
    <button class="ep-clear bi bi-x-lg" type="button" title="پاک کردن"></button>
    ${hasAdd ? `<button class="ep-add bi bi-plus-lg" type="button" title="${_esc(cfg.addTitle || 'افزودن')}"></button>` : ''}
  </div>
  <div class="ep-dropdown"></div>
</div>`;
        _input = _root.querySelector('.ep-input');
        _dd = _root.querySelector('.ep-dropdown');
        const freeText = !!cfg.freeText;
        if (freeText) _input.removeAttribute('readonly');

        _input.addEventListener('click', () => {
            _input.removeAttribute('readonly');
            if (!freeText) _input.value = '';
            _render(freeText ? _input.value : '');
            _openDd();
        });
        _input.addEventListener('input', e => {
            if (freeText) {
                // ویرایشِ دستی → انتخابِ قبلی (اگر بود) دیگر معتبر نیست
                if (_selected && e.target.value !== _selected.label) {
                    _selected = null;
                    if (typeof cfg.onSelect === 'function') cfg.onSelect(null);
                }
                _root.querySelector('.ep-clear').classList.toggle('visible', !!e.target.value);
            }
            _render(e.target.value);
            if (!_open) _openDd();
        });
        _input.addEventListener('keydown', _onKey);
        _root.querySelector('.ep-clear').addEventListener('click', e => {
            e.stopPropagation();
            _reset(true);
        });
        if (hasAdd) {
            _root.querySelector('.ep-add').addEventListener('click', e => {
                e.stopPropagation();
                cfg.onAdd();
            });
        }
        document.addEventListener('click', e => {
            if (_root && !_root.contains(e.target)) _closeDd();
        });

        function _onKey(e) {
            const items = [..._dd.querySelectorAll('.ep-item')];
            const cur = _dd.querySelector('.ep-item.ep-focused');
            let idx = items.indexOf(cur);
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                idx = Math.min(idx + 1, items.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                idx = Math.max(idx - 1, 0);
            } else if (e.key === 'Enter') {
                if (cur) {
                    e.preventDefault();
                    cur.dispatchEvent(new Event('mousedown'));
                }
                return;
            } else if (e.key === 'Escape') {
                _closeDd();
                return;
            } else return;
            items.forEach(it => it.classList.remove('ep-focused'));
            if (items[idx]) {
                items[idx].classList.add('ep-focused');
                items[idx].scrollIntoView({ block: 'nearest' });
            }
        }

        function _render(q) {
            const nq = _norm(q).trim();
            const list = !nq ? _items : _items.filter(it =>
                _norm(it.label).includes(nq) || _norm(it.meta).includes(nq) || _norm(it.search).includes(nq));
            if (!list.length) {
                _dd.innerHTML = `<div class="ep-empty">نتیجه‌ای یافت نشد</div>`;
                return;
            }
            _dd.innerHTML = list.slice(0, 200).map(it => `
<div class="ep-item${_selected && String(_selected.id) === String(it.id) ? ' ep-selected' : ''}" data-id="${_esc(it.id)}">
  <div class="ep-item-name">${_esc(it.label)}</div>
  ${it.meta ? `<div class="ep-item-meta">${_esc(it.meta)}</div>` : ''}
</div>`).join('');
            _dd.querySelectorAll('.ep-item').forEach(el => {
                el.addEventListener('mousedown', ev => {
                    ev.preventDefault();
                    const it = _items.find(x => String(x.id) === el.dataset.id);
                    if (it) _pick(it);
                });
            });
        }

        function _pick(it) {
            _selected = it;
            _input.value = it.label;
            if (!freeText) _input.setAttribute('readonly', true);
            _root.querySelector('.ep-clear').classList.add('visible');
            _closeDd();
            if (typeof cfg.onSelect === 'function') cfg.onSelect(it);
        }

        function _openDd() { _dd.classList.add('open'); _open = true; }
        function _closeDd() {
            _dd.classList.remove('open');
            _open = false;
            if (freeText) {
                // متنِ تایپ‌شده حفظ می‌شود؛ اگر موردی انتخاب شده، برچسبش.
                if (_selected) _input.value = _selected.label;
            } else {
                _input.value = _selected ? _selected.label : '';
                _input.setAttribute('readonly', true);
            }
        }

        function _reset(fire) {
            _selected = null;
            _input.value = '';
            if (!freeText) _input.setAttribute('readonly', true);
            _root.querySelector('.ep-clear').classList.remove('visible');
            _closeDd();
            if (fire && typeof cfg.onSelect === 'function') cfg.onSelect(null);
        }

        if (cfg.value != null && cfg.value !== '') {
            const init = _items.find(x => String(x.id) === String(cfg.value));
            if (init) {
                _selected = init;
                _input.value = init.label;
                _root.querySelector('.ep-clear').classList.add('visible');
            }
        }

        return {
            getValue() { return _selected; },
            getText() { return _input.value; },
            setValue(id) {
                const it = _items.find(x => String(x.id) === String(id));
                if (it) _pick(it);
                else _reset(false);
            },
            setText(str) {
                _selected = null;
                _input.value = (str == null ? '' : String(str));
                if (freeText) {
                    _input.removeAttribute('readonly');
                    _root.querySelector('.ep-clear').classList.toggle('visible', !!_input.value);
                }
            },
            reset() { _reset(false); },
            updateItems(arr) {
                _items = Array.isArray(arr) ? arr.slice() : [];
                if (_selected) {
                    const still = _items.find(x => String(x.id) === String(_selected.id));
                    if (still) {
                        _selected = still;
                        _input.value = still.label;
                    } else _reset(false);
                }
                if (_open) _render(_input.value);
            },
            focus() {
                _input.removeAttribute('readonly');
                if (!freeText) _input.value = '';
                _render(freeText ? _input.value : '');
                _openDd();
                _input.focus();
            }
        };
    }

    function create(cfg) {
        _injectStyle();
        return _Instance(cfg);
    }
    return { create };
})();
