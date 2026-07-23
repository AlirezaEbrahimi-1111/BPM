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
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = intval($input['item_id'] ?? 0);
    $is_done = !empty($input['is_done']) ? 1 : 0;
    $done_note = trim($input['note'] ?? '');   // 🆕 یادداشت انجام‌دهنده

    if (!$item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه آیتم الزامی است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // یافتن آیتم و کار مربوطه (همراه وضعیت فعلی تیک)
    $stmt = $db->prepare("SELECT task_id, is_done FROM task_checklist_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'آیتم یافت نشد']);
        exit;
    }

    // 🔒 قفل: آیتمِ تیک‌خورده دیگر قابل برداشتن نیست (حتی توسط سازنده یا مسئول)
    if ((int)$is_done === 0 && (int)$row['is_done'] === 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'این آیتم تیک خورده و قابل برداشتن نیست'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // دسترسی: creator یا assignee
    $task = getTaskForChecklist($db, $row['task_id'], $user_id);
    if (!$task) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'اجازه تغییر این آیتم را ندارید']);
        exit;
    }
    // 🔒 اگر کار تکمیل/تأیید/متوقف/لغو شده، چک‌لیست قفل است
    if (isChecklistLocked($task)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'این کار به پایان رسیده و چک‌لیست آن قفل شده است']);
        exit;
    }
    // ── تعیین مسئولِ این آیتم و کنترل دسترسی تیک‌زدن ──────────────────
    // قانون:
    //   • آیتمِ دارای ارجاع → فقط مسئولش (کاربر یا اعضای واحد) می‌تواند تیک بزند
    //   • آیتمِ بدون ارجاع → creator یا assignee تسک می‌تواند
    $itemStmt = $db->prepare("SELECT assignee_type, assignee_value FROM task_checklist_items WHERE id = ?");
    $itemStmt->execute([$item_id]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    $assigneeType  = $item['assignee_type']  ?? null;
    $assigneeValue = $item['assignee_value'] ?? null;

    $canToggle = false;

    if ($assigneeType === 'user') {
        // فقط همان کاربر
        $canToggle = ((string)$assigneeValue === (string)$user_id);
    } elseif ($assigneeType === 'section') {
        // 🆕 عضو هر یک از واحدهای کاربر
        $canToggle = in_array($assigneeValue, us_getUserSections($db, $user_id), true);
    } else {
        // بدون ارجاع → creator یا assignee تسک
        $canToggle = ($task['_is_creator'] || $task['_is_assignee']);
    }

    if (!$canToggle) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'این آیتم به فرد دیگری ارجاع شده و فقط مسئولِ آن می‌تواند آن را انجام دهد'
        ]);
        exit;
    }

    // به‌روزرسانی وضعیت آیتم
    if ($is_done) {
        $stmt = $db->prepare("UPDATE task_checklist_items
                              SET is_done = 1, done_at = NOW(), done_by = ?, done_note = ?
                              WHERE id = ?");
        $stmt->execute([$user_id, ($done_note !== '' ? $done_note : null), $item_id]);
    } else {
        $stmt = $db->prepare("UPDATE task_checklist_items
                              SET is_done = 0, done_at = NULL, done_by = NULL, done_note = NULL
                              WHERE id = ?");
        $stmt->execute([$item_id]);
    }
    // 🆕 ثبت در تاریخچهٔ کار
    if ($is_done) {
        try {
            $tStmt = $db->prepare("SELECT title FROM task_checklist_items WHERE id = ?");
            $tStmt->execute([$item_id]);
            $itemTitle = $tStmt->fetchColumn() ?: 'آیتم';
            $histNote = "آیتم چک‌لیست «{$itemTitle}» انجام شد";
            if ($done_note !== '') {
                $histNote .= " — {$done_note}";
            }
            addChecklistEvent(
                $db, $row['task_id'], 'checklist_done',
                $user_id, null, $histNote
            );
        } catch (Exception $hErr) {
            error_log("checklist history failed: " . $hErr->getMessage());
        }
    }

    // 🆕 اگر آیتم تیک خورد، به ارجاع‌دهنده (سازنده‌ی آیتم) اطلاع بده
    if ($is_done) {
        try {
            // اطلاعات کامل آیتم را برای اعلان بخوان
            $itemFull = $db->prepare("SELECT title, created_by FROM task_checklist_items WHERE id = ?");
            $itemFull->execute([$item_id]);
            $itemRow = $itemFull->fetch(PDO::FETCH_ASSOC);
            if ($itemRow) {
                notifyChecklistItemDone($db, $itemRow, $task, $user_id);
            }
        } catch (Exception $notifyErr) {
            error_log("checklist toggle notify failed: " . $notifyErr->getMessage());
        }
    }
    // همگام‌سازی وضعیت کار با چک‌لیست (هر دو جهت)
    $auto = false;
    if ($is_done) {
        // اگر با این تیک همه کامل شدند → تکمیل خودکار، وگرنه همگام‌سازی
        $auto = maybeAutoComplete($db, $task, $user_id);
        if (!$auto) {
            syncTaskStatusWithChecklist($db, $task, $user_id);
        }
    } else {
        // تیک برداشته شد → ممکن است نیاز به بازگشت وضعیت باشد
        syncTaskStatusWithChecklist($db, $task, $user_id);
    }

    $p = checklistProgress($db, $row['task_id']);
    echo json_encode([
        'success' => true,
        'auto_completed' => $auto,
        'percent' => $p['percent'],
        'done' => $p['done'],
        'total' => $p['total']
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("checklist/toggle error: " . $e->getMessage());
}
