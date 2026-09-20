/* ==== bundle: common.js + table-utils.js + date-utils.js + alert.js + subscription-toast.js ==== */
/* ---- common.js ---- */
/**
 * assets/js/common.js
 * توابعِ کوچکِ سراسریِ اپ که قبلاً هرکدوم جداگانه در دوجین فایل تکرار می‌شدن.
 * هدف: از این به بعد، هر صفحه‌ی جدید همینجا رو صدا بزنه به‌جایِ کپی‌کردنِ
 * دوباره‌ی همین چندخط — دقیقاً همون الگویی که باعث شد یک دور کاملِ این
 * جلسه صرفِ پیداکردنِ نقاطِ فراموش‌شده در ۱۲+ فایل بشه.
 *
 * ⚠️ نکته‌ی حیاتی: فقط toFa رو این‌جا به‌عنوانِ function سراسری تعریف می‌کنیم
 * (نه enTofaNumber/faNum). چون هر صفحه‌ای که این اسم‌ها رو صدا می‌زنه، از قبل
 * نسخه‌ی محلیِ خودش رو داره — بعضی‌هاشون با «const faNum = ...» (نه function).
 * اگه این‌جا هم به‌صورتِ سراسری با let/const alias می‌ذاشتیم، چون یک شناسه
 * نمی‌تونه هم‌زمان هم توسطِ let/const هم توسطِ چیزِ دیگه‌ای (حتی function)
 * در همون scope تعریف بشه، دقیقاً همین باعثِ کرشِ کاملِ صفحه با
 * «Identifier has already been declared» می‌شد (چیزی که واقعاً هم افتاد).
 * function toFa با function/var هم‌نامِ خودش تداخلی نداره (redeclare سالم)،
 * پس این یکی امنه؛ فقط همینو سراسری نگه می‌داریم.
 */

// تبدیلِ اعدادِ لاتین به فارسی — برایِ هر عددی که قراره به کاربر نمایش داده بشه
function toFa(n) {
    if (n === null || n === undefined || n === '') return '';
    return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
}

