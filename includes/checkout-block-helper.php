<?php
/**
 * includes/checkout-block-helper.php
 * ───────────────────────────────────────────────────────────────────
 * «آیا این کاربر کارِ مانعِ خروج دارد؟»
 *
 * کارِ مانع = کاری که:
 *   • مسئولش همین کاربر است (assignee_id)،
 *   • موعدش امروز یا گذشته است،
 *   • و کارمند هنوز تکمیلش نکرده → status ∈ (not_started, in_progress).
 *
 * ⚠️ rejected مانع نیست (اصلاح ۱۴۰۵/۰۷/۲۵): در این سیستم status='rejected' یعنی
 *    «اتمام اجباریِ کار دوره‌ای» (api/tasks/terminate-period.php) — کارِ تمام‌شده.
 *    کاری که تأییدکننده رد می‌کند به in_progress برمی‌گردد، نه rejected. فهرست
 *    «کارهای من» (go-api mytasks: t.status != 'rejected') هم این‌ها را نشان نمی‌دهد؛
 *    مانع‌حساب‌کردنشان خروج را برای همیشه می‌بست بی‌آن‌که کاربر کاری ببیند.
 * ⚠️ کاری که «درخواست تمدید موعد»ش در انتظار تأییدِ نفر دیگری است مانع نیست
 *    (اصلاح ۱۴۰۵/۰۷/۲۵): «کارهای من» (go-api mytasks: dr.current_approver_id IS NULL)
 *    آن را از فهرست انجام‌دهنده برمی‌دارد و نوبت با تأییدکننده است؛ مانع‌حساب‌کردنش
 *    خروج را می‌بست بی‌آن‌که کاربر کاری ببیند. با جوابِ مدیر دوباره حساب می‌شود.
 * ⚠️ این تعریف باید با go-api/internal/tasks/mytasks.go و فیلتر due_block در
 *    pages/my-tasks.php یکی بماند.
 *
 * سه نوع کار پوشش داده می‌شود (مثل includes/delayed-tasks-helper.php، ولی
 * تعریفِ «مانع» این‌جا عمداً فرق دارد: شاملِ موعدِ امروز):
 *   ۱) مقطعی      task_type='periodic'  و غیرِ workflow
 *   ۲) روتین      is_workflow_task=1
 *   ۳) دوره‌ای    task_type='continuous'  → با period-engine (دورهٔ معوقه/امروز)
 *
 * خروجی: آرایه‌ای از ['id','title','type']؛ آرایهٔ خالی = مانعی نیست.
 */

require_once __DIR__ . '/working-days-helper.php';
require_once __DIR__ . '/period-engine.php';
require_once __DIR__ . '/support-accounts.php';

