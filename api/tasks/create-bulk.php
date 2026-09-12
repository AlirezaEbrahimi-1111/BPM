<?php

ob_start();

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/Notification.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد غیرمجاز']);
    exit;
}

try {
    // جایگزین requireAuth() اگر middleware کار نکرد
    $auth    = new Auth();
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'احراز هویت الزامی است']);
        exit;
    }
    $input   = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'فرمت JSON نامعتبر است']);
        exit;
    }

    // ── اعتبارسنجی ورودی ─────────────────────────────────────────────────────
    $base_task    = $input['base_task']    ?? null;
    $assignee_ids = $input['assignee_ids'] ?? [];
    $section_key  = $input['section_key']  ?? null;   // 🆕 اگر بیاید، کاربران واحد را بک‌اند پیدا می‌کند

    // base_task همیشه لازم است؛ assignee_ids یا section_key — یکی کافی است
    if (empty($base_task)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'base_task الزامی است']);
        exit;
    }
    if (empty($assignee_ids) && empty($section_key)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'assignee_ids یا section_key الزامی است']);
        exit;
    }

    if (empty($base_task['title'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'عنوان کار الزامی است']);
        exit;
    }

    if (!in_array($base_task['task_type'] ?? '', ['periodic', 'continuous'])) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نوع کار نامعتبر است']);
        exit;
    }

    // ── اتصال به دیتابیس ─────────────────────────────────────────────────────
    $database = new Database();
    $db       = $database->getConnection();
    if (!$db) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطا در اتصال به پایگاه داده']);
        exit;
    }

    // دریافت organization_id سازنده
    $stmt = $db->prepare("SELECT organization_id, role, first_name, last_name, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $creator = $stmt->fetch(PDO::FETCH_ASSOC);
    $organization_id = $creator['organization_id'] ?? null;
    $creator_name    = trim(($creator['first_name'] ?? '') . ' ' . ($creator['last_name'] ?? ''));
    if (empty($creator_name)) $creator_name = $creator['phone'] ?? 'کاربر';

    // 🆕 اگر section_key آمده، کاربرانِ همهٔ واحدها را بک‌اند پیدا می‌کند
    //    (شاملِ کاربرانی که این واحد، واحدِ دومشان است)
    if (!empty($section_key)) {
        $assignee_ids = us_getSectionUserIds($db, $section_key, $organization_id);
        if (empty($assignee_ids)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'هیچ کاربری در واحد انتخابی یافت نشد']);
            exit;
        }
    }

    // ── اعتبارسنجی assignee_ids: فقط کاربران همین سازمان ─────────────────────
    $placeholders = implode(',', array_fill(0, count($assignee_ids), '?'));
    $stmt = $db->prepare(
        "SELECT id FROM users 
         WHERE id IN ($placeholders) 
           AND organization_id = ? 
           AND is_active = 1 
           AND is_deleted = 0"
    );
    $stmt->execute(array_merge(array_map('intval', $assignee_ids), [$organization_id]));
    $validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($validIds)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'هیچ کاربر معتبری یافت نشد']);
        exit;
    }

    // 🔒 موعد فقط وقتی الزامیه که کار به کسِ دیگه‌ای ارجاع داده بشه — همان
    // قانونِ api/tasks/create.php. این‌جا TaskManager::createTask() به آن
    // لایه اعتماد می‌کند و خودش این قانون را چک نمی‌کند (نگاه کن به کامنتِ
    // بالای createTask)، پس این endpoint هم باید مثلِ create.php چک کند —
    // وگرنه واگذاریِ گروهی/واحدی می‌تواند بدونِ موعد ثبت شود (باگِ تسکِ ۶۱۵).
    $hasOtherAssignee = false;
    foreach ($validIds as $vid) {
        if ((int) $vid !== (int) $user_id) {
            $hasOtherAssignee = true;
            break;
        }
    }
    if (($base_task['task_type'] ?? '') === 'periodic' && empty($base_task['due_date']) && $hasOtherAssignee) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'تاریخ انجام برای کارهای مقطعیِ ارجاع‌داده‌شده الزامی است']);
        exit;
    }

    // 🔒 واگذاریِ چندواحدی («همه واحدها/همه کاربران») فقط برای سرپرست
    $ph2 = implode(',', array_fill(0, count($validIds), '?'));
    $stmt = $db->prepare("SELECT COUNT(DISTINCT activity_section) FROM users WHERE id IN ($ph2)");
    $stmt->execute(array_map('intval', $validIds));
    $distinctSections = (int)$stmt->fetchColumn();

    if (($creator['role'] ?? '') !== 'supervisor' && $distinctSections > 1) {
        error_log("create-bulk.php denied (multi-section) | user_id={$user_id} | role={$creator['role']} | distinctSections={$distinctSections}");
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط سرپرست می‌تواند برای همهٔ واحدها/کاربران کار ایجاد کند']);
        exit;
    }
    // ── ایجاد تسک به ازای هر کاربر ──────────────────────────────────────────
    $taskManager   = new TaskManager($db);
    $notif         = new Notification($db);
    $created_count = 0;
    $first_task_id = null;
    $errors        = [];

    foreach ($validIds as $assignee_id) {
        $taskData               = $base_task;
        $taskData['assignee_id'] = (int)$assignee_id;

        $result = $taskManager->createTask($taskData, $user_id, $organization_id);

        if ($result['success']) {
            $created_count++;
            if (!$first_task_id) $first_task_id = $result['task_id'];

            // ارسال نوتیفیکیشن (فقط اگر assignee != سازنده)
            if ((int)$assignee_id !== (int)$user_id) {
                try {
                    $notif->create([
                        'to_user_id'   => $assignee_id,
                        'title'        => 'کار جدید برای شما',
                        'message'      => "یک کار جدید با عنوان «{$base_task['title']}» توسط {$creator_name} برای شما ایجاد شد",
                        'type'         => 'info',
                        'link'         => "/pages/task-detail.php?id={$result['task_id']}",
                        'related_type' => 'task',
                        'related_id'   => $result['task_id']
                    ]);
                } catch (Exception $ne) {
                    error_log("Bulk notification error for user {$assignee_id}: " . $ne->getMessage());
                }
            }
        } else {
            $errors[] = "user {$assignee_id}: " . ($result['message'] ?? 'unknown error');
            error_log("Bulk create failed for user {$assignee_id}: " . ($result['message'] ?? ''));
        }
    }

    ob_clean();

    if ($created_count === 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'هیچ تسکی ایجاد نشد', 'errors' => $errors]);
        exit;
    }

    http_response_code(201);
    echo json_encode([
        'success'       => true,
        'message'       => "{$created_count} تسک با موفقیت ایجاد شد",
        'created_count' => $created_count,
        'first_task_id' => $first_task_id,
        'errors'        => $errors   // اگر برخی fail شدن، لاگ میشه
    ]);
    exit;
} catch (Exception $e) {
    error_log("create-bulk error: " . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای داخلی سرور']);
    exit;
}