// نام‌های تاریخیِ همین «تبدیلِ رقمِ لاتین ← فارسی» که به‌صورتِ کپیِ محلی در
// ۲۰+ صفحه با اسم‌های مختلف تعریف شده بودند. این‌جا بدنه‌شان *دقیقاً* برابرِ
// همان نسخه‌های محلی است (نه delegate به toFa) تا حذفِ کپیِ محلیِ صفحات
// اثباتاً بدونِ تغییرِ رفتار باشد — از جمله رفتارِ لبه: X(null) → 'null'.
// بررسی شد: هیچ‌کدام در پروژه با const/let تعریف نشده‌اند → redeclareِ محلی
// سالم است. (faNum عمداً این‌جا نیست: چند صفحه «const faNum = ...» دارند.)
function toPersian(n)    { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function faDigits(s)     { return String(s == null ? '' : s).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function toFaDigits(n)   { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function toFaNum(n)      { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }
function enTofaNumber(n) { return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); }

// وقتی تأخیر ساعتی از ۲۴ ساعت گذشته، فقط عددِ ساعتِ خام (مثلاً «۸۲ ساعت»)
// خیلی خوانا نیست — این تابع تفکیکِ روز+ساعت رو به‌صورتِ پرانتزی برمی‌گردونه
// (مثلاً « (۳ روز و ۱۰ ساعت)») تا کنارِ متنِ اصلی اضافه بشه؛ اگه کمتر از
// ۲۴ ساعت باشه رشته‌ی خالی برمی‌گردونه (یعنی چیزی اضافه نشه)
function formatHourDelayBreakdown(hours) {
    hours = Number(hours) || 0;
    if (hours < 24) return '';
    const days = Math.floor(hours / 24);
    const remHours = hours % 24;
    let text = toFa(days) + ' روز';
    if (remHours > 0) text += ' و ' + toFa(remHours) + ' ساعت';
    return ' (' + text + ')';
}

// نامِ ماه‌های شمسی — مرجعِ یگانه (به‌جای ~۱۷ کپیِ محلی: months / persianMonths / J_MONTHS).
// صفحات: «const months = FA_MONTHS;» — بدونِ تغییرِ محلِ استفاده. (TimeSync.jMonthName(m)
// هم همین را می‌دهد ولی این آرایه برای index مستقیم مثلِ months[jm-1] دم‌دست‌تر است.)
var FA_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

// escape کردنِ رشته قبل از تزریق در innerHTML — جلوگیری از XSS
// بررسی شد: هرجا در پروژه از قبل «esc» تعریف شده، یا خودش function است
// (redeclare سالم، override می‌شه) یا داخلِ scope محلی/IIFE است (تداخلی
// با نسخه‌ی سراسری نداره) — پس این تعریف برایِ صفحاتی که هنوز escape
// محلی ندارن، امن اضافه می‌شه.
function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// escape برایِ مقادیری که داخلِ رشته‌یِ جاوااسکریپتِ تک‌کوتیشن در یک
// attribute مثلِ onclick="fn('${x}')" قرار می‌گیرن. توجه: esc() معمولی
// این‌جا کافی نیست — چون &#39; که esc() تولید می‌کنه، توسطِ HTML parser
// قبل از این‌که JS parser بخونتش decode میشه و دوباره ' خام می‌شه (یعنی
// escape خنثی می‌شه). این تابع اول escape سطحِ رشته‌ی JS رو انجام می‌ده
// (backslash, quote, newline) که HTML entity decoding خرابش نمی‌کنه.
function escJsAttr(s) {
    return String(s ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\n/g, '\\n')
        .replace(/\r/g, '\\r')
        .replace(/</g, '\\u003C')
        .replace(/>/g, '\\u003E');
}

/* ---- table-utils.js ---- */
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

        // اگر قبلاً handle دارد، دوباره اضافه نکن
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
/* ---- date-utils.js ---- */
// ابزارِ تاریخِ محلی — جایگزینِ امنِ toISOString برای «تاریخِ تقویمی»
// چرا؟ toISOString زمان را به UTC می‌برد و در ایران یک روز عقب می‌اندازد.
function toLocalYMD(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}
function todayLocal() {
    return toLocalYMD(new Date());
}
/* ---- alert.js ---- */
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
// وقتی toastِ فعلی دکمه داره، کلیدِ Enter باید دکمهٔ پیش‌فرض (primary) رو
// اجرا کنه — این listener سطحِ ماژوله تا موقعِ جایگزینی/بستنِ toast پاک بشه
// ⚠️ عمداً var نه let: چندین صفحه (مثلِ task-detail.php و requests.php)
// alert.js رو دوبار لود می‌کنن (یک‌بار از header.php، یک‌بار مستقیم خودشون).
// let/const با تکرارِ خودش تویِ همون scope هم SyntaxError می‌ده و کلِ اسکریپت
// (و در نتیجه کلِ صفحه) رو می‌شکنه؛ var در برابرِ لودِ دوباره امنه
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

        // ─── Enterِ صفحه‌کلید = کلیکِ دکمهٔ پیش‌فرض ─────────────
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
 * showInlineError — نمایشِ خطای پایدار داخلِ یک بخش از صفحه
 *
 * برخلافِ showToast (که گذراست و برایِ نتیجهٔ یک عملیات مناسبه)، این تابع
 * برایِ وقتیه که بارگذاریِ یک لیست/جدول/بخش شکست می‌خوره: محتوایِ همون
 * بخش با یک پیغامِ خطا جایگزین می‌شه و تا تلاشِ بعدی همون‌جا می‌مونه —
 * چون اگه فقط toast نشون بدیم، خودِ بخش خالی/نصفه می‌مونه بدونِ توضیح.
 *
 * @param {string}          containerId    - id المانی که innerHTML‌ش جایگزین می‌شه
 * @param {string}          message        - متنِ خطا (خودکار escape می‌شه، برایِ جلوگیری از XSS)
 * @param {Object}          [opts]
 * @param {Function|string} [opts.onRetry]   - تابع یا نامِ تابعِ سراسری برایِ دکمهٔ «تلاش مجدد»؛ اگر ندید، دکمه نمایش داده نمی‌شه
 * @param {boolean}         [opts.asTableRow=false] - اگر true، به‌جایِ div یک <tr><td> می‌سازه (برایِ tbody)
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
/* ---- subscription-toast.js ---- */
/* ============================================================
   فایل: assets/js/subscription-toast.js
   هدف: نمایش toast هشدار انقضای اشتراک سازمان
   
   کاملاً مستقل — نیازی به alert.js یا هیچ فایل دیگری ندارد
   
   منطق:
     - هر بار صفحه لود میشه، API رو چک می‌کنه
     - اگر days_remaining <= 3 باشه، toast نمایش میده
     - کاربر "متوجه شدم" بزنه → localStorage ذخیره میشه
       و تا 24 ساعت دیگه نمایش داده نمیشه
     - بعد 24 ساعت دوباره نمایش داده میشه
     - اگه اشتراک تمدید بشه → خودکار پاک میشه
   ============================================================ */

(function () {

    /* ثابت‌ها */
    var STORAGE_KEY      = 'subscription_toast_dismissed_at';
    var DISMISS_DURATION = 24 * 60 * 60 * 1000;   // 24 ساعت
    var WARNING_DAYS     = 3;
    var TOAST_DURATION   = 60000;                  // 60 ثانیه نمایش
    var API_URL          = '/api/organization/check-subscription.php';

    /* تبدیلِ اعدادِ لاتین به فارسی، برایِ حفظِ اصلِ «مستقل» بودنِ این فایل */
    function toFa(n) {
        return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
    }

    /* ──────────────────────────────────────────── */
    /*  بررسی اصلی                                  */
    /* ──────────────────────────────────────────── */
    function checkSubscriptionExpiry() {

        var authToken = localStorage.getItem('auth_token');
        if (!authToken) return;

        // آیا قبلاً dismiss شده و هنوز 24 ساعت نگذشته؟
        var dismissedAt = localStorage.getItem(STORAGE_KEY);
        if (dismissedAt) {
            var elapsed = Date.now() - parseInt(dismissedAt, 10);
            if (elapsed < DISMISS_DURATION) {
                return;
            }
        }

        fetch(API_URL, {
            method: 'GET',
            headers: { 'Authorization': 'Bearer ' + authToken }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {

            if (!data.success) return;

            if (!data.has_subscription) {
                showSubscriptionToast(-1, 'no_subscription');
                return;
            }

            if (data.status === 'expired') {
                showSubscriptionToast(data.days_remaining, 'expired');
                return;
            }

            if (data.days_remaining <= WARNING_DAYS) {
                showSubscriptionToast(data.days_remaining, 'expiring_soon');
                return;
            }

            // اشتراک خوبه → پاک کردن dismiss قبلی
            localStorage.removeItem(STORAGE_KEY);
        })
        .catch(function (err) {
            console.error('subscription-toast: خطا', err);
        });
    }

    /* ──────────────────────────────────────────── */
    /*  نمایش toast (کاملاً مستقل)                   */
    /* ──────────────────────────────────────────── */
    function showSubscriptionToast(daysRemaining, status) {

        // حذف toast قبلی
        var existing = document.getElementById('subscription-expiry-toast');
        if (existing) existing.remove();

        // تعیین متن و رنگ
        var message, icon, bgColor, borderColor, iconColor;

        if (status === 'expired' || status === 'no_subscription') {
            message     = 'اشتراک سازمان شما منقضی شده است. لطفا برای تمدید اشتراک با پشتیبانی تماس بگیرید.';
            icon        = 'bi-exclamation-octagon-fill';
            bgColor     = '#fef2f2';
            borderColor = '#ef4444';
            iconColor   = '#ef4444';
        } else if (daysRemaining <= 0) {
            message     = 'اشتراک سازمان شما امروز منقضی می‌شود! لطفا برای تمدید با پشتیبانی تماس بگیرید.';
            icon        = 'bi-exclamation-octagon-fill';
            bgColor     = '#fef2f2';
            borderColor = '#ef4444';
            iconColor   = '#ef4444';
        } else if (daysRemaining === 1) {
            message     = 'اشتراک سازمان شما فردا منقضی می‌شود. لطفا برای خرید یا تمدید اشتراک با پشتیبانی ارتباط بگیرید.';
            icon        = 'bi-exclamation-triangle-fill';
            bgColor     = '#fffbeb';
            borderColor = '#f59e0b';
            iconColor   = '#f59e0b';
        } else {
            message     = 'اشتراک سازمان شما ' + toFa(daysRemaining) + ' روز دیگر منقضی می‌شود. لطفا برای خرید یا تمدید اشتراک با پشتیبانی ارتباط بگیرید.';
            icon        = 'bi-exclamation-triangle-fill';
            bgColor     = '#fffbeb';
            borderColor = '#f59e0b';
            iconColor   = '#f59e0b';
        }

        // ساخت المان toast
        var toast = document.createElement('div');
        toast.id = 'subscription-expiry-toast';
        toast.style.cssText =
            'position:fixed;' +
            'top:80px;' +
            'left:20px;' +
            'right:auto;' +
            'min-width:320px;' +
            'max-width:440px;' +
            'background:' + bgColor + ';' +
            'border-radius:14px;' +
            'padding:16px 20px;' +
            'box-shadow:0 8px 32px rgba(0,0,0,0.15);' +
            'z-index:10000;' +
            'border-right:4px solid ' + borderColor + ';' +
            'border-left:none;' +
            'transform:translateX(-120%);' +
            'transition:transform 0.4s ease, opacity 0.4s ease;' +
            'opacity:0;' +
            'direction:rtl;' +
            'font-family:Vazir,sans-serif;' +
            'overflow:hidden;';

        // ردیف آیکن + متن
        var header = document.createElement('div');
        header.style.cssText = 'display:flex;align-items:flex-start;gap:10px;';
        header.innerHTML =
            '<i class="bi ' + icon + '" style="color:' + iconColor + ';font-size:1.4rem;flex-shrink:0;margin-top:2px;"></i>' +
            '<span style="font-weight:600;color:#1e293b;font-size:0.88rem;line-height:1.7;">' + message + '</span>';
        toast.appendChild(header);

        // ردیف دکمه‌ها
        var btnRow = document.createElement('div');
        btnRow.style.cssText = 'display:flex;gap:8px;margin-top:14px;justify-content:flex-end;';

        // دکمه "متوجه شدم"
        var dismissBtn = document.createElement('button');
        dismissBtn.textContent = 'متوجه شدم';
        dismissBtn.style.cssText =
            'padding:7px 20px;' +
            'border-radius:8px;' +
            'border:none;' +
            'background:' + borderColor + ';' +
            'color:white;' +
            'cursor:pointer;' +
            'font-size:0.85rem;' +
            'font-weight:600;' +
            'font-family:Vazir,sans-serif;' +
            'transition:opacity 0.2s;';
        dismissBtn.onmouseenter = function () { this.style.opacity = '0.85'; };
        dismissBtn.onmouseleave = function () { this.style.opacity = '1'; };
        dismissBtn.onclick = function () {
            localStorage.setItem(STORAGE_KEY, String(Date.now()));
            closeToast();
        };

        // دکمه بستن
        var closeXBtn = document.createElement('button');
        closeXBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
        closeXBtn.title = 'بستن';
        closeXBtn.style.cssText =
            'padding:7px 12px;' +
            'border-radius:8px;' +
            'border:1.5px solid ' + borderColor + ';' +
            'background:transparent;' +
            'color:' + borderColor + ';' +
            'cursor:pointer;' +
            'font-size:0.85rem;' +
            'font-family:Vazir,sans-serif;' +
            'transition:opacity 0.2s;';
        closeXBtn.onmouseenter = function () { this.style.opacity = '0.7'; };
        closeXBtn.onmouseleave = function () { this.style.opacity = '1'; };
        closeXBtn.onclick = function () {
            closeToast();   // فقط بستن، بدون ذخیره — دفعه بعد باز نشون میده
        };

        btnRow.appendChild(dismissBtn);
        btnRow.appendChild(closeXBtn);
        toast.appendChild(btnRow);

        // نوار پیشرفت
        var progressBar = document.createElement('div');
        progressBar.style.cssText =
            'position:absolute;' +
            'bottom:0;' +
            'right:0;' +
            'height:3px;' +
            'width:100%;' +
            'background:' + borderColor + ';' +
            'border-radius:0 0 14px 14px;' +
            'transform-origin:right;' +
            'transition:transform ' + TOAST_DURATION + 'ms linear;';
        toast.appendChild(progressBar);

        document.body.appendChild(toast);

        // انیمیشن ورود
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.style.transform = 'translateX(0)';
                toast.style.opacity   = '1';
                progressBar.style.transform = 'scaleX(0)';
            });
        });

        // بسته شدن خودکار
        var autoCloseTimer = setTimeout(closeToast, TOAST_DURATION);

        // hover: متوقف کردن تایمر
        toast.onmouseenter = function () {
            clearTimeout(autoCloseTimer);
            progressBar.style.transition = 'none';
        };
        toast.onmouseleave = function () {
            var remaining = TOAST_DURATION * 0.3;
            progressBar.style.transition = 'transform ' + remaining + 'ms linear';
            progressBar.style.transform  = 'scaleX(0)';
            autoCloseTimer = setTimeout(closeToast, remaining);
        };

        function closeToast() {
            clearTimeout(autoCloseTimer);
            toast.style.transform = 'translateX(-120%)';
            toast.style.opacity   = '0';
            setTimeout(function () {
                if (toast.parentNode) toast.remove();
            }, 400);
        }
    }

    /* ──────────────────────────────────────────── */
    /*  اجرای خودکار                                */
    /* ──────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(checkSubscriptionExpiry, 2000);
        });
    } else {
        setTimeout(checkSubscriptionExpiry, 2000);
    }

    window.checkSubscriptionExpiry = checkSubscriptionExpiry;

})();