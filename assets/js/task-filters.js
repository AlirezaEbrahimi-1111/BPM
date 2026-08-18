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

    /**
     * آیا این کارِ دوره‌ای نیازمندِ تصمیمِ تمدید است؟ — از قبل توسطِ
     * enrichTaskDates() سمتِ سرور (بر پایه‌یِ ساعتِ سرور) رویِ کار محاسبه
     * شده؛ این‌جا فقط خونده می‌شه
     */
    function needsRenewalDecision(t) {
        return !!t.needs_renewal_decision;
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
            // ⏰ بازه‌اش تمام شده و هنوز تعیین‌تکلیف نشده → همیشه در «امروز»،
            // تا کاربر تصمیم بگیرد (تمدید یا اتمام)
            if (needsRenewalDecision(t)) return true;

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
        not_started:           { label: 'شروع نشده',        cls: 'status-not_started',           icon: 'circle' },
        in_progress:           { label: 'در حال انجام',      cls: 'status-in_progress',           icon: 'play-circle' },
        pending_approval:      { label: 'در انتظار تایید',   cls: 'status-pending_approval',      icon: 'hourglass-split' },
        completed:             { label: 'تکمیل شده',         cls: 'status-completed',             icon: 'check-circle' },
        approved:              { label: 'تأیید شده',         cls: 'status-approved',              icon: 'check-circle-fill' },
        delegated:             { label: 'ارجاع شده',         cls: 'status-delegated',             icon: 'arrow-left-right' },
        // 🆕 rejected/stopped عمداً از هم متمایز شدن — قبلاً «متوقف»/«متوقف شده»
        // بودن که تقریباً یک‌کلمه‌ای به‌نظر می‌رسیدن با اینکه دو معنیِ کاملاً
        // متفاوت دارن: rejected = تعریف‌کننده دستی یک کارِ دوره‌ای رو زودتر
        // از موعد تمام کرده؛ stopped = کار بخشی از یک فرآیند/روتین بوده که
        // کلِ اون فرآیند قبل از پایان، متوقف شده
        rejected:              { label: 'متوقف شده(کارهای عادی)', cls: 'status-rejected',          icon: 'pause-circle' },
        stopped:               { label: 'متوقف شده(فرآیندها)',    cls: 'status-stopped',           icon: 'stop-circle' },
        period_done:           { label: 'دوره انجام شد',     cls: 'status-period_done',           icon: 'calendar-check' },
        termination_requested: { label: 'در انتظار اتمام',   cls: 'status-termination_requested', icon: 'hourglass-split' }
    };

    /** برچسب فارسی یک وضعیت */
    function statusLabel(status) {
        return (statusCfg[status] && statusCfg[status].label) || status || '—';
    }

    /** کلاس CSS یک وضعیت */
    function statusClass(status) {
        return (statusCfg[status] && statusCfg[status].cls) || 'status-not_started';
    }

    /** آیکنِ بوت‌استرپ‌آیکنزِ یک وضعیت (بدونِ پیشوندِ bi-) */
    function statusIcon(status) {
        return (statusCfg[status] && statusCfg[status].icon) || 'circle';
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
        if (needsRenewalDecision(t)) {
            return '<span class="status-badge status-needs_renewal">نیازمند تمدید</span>';
        }
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
       ۷) رویدادهایِ تاریخچه (task_history.action)
       ───────────────────────────────────────────────────
       تنها مرجع — قبلاً سه‌جا (task-detail.php، dashboard-manager.php،
       dashboard-user.php) این جدول رو جدا و ناهماهنگ نگه می‌داشتن؛ هر
       رویدادِ جدید (مثلاً تمدیدِ دوره) باید تویِ هر سه جا دستی اضافه می‌شد
       و معمولاً یکی جا می‌افتاد — نتیجه‌ش نمایشِ کلیدِ خامِ انگلیسی
       (مثلاً «renewal_applied») به‌جایِ برچسبِ فارسی بود.
       دو شکلِ برچسب داریم چون دو جا با سبکِ متفاوت نمایش می‌دن:
         • label: اسم/عبارتِ کوتاه — برایِ بجِ کوچیکِ تاریخچه‌یِ خودِ کار
         • verb:  فعلِ کامل («... شد») — برایِ ردیفِ روایت‌گونه‌یِ
                  «فعالیت‌های اخیر» («(نام) - VERB: عنوانِ کار»)
       ═══════════════════════════════════════════════════ */
    const actionCfg = {
        created:               { label: 'ایجاد',                        verb: 'ایجاد شد',                          cls: 'ab-created' },
        assigned:               { label: 'واگذاری',                      verb: 'واگذار شد',                         cls: 'ab-assigned' },
        completed:              { label: 'تکمیل',                        verb: 'تکمیل شد',                          cls: 'ab-completed' },
        pending_approval:       { label: 'در انتظار تأیید',              verb: 'در انتظار تأیید قرار گرفت',         cls: 'ab-pending' },
        approved:               { label: 'تأیید',                        verb: 'تأیید شد',                          cls: 'ab-approved' },
        rejected:               { label: 'رد',                           verb: 'رد شد',                             cls: 'ab-rejected' },
        stopped:                { label: 'توقف',                         verb: 'متوقف شد',                          cls: 'ab-stopped' },
        delegated:              { label: 'ارجاع',                        verb: 'ارجاع شد',                          cls: 'ab-delegated' },
        updated:                { label: 'یادآوری',                      verb: 'یادآوری شد',                        cls: 'ab-updated' },
        deadline_extended:      { label: 'تمدید موعد',                   verb: 'مهلت تمدید شد',                     cls: 'ab-deadline' },
        deadline_rejected:      { label: 'رد درخواست تمدید موعد',        verb: 'درخواست تمدید مهلت رد شد',          cls: 'ab-rejected' },
        termination_requested:  { label: 'درخواست اتمام',                verb: 'درخواستِ اتمامِ کار ثبت شد',         cls: 'ab-pending' },
        checklist_sync:         { label: 'به‌روزرسانی چک‌لیست',           verb: 'چک‌لیست به‌روزرسانی شد',             cls: 'ab-updated' },
        checklist_assigned:     { label: 'ارجاع آیتم چک‌لیست',            verb: 'آیتم چک‌لیست به شما ارجاع شد',      cls: 'ab-delegated' },
        checklist_done:         { label: 'انجام آیتم چک‌لیست',            verb: 'آیتم چک‌لیست تکمیل شد',             cls: 'ab-completed' },
        period_done:            { label: 'دوره انجام شد',                verb: 'دوره انجام شد',                     cls: 'ab-completed' },
        workflow_prev_note:     { label: 'توضیحات مرحلهٔ قبل',           verb: 'یادداشت مرحله‌ی قبل ثبت شد',        cls: 'ab-completed' },
        renewal_applied:        { label: 'تمدید دوره',                   verb: 'دوره تمدید شد',                     cls: 'ab-approved' },
        renewal_requested:      { label: 'درخواست تمدید دوره',           verb: 'درخواستِ تمدید دوره ثبت شد',        cls: 'ab-pending' },
        renewal_step_approved:  { label: 'تأیید تمدید دوره',             verb: 'تمدید دوره تأیید شد',               cls: 'ab-approved' },
        renewal_rejected:       { label: 'رد تمدید دوره',                verb: 'درخواست تمدید دوره رد شد',          cls: 'ab-rejected' },
        deleted:                { label: 'حذف',                          verb: 'حذف شد',                            cls: 'ab-rejected' },
        in_progress:            { label: 'شروع',                         verb: 'شروع شد',                           cls: 'ab-updated' },
        not_started:            { label: 'شروع نشده',                    verb: 'به حالتِ شروع‌نشده بازگشت',          cls: 'ab-pending' }
    };

    /** برچسبِ کوتاه (اسمی) یک رویداد — برایِ بجِ تاریخچه */
    function actionLabel(action) {
        return (actionCfg[action] && actionCfg[action].label) || action || '—';
    }

    /** برچسبِ فعلی (جمله‌ای، «... شد») یک رویداد — برایِ ردیفِ روایت‌گونه */
    function actionVerb(action) {
        return (actionCfg[action] && actionCfg[action].verb) || action || '';
    }

    /** کلاسِ CSSِ بجِ یک رویداد */
    function actionClass(action) {
        return (actionCfg[action] && actionCfg[action].cls) || 'ab-updated';
    }


    /* ═══════════════════════════════════════════════════
       ۸) خروجی عمومی
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
        needsRenewalDecision,
        isWaitingMyApproval,
        isWaitingMyDeadline,

        // فیلترها
        isDueToday,
        isOverdue,

        // وضعیت‌ها
        statusCfg,
        statusLabel,
        statusClass,
        statusIcon,
        statusBadge,

        // رویدادهایِ تاریخچه
        actionCfg,
        actionLabel,
        actionVerb,
        actionClass
    };
})();