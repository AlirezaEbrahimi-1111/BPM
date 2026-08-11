<?php
// api/checklist/_helpers.php — توابع مشترک چک‌لیست
// این فایل توسط بقیه API‌های چک‌لیست require می‌شود.

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-access.php';

/**
 * گرفتن کار + بررسی دسترسی (زنجیره‌ی مشترکِ taskUserAccess: سازنده/مسئول/
 * مدیر/تاریخچه/چک‌لیست/بیننده — همان منبعِ حقیقتی که detail.php و
 * get-attachments.php هم استفاده می‌کنند، تا بیننده‌ای که با task_viewers
 * دسترسیِ چک‌لیست براش صریحاً روشن/خاموش شده، اینجا هم واقعاً رعایت بشه)
 * خروجی: آرایه task یا null
 */
function getTaskForChecklist($db, $task_id, $user_id)
{
    $stmt = $db->prepare("SELECT id, title, creator_id, assignee_id, status, checklist_auto_complete, activity_section, organization_id,
                                 task_type, period_type, start_date, end_date, is_workflow_task
                          FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) return null;

    $access = taskUserAccess($db, (int) $user_id, $task);

    // بیننده‌ای که صریحاً دسترسیِ چک‌لیست براش خاموش شده، حتی اگر از راهِ
    // دیگه‌ای (مثلاً مسئولِ یک آیتم) هم واجدِ شرایط بود، دسترسی نداره
    if ($access['is_viewer_only'] && !$access['viewer_can_view_checklist']) {
        return null;
    }
    if (!$access['has_access']) return null;

    $task['_is_creator']            = ((int) $task['creator_id']  === (int) $user_id);
    $task['_is_assignee']           = ((int) $task['assignee_id'] === (int) $user_id);
    $task['_is_checklist_assignee'] = $access['is_checklist_only'];  // مسئولِ صرفِ یک/چند آیتم → دیدِ محدود
    $task['_is_pure_viewer']        = $access['is_viewer_only'];     // 🆕 بیننده‌یِ task_viewers → دیدِ کامل، فقط مشاهده
    return $task;
}

function isChecklistLocked($task)
{
    $lockedStatuses = ['completed', 'approved', 'stopped', 'cancelled', 'period_done'];
    $status = $task['status'] ?? '';
    $result = in_array($status, $lockedStatuses, true);
    // خط تست موقت — نشان می‌دهد این نسخه‌ی فایل واقعاً اجرا می‌شود
    return $result;
}

function notifyChecklistAssignee($db, $assignee_type, $assignee_value, $task, $actor_id, $itemTitle = '')
{
    if (!$assignee_type || !$assignee_value) return;

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
    $notif = new Notification($db);

    // تعیین گیرندگان
    $recipients = [];
    if ($assignee_type === 'user') {
        $recipients[] = (int)$assignee_value;
    } elseif ($assignee_type === 'section') {
        // 🆕 همه‌ی اعضای آن واحد (شاملِ کسانی که این واحد، واحدِ دومشان است)
        $orgStmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
        $orgStmt->execute([$actor_id]);
        $actor_org = $orgStmt->fetchColumn();
        $recipients = us_getSectionUserIds($db, $assignee_value, $actor_org);
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
            'link'         => "/pages/task-detail.php?id={$taskId}",
            'related_type' => 'checklist',
            'related_id'   => $taskId,
            'sms_pattern'  => 'checklist_assigned',
            'sms_args'     => [$taskTitle]
        ]);
    }
}
/**
 * اطلاع به ارجاع‌دهنده (سازنده‌ی آیتم) وقتی آیتمش تیک خورد.
 * $item: آرایه‌ی آیتم چک‌لیست (باید شامل created_by و title باشد)
 * $task: آرایه‌ی کار (برای عنوان)
 * $doer_id: کسی که تیک زده (به او اعلان نرود)
 */
