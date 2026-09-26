/**
 * initColumnResize — تغییر عرض ستون‌های جدول با درگ
 * 
 * @param {string} tableSelector  - CSS selector جدول (پیش‌فرض: '.table')
 * @param {number[]} defaultWidths - آرایه عرض اولیه ستون‌ها به پیکسل (اختیاری)
 *                                   مقدار 0 یعنی عرض طبیعی آن ستون حفظ شود
 * 
 * مثال:
 *   initColumnResize('.table', [50, 200, 100, 100, 110, 80, 80, 100, 100, 120]);
 */

// ─── متغیرهای گلوبال ───
window._isResizing = false;
window._lastResizeEnd = 0;

function initColumnResize(tableSelector, defaultWidths) {
    tableSelector = tableSelector || '.table';
    var table = document.querySelector(tableSelector);
    if (!table) return;

    // کلید یکتا برای localStorage بر اساس نام صفحه
    var storageKey = 'col_widths_' + (window.location.pathname.split('/').pop().replace('.php','') || 'table');

    // ─── مرحله ۱: اجباری کردن table-layout: fixed ───
    table.classList.add('resizable');
    table.style.tableLayout = 'fixed';
    table.style.width = '100%';

    var headers = Array.from(table.querySelectorAll('thead th'));

    // اولویت: ۱) localStorage  ۲) defaultWidths  ۳) عرض طبیعی
    var savedWidths = {};
    try { savedWidths = JSON.parse(localStorage.getItem(storageKey) || '{}'); } catch(e) {}

    headers.forEach(function(th, idx) {
        th.style.position = 'relative';

        // اعمال عرض
        if (savedWidths[idx]) {
            th.style.width    = savedWidths[idx] + 'px';
            th.style.minWidth = savedWidths[idx] + 'px';
        } else if (defaultWidths && defaultWidths[idx] && defaultWidths[idx] > 0) {
            th.style.width    = defaultWidths[idx] + 'px';
            th.style.minWidth = defaultWidths[idx] + 'px';
        }

        // اگر قبلا handle دارد، دوباره اضافه نکن
        if (th.querySelector('.resize-handle')) return;

        // ─── مرحله ۲: ایجاد resize handle ───
        var handle = document.createElement('div');
        handle.className = 'resize-handle';
        th.appendChild(handle);

        var startX, startWidth;

        // ---- Mouse ----
        handle.addEventListener('mousedown', function(e) {
            e.stopPropagation();
            e.preventDefault();

            window._isResizing = true;
            startX = e.clientX;
            startWidth = th.offsetWidth;
            handle.classList.add('resizing');
            document.body.style.cursor     = 'col-resize';
            document.body.style.userSelect = 'none';

            function onMouseMove(e) {
                var delta    = startX - e.clientX;
                var newWidth = Math.max(40, startWidth + delta);
                th.style.width    = newWidth + 'px';
                th.style.minWidth = newWidth + 'px';
            }

            function onMouseUp() {
                handle.classList.remove('resizing');
                document.body.style.cursor     = '';
                document.body.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                saveWidths();

                // ★ زمان پایان resize را ثبت کن
                window._lastResizeEnd = Date.now();

                // ★ با تأخیر ۴۰۰ms فلگ را false کن
                setTimeout(function() {
                    window._isResizing = false;
                }, 400);
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        // ---- Touch ----
        handle.addEventListener('touchstart', function(e) {
            e.stopPropagation();
            var touch = e.touches[0];
            startX     = touch.clientX;
            startWidth = th.offsetWidth;
            window._isResizing = true;
            handle.classList.add('resizing');

            function onTouchMove(e) {
                var t        = e.touches[0];
                var delta    = startX - t.clientX;
                var newWidth = Math.max(40, startWidth + delta);
                th.style.width    = newWidth + 'px';
                th.style.minWidth = newWidth + 'px';
            }
            function onTouchEnd() {
                handle.classList.remove('resizing');
                document.removeEventListener('touchmove', onTouchMove);
                document.removeEventListener('touchend', onTouchEnd);
                saveWidths();
                window._lastResizeEnd = Date.now();
                setTimeout(function() {
                    window._isResizing = false;
                }, 400);
            }
            document.addEventListener('touchmove', onTouchMove, { passive: false });
            document.addEventListener('touchend', onTouchEnd);
        }, { passive: false });

        // ---- دابل‌کلیک: reset ----
        handle.addEventListener('dblclick', function(e) {
            e.stopPropagation();
            if (defaultWidths && defaultWidths[idx] && defaultWidths[idx] > 0) {
                th.style.width    = defaultWidths[idx] + 'px';
                th.style.minWidth = defaultWidths[idx] + 'px';
            } else {
                th.style.width    = '';
                th.style.minWidth = '';
            }
            saveWidths();
        });
    });

    // ذخیره عرض ستون‌ها
    function saveWidths() {
        var widths = {};
        headers.forEach(function(h, i) {
            if (h.style.width) widths[i] = h.offsetWidth;
        });
        try { localStorage.setItem(storageKey, JSON.stringify(widths)); } catch(e) {}
    }
}

/**
 * ★ تابع کمکی — آیا الان در حال resize هستیم؟
 * از این تابع در handleSort استفاده کنید
 */
function isCurrentlyResizing() {
    if (window._isResizing) return true;
    if (Date.now() - (window._lastResizeEnd || 0) < 400) return true;
    return false;
}