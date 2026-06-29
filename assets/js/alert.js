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
function showToast(message, type = 'success', options = {}) {

    const { duration = 5000, buttons = [] } = options;

    // ─── تنظیمات هر نوع ───────────────────────────────────────
    const config = {
        success: {
            icon:        'check-circle-fill',
            color:       '#10b981',
            bgColor:     '#f0fdf4',
            borderColor: '#10b981',
            label:       'موفق',
        },
        error: {
            icon:        'x-circle-fill',
            color:       '#ef4444',
            bgColor:     '#fef2f2',
            borderColor: '#ef4444',
            label:       'خطا',
        },
        warning: {
            icon:        'exclamation-triangle-fill',
            color:       '#f59e0b',
            bgColor:     '#fffbeb',
            borderColor: '#f59e0b',
            label:       'اخطار',
        },
        info: {
            icon:        'info-circle-fill',
            color:       '#3b82f6',
            bgColor:     '#fff5f5ff',
            borderColor: '#3b82f6',
            label:       'اطلاعات',
        },
    };

    const { icon, color, bgColor, borderColor } = config[type] ?? config.info;

    // ─── حذف toast قبلی (اگر وجود دارد) ──────────────────────
    document.querySelector('.custom-toast')?.remove();

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
        display:    'flex',
        alignItems: 'center',
        gap:        '10px',
    });

    header.innerHTML = `
        <i class="bi bi-${icon}" style="color:${color}; font-size:1.3rem; "></i>
        <span style="font-weight:600; color:#1e293b;  ">${message}</span>
        <button class="toast-close-btn" style="
            background: none; border: none; cursor: pointer;
            color: #94a3b8; font-size: 1.1rem; padding: 0; line-height:1;" title="بستن">
            <i class="bi bi-x-lg"></i>
        </button>
    `;

    toast.appendChild(header);

    // ─── دکمه‌های سفارشی ──────────────────────────────────────
    if (buttons.length > 0) {
        const btnRow = document.createElement('div');
        Object.assign(btnRow.style, {
            display:    'flex',
            gap:        '8px',
            marginTop:  '12px',
            justifyContent: 'flex-end',
        });

        buttons.forEach(btn => {
            const el = document.createElement('button');
            el.textContent = btn.label ?? '';

            const isPrimary = (btn.style ?? 'ghost') === 'primary';
            Object.assign(el.style, {
                padding:      '6px 16px',
                borderRadius: '8px',
                border:       isPrimary ? 'none' : `1.5px solid ${color}`,
                background:   isPrimary ? color : 'transparent',
                color:        isPrimary ? 'white' : color,
                cursor:       'pointer',
                fontSize:     '0.85rem',
                fontWeight:   '600',
                fontFamily:   'Vazir, sans-serif',
                transition:   'opacity 0.2s',
            });
            el.onmouseenter = () => el.style.opacity = '0.8';
            el.onmouseleave = () => el.style.opacity = '1';

            el.onclick = () => {
                btn.onClick?.();
                closeToast();
            };

            btnRow.appendChild(el);
        });

        toast.appendChild(btnRow);
    }

    // ─── نوار پیشرفت ──────────────────────────────────────────
    const progressBar = document.createElement('div');
    Object.assign(progressBar.style, {
        position:        'absolute',
        bottom:          '0',
        right:           '0',         // ◄ RTL
        height:          '3px',
        width:           '100%',
        background:      color,
        borderRadius:    '0 0 14px 14px',
        transformOrigin: 'right',     // ◄ RTL: از راست کم می‌شه
        transition:      `transform ${duration}ms linear`,
    });
    toast.style.position = 'fixed';   // برای position نوار
    toast.style.overflow = 'hidden';
    toast.appendChild(progressBar);

    document.body.appendChild(toast);

    // ─── انیمیشن ورود ─────────────────────────────────────────
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity   = '1';
            // شروع نوار
            progressBar.style.transform = 'scaleX(0)';
        });
    });

    // ─── بستن toast ───────────────────────────────────────────
    let timer;

    function closeToast() {
        clearTimeout(timer);
        toast.style.transform = 'translateX(-420px)';
        toast.style.opacity   = '0';
        setTimeout(() => toast.remove(), 400);
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
        progressBar.style.transform  = 'scaleX(0)';
        timer = setTimeout(closeToast, duration * 0.3);
    };

    return { close: closeToast };  // ◄ قابلیت بستن دستی از بیرون
}