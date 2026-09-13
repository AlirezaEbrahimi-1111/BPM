<?php
// ==================================================
// api/tasks/request-overdue-clear.php
// ثبت درخواست رفع دوره‌های معوقه (کار دوره‌ای continuous)
// اگر درخواست‌دهنده خودِ تعریف‌کننده باشد → تأیید خودکار
// ==================================================

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period-engine.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
/**
 * محاسبهٔ تعداد دوره‌های معوقه و باقی‌مانده (هم‌خوان با complete-recurring.php)
 * باقی‌مانده = معوقه − تکمیل‌شده − اعتبار بخشش
 */
/**
 * ✅ موتور مشترک — دیگر فرمول محلی نداریم.
 *    منبع واحد: includes/period-engine.php
 */
function oc_calc_remaining(PDO $db, array $task): array {
    $holidays = getHolidaySet($db);
    $state    = pe_state($db, $task, $holidays);

    return [
        'overdue'   => $state['overdue_raw'],       // پیش از کسر بخشش
        'remaining' => $state['overdue_periods'],   // معوقهٔ واقعی
    ];
}

/**
 * پیدا کردن تأییدکننده:
 *   ۱) تعریف‌کنندهٔ کار (اگر فعال باشد)
 *   ۲) وگرنه یک سرپرست در همان واحد
 *   ۳) وگرنه یک سرپرست در سازمان (هر واحدی)
 *   ۴) وگرنه خودِ تعریف‌کننده
 *
 * ⚠️ نقش‌های معتبر: supervisor و manager
 *    (پیش از این «admin» و «management» هم چک می‌شدند که هیچ‌کدام
 *     نقش معتبر نیستند — «management» یک واحد است، نه نقش)
 */
