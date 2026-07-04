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
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php'; // ← اضافه شد
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/checklist-search-helper.php';
    $user_id = requireAuth();
    $user = getUserInfo($user_id);

    if (!$user || !isset($user['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    $activity_section = $user['activity_section'] ?? null;
    $today = date('Y-m-d');

    $database = new Database();
    $db = $database->getConnection();

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
              -- کاربر نباید از راه دیگری (assignee) به تسک وصل باشد
              AND t.assignee_id <> ?
              -- حداقل یک آیتم به کاربر/واحدش ارجاع شده باشد
              AND EXISTS (
                  SELECT 1 FROM task_checklist_items ci
                  WHERE ci.task_id = t.id
                    AND (
                        (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
                        OR (ci.assignee_type = 'section' AND ci.assignee_value = ?)
                    )
              )
              -- ولی هیچ آیتمِ ناتمامی برای کاربر/واحدش باقی نمانده باشد
              AND NOT EXISTS (
                  SELECT 1 FROM task_checklist_items ci2
                  WHERE ci2.task_id = t.id
                    AND ci2.is_done = 0
                    AND (
                        (ci2.assignee_type = 'user'    AND ci2.assignee_value = ?)
                        OR (ci2.assignee_type = 'section' AND ci2.assignee_value = ?)
                    )
              )
            ORDER BY t.created_at DESC
        ";

        $stmt = $db->prepare($archiveSql);
        $stmt->execute([
            $user_id,            // t.assignee_id <> ?  (نباید assignee باشد)
            (string) $user_id,    // EXISTS: ارجاع به کاربر
            $activity_section,   // EXISTS: ارجاع به واحد
            (string) $user_id,    // NOT EXISTS: کاربر
            $activity_section    // NOT EXISTS: واحد
        ]);
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
    ph.last_pending_date,
    creator.first_name as creator_first_name,
    creator.last_name as creator_last_name,
    CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
    assignee.first_name as assignee_first_name,
    assignee.last_name as assignee_last_name,
    CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
        t.group_id,
    tg.name as group_name,
    tg.color as group_color,
    tg.icon as group_icon
FROM tasks t
LEFT JOIN users creator ON t.creator_id = creator.id
LEFT JOIN users assignee ON t.assignee_id = assignee.id
LEFT JOIN task_groups tg ON t.group_id = tg.id
LEFT JOIN deadline_requests dr ON t.id = dr.task_id AND dr.status = 'pending'
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
    OR (
      dr.current_approver_id IS NULL 
      AND (
        t.assignee_id = ? 
        OR (
  t.is_workflow_task = 1 
  AND t.activity_section = ?
  AND t.status NOT IN ('completed', 'cancelled')
  AND (t.assignee_id IS NULL OR t.assignee_id = 0)
  AND EXISTS (
      SELECT 1 FROM workflow_instance_steps wis
      WHERE wis.task_id = t.id AND wis.status = 'active'
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
              OR (ci.assignee_type = 'section' AND ci.assignee_value = ?)
          )
    )
  )

ORDER BY 
    CASE WHEN t.has_pending_deadline_request = 1 AND t.creator_id = ? THEN 0 ELSE 1 END,
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
    $stmt->execute([
        $user_id,            // dr.current_approver_id
        $user_id,            // t.assignee_id
        $activity_section,   // واحدِ کار روتین
        $user_id,            // assignee در حالت pending
        (string) $user_id,    // 🆕 ارجاع چک‌لیست به این کاربر
        $activity_section,   // 🆕 ارجاع چک‌لیست به واحدِ این کاربر
        $user_id             // ORDER BY: creator_id
    ]);

    $all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // محاسبه overdue_periods
    $processed_tasks = [];

    foreach ($all_tasks as $task) {
        $task['overdue_periods'] = 0;
        $task['next_due_date'] = null;
        $task['days_remaining'] = null;
        // ← فیلد جدید: تاخیر به روز کاری (برای نمایش در badge)
        $task['working_days_delayed'] = 0;

        // ══════════════════════════════════════════════════════
        //  کار دوره‌ای (continuous)
        // ══════════════════════════════════════════════════════
        if ($task['task_type'] === 'continuous' && !empty($task['start_date'])) {
            try {
                $start_date = new DateTime($task['start_date']);
                $current_date = new DateTime($today);
                $current_date->setTime(0, 0, 0);

                // هنوز شروع نشده
                if ($current_date < $start_date) {
                    $task['overdue_periods'] = 0;
                    $task['next_due_date'] = $task['start_date'];
                    // days_remaining را بر اساس روز تقویمی نگه می‌داریم (منفی نیست)
                    $task['days_remaining'] = (int) $current_date->diff($start_date)->format('%r%a');
                    $processed_tasks[] = $task;
                    continue;
                }

                // تعداد دوره‌های انجام شده
                $count_sql = "SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'";
                $count_stmt = $db->prepare($count_sql);
                $count_stmt->execute([$task['id']]);
                $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
                $completed_count = (int) ($count_result['count'] ?? 0);

                // ✅ محاسبه با روزهای کاری (بدون جمعه و تعطیلات)
                $task['overdue_periods'] = calcOverduePeriods(
                    $task['period_type'],
                    $start_date,
                    $current_date,
                    $completed_count,
                    $holidays
                );

                // ✅ کسر دوره‌های بخشیده‌شده (رفع معوقه)
                $forgiven_credit = (int) ($task['overdue_forgiven_credit'] ?? 0);
                $task['overdue_periods'] = max(0, $task['overdue_periods'] - $forgiven_credit);

                // محاسبه اولین موعد انجام نشده
                $next_due = clone $start_date;
                for ($i = 0; $i < $completed_count; $i++) {
                    switch ($task['period_type']) {
                        case 'daily':
                            // برای daily، موعد بعدی اولین روز کاری بعد از آخرین انجام
                            do {
                                $next_due->modify('+1 day');
                            } while (!isWorkingDay($next_due, $holidays));
                            break;
                        case 'weekly':
                            $next_due->modify('+1 week');
                            break;
                        case 'monthly':
                            $next_due->modify('+1 month');
                            break;
                    }
                }

                $task['next_due_date'] = $next_due->format('Y-m-d');

                // بررسی تاریخ پایان
                if (!empty($task['end_date'])) {
                    $end_date = new DateTime($task['end_date']);
                    if ($next_due > $end_date) {
                        continue; // کار پایان یافته، نمایش نده
                    }
                }

                $task['days_remaining'] = (int) $current_date->diff($next_due)->format('%r%a');
            } catch (Exception $e) {
                $task['overdue_periods'] = 0;
                error_log("overdue calc error task#{$task['id']}: " . $e->getMessage());
            }
        }

        // ══════════════════════════════════════════════════════
        //  کار مقطعی (periodic)
        // ══════════════════════════════════════════════════════
        elseif ($task['task_type'] === 'periodic') {
            $dates = [];

            if (!empty($task['due_date']))
                $dates[] = new DateTime($task['due_date']);
            if (!empty($task['deadline']))
                $dates[] = new DateTime($task['deadline']);
            if (!empty($task['original_deadline']))
                $dates[] = new DateTime($task['original_deadline']);

            if (!empty($dates)) {
                $max_date = max($dates);
                $task['next_due_date'] = $max_date->format('Y-m-d');

                $current_date = new DateTime($today);

                // days_remaining: تقویمی (مثبت=مانده، منفی=گذشته)
                $task['days_remaining'] = (int) $current_date->diff($max_date)->format('%r%a');

                // ✅ تاخیر به روز کاری (بدون جمعه و تعطیلات)
                if (
                    $current_date > $max_date &&
                    $task['status'] !== 'completed' &&
                    $task['status'] !== 'approved'
                ) {
                    $task['working_days_delayed'] = calcPeriodicDelayWorkingDays(
                        $max_date->format('Y-m-d'),
                        $today,
                        $holidays
                    );
                }
            }
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
