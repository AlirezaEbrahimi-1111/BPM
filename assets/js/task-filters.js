/**
 * ═══════════════════════════════════════════════════════════════════
 *  task-filters.js  —  منطق مشترک فیلتر، تاریخ و وضعیت کارها
 *  محل: assets/js/task-filters.js
 * ───────────────────────────────────────────────────────────────────
 *  چرا این فایل؟
 *  تا امروز، منطق «کار امروز است؟»، «کار عقب‌افتاده است؟» و جدول
 *  برچسب وضعیت‌ها در ۵-۶ فایل جداگانه تکرار شده بود. هر بار که یک
 *  وضعیت یا قانون جدید اضافه می‌شد، باید همه‌جا دستی عوض می‌شد و
 *  معمولاً یکی از قلم می‌افتاد.
 *
 *  از این به بعد: هر تغییری در این قوانین، فقط همین‌جا انجام می‌شود.
 * ───────────────────────────────────────────────────────────────────
 *  نحوهٔ استفاده در هر صفحه (قبل از اسکریپت خودِ صفحه):
 *      <script src="../assets/js/task-filters.js"></script>
 *
 *  سپس:
 *      TF.isDueToday(task, currentUser)
 *      TF.isOverdue(task, currentUser)
 *      TF.statusBadge(task)
 * ═══════════════════════════════════════════════════════════════════
 */

