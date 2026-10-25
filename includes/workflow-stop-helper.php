<?php
/**
 * درخواست توقف روتین — توابع مشترک
 * (api/workflows/request-stop.php، review-stop.php و api/tasks/detail.php)
 *
 * روال: مسئولِ مرحلهٔ جاری درخواست می‌دهد → تعریف‌کنندهٔ روتین
 * (workflow_instances.created_by) تأیید یا رد می‌کند → با تأیید، روتین با
 * WorkflowManager::cancelWorkflow متوقف می‌شود.
 */

require_once __DIR__ . '/support-accounts.php';
require_once __DIR__ . '/user-sections.php';

/** روتین فعال‌بودنی که هنوز می‌شود متوقفش کرد */
const WSR_OPEN_INSTANCE_STATUSES = ['in_progress', 'delayed'];
/** وضعیت‌هایی از کارِ مرحله که یعنی آن مرحله دیگر دست کسی نیست */
const WSR_CLOSED_TASK_STATUSES = ['completed', 'approved', 'rejected', 'stopped'];

function wsr_loadInstance(PDO $db, int $instance_id): ?array
{
    $s = $db->prepare("SELECT * FROM workflow_instances WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
    $s->execute([$instance_id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * تأییدکننده:
 *   ۱) تعریف‌کنندهٔ روتین (اگر فعال باشد)
 *   ۲) وگرنه یک سرپرست/مدیر در همان سازمان
 *   ۳) وگرنه خود تعریف‌کننده
 * (هم‌الگو با oc_resolve_approver در api/tasks/request-overdue-clear.php)
 */
function wsr_resolveApprover(PDO $db, array $instance): int
{
    $creator_id = (int) $instance['created_by'];

    $c = $db->prepare("SELECT is_active, is_deleted FROM users WHERE id = ?");
    $c->execute([$creator_id]);
    $cu = $c->fetch(PDO::FETCH_ASSOC);
    if ($cu && (int) $cu['is_active'] === 1 && (int) ($cu['is_deleted'] ?? 0) === 0) return $creator_id;

    $o = $db->prepare("
        SELECT id FROM users
        WHERE organization_id = ?
          AND role IN ('supervisor','manager')
          AND is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL)
          AND " . supportOnlySqlExclude('id') . "
        ORDER BY id LIMIT 1
    ");
    $o->execute([$instance['organization_id']]);
    $oid = $o->fetchColumn();

    return $oid ? (int) $oid : $creator_id;
}

/** درخواست باز (در انتظار) یک روتین، همراه با نام درخواست‌دهنده */
function wsr_pendingRequest(PDO $db, int $instance_id): ?array
{
    $s = $db->prepare("
        SELECT r.*, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS requester_name
        FROM workflow_stop_requests r
        JOIN users u ON u.id = r.requested_by
        WHERE r.instance_id = ? AND r.status = 'pending'
        ORDER BY r.id DESC LIMIT 1
    ");
    $s->execute([$instance_id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * آیا این کاربر «کار دستش است»؟ — مسئولِ کارِ بازِ این مرحله، یا اگر مرحله
 * به واحد سپرده شده و هنوز مسئول ندارد، عضو همان واحد.
 */
function wsr_isStepHolder(PDO $db, int $user_id, array $task): bool
{
    if (in_array($task['status'], WSR_CLOSED_TASK_STATUSES, true)) return false;
    if ((int) ($task['assignee_id'] ?? 0) === $user_id) return true;
    if (empty($task['assignee_id']) && !empty($task['activity_section'])) {
        return us_userInSection($db, $user_id, $task['activity_section']);
    }
    return false;
}

function wsr_userName(PDO $db, int $id): string
{
    $s = $db->prepare("SELECT TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) FROM users WHERE id = ?");
    $s->execute([$id]);
    return (string) ($s->fetchColumn() ?: 'کاربر');
}

/** مسئولان کارهای باز روتین (برای خبر دادن توقف) — پیش از cancelWorkflow خوانده شود */
function wsr_openTaskAssignees(PDO $db, int $instance_id): array
{
    $s = $db->prepare("
        SELECT id, assignee_id FROM tasks
        WHERE workflow_instance_id = ? AND assignee_id IS NOT NULL
          AND status NOT IN ('completed','approved','rejected','stopped')
          AND (is_deleted = 0 OR is_deleted IS NULL)
    ");
    $s->execute([$instance_id]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * توقف روتین + ثبت در تاریخچه + اعلان به درخواست‌دهنده و مسئولان مراحل باز.
 * cancelWorkflow تراکنش خودش را دارد؛ این تابع نباید داخل تراکنش صدا زده شود.
 */
function wsr_stopInstance(PDO $db, array $instance, array $request, int $by_user_id): array
{
    require_once __DIR__ . '/WorkflowManager.php';
    require_once __DIR__ . '/Notification.php';

    $instance_id = (int) $instance['id'];
    $openTasks = wsr_openTaskAssignees($db, $instance_id);

    $wm = new WorkflowManager($db);
    $res = $wm->cancelWorkflow($instance_id, $by_user_id, $request['reason']);
    if (empty($res['success'])) return $res;

    $byName = wsr_userName($db, $by_user_id);
    $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                  VALUES (?, ?, ?, 'workflow_stopped', ?)")
        ->execute([
            $request['task_id'], $by_user_id, $request['requested_by'],
            'روتین «' . $instance['title'] . '» با تأیید «' . $byName . '» متوقف شد. دلیل درخواست: ' . $request['reason'],
        ]);

    $notification = new Notification($db);
    $notified = [];
    $targets = array_merge([['id' => $request['task_id'], 'assignee_id' => $request['requested_by']]], $openTasks);
    foreach ($targets as $t) {
        $uid = (int) $t['assignee_id'];
        if ($uid === $by_user_id || isset($notified[$uid])) continue;
        $notified[$uid] = true;
        try {
            $notification->create([
                'to_user_id'   => $uid,
                'title'        => 'روتین متوقف شد: ' . $instance['title'],
                'message'      => "روتین «{$instance['title']}» با تأیید «{$byName}» متوقف شد و کارهای باز آن بسته شدند.",
                'type'         => 'warning',
                'related_type' => 'task',
                'related_id'   => (int) $t['id'],
                'link'         => '/pages/task-detail.php?id=' . (int) $t['id'],
            ]);
        } catch (Exception $e) {
            error_log('workflow stop notif error: ' . $e->getMessage());
        }
    }

    return ['success' => true];
}
