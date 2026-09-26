<?php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once __DIR__ . '/_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    $user_id = requireAuth();
    $task_id = intval($_GET['task_id'] ?? 0);
    if (!$task_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه کار الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $task = getTaskForChecklist($db, $task_id, $user_id);
    if (!$task) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی به این کار ندارید']);
        exit;
    }
    // آیا کاربر فقط «مسئول چک‌لیست» است؟ (نه creator، نه assignee، و نه بیننده‌ی
    // task_viewers — بیننده باید کل چک‌لیست رو ببینه، نه فقط آیتم‌های خودش)
    $onlyChecklistAssignee = $task['_is_checklist_assignee'];

    // 🆕 همهٔ واحدهای کاربر (برای تشخیص آیتم‌های ارجاع‌شده به واحدهایش)
    $userSections = us_getUserSections($db, $user_id);
    // آیتم‌ها + نام تیک‌زننده
    $stmt = $db->prepare("
        SELECT ci.id, ci.title, ci.description, ci.is_done, ci.sort_order, ci.done_at,
               ci.assignee_type, ci.assignee_value, ci.done_note,
               CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS done_by_name,
               CONCAT(COALESCE(au.first_name,''),' ',COALESCE(au.last_name,'')) AS assignee_user_name,
               sec.section_label AS assignee_section_name
        FROM task_checklist_items ci
        LEFT JOIN users u  ON ci.done_by = u.id
        LEFT JOIN users au ON (ci.assignee_type = 'user' AND ci.assignee_value = au.id)
        LEFT JOIN organization_activity_sections sec
               ON (ci.assignee_type = 'section'
                   AND ci.assignee_value = sec.section_key
                   AND sec.organization_id = ?)
        WHERE ci.task_id = ?
        ORDER BY ci.sort_order ASC, ci.id ASC
    ");
    $stmt->execute([$task['organization_id'], $task_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔒 کاربری که فقط مسئول چک‌لیست است: فقط آیتم‌های خودش یا واحدش را ببیند
    if ($onlyChecklistAssignee) {
        $items = array_values(array_filter($items, function ($it) use ($user_id, $userSections) {
            $type  = $it['assignee_type']  ?? null;
            $value = $it['assignee_value'] ?? null;
            if ($type === 'user')    return ((string)$value === (string)$user_id);
            if ($type === 'section') return in_array($value, $userSections, true);
            return false;
        }));
    }

    // تمیزکردن نام مسئول (اگر کاربر نبود، رشته خالی شود نه یک فاصله)
    foreach ($items as &$it) {
        $it['assignee_user_name']    = trim($it['assignee_user_name'] ?? '');
        $it['assignee_section_name'] = $it['assignee_section_name'] ?? '';
    }
    unset($it);

    // تعیین اینکه کاربر مجاز به تیک‌زدن هر آیتم هست یا نه (هماهنگ با toggle.php)
    $__locked = isChecklistLocked($task);   // 🔒 کار به پایان رسیده؟
    foreach ($items as &$it) {
        $type  = $it['assignee_type']  ?? null;
        $value = $it['assignee_value'] ?? null;

        if ($__locked) {
            // کار قفل است → هیچ‌کس نمی‌تواند تیک بزند
            $it['can_toggle_this'] = false;
        } elseif ($type === 'user') {
            $it['can_toggle_this'] = ((string)$value === (string)$user_id);
        } elseif ($type === 'section') {
            $it['can_toggle_this'] = in_array($value, $userSections, true);
        } else {
            // بدون ارجاع → فقط مسئول فعلی کار (هماهنگ با قانون toggle.php:
            // تعریف‌کننده پس از واگذاری کار به شخص دیگر، حق تیک‌زدن ندارد)
            $it['can_toggle_this'] = !empty($task['_is_assignee']);
        }
    }
    unset($it);

    // برای مسئول صرف یک/چند آیتم، پیشرفت هم فقط روی همان آیتم‌های
    // قابل‌دیدنش حساب شود — وگرنه با ۱ آیتم نمایش‌داده‌شده ولی درصد
    // کل چک‌لیست، گیج‌کننده می‌شود
    $p = $onlyChecklistAssignee
        ? checklistProgressFromItems($items)
        : checklistProgress($db, $task_id);

    $locked = isChecklistLocked($task);   // 🔒 آیا کار به پایان رسیده؟
    echo json_encode([
        'success' => true,
        'items'   => $items,
        'total'   => $p['total'],
        'done'    => $p['done'],
        'percent' => $p['percent'],
        'can_edit' => $task['_is_creator'] && !$locked,
        'can_toggle' => !$locked,
        'is_locked' => $locked,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);

}
