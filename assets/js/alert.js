/**
 * showToast — نمایش پیغام toast
 *
 * @param {string}   message       - متن پیغام
 * @param {string}   type          - نوع: 'success' | 'error' | 'warning' | 'info'
 * @param {Object}   [options]     - تنظیمات اختیاری
 * @param {number}   [options.duration=5000]        - مدت نمایش (ms)
 * @param {Array}    [options.buttons]              - دکمه‌های سفارشی
 * @param {string}   [options.buttons[].label]      - متن دکمه
 * @param {string}   [options.buttons[].style]      - استایل: 'primary' | 'ghost'
 * @param {Function} [options.buttons[].onClick]    - تابع کلیک
 *
 * @example
 * // پیغام ساده
 * showToast('عملیات موفق بود', 'success');
 *
 * // پیغام با دکمه
 * showToast('آیا مطمئن هستید؟', 'warning', {
 *   buttons: [
 *     { label: 'بله', style: 'primary', onClick: () => doSomething() },
 *     { label: 'خیر', style: 'ghost' }
 *   ]
 * });
 */
// وقتی toast فعلی دکمه داره، کلید Enter باید دکمهٔ پیش‌فرض (primary) رو
// اجرا کنه — این listener سطح ماژوله تا موقع جایگزینی/بستن toast پاک بشه
// ⚠️ عمدا var نه let: چندین صفحه (مثل task-detail.php و requests.php)
// alert.js رو دوبار لود می‌کنن (یک‌بار از header.php، یک‌بار مستقیم خودشون).
// let/const با تکرار خودش توی همون scope هم SyntaxError می‌ده و کل اسکریپت
// (و در نتیجه کل صفحه) رو می‌شکنه؛ var در برابر لود دوباره امنه
var _toastEnterHandler = null;

function showToast(message, type = 'success', options = {}) {

    const { duration = 5000, buttons = [] } = options;

    // ─── تنظیمات هر نوع ───────────────────────────────────────
    const config = {
        success: {
            icon: 'check-circle-fill',
            color: '#1b7b39',
            bgColor: 'var(--toast-success-bg)',
            borderColor: '#1b7b39',
            label: 'موفق',
        },
        error: {
            icon: 'x-circle-fill',
            color: '#ef4444',
            bgColor: 'var(--toast-error-bg)',
            borderColor: '#ef4444',
            label: 'خطا',
        },
        warning: {
            icon: 'exclamation-triangle-fill',
            color: '#f59e0b',
            bgColor: 'var(--toast-warning-bg)',
            borderColor: '#f59e0b',
            label: 'اخطار',
        },
        info: {
            icon: 'info-circle-fill',
            color: '#3b82f6',
            bgColor: 'var(--toast-info-bg)',
            borderColor: '#3b82f6',
            label: 'اطلاعات',
        },
    };

    const { icon, color, bgColor, borderColor } = config[type] ?? config.info;

    // ─── حذف toast قبلی (اگر وجود دارد) ──────────────────────
    document.querySelector('.custom-toast')?.remove();
    if (_toastEnterHandler) {
        document.removeEventListener('keydown', _toastEnterHandler);
        _toastEnterHandler = null;
    }

    // ─── ساخت المان اصلی ──────────────────────────────────────
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;
    Object.assign(toast.style, {
        position: 'fixed',
        top: '80px',
        left: '20px',           // ◄ سمت چپ
        right: 'auto',           // ◄ غیرفعال کردن right از CSS
        minWidth: '300px',
        maxWidth: '420px',
        background: bgColor,
        borderRadius: '14px',
        padding: '16px 20px',
        boxShadow: '0 8px 32px rgba(0,0,0,0.13)',
        zIndex: '10000',
        borderRight: `4px solid ${borderColor}`,  // ◄ حاشیه راست (برای RTL)
        borderLeft: 'none',                         // ◄ حاشیه چپ خاموش
        transform: 'translateX(-100%)',
        transition: 'transform 0.4s ease, opacity 0.4s ease',
        opacity: '0',
        direction: 'rtl',
        fontFamily: 'Vazir, sans-serif',
    });

    // ─── ساخت محتوا ───────────────────────────────────────────
    const header = document.createElement('div');
    Object.assign(header.style, {
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
    });

    header.innerHTML = `
        <i class="bi bi-${icon}" style="color:${color}; font-size:1.3rem; "></i>
        <span style="font-weight:600; color:var(--text-strong);  ">${message}</span>
        <button class="toast-close-btn" style="
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 1.1rem; padding: 0; line-height:1;" title="بستن">
            <i class="bi bi-x-lg"></i>
        </button>
    `;

    toast.appendChild(header);

    // ─── دکمه‌های سفارشی ──────────────────────────────────────
    if (buttons.length > 0) {
        const btnRow = document.createElement('div');
        Object.assign(btnRow.style, {
            display: 'flex',
            gap: '8px',
            marginTop: '12px',
            justifyContent: 'flex-end',
        });

        let primaryBtnEl = null;

        buttons.forEach(btn => {
            const el = document.createElement('button');
            el.textContent = btn.label ?? '';

            const isPrimary = (btn.style ?? 'ghost') === 'primary';
            Object.assign(el.style, {
                padding: '6px 16px',
                borderRadius: '8px',
                border: isPrimary ? 'none' : `1.5px solid ${color}`,
                background: isPrimary ? color : 'transparent',
                color: isPrimary ? 'white' : color,
                cursor: 'pointer',
                fontSize: '0.85rem',
                fontWeight: '600',
                fontFamily: 'Vazir, sans-serif',
                transition: 'opacity 0.2s',
            });
            el.onmouseenter = () => el.style.opacity = '0.8';
            el.onmouseleave = () => el.style.opacity = '1';

            el.onclick = () => {
                btn.onClick?.();
                closeToast();
            };

            if (isPrimary) primaryBtnEl = el;
            btnRow.appendChild(el);
        });

        toast.appendChild(btnRow);

        // ─── Enter صفحه‌کلید = کلیک دکمهٔ پیش‌فرض ─────────────
        // اگه هیچ دکمه‌ای style:'primary' نداشت، اولین دکمه پیش‌فرض حساب می‌شه
        const defaultBtnEl = primaryBtnEl || btnRow.firstElementChild;
        if (defaultBtnEl) {
            _toastEnterHandler = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    defaultBtnEl.click();
                }
            };
            document.addEventListener('keydown', _toastEnterHandler);
        }
    }

    // ─── نوار پیشرفت ──────────────────────────────────────────
    const progressBar = document.createElement('div');
    Object.assign(progressBar.style, {
        position: 'absolute',
        bottom: '0',
        right: '0',         // ◄ RTL
        height: '3px',
        width: '100%',
        background: color,
        borderRadius: '0 0 14px 14px',
        transformOrigin: 'right',     // ◄ RTL: از راست کم می‌شه
        transition: `transform ${duration}ms linear`,
    });
    toast.style.position = 'fixed';   // برای position نوار
    toast.style.overflow = 'hidden';
    toast.appendChild(progressBar);

    document.body.appendChild(toast);

    // ─── انیمیشن ورود ─────────────────────────────────────────
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
            // شروع نوار
            progressBar.style.transform = 'scaleX(0)';
        });
    });

    // ─── بستن toast ───────────────────────────────────────────
    let timer;

    function closeToast() {
        clearTimeout(timer);
        toast.style.transform = 'translateX(-420px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
        if (_toastEnterHandler) {
            document.removeEventListener('keydown', _toastEnterHandler);
            _toastEnterHandler = null;
        }
    }

    // دکمه X
    toast.querySelector('.toast-close-btn').onclick = closeToast;

    // بسته شدن خودکار
    timer = setTimeout(closeToast, duration);

    // متوقف کردن تایمر هنگام hover
    toast.onmouseenter = () => {
        clearTimeout(timer);
        progressBar.style.transition = 'none';
    };
    toast.onmouseleave = () => {
        progressBar.style.transition = `transform ${duration * 0.3}ms linear`;
        progressBar.style.transform = 'scaleX(0)';
        timer = setTimeout(closeToast, duration * 0.3);
    };

    return { close: closeToast };  // ◄ قابلیت بستن دستی از بیرون
}