window.TF = (function () {
    'use strict';

    /* ═══════════════════════════════════════════════════
       ۱) کمکی‌های تاریخ
       ═══════════════════════════════════════════════════ */

    /** تاریخ امروز به شکل 'YYYY-MM-DD' (وقت محلی، نه UTC) */
    function today() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
    }

    /** بریدن بخش ساعت از تاریخ ('2026-07-11 14:30' → '2026-07-11') */
    function dateOnly(v) {
        if (!v) return '';
        return String(v).split(' ')[0].split('T')[0];
    }

    /** تبدیل ارقام انگلیسی به فارسی */
    function toFa(n) {
        if (n === null || n === undefined || n === '') return '۰';
        return String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    }


    /* ═══════════════════════════════════════════════════
       ۲) تاریخ مؤثر سررسید
       ═══════════════════════════════════════════════════ */

    /**
     * «تاریخ سررسید مؤثر» یک کار را برمی‌گرداند.
     *
     *  • کار دوره‌ای (continuous):
     *      next_due_date که سرور حساب کرده (و اگر نبود، start_date)
     *
     *  • کار مقطعی (periodic):
     *      بزرگ‌ترین تاریخ بین due_date / deadline / original_deadline
     *      (چون تمدید موعد ممکن است یکی از این‌ها را جلو برده باشد)
     */
    function effectiveDue(t) {
        if (!t) return '';

        if (t.task_type === 'continuous') {
            return dateOnly(t.next_due_date) || dateOnly(t.start_date) || '';
        }

        const dates = [t.due_date, t.deadline, t.original_deadline]
            .map(dateOnly)
            .filter(Boolean);

        return dates.length ? dates.sort().pop() : '';   // بزرگ‌ترین
    }


    /* ═══════════════════════════════════════════════════
       ۳) وضعیت‌های پایه
       ═══════════════════════════════════════════════════ */

    /** آیا کار تمام شده است؟ (دیگر نه امروز است، نه عقب‌افتاده) */
    function isDone(t) {
        return t.status === 'completed' || t.status === 'approved';
    }

    /** آیا بازهٔ کار دوره‌ای به پایان رسیده؟ */
    function isExpired(t, todayStr) {
        const td = todayStr || today();
        return !!(t.end_date && dateOnly(t.end_date) < td);
    }

    /** آیا کار منتظر تأیید همین کاربر است؟ */
    function isWaitingMyApproval(t, user) {
        if (!user || t.is_pending_approval != 1) return false;
        return user.id == t.creator_id || user.id == t.current_approver_id;
    }

    /** آیا درخواست تمدید موعد منتظر تأیید همین کاربر است؟ */
    function isWaitingMyDeadline(t, user) {
        if (!user || t.has_pending_deadline_request != 1) return false;
        return user.id == t.current_approver_id;
    }


    /* ═══════════════════════════════════════════════════
       ۴) فیلتر «امروز»
       ═══════════════════════════════════════════════════ */

    /**
     * آیا این کار امروز نیاز به اقدام دارد؟
     *
     * ⚠️ نکتهٔ مهم: «امروز» با «عقب‌افتاده» فرق دارد.
     *    کار دوره‌ای که دوره‌اش دقیقاً امروز سررسید شده (ولی هنوز
     *    عقب نیفتاده) هم باید در «امروز» دیده شود. این همان باگی بود
     *    که کارهای دوره‌ای پس از «رفع معوقه» ناپدید می‌شدند.
     */
    function isDueToday(t, user, todayStr) {
        const td = todayStr || today();

        if (isDone(t)) return false;

        // منتظر تأیید من (فارغ از تاریخ)
        if (isWaitingMyApproval(t, user)) return true;

        // منتظر تأیید تمدید موعد توسط من (فارغ از تاریخ)
        if (isWaitingMyDeadline(t, user)) return true;

        // کارهای فرآیندی که در جریان‌اند
        if (t.is_workflow_task == 1 &&
            (t.status === 'in_progress' || t.status === 'not_started')) {
            return true;
        }

        // ── کار دوره‌ای ──────────────────────────────
        if (t.task_type === 'continuous') {
            if (isExpired(t, td)) return false;

            // ۱) دورهٔ معوقه دارد
            if ((t.overdue_periods || 0) > 0) return true;

            // ۲) دورهٔ بعدی دقیقاً امروز سررسید شده
            if (effectiveDue(t) === td) return true;

            return false;
        }

        // ── کار مقطعی ────────────────────────────────
        if (t.task_type === 'periodic') {
            const due = effectiveDue(t);
            return !!due && due <= td;     // امروز یا گذشته
        }

        return false;
    }


    /* ═══════════════════════════════════════════════════
       ۵) فیلتر «عقب افتاده»
       ═══════════════════════════════════════════════════ */

    /** آیا این کار عقب افتاده است؟ */
    function isOverdue(t, user, todayStr) {
        const td = todayStr || today();

        if (isDone(t)) return false;

        // منتظر تأیید من، و از قبلِ امروز منتظر مانده
        if (isWaitingMyApproval(t, user) &&
            t.last_pending_date &&
            dateOnly(t.last_pending_date) < td) {
            return true;
        }

        // درخواست تمدید موعد که از قبلِ امروز منتظر مانده
        if (isWaitingMyDeadline(t, user) &&
            t.deadline_request_date &&
            dateOnly(t.deadline_request_date) < td) {
            return true;
        }

        // کار فرآیندی
        if (t.is_workflow_task == 1) {
            const due = effectiveDue(t);
            if (due && due < td) return true;
        }

        // ── کار دوره‌ای ──────────────────────────────
        if (t.task_type === 'continuous') {
            if (isExpired(t, td)) return false;
            return (t.overdue_periods || 0) > 0;
        }

        // ── کار مقطعی ────────────────────────────────
        if (t.task_type === 'periodic') {
            const due = effectiveDue(t);
            // 🔒 وضعیتِ 'delegated' هم باید اینجا حساب بشه: بعد از ارجاع (حتی
            // ارجاعِ برگشتی به خودِ تعریف‌کننده)، status در دیتابیس همچنان
            // 'delegated' می‌مونه — اگه اینجا لحاظ نشه، کارِ عقب‌افتاده‌ای که
            // الان واقعاً مسئولش کاربرِ جاریه، به‌جایِ «عقب افتاده»، همچنان
            // برچسبِ نامربوطِ «ارجاع شده» رو نشون می‌ده
            return !!due && due < td &&
                   (t.status === 'not_started' || t.status === 'in_progress' || t.status === 'delegated');
        }

        return false;
    }


    /* ═══════════════════════════════════════════════════
       ۶) وضعیت‌ها (برچسب و رنگ)
       ═══════════════════════════════════════════════════ */

    /**
     * جدول وضعیت‌ها — تنها مرجع.
     * برای افزودن وضعیت جدید، فقط یک ردیف اینجا اضافه کنید.
     */
    const statusCfg = {
        not_started:           { label: 'شروع نشده',        cls: 'status-not_started' },
        in_progress:           { label: 'در حال انجام',      cls: 'status-in_progress' },
        pending_approval:      { label: 'در انتظار تایید',   cls: 'status-pending_approval' },
        completed:             { label: 'تکمیل شده',         cls: 'status-completed' },
        approved:              { label: 'تأیید شده',         cls: 'status-approved' },
        delegated:             { label: 'ارجاع شده',         cls: 'status-delegated' },
        rejected:              { label: 'متوقف',             cls: 'status-rejected' },
        stopped:               { label: 'متوقف شده',         cls: 'status-stopped' },
        period_done:           { label: 'دوره انجام شد',     cls: 'status-period_done' },
        termination_requested: { label: 'در انتظار اتمام',   cls: 'status-termination_requested' }
    };

    /** برچسب فارسی یک وضعیت */
    function statusLabel(status) {
        return (statusCfg[status] && statusCfg[status].label) || status || '—';
    }

    /** کلاس CSS یک وضعیت */
    function statusClass(status) {
        return (statusCfg[status] && statusCfg[status].cls) || 'status-not_started';
    }

    /**
     * HTML آمادهٔ برچسب وضعیت.
     * اگر کار عقب‌افتاده باشد، برچسب «عقب افتاده» اولویت دارد — به‌جز وقتی
     * وضعیت «در انتظار تأیید» است: مثلاً وقتی خودِ کاربرِ جاری تأییدکننده است
     * و دیر در تأییدکردن است، isOverdue() هم true برمی‌گردد؛ ولی چیزی که
     * الان واقعاً باید به کاربر گفته بشه اینه که باید تأیید کنه، نه صرفاً
     * اینکه کار «عقب افتاده»— پس «در انتظار تأیید» اولویتِ بالاتری داره
     */
    function statusBadge(t, user) {
        if (t.status === 'pending_approval') {
            return `<span class="status-badge ${statusClass(t.status)}">${statusLabel(t.status)}</span>`;
        }
        if (isOverdue(t, user)) {
            return '<span class="status-badge status-overdue">عقب افتاده</span>';
        }
        // status='delegated' یعنی این کار به یه assigneeِ جدید ارجاع شده — ولی
        // از نگاهِ خودِ همون assigneeِ جدید (کاربرِ فعلی)، دیگه «ارجاع‌شده» معنی
        // نداره: نوبتِ خودشه که شروعش کنه، نه این‌که منتظرِ کسِ دیگه‌ای باشه.
        // بقیه‌ی سیستم (شروعِ خودکار با تیکِ چک‌لیست، دکمه‌ی «شروع کار» در
        // task-detail.php) هم دقیقاً delegated رو هم‌ردیفِ not_started می‌دونه،
        // پس برچسب هم باید همین رفتار رو داشته باشه
        if (t.status === 'delegated' && user && Number(t.assignee_id) === Number(user.id)) {
            return `<span class="status-badge ${statusClass('not_started')}">${statusLabel('not_started')}</span>`;
        }
        const s = t.status || 'not_started';
        return `<span class="status-badge ${statusClass(s)}">${statusLabel(s)}</span>`;
    }


    /* ═══════════════════════════════════════════════════
       ۷) خروجی عمومی
       ═══════════════════════════════════════════════════ */
    return {
        // تاریخ
        today,
        dateOnly,
        toFa,
        effectiveDue,

        // وضعیت پایه
        isDone,
        isExpired,
        isWaitingMyApproval,
        isWaitingMyDeadline,

        // فیلترها
        isDueToday,
        isOverdue,

        // وضعیت‌ها
        statusCfg,
        statusLabel,
        statusClass,
        statusBadge
    };
})();