function notifyChecklistItemDone($db, $item, $task, $doer_id)
{
    $creator_id = (int)($item['created_by'] ?? 0);
    if (!$creator_id) return;

    // اگر خودِ سازنده تیک زده، نیازی به اعلان نیست
    if ($creator_id === (int)$doer_id) return;

    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
    $notif = new Notification($db);

    // نام کسی که تیک زده
    $stmt = $db->prepare("SELECT CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) FROM users WHERE id = ?");
    $stmt->execute([$doer_id]);
    $doerName = trim($stmt->fetchColumn() ?: '') ?: 'کاربر';

    $taskTitle = $task['title'] ?? 'کار';
    $itemTitle = $item['title'] ?? 'آیتم';
    $taskId    = $task['id'];

    $title   = 'آیتم چک‌لیست انجام شد';
    $message = "آیتم «{$itemTitle}» از کار «{$taskTitle}» توسط {$doerName} انجام شد";

    $createResult = $notif->create([
        'to_user_id'   => $creator_id,
        'title'        => $title,
        'message'      => $message,
        'type'         => 'info',
        'link'         => "/pages/task-detail.php?id={$taskId}",
        'related_type' => 'checklist',
        'related_id'   => $taskId,
        'sms_pattern'  => 'checklist_done',
        'sms_args'     => [$itemTitle, $doerName]
    ]);
}
/**
 * 🔒 خط قرمز: کسی که فقط مسئولِ یک/چند آیتمِ چک‌لیست است (نه سازنده، نه
 * مسئولِ کل کار)، فقط باید همان آیتم‌های ارجاع‌شده به خودش (یا واحدش) را
 * ببیند — نه کل چک‌لیست را. برای سازنده/مسئولِ کار، لیست بدون تغییر برمی‌گردد.
 * $items هر عضو باید کلیدهای assignee_type و assignee_value داشته باشد.
 */
function filterChecklistItemsForViewer(array $items, bool $onlyChecklistAssignee, $user_id, array $userSections): array
{
    if (!$onlyChecklistAssignee) return $items;

    return array_values(array_filter($items, function ($it) use ($user_id, $userSections) {
        $type  = $it['assignee_type']  ?? null;
        $value = $it['assignee_value'] ?? null;
        if ($type === 'user') {
            return (string) $value === (string) $user_id;
        }
        if ($type === 'section') {
            return in_array($value, $userSections, true);
        }
        return false; // آیتمِ بدون ارجاع، به مسئولِ صرفِ یک آیتمِ دیگر ربطی ندارد
    }));
}

/**
 * محاسبه پیشرفت چک‌لیست یک کار
 * خروجی: ['total'=>N, 'done'=>N, 'percent'=>N]
 */