function oc_resolve_approver(PDO $db, array $task): int
{
    $creator_id = (int) $task['creator_id'];

    // ۱) تعریف‌کننده
    $c = $db->prepare("SELECT is_active, is_deleted FROM users WHERE id = ?");
    $c->execute([$creator_id]);
    $cu = $c->fetch(PDO::FETCH_ASSOC);
    $creatorOk = $cu && (int)$cu['is_active'] === 1 && (int)($cu['is_deleted'] ?? 0) === 0;
    if ($creatorOk) return $creator_id;

    // نقش‌هایی که اجازهٔ تأیید رفع معوقه دارند
    $approverRoles = "'supervisor','manager'";

    // ۲) سرپرست همان واحد
    if (!empty($task['activity_section'])) {
        $m = $db->prepare("
            SELECT id FROM users
            WHERE organization_id = ? AND activity_section = ?
              AND role IN ($approverRoles)
              AND is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL)
            ORDER BY id LIMIT 1
        ");
        $m->execute([$task['organization_id'], $task['activity_section']]);
        $mid = $m->fetchColumn();
        if ($mid) return (int) $mid;
    }

    // ۳) هر سرپرستی در سازمان (بدون قید واحد)
    $o = $db->prepare("
        SELECT id FROM users
        WHERE organization_id = ?
          AND role IN ($approverRoles)
          AND is_active = 1 AND (is_deleted = 0 OR is_deleted IS NULL)
        ORDER BY id LIMIT 1
    ");
    $o->execute([$task['organization_id']]);
    $oid = $o->fetchColumn();

    return $oid ? (int) $oid : $creator_id;
}

try {
    $user_id = requireAuth();
    $input   = json_decode(file_get_contents('php://input'), true);
    $task_id = (int) ($input['task_id'] ?? 0);
    $reason  = trim($input['reason'] ?? '');

    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($task['task_type'] !== 'continuous') {
        echo json_encode(['success' => false, 'message' => 'این کار دوره‌ای نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // مجوز: مسئول انجام، یا تعریف‌کننده، یا عضو همان بخش
    $isAssignee = ((int)$task['assignee_id'] === $user_id);
    $isCreator  = ((int)$task['creator_id']  === $user_id);
    $isSection  = false;
    if (!$isAssignee && !$isCreator && !empty($task['activity_section'])) {
        $isSection = us_userInSection($db, $user_id, $task['activity_section']);
    }
    // سوپرادمین و سرپرست سازمان هم مجازند
    if (!$isAssignee && !$isCreator && !$isSection) {
        $me = loadUserForPermissions($db, $user_id);
        if (
            hasPermission($me, 'view_all_org_tasks')
            && isSameOrganization($me, $task['organization_id'])
        ) {
            $isSection = true;   // اجازه بده عبور کند
        }
    }
    // managerِ فقط اگر سازنده/مسئولِ این کار زیرمجموعهٔ خودش باشد
    if (!$isAssignee && !$isCreator && !$isSection) {
        $me = $me ?? loadUserForPermissions($db, $user_id);
        if (
            canManageTargetUser($db, $me, (int) $task['creator_id'])
            || canManageTargetUser($db, $me, (int) $task['assignee_id'])
        ) {
            $isSection = true;
        }
    }
    if (!$isAssignee && !$isCreator && !$isSection) {
        error_log("request-overdue-clear denied (not assignee/creator/section/manager) | user_id={$user_id} | task_id={$task_id} | assignee_id={$task['assignee_id']} | creator_id={$task['creator_id']} | organization_id={$task['organization_id']}");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'شما مجاز به این عملیات نیستید'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // آیا درخواست باز دارد؟
    if ((int)$task['has_pending_overdue_request'] === 1) {
        echo json_encode(['success' => false, 'message' => 'یک درخواست رفع معوقهٔ باز برای این کار وجود دارد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // محاسبهٔ باقی‌مانده
    $calc = oc_calc_remaining($db, $task);
    if ($calc['remaining'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'این کار معوقه‌ای ندارد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $remaining = $calc['remaining'];

    $db->beginTransaction();

    // ✅ تأیید خودکار اگر درخواست‌دهنده خودِ تعریف‌کننده باشد
    if ($isCreator) {
        // ⚠️ last_approved_date این‌جا ست نمی‌شود — رفعِ معوقه = بخششِ دوره‌های
        //    گذشته، نه تأییدِ دورهٔ امروز. نوشتنِ CURDATE() این‌جا باعث می‌شد
        //    گاردِ فرانت (isLastApprovedDateToday) دکمهٔ «تکمیل» را تا آخرِ روز
        //    مخفی کند، حتی وقتی دورهٔ امروز باز بود.
        $db->prepare("UPDATE tasks
                      SET overdue_forgiven_credit = overdue_forgiven_credit + ?,
                          has_pending_overdue_request = 0,
                          updated_at = NOW()
                      WHERE id = ?")
            ->execute([$remaining, $task_id]);

        $db->prepare("INSERT INTO overdue_clear_requests
            (organization_id, task_id, requested_by, periods_count, reason, status, current_approver_id, forgiven_count)
            VALUES (?, ?, ?, ?, ?, 'approved', ?, ?)")
            ->execute([$task['organization_id'], $task_id, $user_id, $remaining, $reason, $user_id, $remaining]);

        $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes)
                      VALUES (?, ?, ?, 'updated', ?)")
            ->execute([
                $task_id,
                $user_id,
                $user_id,
                'رفع دوره‌های معوقه (تأیید خودکار توسط تعریف‌کننده) — تعداد: ' . $remaining
            ]);

        $db->commit();
        echo json_encode([
            'success' => true,
            'auto_approved' => true,
            'message' => "تعداد $remaining دورهٔ معوقه برداشته شد (تأیید خودکار)",
            'forgiven' => $remaining
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // در غیر این صورت: ثبت درخواست و ارسال برای تأیید
    $approver_id = oc_resolve_approver($db, $task);

    $db->prepare("INSERT INTO overdue_clear_requests
        (organization_id, task_id, requested_by, periods_count, reason, status, current_approver_id)
        VALUES (?, ?, ?, ?, ?, 'pending', ?)")
        ->execute([$task['organization_id'], $task_id, $user_id, $remaining, $reason, $approver_id]);

    $db->prepare("UPDATE tasks SET has_pending_overdue_request = 1, updated_at = NOW() WHERE id = ?")
        ->execute([$task_id]);

    // نوتیفیکیشن به تأییدکننده
    try {
        $requester = $db->prepare("SELECT CONCAT(first_name,' ',last_name) AS full_name FROM users WHERE id = ?");
        $requester->execute([$user_id]);
        $rname = $requester->fetchColumn() ?: 'کاربر';

        $notification = new Notification($db);
        $notification->create([
            'to_user_id'   => $approver_id,
            'title'        => 'درخواست رفع دوره‌های معوقه: ' . $task['title'],
            'message'      => "«$rname» برای کار «{$task['title']}» درخواست رفع $remaining دورهٔ معوقه داده است. لطفا بررسی کنید.",
            'type'         => 'warning',
            'related_type' => 'task',
            'related_id'   => $task_id,
            'link'         => '/pages/task-detail.php?id=' . $task_id,
            'sms_pattern'  => 'overdue_clear_request',
            'sms_args'     => [$rname, $task['title'], (string)$remaining],
        ]);
    } catch (Exception $e) {
        error_log('overdue-clear notif error: ' . $e->getMessage());
    }

    $db->commit();
    echo json_encode([
        'success' => true,
        'auto_approved' => false,
        'message' => 'درخواست رفع معوقه ثبت شد و برای تأیید ارسال گردید',
        'pending_periods' => $remaining
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('request-overdue-clear error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}
