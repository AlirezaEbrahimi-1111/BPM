/**
 * SelectBox — جایگزینِ ظاهریِ <select> بومی با یک منوی DOM‌ای که هاورش
 * بنفشِ استانداردِ پروژه است (rgba(142,87,254,.12) / .18 تاریک).
 *
 * روشِ کار: «بهبودِ تدریجی» — خودِ <select> در DOM می‌ماند (فقط از دید پنهان
 * می‌شود)، یک تریگر + منو رویش سوار می‌شود، و هر انتخاب مقدارِ <select> را ست
 * و رویدادِ change/input پخش می‌کند. پس هر کدِ صفحه که .value می‌خواند یا به
 * change گوش می‌دهد، بدونِ تغییر کار می‌کند.
 *
 * راه‌اندازیِ خودکار: به‌محضِ لود، همه‌ی <select>های عادی (نه multiple، نه
 * [data-no-enhance]) بهبود می‌یابند؛ یک observer روی body، selectهای بعدی
 * (مودال‌ها، ردیف‌های پویا) را هم می‌گیرد.
 */
(function () {
    'use strict';

    function injectStyle() {
        if (document.getElementById('sb-style')) return;
        var s = document.createElement('style');
        s.id = 'sb-style';
        s.textContent = [
            '.sb-wrap{position:relative;display:block;width:100%}',
            '.sb-native{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;white-space:nowrap!important;border:0!important}',
            '.sb-trigger{',
            '  display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;',
            '  padding:.375rem .75rem;font-size:1rem;line-height:1.5;font-family:inherit;',
            '  color:var(--bs-body-color,#212529);background:var(--bs-body-bg,#fff);',
            '  border:1px solid var(--bs-border-color,#dee2e6);border-radius:.375rem;',
            '  cursor:pointer;text-align:start;transition:border-color .12s,box-shadow .12s;user-select:none}',
            '.sb-trigger:focus,.sb-wrap.sb-open .sb-trigger{outline:0;border-color:#8e57fe;box-shadow:0 0 0 .2rem rgba(142,87,254,.18)}',
            '.sb-trigger .sb-label{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}',
            '.sb-trigger .sb-label.sb-placeholder{color:var(--bs-secondary-color,#6c757d)}',
            '.sb-trigger .sb-caret{flex-shrink:0;width:.7em;height:.7em;border:solid currentColor;border-width:0 2px 2px 0;transform:rotate(45deg);margin-bottom:3px;opacity:.55;transition:transform .15s}',
            '.sb-wrap.sb-open .sb-trigger .sb-caret{transform:rotate(-135deg);margin-bottom:-3px}',
            '.sb-sm .sb-trigger{padding:.25rem .5rem;font-size:.875rem;border-radius:.25rem}',
            '.sb-lg .sb-trigger{padding:.5rem 1rem;font-size:1.25rem;border-radius:.5rem}',
            '.sb-menu{',
            '  position:absolute;z-index:1060;top:calc(100% + 4px);left:0;right:0;min-width:100%;',
            '  max-height:260px;overflow-y:auto;display:none;',
            '  background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);',
            '  border-radius:.5rem;box-shadow:0 6px 24px rgba(0,0,0,.13);padding:4px}',
            '.sb-wrap.sb-open .sb-menu{display:block}',
            '.sb-wrap.sb-drop-up .sb-menu{top:auto;bottom:calc(100% + 4px)}',
            '.sb-opt{padding:7px 12px;font-size:.9rem;border-radius:6px;cursor:pointer;white-space:nowrap;color:var(--bs-body-color,#212529)}',
            '.sb-opt:hover,.sb-opt.sb-active{background:rgba(142,87,254,.12)}',
            '.sb-opt.sb-selected{background:rgba(142,87,254,.16);font-weight:600}',
            '.sb-opt.sb-disabled{opacity:.45;cursor:not-allowed;background:none}',
            '.sb-group{padding:6px 10px 2px;font-size:.72rem;font-weight:700;color:var(--bs-secondary-color,#6c757d);letter-spacing:.3px}',
            '.sb-empty{padding:10px 12px;color:var(--bs-secondary-color,#9ca3af);font-size:.85rem}',
            ':root[data-theme="dark"] .sb-trigger{background:var(--surface);border-color:var(--border-soft);color:var(--text-strong)}',
            ':root[data-theme="dark"] .sb-menu{background:var(--surface);border-color:var(--border-soft);box-shadow:0 6px 24px rgba(0,0,0,.5)}',
            ':root[data-theme="dark"] .sb-opt{color:var(--text-strong)}',
            ':root[data-theme="dark"] .sb-opt:hover,:root[data-theme="dark"] .sb-opt.sb-active{background:rgba(142,87,254,.18)}',
            ':root[data-theme="dark"] .sb-opt.sb-selected{background:rgba(205,184,255,.16)}'
        ].join('\n');
        document.head.appendChild(s);
    }

    var openWrap = null;

    document.addEventListener('click', function (e) {
        if (openWrap && !openWrap.contains(e.target)) close(openWrap);
    });

    function close(wrap) {
        wrap.classList.remove('sb-open', 'sb-drop-up');
        var t = wrap.querySelector('.sb-trigger');
        if (t) t.setAttribute('aria-expanded', 'false');
        if (openWrap === wrap) openWrap = null;
    }

    function open(wrap) {
        if (openWrap && openWrap !== wrap) close(openWrap);
        openWrap = wrap;
        wrap.classList.add('sb-open');
        wrap.querySelector('.sb-trigger').setAttribute('aria-expanded', 'true');
        // اگر جا نبود، به بالا باز کن
        var menu = wrap.querySelector('.sb-menu');
        var r = wrap.getBoundingClientRect();
        if (r.bottom + 270 > window.innerHeight && r.top > 300) wrap.classList.add('sb-drop-up');
        var sel = menu.querySelector('.sb-opt.sb-selected') || menu.querySelector('.sb-opt:not(.sb-disabled)');
        setActive(menu, sel);
        if (sel) sel.scrollIntoView({ block: 'nearest' });
    }

    function setActive(menu, el) {
        menu.querySelectorAll('.sb-opt.sb-active').forEach(function (o) { o.classList.remove('sb-active'); });
        if (el) el.classList.add('sb-active');
    }

    function enhance(select) {
        if (!(select instanceof HTMLSelectElement)) return;
        if (select.multiple || select.dataset.noEnhance !== undefined || select.dataset.sbDone) return;
        if (select.closest('.sb-wrap')) return;
        injectStyle();
        select.dataset.sbDone = '1';

        var wrap = document.createElement('div');
        wrap.className = 'sb-wrap';
        if (/\bform-select-sm\b|\bform-control-sm\b/.test(select.className)) wrap.classList.add('sb-sm');
        if (/\bform-select-lg\b|\bform-control-lg\b/.test(select.className)) wrap.classList.add('sb-lg');

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('sb-native');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');

        var trigger = document.createElement('div');
        trigger.className = 'sb-trigger';
        trigger.tabIndex = select.disabled ? -1 : 0;
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = '<span class="sb-label"></span><span class="sb-caret"></span>';

        var menu = document.createElement('div');
        menu.className = 'sb-menu';
        menu.setAttribute('role', 'listbox');

        wrap.appendChild(trigger);
        wrap.appendChild(menu);

        function currentText() {
            var o = select.options[select.selectedIndex];
            return o ? o.textContent.trim() : '';
        }

        function syncLabel() {
            var lbl = trigger.querySelector('.sb-label');
            var txt = currentText();
            var o = select.options[select.selectedIndex];
            lbl.textContent = txt || '';
            lbl.classList.toggle('sb-placeholder', !!o && (o.value === '' ) && select.selectedIndex === 0 && !!select.dataset.sbPlaceholderDim);
        }

        function buildMenu() {
            menu.innerHTML = '';
            var kids = select.childNodes, any = false;
            for (var i = 0; i < kids.length; i++) {
                var node = kids[i];
                if (node.tagName === 'OPTGROUP') {
                    var gh = document.createElement('div');
                    gh.className = 'sb-group';
                    gh.textContent = node.label || '';
                    menu.appendChild(gh);
                    for (var j = 0; j < node.children.length; j++) addOpt(node.children[j]);
                    any = any || node.children.length > 0;
                } else if (node.tagName === 'OPTION') {
                    addOpt(node);
                    any = true;
                }
            }
            if (!any) {
                var em = document.createElement('div');
                em.className = 'sb-empty';
                em.textContent = 'موردی نیست';
                menu.appendChild(em);
            }
        }

        function addOpt(opt) {
            var d = document.createElement('div');
            d.className = 'sb-opt';
            d.setAttribute('role', 'option');
            d.dataset.value = opt.value;
            d.textContent = opt.textContent.trim() || ' ';
            if (opt.disabled) d.classList.add('sb-disabled');
            if (opt.selected) { d.classList.add('sb-selected'); d.setAttribute('aria-selected', 'true'); }
            d.addEventListener('mouseenter', function () { setActive(menu, d); });
            d.addEventListener('click', function (e) {
                e.stopPropagation();
                if (opt.disabled) return;
                pick(opt.value);
            });
            menu.appendChild(d);
        }

        function pick(value) {
            if (select.value === value) { close(wrap); trigger.focus(); return; }
            select.value = value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            refresh();
            close(wrap);
            trigger.focus();
        }

        function refresh() {
            buildMenu();
            syncLabel();
            trigger.classList.toggle('sb-is-disabled', select.disabled);
            trigger.tabIndex = select.disabled ? -1 : 0;
        }

        // ── رویدادها ──
        trigger.addEventListener('click', function () {
            if (select.disabled) return;
            wrap.classList.contains('sb-open') ? close(wrap) : open(wrap);
        });

        trigger.addEventListener('keydown', function (e) {
            var isOpen = wrap.classList.contains('sb-open');
            var opts = [].slice.call(menu.querySelectorAll('.sb-opt:not(.sb-disabled)'));
            var act = menu.querySelector('.sb-opt.sb-active');
            var idx = opts.indexOf(act);

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!isOpen) { open(wrap); return; }
                idx = e.key === 'ArrowDown' ? Math.min(idx + 1, opts.length - 1) : Math.max(idx - 1, 0);
                if (opts[idx]) { setActive(menu, opts[idx]); opts[idx].scrollIntoView({ block: 'nearest' }); }
            } else if (e.key === 'Enter' || (e.key === ' ' && !isOpen)) {
                e.preventDefault();
                if (!isOpen) open(wrap);
                else if (act) pick(act.dataset.value);
            } else if (e.key === 'Escape') {
                if (isOpen) { e.preventDefault(); close(wrap); }
            } else if (e.key === 'Tab') {
                close(wrap);
            } else if (e.key.length === 1 && /\S/.test(e.key)) {
                // type-ahead
                var q = e.key.toLowerCase();
                var match = opts.filter(function (o) { return o.textContent.trim().toLowerCase().indexOf(q) === 0; });
                if (match.length) {
                    if (!isOpen) open(wrap);
                    var next = match[(match.indexOf(act) + 1) % match.length] || match[0];
                    setActive(menu, next);
                    next.scrollIntoView({ block: 'nearest' });
                }
            }
        });

        // تغییرِ بیرونی مقدار
        select.addEventListener('change', syncLabel);
        select.addEventListener('sb:refresh', refresh);

        // تغییرِ لیستِ گزینه‌ها از بیرون (کدِ صفحه)
        var mo = new MutationObserver(function () { refresh(); });
        mo.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });

        refresh();
        // یک همگام‌سازیِ تأخیری برای وقتی صفحه بعدِ enhance مقدار را بی‌صدا ست می‌کند
        setTimeout(refresh, 400);
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('select:not([data-sb-done])').forEach(function (s) {
            try { enhance(s); } catch (e) { /* یک selectِ خراب کلِ صفحه را نباید بشکند */ }
        });
    }

    function boot() {
        enhanceAll(document);
        // selectهایی که بعداً به DOM اضافه می‌شوند (مودال، ردیفِ پویا)
        new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                var added = muts[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var n = added[j];
                    if (n.nodeType !== 1) continue;
                    if (n.tagName === 'SELECT') { try { enhance(n); } catch (e) {} }
                    else if (n.querySelectorAll) enhanceAll(n);
                }
            }
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.SelectBox = { enhance: enhance, enhanceAll: enhanceAll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
