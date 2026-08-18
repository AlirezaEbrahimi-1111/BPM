<?php

/**
 * API: my-tasks.php
 * نسخه اصلاح شده — محاسبه دوره معوقه بدون جمعه و تعطیلات رسمی
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-dates-helper.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/checklist-search-helper.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/recurring-helper.php';
    $user_id = requireAuth();
    $user = getUserInfo($user_id);

    if (!$user || !isset($user['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    $activity_section = $user['activity_section'] ?? null;
    $org_id = $user['organization_id'] ?? null;
    $today = date('Y-m-d');

    $database = new Database();
    $db = $database->getConnection();

    // 🆕 همهٔ واحدهای کاربر (چندواحدی)
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
    $userSections = us_getUserSections($db, $user_id);
    $secPh = us_placeholders($userSections);   // مثل '?,?' یا 'NULL'

    if (!$db) {
        throw new Exception('اتصال به پایگاه داده ناموفق');
    }

    // ════════════════════════════════════════════════════════════
    //  مسیر بایگانیِ چک‌لیست
    //  تسک‌هایی که کاربر «صرفاً چک‌لیستی» است و همه‌ی آیتم‌های
    //  مربوط به او (یا واحدش) تیک خورده‌اند.
    // ════════════════════════════════════════════════════════════
    $filter = $_GET['filter'] ?? '';
    if ($filter === 'checklist_archive') {

        $archiveSql = "
            SELECT
                t.id, t.title, t.description, t.task_type, t.priority, t.status,
                t.due_date, t.created_at, t.creator_id, t.assignee_id,
                CONCAT(COALESCE(creator.first_name,''),' ',COALESCE(creator.last_name,'')) AS creator_name,
                CONCAT(COALESCE(assignee.first_name,''),' ',COALESCE(assignee.last_name,'')) AS assignee_name
            FROM tasks t
            LEFT JOIN users creator  ON t.creator_id  = creator.id
            LEFT JOIN users assignee ON t.assignee_id = assignee.id
            WHERE t.is_deleted = 0
              AND t.organization_id = ?
              -- کاربر نباید از راه دیگری (assignee) به تسک وصل باشد
              AND t.assignee_id <> ?
              -- حداقل یک آیتم به کاربر/واحدش ارجاع شده باشد
              AND EXISTS (
                  SELECT 1 FROM task_checklist_items ci
                  WHERE ci.task_id = t.id
                   AND (
                        (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
                        OR (ci.assignee_type = 'section' AND ci.assignee_value IN ($secPh))
                    )
              )
              -- ولی هیچ آیتمِ ناتمامی برای کاربر/واحدش باقی نمانده باشد
              AND NOT EXISTS (
                  SELECT 1 FROM task_checklist_items ci2
                  WHERE ci2.task_id = t.id
                    AND ci2.is_done = 0
                    AND (
                        (ci2.assignee_type = 'user'    AND ci2.assignee_value = ?)
                        OR (ci2.assignee_type = 'section' AND ci2.assignee_value IN ($secPh))
                    )
              )
            ORDER BY t.created_at DESC
        ";

        $stmt = $db->prepare($archiveSql);
        $stmt->execute(array_merge(
            [
                $org_id,           // t.organization_id = ?
                $user_id,          // t.assignee_id <> ?
                (string) $user_id, // EXISTS: ارجاع به کاربر
            ],
            $userSections,         // EXISTS: ارجاع به واحدها (IN)
            [(string) $user_id], // NOT EXISTS: کاربر
            $userSections          // NOT EXISTS: واحدها (IN)
        ));
        $archived = $stmt->fetchAll(PDO::FETCH_ASSOC);
        attachChecklistTitles($db, $archived);
        echo json_encode([
            'success' => true,
            'data' => [
                'tasks' => $archived,
                'count' => count($archived),
                'user_id' => $user_id,
                'filter' => 'checklist_archive'
            ],
            'message' => 'کارهای تمام‌شده‌ی چک‌لیستی'
        ]);
        exit; // پایان مسیر بایگانی — کوئری اصلی اجرا نشود
    }

    // ─── بارگذاری تعطیلات (یک‌بار برای کل request) ───────────────────────────
    $holidays = getHolidaySet($db);

    // Query ساده
    $sql = "
SELECT DISTINCT 
    t.id,
    t.title,
    t.description,
    t.task_type,
    t.priority,
    t.status,
    t.due_date,
    t.start_date,
    t.end_date,
    t.last_approved_date,
    t.period_type,
    t.overdue_forgiven_credit,
    t.is_pending_approval,
    t.has_pending_renewal_request,
    t.is_workflow_task,
    t.deadline,
    t.original_deadline,
    t.activity_section,
    t.creator_id,
    t.assignee_id,
    t.created_at,
    t.updated_at,
    dr.current_approver_id,
    dr.created_at AS deadline_request_date,
    t.has_pending_deadline_request,
    t.has_pending_overdue_request,
    ph.last_pending_date,
    creator.first_name as creator_first_name,
    creator.last_name as creator_last_name,
    CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
    assignee.first_name as assignee_first_name,
    assignee.last_name as assignee_last_name,
    CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
    (
        SELECT CONCAT_WS(' ',
            (
                SELECT GROUP_CONCAT(
                    CONCAT_WS(' ', fu.first_name, fu.last_name, tu.first_name, tu.last_name,
                        CASE WHEN th.notes LIKE '{%' THEN JSON_UNQUOTE(JSON_EXTRACT(th.notes, '$.reason')) ELSE th.notes END)
                    SEPARATOR ' '
                )
                FROM task_history th
                LEFT JOIN users fu ON th.from_user_id = fu.id
                LEFT JOIN users tu ON th.to_user_id = tu.id
                WHERE th.task_id = t.id
            ),
            (
                SELECT GROUP_CONCAT(ta.file_original_name SEPARATOR ' ')
                FROM task_attachments ta
                WHERE ta.task_id = t.id
            )
        )
    ) AS history_text,
        t.group_id,
    tg.name as group_name,
    tg.color as group_color,
    tg.icon as group_icon
FROM tasks t
LEFT JOIN users creator ON t.creator_id = creator.id
LEFT JOIN users assignee ON t.assignee_id = assignee.id
LEFT JOIN task_groups tg ON t.group_id = tg.id
LEFT JOIN deadline_requests dr ON t.id = dr.task_id AND dr.status = 'pending'
LEFT JOIN overdue_clear_requests ocr ON t.id = ocr.task_id AND ocr.status = 'pending'
LEFT JOIN (
    SELECT h1.task_id, h1.created_at as last_pending_date
    FROM task_history h1
    WHERE h1.action = 'pending_approval'
    AND NOT EXISTS (
        SELECT 1 FROM task_history h2
        WHERE h2.task_id = h1.task_id
        AND h2.action = 'rejected'
        AND h2.created_at > h1.created_at
    )
    AND h1.created_at = (
        SELECT MAX(h3.created_at) FROM task_history h3
        WHERE h3.task_id = h1.task_id
        AND h3.action = 'pending_approval'
        AND NOT EXISTS (
            SELECT 1 FROM task_history h4
            WHERE h4.task_id = h3.task_id
            AND h4.action = 'rejected'
            AND h4.created_at > h3.created_at
        )
    )
) ph ON t.id = ph.task_id
WHERE t.is_deleted = 0 
AND t.status != 'rejected'
  AND (
    dr.current_approver_id = ?
    OR ocr.current_approver_id = ?
    OR (
      dr.current_approver_id IS NULL 
    AND (
      (
        t.assignee_id = ?
        AND NOT (
          t.is_workflow_task = 1
          AND NOT EXISTS (
              SELECT 1 FROM workflow_instance_steps wis
              WHERE wis.task_id = t.id AND wis.status IN ('active', 'pending', 'delayed')
          )
        )
      )
      OR (
        t.is_workflow_task = 1 
        AND t.activity_section IN ($secPh)
        AND t.organization_id = ?
        AND t.status NOT IN ('completed', 'cancelled')
        AND (t.assignee_id IS NULL OR t.assignee_id = 0)
        AND EXISTS (
            SELECT 1 FROM workflow_instance_steps wis
            WHERE wis.task_id = t.id AND wis.status IN ('active', 'pending', 'delayed')
        )
      )
      OR (t.is_pending_approval = 1 AND t.assignee_id = ?)
    )
    )
     OR EXISTS (
        SELECT 1 FROM task_checklist_items ci
        WHERE ci.task_id = t.id
        AND ci.is_done = 0
          AND (
              (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
              OR (ci.assignee_type = 'section' AND ci.assignee_value IN ($secPh) AND t.organization_id = ?)
          )
    )
  )
ORDER BY 
    CASE WHEN t.has_pending_deadline_request = 1 AND t.creator_id = ? THEN 0 ELSE 1 END,
    CASE WHEN t.has_pending_overdue_request = 1 AND ocr.current_approver_id = ? THEN 0 ELSE 1 END,
    CASE WHEN t.is_pending_approval = 1 THEN 1 ELSE 2 END,

    -- مرتب‌سازی بر اساس موعد واقعی
    GREATEST(
        COALESCE(t.original_deadline, '1000-01-01'),
        COALESCE(t.deadline, '1000-01-01'),
        COALESCE(t.due_date, '1000-01-01')
    ) ASC,

    -- اگر تاریخ برابر بود، قدیمی‌تر اول
    t.created_at ASC
";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge(
        [
            $user_id,          // ۱. dr.current_approver_id = ?
            $user_id,          // ۲. ocr.current_approver_id = ?
            $user_id,          // ۳. t.assignee_id = ?
        ],
        $userSections,         // ۴. activity_section IN
        [
            $org_id,           // ۴.۵ 🆕 organization_id کار روتین
            $user_id,          // ۵. is_pending assignee
            (string) $user_id, // ۶. چک‌لیست کاربر
        ],
        $userSections,         // ۷. 🆕 ارجاع چک‌لیست به واحدها IN (...)
        [
            $org_id,           // ۸. t.organization_id در چک‌لیست
            $user_id,          // ۹. ORDER BY: creator_id
            $user_id,          // ۱۰. ORDER BY: ocr.current_approver_id
        ]
    ));

    $all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
   

    // محاسبه overdue_periods
    $processed_tasks = [];

    // 🆕 پیش‌واکشیِ دسته‌ایِ تاریخ‌های تکمیل برای همهٔ تسک‌های دوره‌ای —
    // به‌جای یک کوئری جداگانه به task_history به‌ازای هر تسک (مشکل N+1)
    $continuousIds = [];
    foreach ($all_tasks as $t) {
        if ($t['task_type'] === 'continuous') $continuousIds[] = $t['id'];
    }
    $completionMap = pe_preloadCompletionDates($db, $continuousIds);

    foreach ($all_tasks as $task) {
        $task = enrichTaskDates($task, $db, $holidays, $today, $completionMap);
        // کار دوره‌ای که بازه‌اش تمام شده → نمایش نده
        if (
            $task['task_type'] === 'continuous' && !empty($task['end_date'])
            && $task['next_due_date'] > $task['end_date']
        ) {
            continue;
        }
        $processed_tasks[] = $task;
    }


    attachChecklistTitles($db, $processed_tasks);


    echo json_encode([
        'success' => true,
        'data' => [
            'tasks' => $processed_tasks,
            'count' => count($processed_tasks),
            'user_id' => $user_id,
            'activity_section' => $activity_section,
            'today' => $today
        ],
        'message' => 'کارها با موفقیت بارگذاری شدند'
    ]);
} catch (Exception $e) {
    error_log("API Error in my-tasks.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}

if (isset($db)) {
    $db = null;
}
