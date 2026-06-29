// /assets/js/undo-toast.js — Toast بازگردانی مشترک (هماهنگ با استایل پروژه)
(function () {
    let el = null, timer = null;

    function ensureEl() {
        if (el) return el;
        el = document.createElement('div');
        el.className = 'toast-notification';
        el.innerHTML = `
            <div class="toast-header">
                <i class="bi bi-trash" style="color:#744ca4;"></i>
                <span class="undo-toast-title">حذف شد</span>
                <button type="button" class="undo-toast-close"
                    style="margin-right:auto;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1rem;line-height:1;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="toast-body">
                <span class="undo-toast-msg">با موفقیت حذف شد</span>
                <div style="margin-top:10px;">
                    <button type="button" class="undo-toast-btn"
                        style="display:inline-flex;align-items:center;gap:6px;background:none;border:none;color:#744ca4;font-weight:600;font-size:12.5px;cursor:pointer;padding:0;">
                        <i class="bi bi-arrow-counterclockwise"></i> بازگرداندن
                    </button>
                </div>
            </div>`;
        document.body.appendChild(el);
        el.querySelector('.undo-toast-close').addEventListener('click', hide);
        return el;
    }

    function hide() {
        if (!el) return;
        el.classList.remove('show');
        clearTimeout(timer);
    }

    // تابع عمومی: showUndoToast({ message, onUndo, title, duration })
    window.showUndoToast = function ({ message, onUndo, title = 'حذف شد', duration = 6000 }) {
        const node = ensureEl();
        node.querySelector('.undo-toast-title').textContent = title;
        node.querySelector('.undo-toast-msg').textContent   = message || 'با موفقیت حذف شد';

        const btn   = node.querySelector('.undo-toast-btn');
        const fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', async () => {
            hide();
            if (typeof onUndo === 'function') await onUndo();
        });

        node.classList.add('show');
        clearTimeout(timer);
        timer = setTimeout(hide, duration);
    };

    window.hideUndoToast = hide;
})();