function checklistProgress($db, $task_id)
{
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
 * محاسبهٔ پیشرفت روی یک آرایهٔ از آیتم‌های از پیش واکشی‌شده (نه کل چک‌لیست).
 * برای مسئولِ صرفِ یک/چند آیتم استفاده می‌شود تا درصد فقط روی آیتم‌های
 * قابل‌دیدنِ او حساب شود، نه کل چک‌لیستِ کار.
 */
function checklistProgressFromItems(array $items): array
{
    $total = count($items);
    $done  = 0;
    foreach ($items as $it) {
        if ((int) $it['is_done'] === 1) $done++;
    }
    return ['total' => $total, 'done' => $done, 'percent' => $total > 0 ? round($done * 100 / $total) : 0];
}

/**
 * بررسی و اعمال تکمیل خودکار.
 * اگر گزینه روشن باشد و همه آیتم‌ها تیک خورده باشند،
 * کار را از مسیر استاندارد updateTaskStatus به 'completed' می‌برد
 * (که خودش تصمیم می‌گیرد pending_approval شود یا completed).
 * خروجی: true اگر تکمیل خودکار اجرا شد.
 */
function maybeAutoComplete($db, $task, $user_id)
{
    if (empty($task['checklist_auto_complete'])) return false;

    $p = checklistProgress($db, $task['id']);
    if ($p['total'] === 0 || $p['done'] < $p['total']) return false;

    // کار قبلاً تکمیل/تأیید شده؟ کاری نکن
    if (in_array($task['status'], ['completed', 'approved', 'pending_approval'])) return false;

    // ══════════════════════════════════════════════════════════════
    //  تسک دوره‌ای (continuous): به‌جای «تکمیل»، «ثبت دوره» می‌کنیم
    // ══════════════════════════════════════════════════════════════
    if (($task['task_type'] ?? '') === 'continuous') {
        return registerRecurringPeriod($db, $task, $user_id);
    }

    // ── تسک عادی (رفتار قبلی، بدون تغییر) ──
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
    $tm = new TaskManager($db);
    $tm->updateTaskStatus($task['id'], 'completed', $user_id, 'تکمیل خودکار با اتمام چک‌لیست');
    return true;
}

/**
 * ثبت یک دوره‌ی انجام‌شده برای تسک دوره‌ای.
 *
 * ⚠️ رفع باگ: قبلاً این تابع بدون توجه به اینکه سازنده و انجام‌دهنده
 * یکی هستند یا نه، همیشه مستقیم status='period_done' می‌گذاشت — یعنی
 * زنجیرهٔ تأیید را کامل دور می‌زد (برخلاف مسیر عادیِ تکمیل در
 * TaskManager::updateTaskStatus که اگر creator ≠ assignee باشد، کار
 * را برای تأیید نزد تعریف‌کننده می‌فرستد). حالا همان قاعده اینجا هم
 * رعایت می‌شود.
 */
function registerRecurringPeriod($db, $task, $user_id)
{
    $task_id = $task['id'];
    $creatorId  = (int) $task['creator_id'];
    $assigneeId = (int) $task['assignee_id'];
    $needsApproval = ($creatorId !== $assigneeId);

    try {
        $db->beginTransaction();

        // ۱) ثبت دوره در تاریخچه — با تاریخ واقعیِ همین لحظه
        //    (روزی که چک‌لیست واقعاً کامل شد، نه روزِ تأییدِ احتمالیِ بعدی)
        $hist = $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                              VALUES (?, ?, ?, 'completed', ?)");
        $hist->execute([
            $task_id,
            $user_id,
            $needsApproval ? $creatorId : null,
            'دوره‌ی جاری با اتمام چک‌لیست ثبت شد'
        ]);

        // ۲) ریست چک‌لیست: پاک‌کردن همه‌ی تیک‌ها (برای دورهٔ بعدی)
        $reset = $db->prepare("UPDATE task_checklist_items
                               SET is_done = 0, done_at = NULL, done_by = NULL
                               WHERE task_id = ?");
        $reset->execute([$task_id]);

        if ($needsApproval) {
            // ✅ سازنده ≠ انجام‌دهنده → نباید خودکار تمام شود؛ باید مثل مسیر
            // عادیِ تکمیل، منتظر تأیید تعریف‌کننده بماند
            $upd = $db->prepare("UPDATE tasks
                                 SET status = 'pending_approval',
                                     is_pending_approval = TRUE,
                                     assignee_id = ?,
                                     last_completed_date = CURDATE(),
                                     updated_at = NOW()
                                 WHERE id = ?");
            $upd->execute([$creatorId, $task_id]);
        } else {
            // سازنده = انجام‌دهنده → همان رفتار قبلی (تکمیل خودکار، بدون نیاز به تأیید)
            $upd = $db->prepare("UPDATE tasks
                                 SET status = 'period_done', is_pending_approval = FALSE,
                                     pending_approval_count = 0,
                                     last_approved_date = CURDATE(),
                                     updated_at = NOW()
                                 WHERE id = ?");
            $upd->execute([$task_id]);
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        return false;
    }
}

function syncTaskStatusWithChecklist($db, $task, $user_id)
{
    // فقط وقتی auto_complete روشن است معنا دارد
    if (empty($task['checklist_auto_complete'])) return;

    $p = checklistProgress($db, $task['id']);
    if ($p['total'] === 0) return; // چک‌لیستی نمانده، کاری نکن

    $status = $task['status'];

    // وضعیت‌هایی که نباید به‌خاطر چک‌لیست تغییر کنند
    // period_done: تسک دوره‌ای که دوره‌اش ثبت شده و منتظر دوره‌ی بعدی است
    if (in_array($status, ['approved', 'stopped', 'rejected', 'period_done'])) return;

    // ── حالت ۱: همه تیک خورده → اینجا کاری نکن (maybeAutoComplete مسئول است)
    if ($p['done'] >= $p['total']) return;

    // ── حالت ۲: هیچ تیک نخورده → اگر در حال انجام بود، به not_started برگردان
    if ($p['done'] === 0) {
        if ($status === 'in_progress') {
            $db->prepare("UPDATE tasks
                          SET status = 'not_started', is_pending_approval = FALSE,
                              pending_approval_count = 0, updated_at = NOW()
                          WHERE id = ?")->execute([$task['id']]);
            addChecklistStatusHistory(
                $db,
                $task['id'],
                $user_id,
                'not_started',
                'بازگشت به «شروع نشده» چون همه تیک‌ها برداشته شد'
            );
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
 * ثبت رویدادهای چک‌لیست در تاریخچهٔ کار.
 * ⚠️ اکشن‌ها با پیشوند checklist_ هستند تا در بررسی دسترسی
 *    (زنجیرهٔ ارجاعات) به‌حساب نیایند.
 */
function addChecklistEvent($db, $task_id, $action, $from_user_id, $to_user_id, $note, $section = null)
{
    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, checklist_section, action, notes)
                  VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$task_id, $from_user_id, $to_user_id, $section, $action, $note]);
}
/**
 * ثبت یک رکورد تاریخچه برای تغییر وضعیتِ ناشی از چک‌لیست.
 * action را 'updated' می‌گذاریم تا با اکشن‌های اصلی قاطی نشود،
 * و توضیح را در notes می‌نویسیم.
 */
function addChecklistStatusHistory($db, $task_id, $user_id, $newStatus, $note)
{
    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                  VALUES (?, ?, NULL, 'checklist_sync', ?)")
        ->execute([$task_id, $user_id, $note]);
}