/**
 * showInlineError — نمایش خطای پایدار داخل یک بخش از صفحه
 *
 * برخلاف showToast (که گذراست و برای نتیجهٔ یک عملیات مناسبه)، این تابع
 * برای وقتیه که بارگذاری یک لیست/جدول/بخش شکست می‌خوره: محتوای همون
 * بخش با یک پیغام خطا جایگزین می‌شه و تا تلاش بعدی همون‌جا می‌مونه —
 * چون اگه فقط toast نشون بدیم، خود بخش خالی/نصفه می‌مونه بدون توضیح.
 *
 * @param {string}          containerId    - id المانی که innerHTML‌ش جایگزین می‌شه
 * @param {string}          message        - متن خطا (خودکار escape می‌شه، برای جلوگیری از XSS)
 * @param {Object}          [opts]
 * @param {Function|string} [opts.onRetry]   - تابع یا نام تابع سراسری برای دکمهٔ «تلاش مجدد»؛ اگر ندید، دکمه نمایش داده نمی‌شه
 * @param {boolean}         [opts.asTableRow=false] - اگر true، به‌جای div یک <tr><td> می‌سازه (برای tbody)
 * @param {number}          [opts.colspan=1] - فقط وقتی asTableRow=true
 *
 * @example
 * showInlineError('activitiesContainer', 'خطا در بارگذاری', { onRetry: loadTodayActivities });
 * showInlineError('tasksTableBody', 'خطا در بارگذاری', { asTableRow: true, colspan: 11 });
 */
function showInlineError(containerId, message, opts = {}) {
    const el = document.getElementById(containerId);
    if (!el) return;

    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const safeMsg = esc(message);

    const retryId = 'inlineErrorRetry_' + containerId;
    const retryBtn = opts.onRetry
        ? `<button class="btn btn-primary btn-sm mt-2" id="${retryId}"><i class="bi bi-arrow-clockwise me-2"></i>تلاش مجدد</button>`
        : '';

    const body = `
        <div class="empty-state">
            <i class="bi bi-exclamation-triangle text-danger"></i>
            <h5 class="text-danger">خطا</h5>
            <p>${safeMsg}</p>
            ${retryBtn}
        </div>
    `;

    el.innerHTML = opts.asTableRow
        ? `<tr><td colspan="${opts.colspan || 1}">${body}</td></tr>`
        : body;

    if (opts.onRetry) {
        const btn = document.getElementById(retryId);
        const handler = typeof opts.onRetry === 'function' ? opts.onRetry : window[opts.onRetry];
        if (btn && typeof handler === 'function') btn.addEventListener('click', handler);
    }
}