function userBlockingTasks(PDO $db, int $userId, ?string $today = null): array
{
    if ($userId <= 0) return [];
    $today = $today ?: date('Y-m-d');
    $BLOCK = "'not_started','in_progress'";
    // هم‌تعریف با go-api mytasks: درخواست تمدیدِ در انتظارِ نفرِ دیگر → از فهرستِ کاربر پنهان است
    $NO_PENDING_DR = "NOT EXISTS (
            SELECT 1 FROM deadline_requests dr
            WHERE dr.task_id = t.id AND dr.status = 'pending'
              AND dr.current_approver_id IS NOT NULL AND dr.current_approver_id <> ?
        )";
    $out = [];

    // ── ۱) کارهای مقطعی (periodic، غیرِ روتین) با موعدِ امروز یا گذشته ──
    $st = $db->prepare("
        SELECT t.id, t.title,
            GREATEST(
                COALESCE(CAST(t.due_date AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.deadline AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.original_deadline AS DATE), CAST('1000-01-01' AS DATE))
            ) AS eff
        FROM tasks t
        WHERE t.assignee_id = ? AND t.is_deleted = 0
          AND t.task_type = 'periodic' AND COALESCE(t.is_workflow_task, 0) = 0
          AND t.status IN ($BLOCK)
          AND $NO_PENDING_DR
        HAVING eff > '1000-01-01' AND eff <= ?
    ");
    $st->execute([$userId, $userId, $today]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $out[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'type' => 'periodic'];
    }

    // ── ۲) کارهای روتین (workflow) با موعدِ امروز یا گذشته ──
    $st = $db->prepare("
        SELECT DISTINCT t.id, t.title,
            GREATEST(
                COALESCE(CAST(t.due_date AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.deadline AS DATE), CAST('1000-01-01' AS DATE)),
                COALESCE(CAST(t.original_deadline AS DATE), CAST('1000-01-01' AS DATE))
            ) AS eff
        FROM tasks t
        JOIN workflow_instance_steps wis ON wis.task_id = t.id
        WHERE t.assignee_id = ? AND t.is_deleted = 0 AND t.is_workflow_task = 1
          AND wis.status IN ('active', 'pending', 'delayed')
          AND t.status IN ($BLOCK)
          AND $NO_PENDING_DR
        HAVING eff > '1000-01-01' AND eff <= ?
    ");
    $st->execute([$userId, $userId, $today]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $out[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'type' => 'workflow'];
    }

    // ── ۳) کارهای دوره‌ای (continuous) — با موتورِ دوره‌ای ──
    $holidays = getHolidaySet($db);
    $st = $db->prepare("
        SELECT t.* FROM tasks t
        WHERE t.assignee_id = ? AND t.is_deleted = 0
          AND t.task_type = 'continuous' AND t.status IN ($BLOCK)
          AND $NO_PENDING_DR
    ");
    $st->execute([$userId, $userId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $state = pe_state($db, $t, $holidays, $today);
        // دوره‌ی امروز فقط وقتی مانع است که هنوز انجام نشده (قبلا is_today_done چک
        // نمی‌شد و کسی که کار روزانه‌اش را همین امروز انجام داده بود هم خروجش بسته بود)
        $dueToday = isset($state['current_period_date']) && $state['current_period_date'] === $today
            && empty($state['is_today_done']);
        if (($state['overdue_periods'] ?? 0) > 0 || $dueToday) {
            $out[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'type' => 'continuous'];
        }
    }

    return $out;
}

/**
 * چه کسی از مسدودسازیِ خروج معاف است؟ فقط حساب «فقط پشتیبان» (id=1).
 * (اصلاح ۱۴۰۵/۰۷/۲۸: قبلا همهٔ مدیران/سوپروایزرها معاف بودند؛ حالا قانون برای
 *  سوپروایزرها هم اعمال می‌شود — includes/support-accounts.php)
 * $db برای سازگاری امضا با فراخوانی موجود نگه داشته شده.
 */
function checkoutExemptRole(PDO $db, int $userId): bool
{
    return $userId > 0 && isSupportOnlyUser($userId);
}

/**
 * آیا مرخصی/مأموریتِ تأییدشدهٔ کاربر، «بازهٔ ساعتیِ شیفتِ دوم» را در امروز می‌پوشاند؟
 * یعنی کاربر عصر/شیفت۲ واقعاً سرِ کار نیست → پس خروجِ شیفت ۱ «نهایی» حساب می‌شود.
 *
 * چرا این‌طوری؟ قبلاً فقط «آیا امروز مرخصی دارد؟» چک می‌شد که خام بود: مرخصیِ
 * صبح (شیفت۱) را هم اشتباهاً «شیفت۲ تعطیل» می‌گرفت. حالا دقیق می‌سنجیم.
 *
 * شرط = دربرگیریِ کامل (containment): بازهٔ مرخصی/مأموریت باید کلِ پنجرهٔ شیفت۲ را
 * بپوشاند، یعنی زودتر/هم‌زمان شروع شود و دیرتر/هم‌زمان تمام شود:
 *     L_start <= S2_start   و   L_end >= S2_end
 *   پنجرهٔ شیفت۲ = [today+shift2Start ، today+shift2End]
 *   بازهٔ مرخصی  = [start_date+start_time ، end_date+end_time]
 *   بازهٔ مأموریت = [start_date ، end_date]  (این‌ها مستقیماً datetime‌اند)
 * (مرخصیِ نیمِ‌شیفت کافی نیست؛ چون کاربر برای بقیهٔ شیفت۲ برمی‌گردد و خروجِ
 *  شیفت ۱ نهایی نیست.) اگر ساعتِ شیفت۲ تعریف نشده باشد → false (محتاطانه).
 */
function shift2CoveredByLeaveOrMission(PDO $db, int $userId, string $today, ?string $shift2Start, ?string $shift2End): bool
{
    $shift2Start = trim((string) $shift2Start);
    $shift2End   = trim((string) $shift2End);
    if ($shift2Start === '' || $shift2End === '') return false;

    try {
        // مرخصی: start_time/end_time ممکن است خالی/NULL باشند → کلِ روز فرض می‌شود
        $st = $db->prepare("
            SELECT 1 FROM leave_requests
            WHERE user_id = ? AND status = 'approved'
              AND TIMESTAMP(start_date, COALESCE(NULLIF(start_time, ''), '00:00:00')) <= TIMESTAMP(?, ?)
              AND TIMESTAMP(end_date,   COALESCE(NULLIF(end_time,   ''), '23:59:59')) >= TIMESTAMP(?, ?)
            LIMIT 1
        ");
        $st->execute([$userId, $today, $shift2Start, $today, $shift2End]);
        if ($st->fetchColumn()) return true;

        // مأموریت: start_date/end_date از نوع datetime‌اند
        $st = $db->prepare("
            SELECT 1 FROM mission_requests
            WHERE user_id = ? AND status = 'approved'
              AND start_date <= TIMESTAMP(?, ?)
              AND end_date   >= TIMESTAMP(?, ?)
            LIMIT 1
        ");
        $st->execute([$userId, $today, $shift2Start, $today, $shift2End]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false; // در صورت خطا، نهایی نگیر تا خروج بی‌دلیل مسدود نشود
    }
}
