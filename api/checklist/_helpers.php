<?php
// api/checklist/_helpers.php — توابع مشترک چک‌لیست
// این فایل توسط بقیه API‌های چک‌لیست require می‌شود.

/**
 * گرفتن کار + بررسی دسترسی پایه (کاربر باید creator یا assignee باشد)
 * خروجی: آرایه task یا null
 */
function getTaskForChecklist($db, $task_id, $user_id) {
    $stmt = $db->prepare("SELECT id, title, creator_id, assignee_id, status, checklist_auto_complete, activity_section, organization_id
                          FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) return null;

    $isCreator  = (int)$task['creator_id']  === (int)$user_id;
    $isAssignee = (int)$task['assignee_id'] === (int)$user_id;

    // آیا کاربر مسئولِ حداقل یک آیتم چک‌لیست است؟ (مستقیم یا از طریق واحدش)
    $isChecklistAssignee = false;
    if (!$isCreator && !$isAssignee) {
        // واحدِ کاربر را بخوان
        $secStmt = $db->prepare("SELECT activity_section FROM users WHERE id = ?");
        $secStmt->execute([$user_id]);
        $user_section = $secStmt->fetchColumn() ?: '';

        $chkStmt = $db->prepare("
            SELECT COUNT(*) FROM task_checklist_items ci
            WHERE ci.task_id = ?
              AND (
                  (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
                  OR (ci.assignee_type = 'section' AND ci.assignee_value = ?)
              )
        ");
        $chkStmt->execute([$task_id, (string)$user_id, $user_section]);
        $isChecklistAssignee = ((int)$chkStmt->fetchColumn() > 0);
    }

    // هیچ‌کدام نبود → دسترسی ندارد
    if (!$isCreator && !$isAssignee && !$isChecklistAssignee) return null;

    $task['_is_creator']            = $isCreator;
    $task['_is_assignee']           = $isAssignee;
    $task['_is_checklist_assignee'] = $isChecklistAssignee;  // 🆕 فلگ جدید
    return $task;
}
/**
 * ارسال اعلان به فرد/واحدی که آیتم چک‌لیست به او ارجاع شده.
 * $assignee_type: 'user' یا 'section'
 * $assignee_value: شناسه کاربر یا کلید واحد
 * $task: آرایه‌ی کار (برای عنوان و organization و جلوگیری از اعلان به خود ارجاع‌دهنده)
 * $actor_id: کسی که ارجاع را انجام داده (به او اعلان نرود)
 * $itemTitle: عنوان آیتم (برای متن اعلان)
 */
function notifyChecklistAssignee($db, $assignee_type, $assignee_value, $task, $actor_id, $itemTitle = '') {
    if (!$assignee_type || !$assignee_value) return;

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
    $notif = new Notification($db);

    // تعیین گیرندگان
    $recipients = [];
    if ($assignee_type === 'user') {
        $recipients[] = (int)$assignee_value;
    } elseif ($assignee_type === 'section') {
        // همه‌ی اعضای فعالِ آن واحد در همان سازمان
        $stmt = $db->prepare("
            SELECT id FROM users
            WHERE activity_section = ? AND is_active = 1
              AND organization_id = (SELECT organization_id FROM users WHERE id = ?)
        ");
        $stmt->execute([$assignee_value, $actor_id]);
        $recipients = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $taskTitle = $task['title'] ?? 'کار';
    $taskId    = $task['id'];

    foreach ($recipients as $uid) {
        // به خودِ ارجاع‌دهنده اعلان نده
        if ($uid === (int)$actor_id) continue;

        $title = 'ارجاع آیتم چک‌لیست';
        $message = $itemTitle !== ''
            ? "آیتم «{$itemTitle}» از کار «{$taskTitle}» به شما ارجاع شد"
            : "یک آیتم چک‌لیست از کار «{$taskTitle}» به شما ارجاع شد";

        $notif->create([
            'to_user_id'   => $uid,
            'title'        => $title,
            'message'      => $message,
            'type'         => 'info',
            'link'         => "pages/task-detail.php?id={$taskId}",
            'related_type' => 'task',
            'related_id'   => $taskId
        ]);
    }
}
/**
 * محاسبه پیشرفت چک‌لیست یک کار
 * خروجی: ['total'=>N, 'done'=>N, 'percent'=>N]
 */
function checklistProgress($db, $task_id) {
    $stmt = $db->prepare("SELECT COUNT(*) total, SUM(is_done) done
                          FROM task_checklist_items WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = (int)($r['total'] ?? 0);
    $done  = (int)($r['done'] ?? 0);
    $percent = $total > 0 ? round($done * 100 / $total) : 0;
    return ['total' => $total, 'done' => $done, 'percent' => $percent];
}

/**
 * بررسی و اعمال تکمیل خودکار.
 * اگر گزینه روشن باشد و همه آیتم‌ها تیک خورده باشند،
 * کار را از مسیر استاندارد updateTaskStatus به 'completed' می‌برد
 * (که خودش تصمیم می‌گیرد pending_approval شود یا completed).
 * خروجی: true اگر تکمیل خودکار اجرا شد.
 */
function maybeAutoComplete($db, $task, $user_id) {
    if (empty($task['checklist_auto_complete'])) return false;

    $p = checklistProgress($db, $task['id']);
    if ($p['total'] === 0 || $p['done'] < $p['total']) return false;

    // کار قبلاً تکمیل/تأیید شده؟ کاری نکن
    if (in_array($task['status'], ['completed', 'approved', 'pending_approval'])) return false;

    // از مسیر استاندارد پروژه عبور کن (منطق تأیید چندمرحله‌ای حفظ می‌شود)
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
    $tm = new TaskManager($db);
    $tm->updateTaskStatus($task['id'], 'completed', $user_id, 'تکمیل خودکار با اتمام چک‌لیست');
    return true;
}

/**
 * همگام‌سازی وضعیت کار با وضعیت چک‌لیست.
 * بعد از هر تغییر چک‌لیست (تیک، برداشتن تیک، افزودن، حذف) صدا زده می‌شود.
 *
 * قانون:
 *   • هیچ آیتمی تیک نخورده  → اگر کار in_progress است، به not_started برگردد
 *   • بعضی تیک خورده (نه همه) → اگر کار not_started/completed/pending_approval است، به in_progress برگردد
 *   • همه تیک خورده          → maybeAutoComplete (جداگانه فراخوانی می‌شود)
 *
 * توجه: این تابع فقط کارهای «دارای چک‌لیست با auto_complete روشن» را مدیریت می‌کند
 * و کارهای approved/stopped/rejected را دست نمی‌زند.
 */
function syncTaskStatusWithChecklist($db, $task, $user_id) {
    // فقط وقتی auto_complete روشن است معنا دارد
    if (empty($task['checklist_auto_complete'])) return;

    $p = checklistProgress($db, $task['id']);
    if ($p['total'] === 0) return; // چک‌لیستی نمانده، کاری نکن

    $status = $task['status'];

    // وضعیت‌هایی که نباید به‌خاطر چک‌لیست تغییر کنند
    if (in_array($status, ['approved', 'stopped', 'rejected'])) return;

    // ── حالت ۱: همه تیک خورده → اینجا کاری نکن (maybeAutoComplete مسئول است)
    if ($p['done'] >= $p['total']) return;

    // ── حالت ۲: هیچ تیک نخورده → اگر در حال انجام بود، به not_started برگردان
    if ($p['done'] === 0) {
        if ($status === 'in_progress') {
            $db->prepare("UPDATE tasks
                          SET status = 'not_started', is_pending_approval = FALSE,
                              pending_approval_count = 0, updated_at = NOW()
                          WHERE id = ?")->execute([$task['id']]);
            addChecklistStatusHistory($db, $task['id'], $user_id, 'not_started',
                'بازگشت به «شروع نشده» چون همه تیک‌ها برداشته شد');
        }
        return;
    }

    // ── حالت ۳: بعضی تیک خورده (نه همه)
    // اگر کار not_started بود (اولین تیک) یا از حالت تکمیل/منتظرتأیید برمی‌گردد → in_progress
    if (in_array($status, ['not_started', 'completed', 'pending_approval'])) {
        // assignee_id را دست نمی‌زنیم؛ فقط وضعیت و فلگ‌های تأیید را تمیز می‌کنیم
        $db->prepare("UPDATE tasks
                      SET status = 'in_progress', is_pending_approval = FALSE,
                          pending_approval_count = 0, updated_at = NOW()
                      WHERE id = ?")->execute([$task['id']]);

        $msg = ($status === 'not_started')
            ? 'کار با ثبت اولین آیتم چک‌لیست آغاز شد'
            : 'کار به‌دلیل تغییر چک‌لیست به «در حال انجام» بازگشت';
        addChecklistStatusHistory($db, $task['id'], $user_id, 'in_progress', $msg);
    }
}

/**
 * ثبت یک رکورد تاریخچه برای تغییر وضعیتِ ناشی از چک‌لیست.
 * action را 'updated' می‌گذاریم تا با اکشن‌های اصلی قاطی نشود،
 * و توضیح را در notes می‌نویسیم.
 */
function addChecklistStatusHistory($db, $task_id, $user_id, $newStatus, $note) {
    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                  VALUES (?, ?, NULL, 'checklist_sync', ?)")
       ->execute([$task_id, $user_id, $note]);
}
