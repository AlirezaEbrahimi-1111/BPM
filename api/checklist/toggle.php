<?php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once __DIR__ . '/_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/TaskManager.php';
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
    //   • آیتمِ بدون ارجاع → فقط مسئولِ فعلیِ کار (assignee) می‌تواند تیک بزند.
    //     ✅ تعریف‌کننده (creator) پس از واگذاریِ کار به شخص دیگر، دیگر حق
    //     تیک‌زدن ندارد — فقط می‌تواند آیتم اضافه/حذف کند (add-item.php،
    //     delete-item.php). اگر creator خودش هنوز assignee باشد (کار
    //     واگذار نشده)، همان‌طور که قبلاً بود می‌تواند تیک بزند، چون
    //     _is_assignee در آن حالت هم true است.
    $itemStmt = $db->prepare("SELECT assignee_type, assignee_value FROM task_checklist_items WHERE id = ?");
    $itemStmt->execute([$item_id]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    $canToggle = canActOnChecklistItem($db, $item, $task, $user_id);

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
            error_log("checklist history write error: " . $hErr->getMessage() . " | item_id={$item_id} | task_id={$row['task_id']}");
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
            error_log("notifyChecklistItemDone error: " . $notifyErr->getMessage() . " | item_id={$item_id} | task_id={$row['task_id']}");
        }
    }
    // 🆕 شروعِ خودکارِ کار با اولین تیکِ چک‌لیست — هم برای تسک معمولی، هم مرحلهٔ روتین.
    // چون تیک‌زدنِ یک آیتم یعنی کاربر عملاً شروع به کار کرده، دیگر منطقی نیست دکمهٔ
    // «شروع کار» را همچنان نشان بدهیم. این مسیر همان چیزی است که خودِ دکمهٔ «شروع کار»
    // هم صدا می‌زند (TaskManager::updateTaskStatus با status='in_progress')، پس برای
    // مرحلهٔ روتین هم امن است — برخلاف تکمیل، شروع‌کردن نیازی به WorkflowManager ندارد.
    // اگر syncTaskStatusWithChecklist (زیر) قرار است همین کار را انجام دهد (تسکِ معمولیِ
    // دارای «تکمیل خودکار»)، از دوباره‌کاری/تاریخچهٔ تکراری خودداری می‌کنیم.
    if ($is_done && in_array($task['status'], ['not_started', 'delegated', 'rejected'], true)) {
        $handledByLegacySync = empty($task['is_workflow_task']) && !empty($task['checklist_auto_complete']);
        if (!$handledByLegacySync) {
            try {
                $tm = new TaskManager($db);
                $tm->updateTaskStatus($task['id'], 'in_progress', $user_id, 'کار با تیک‌زدنِ اولین آیتمِ چک‌لیست شروع شد');
            } catch (Exception $startErr) {
                error_log('checklist auto-start error: ' . $startErr->getMessage());
            }
        }
    }

    // همگام‌سازی وضعیت کار با چک‌لیست (هر دو جهت)
    // 🔒 خط قرمز: برای مرحلهٔ روتین (is_workflow_task=1) این همگام‌سازی نباید اجرا شود —
    // این دو تابع مستقیماً ستون tasks.status را دستکاری می‌کنند بدون این‌که از
    // WorkflowManager::completeStep رد شوند (که workflow_instance_steps را هم به‌روز
    // می‌کند و مرحلهٔ بعدی را فعال می‌کند). بدون این قفل، تیک‌زدنِ آخرین آیتمِ چک‌لیستِ
    // یک مرحلهٔ روتین باعث می‌شد tasks.status مستقیم 'completed' شود ولی
    // workflow_instance_steps همچنان 'active' بماند و کل روتین گیر کند — دقیقاً همان
    // باگی که تسک ۱۳۷۳ را قفل کرد. برای روتین، تکمیل فقط باید از طریق دکمهٔ
    // «تکمیل کار» (که به‌درستی completeStep را صدا می‌زند) انجام شود.
    $auto = false;
    if (empty($task['is_workflow_task'])) {
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
    }

    // 🔒 هم‌راستا با get.php: مسئولِ صرفِ یک/چند آیتم، فقط پیشرفتِ همان
    // آیتم‌های خودش را ببیند، نه کل چک‌لیستِ کار را
    $onlyChecklistAssignee = !$task['_is_creator'] && !$task['_is_assignee'];
    if ($onlyChecklistAssignee) {
        $userSections = us_getUserSections($db, $user_id);

        $allStmt = $db->prepare("SELECT is_done, assignee_type, assignee_value FROM task_checklist_items WHERE task_id = ?");
        $allStmt->execute([$row['task_id']]);
        $allItems = $allStmt->fetchAll(PDO::FETCH_ASSOC);

        $ownItems = filterChecklistItemsForViewer($allItems, true, $user_id, $userSections);
        $p = checklistProgressFromItems($ownItems);
    } else {
        $p = checklistProgress($db, $row['task_id']);
    }
    echo json_encode([
        'success' => true,
        'auto_completed' => $auto,
        'percent' => $p['percent'],
        'done' => $p['done'],
        'total' => $p['total']
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("checklist toggle error: " . $e->getMessage() . " | user_id=" . ($user_id ?? 'n/a') . " | item_id=" . ($item_id ?? 'n/a'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
}
