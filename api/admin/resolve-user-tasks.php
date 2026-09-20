<?php
/**
 * API: رسیدگیِ دستیِ کارهایِ بازِ یک کاربر، درست قبل از غیرفعال‌سازیِ او
 * POST /api/admin/resolve-user-tasks.php
 *   body: {
 *     user_id,
 *     resolutions: [
 *       { task_id, action: 'reassign'|'complete'|'cancel', reason, to_user_id? }
 *     ]
 *   }
 *
 * هر سه اکشن اینجا عمداً از مسیرِ عادیِ TaskManager::updateTaskStatus/
 * delegateTask رد نمی‌شن — این یک override اداریه (مدیر دارد کارهایِ یک
 * کاربرِ در‌حالِ‌غیرفعال‌شدن رو جمع‌وجور می‌کنه)، نه یک اقدامِ عادیِ
 * assignee/creator، پس قفل‌هایی مثلِ «چک‌لیستِ ناتمام»/«زنجیره‌ی تأیید»/
 * «کارِ عقب‌افتاده قابلِ ارجاع نیست» اینجا معنی ندارن.
 *
 * بعد از رسیدگیِ موفقِ همه‌ی کارها، خودِ کاربر هم غیرفعال می‌شه — یعنی
 * دیگه لازم نیست toggle-user-status.php جداگانه صدا زده بشه.
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/audit-log.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    $currentUser = loadUserForPermissions($db, $user_id);
    requirePermission($currentUser, 'manage_users');

    $input = json_decode(file_get_contents('php://input'), true);
    $target_user_id = (int) ($input['user_id'] ?? 0);
    $resolutions = $input['resolutions'] ?? [];

    if (!$target_user_id || !is_array($resolutions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    if (!canManageTargetUser($db, $currentUser, $target_user_id)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    // نامِ کاربرِ در‌حالِ‌غیرفعال‌شدن — برایِ متنِ توضیح/تاریخچه/نوتیف
    $stmt = $db->prepare("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $targetUserName = trim($stmt->fetchColumn() ?: '') ?: 'کاربر';

    $notif = new Notification($db);
    $db->beginTransaction();

    foreach ($resolutions as $r) {
        $taskId = (int) ($r['task_id'] ?? 0);
        $action = $r['action'] ?? '';
        $reason = trim((string) ($r['reason'] ?? ''));
        if (!$taskId || !in_array($action, ['reassign', 'complete', 'cancel'], true)) {
            continue;
        }

        $tStmt = $db->prepare("SELECT id, title, task_type, is_workflow_task, assignee_id, organization_id FROM tasks WHERE id = ? AND assignee_id = ? AND is_deleted = 0");
        $tStmt->execute([$taskId, $target_user_id]);
        $task = $tStmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) continue; // یا قبلاً رسیدگی شده، یا مالِ این کاربر نیست — رد شو

        $noteSuffix = $reason !== '' ? (' — ' . $reason) : '';

        if ($action === 'reassign') {
            $toUserId = (int) ($r['to_user_id'] ?? 0);
            if (!$toUserId) continue;

            // 🔒 مقصد باید کاربرِ فعالِ همون سازمان باشه
            $uStmt = $db->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND is_active = 1 AND is_deleted = 0");
            $uStmt->execute([$toUserId, $task['organization_id']]);
            if (!$uStmt->fetch()) continue;

            $db->prepare("UPDATE tasks SET assignee_id = ?, status = 'not_started', updated_at = NOW() WHERE id = ?")
                ->execute([$toUserId, $taskId]);

            $db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, ?, 'delegated', ?)")
                ->execute([$taskId, $user_id, $toUserId, 'ارجاعِ خودکار به‌خاطرِ غیرفعال‌سازیِ ' . $targetUserName . $noteSuffix]);

            try {
                $notif->create([
                    'to_user_id' => $toUserId,
                    'title' => 'کار جدید ارجاع شده',
                    'message' => 'کار «' . $task['title'] . '» به‌خاطرِ غیرفعال‌سازیِ ' . $targetUserName . ' به شما ارجاع داده شد' . ($reason !== '' ? "\n\nتوضیحات: " . $reason : ''),
                    'type' => 'warning',
                    'related_type' => 'task',
                    'related_id' => $taskId,
                    'link' => '/pages/task-detail.php?id=' . $taskId,
                    'skip_self_check' => true,
                ]);
            } catch (Exception $e) { /* بی‌صدا */
            }

        } elseif ($action === 'complete') {
            // 🔒 تکمیلِ اداری — بدونِ نیاز به تأییدِ زنجیره یا تکمیلِ چک‌لیست
            $newStatus = ((int) $task['is_workflow_task'] === 1) ? 'approved' : 'completed';
            $db->prepare("UPDATE tasks SET status = ?, is_pending_approval = 0, updated_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $taskId]);

            $db->prepare("INSERT INTO task_history (task_id, from_user_id, action, notes) VALUES (?, ?, 'completed', ?)")
                ->execute([$taskId, $user_id, 'تکمیلِ اداری به‌خاطرِ غیرفعال‌سازیِ ' . $targetUserName . $noteSuffix]);

        } elseif ($action === 'cancel') {
            $newStatus = ((int) $task['is_workflow_task'] === 1) ? 'stopped' : 'rejected';
            $db->prepare("UPDATE tasks SET status = ?, is_pending_approval = 0, updated_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $taskId]);

            $db->prepare("INSERT INTO task_history (task_id, from_user_id, action, notes) VALUES (?, ?, 'rejected', ?)")
                ->execute([$taskId, $user_id, 'لغوِ اداری به‌خاطرِ غیرفعال‌سازیِ ' . $targetUserName . $noteSuffix]);
        }
    }

    // همه‌ی کارهایِ باقی‌مانده رسیدگی شدن؟ اگه هنوز کاری بدونِ تصمیم مونده
    // (مثلاً فرانت‌اند یک تسک رو جا انداخته)، غیرفعال‌سازی رو متوقف کن —
    // وگرنه دقیقاً همون مشکلی پیش میاد که این فیچر قراره جلوش رو بگیره
    $remainStmt = $db->prepare("
        SELECT COUNT(*) FROM tasks
        WHERE assignee_id = ? AND is_deleted = 0
          AND status NOT IN ('completed', 'approved', 'rejected', 'stopped')
    ");
    $remainStmt->execute([$target_user_id]);
    if ((int) $remainStmt->fetchColumn() > 0) {
        $db->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'هنوز کارهای بازِ رسیدگی‌نشده وجود دارد']);
        exit;
    }

    // غیرفعال‌سازیِ خودِ کاربر — دقیقاً همون منطقِ toggle-user-status.php
    $db->prepare("UPDATE users SET is_active = 0, token_version = token_version + 1 WHERE id = ?")
        ->execute([$target_user_id]);

    $db->commit();

    logSecurityEvent($user_id, 'user_deactivated', $target_user_id, ['via' => 'resolve-user-tasks', 'task_count' => count($resolutions)]);

    echo json_encode(['success' => true, 'message' => 'کارها رسیدگی شد و کاربر غیرفعال شد']);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("resolve-user-tasks error: " . $e->getMessage());
}
