<?php
/**
 * includes/ucrm_tasks.php — مرحله‌های تأیید مشتری CRM به‌صورت «تسک واقعی».
 *
 *   manager    → هر سرپرست یک نسخه «تأیید انتساب مشتری: X»، وضعیت «در انتظار تأیید»
 *   accounting → هر عضو حسابداری یک نسخه «تأیید حسابداری مشتری: X»، «در انتظار تأیید»
 *   rep_fix    → کارشناس «اصلاح مشتری: X» (شروع نشده) بعد از رد
 * مهلت = یک روز کاری بعد (جمعه/تعطیلات حساب نمی‌شوند). «در انتظار تأیید» جلوی
 * ثبت خروج را نمی‌گیرد (includes/checkout-block-helper.php).
 *
 * تأیید/رد از کادر CRM انجام می‌شود (pages/ucrm-approval.php → crm-service)، نه
 * دکمه‌های عادی تسک؛ برای همین دکمه‌های عادی روی این تسک‌ها قفل‌اند
 * (ucrmGuardTaskAction در api/tasks/approve*.php، delegate.php، update-status.php).
 * وقتی یک نفر اقدام کرد، بقیهٔ نسخه‌های همان مرحله بسته می‌شوند.
 *
 * جدول اتصال: ucrm_customer_tasks (مهاجرت 20261023_110000).
 */
require_once __DIR__ . '/working-days-helper.php';

/** ردیف اتصال یک تسک به مشتری CRM، یا null */
function ucrmTaskLink(PDO $db, int $taskId): ?array
{
    try {
        $st = $db->prepare("SELECT * FROM ucrm_customer_tasks WHERE task_id = ?");
        $st->execute([$taskId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    } catch (Throwable $e) {
        return null; // جدول هنوز ساخته نشده (قبل از مهاجرت) → تسک عادی
    }
}

/**
 * دکمه‌های عادی تسک (تأیید/رد/ارجاع/تغییر وضعیت) روی تسک CRM کار نکنند —
 * تصمیم باید از کادر CRM ثبت شود تا وضعیت مشتری هم درست عوض شود.
 */
function ucrmGuardTaskAction(PDO $db, $taskId): void
{
    $link = ucrmTaskLink($db, (int) $taskId);
    if (!$link) return;
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(409);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'این کار مربوط به CRM است؛ تأیید یا رد آن را از صفحهٔ «درخواست تأیید مشتری» انجام دهید.',
        'ucrm_url' => '/pages/ucrm-approval.php?task=' . (int) $taskId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** فردا یا اولین روز کاری بعد از امروز (امروزِ دیتابیس = وقت تهران؛ ساعت PHP روی سرور تهران نیست) */
function ucrmNextWorkingDay(PDO $db): string
{
    $today = (string) $db->query("SELECT CURDATE()")->fetchColumn();
    $holidays = getHolidaySet($db, 1);
    $weekly = getRecurringHolidayWeekdays($db, 1);
    $d = new DateTime($today);
    for ($i = 0; $i < 30; $i++) {
        $d->modify('+1 day');
        if (isWorkingDay($d, $holidays, $weekly)) break;
    }
    return $d->format('Y-m-d');
}

/**
 * یک تسک CRM می‌سازد و به مشتری وصل می‌کند. شناسهٔ تسک را برمی‌گرداند.
 * $pending = true → «در انتظار تأیید» (تأییدکننده = مسئول)، وگرنه «شروع نشده».
 */
function ucrmCreateTask(PDO $db, int $customerId, string $stage, int $assigneeId, int $creatorId,
                        string $title, string $description, bool $pending, string $due): int
{
    $st = $db->prepare("
        INSERT INTO tasks
          (title, description, creator_id, assignee_id, task_type, priority, due_date, deadline, original_deadline,
           has_pending_deadline_request, status, is_pending_approval, organization_id, checklist_auto_complete, share_history)
        VALUES (?, ?, ?, ?, 'periodic', 'high', ?, ?, ?, 0, ?, ?, 1, 1, 1)
    ");
    $st->execute([$title, $description, $creatorId, $assigneeId, $due, $due, $due,
                  $pending ? 'pending_approval' : 'not_started', $pending ? 1 : 0]);
    $taskId = (int) $db->lastInsertId();

    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, ?, 'created', ?)")
       ->execute([$taskId, $creatorId, $assigneeId, 'این کار به‌صورت خودکار از CRM ساخته شد.']);
    $db->prepare("INSERT INTO ucrm_customer_tasks (customer_id, task_id, stage, user_id) VALUES (?, ?, ?, ?)")
       ->execute([$customerId, $taskId, $stage, $assigneeId]);
    return $taskId;
}

/**
 * تسک‌های باز یک یا چند مرحلهٔ مشتری را می‌بندد — وضعیت پایانی همیشه «تأیید شده» (approved).
 *   ⚠️ نه completed: تریگر notify_task_completed روی جدول tasks با هر رفتن به completed
 *   برای تعریف‌کننده اعلان «کار تکمیل شد» می‌سازد (اعلان بی‌ربط برای کارشناس).
 *   تصمیم واقعی (تأیید/رد) در تاریخچهٔ تسک ثبت می‌شود:
 *   نسخهٔ خودِ انجام‌دهنده: $ownAction/$ownNote — بقیهٔ نسخه‌ها: completed + $othersNote
 */
function ucrmCloseStage(PDO $db, int $customerId, array $stages, int $actorId,
                        string $ownAction, string $ownNote, string $othersNote): void
{
    if (!$stages) return;
    $in = implode(',', array_fill(0, count($stages), '?'));
    $st = $db->prepare("SELECT id, task_id, user_id FROM ucrm_customer_tasks
                        WHERE customer_id = ? AND closed_at IS NULL AND stage IN ($in)");
    $st->execute(array_merge([$customerId], $stages));
    $upd = $db->prepare("UPDATE tasks SET status = 'approved', is_pending_approval = 0, updated_at = NOW() WHERE id = ?");
    $his = $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, ?, ?, ?)");
    $close = $db->prepare("UPDATE ucrm_customer_tasks SET closed_at = NOW() WHERE id = ?");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $own = (int) $r['user_id'] === $actorId;
        $upd->execute([(int) $r['task_id']]);
        $his->execute([(int) $r['task_id'], $actorId, (int) $r['user_id'], $own ? $ownAction : 'completed',
                       $own ? $ownNote : $othersNote]);
        $close->execute([(int) $r['id']]);
    }
}
