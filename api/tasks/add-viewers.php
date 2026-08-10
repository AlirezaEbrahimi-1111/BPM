<?php
/**
 * API: افزودنِ چند «بیننده» (فقط مشاهده، بدونِ ویرایش/اقدام) به یک تسک
 * POST /api/tasks/add-viewers.php   { task_id, user_ids: [..] }
 *
 * فقط سازنده/مدیرِ سازنده‌یا‌مسئول/سرپرستِ سازمان اجازه دارن — دقیقاً
 * همون سطحِ دسترسی‌ای که در api/tasks/detail.php برایِ «مدیریتِ کار» هست.
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

try {
    $user_id = requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $task_id = (int) ($input['task_id'] ?? 0);
    $userIds = array_values(array_unique(array_map('intval', $input['user_ids'] ?? [])));
    // پیش‌فرض روشن — «بیننده» یعنی حداقل جزئیاتِ اصلی رو می‌بینه؛ این دو فقط
    // اختیاریِ خاموش‌کردنه (همون یک ست برایِ کلِ این دسته‌یِ افزودن)
    $canViewAttachments = !array_key_exists('can_view_attachments', $input) || !empty($input['can_view_attachments']);
    $canViewHistory     = !array_key_exists('can_view_history', $input) || !empty($input['can_view_history']);

    if (!$task_id || empty($userIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'پارامترهای نامعتبر']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, title, creator_id, assignee_id, organization_id FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کار یافت نشد']);
        exit;
    }

    $me = loadUserForPermissions($db, $user_id);
    $canManageViewers = (
        (int) $task['creator_id'] === (int) $user_id
        || canManageTargetUser($db, $me, (int) $task['creator_id'])
        || canManageTargetUser($db, $me, (int) $task['assignee_id'])
        || (hasPermission($me, 'view_all_org_tasks') && isSameOrganization($me, $task['organization_id'] ?? 0))
    );
    if (!$canManageViewers) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // 🔒 فقط کاربرانِ همون سازمان قابلِ افزودن‌اند
    $ph = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $db->prepare("SELECT id FROM users WHERE id IN ($ph) AND organization_id = ? AND is_active = 1 AND is_deleted = 0");
    $stmt->execute(array_merge($userIds, [$task['organization_id']]));
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (empty($validIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'هیچ کاربرِ معتبری یافت نشد']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO task_viewers (task_id, user_id, granted_by, can_view_attachments, can_view_history)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE can_view_attachments = VALUES(can_view_attachments), can_view_history = VALUES(can_view_history)
    ");
    $notif = new Notification($db);
    foreach ($validIds as $viewerId) {
        $stmt->execute([$task_id, $viewerId, $user_id, $canViewAttachments ? 1 : 0, $canViewHistory ? 1 : 0]);
        if ($viewerId !== $user_id) {
            $notif->create([
                'to_user_id'   => $viewerId,
                'title'        => 'دسترسیِ مشاهدهٔ یک کار',
                'message'      => 'به شما دسترسیِ مشاهدهٔ کارِ «' . $task['title'] . '» داده شد',
                'type'         => 'info',
                'link'         => '/pages/task-detail.php?id=' . $task_id,
                'related_type' => 'task',
                'related_id'   => $task_id,
                'sms_pattern'  => 'general',
            ]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'بیننده‌ها اضافه شدند'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("add-viewers error: " . $e->getMessage());
}
