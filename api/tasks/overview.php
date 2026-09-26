<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/checklist-search-helper.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/working-days-helper.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/recurring-helper.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/task-dates-helper.php';

    // overdue_periods + next_due_date برای کارهای دوره‌ای (هم‌راستا با my-tasks.php/delegated-tasks.php)
    //
    // ⚠️ رفع باگ: قبلا اینجا یک فرمول جداگانه و قدیمی (calcOverduePeriods بر
    // مبنای شمارش خام completed_count) استفاده می‌شد که می‌توانست با آنچه
    // my-tasks.php/delegated-tasks.php/task-detail.php نشان می‌دهند فرق داشته
    // باشد (مثلا اگر برای یک روز دو رکورد completed ثبت شده باشد، آن فرمول
    // عدد معوقهٔ اشتباه می‌داد). حالا همه‌جا از یک مرجع مشترک استفاده می‌شود:
    // enrichTaskDates() → pe_state() در includes/period-engine.php.
    function attachContinuousFields($db, &$tasks, $user_id)
    {
        $holidays = getHolidaySet($db);
        $today = date('Y-m-d');

        // 🆕 به‌جای یک کوئری به task_history به‌ازای هر تسک دوره‌ای (مشکل N+1 که
        // باعث کندی این صفحه می‌شد)، تاریخ‌های تکمیل همهٔ تسک‌های دوره‌ای را
        // یک‌جا واکشی می‌کنیم و به enrichTaskDates/maybeStartNextPeriod می‌دهیم.
        $continuousIds = [];
        foreach ($tasks as $t) {
            if ($t['task_type'] === 'continuous' && !empty($t['start_date'])) {
                $continuousIds[] = $t['id'];
            }
        }
        $completionMap = pe_preloadCompletionDates($db, $continuousIds);

        foreach ($tasks as &$task) {
            if ($task['task_type'] === 'continuous' && !empty($task['start_date'])) {
                // برگشت از period_done به دوره‌ی بعدی (اگر موعدش رسیده)
                maybeStartNextPeriod($db, $task, $user_id, $holidays, $completionMap);
            }
            $task = enrichTaskDates($task, $db, $holidays, $today, $completionMap);
        }
        unset($task);
    }

    // ========================================
    // تابع کمکی (نسخهٔ دسته‌ای): آیا کاربر اجازهٔ ارسال یادآوری دارد؟
    // شرط 1: کاربر creator تسک باشد
    // شرط 2: یا در زنجیره ارجاعات، آخرین نفری باشد که کار را واگذار کرده
    //
    // 🆕 قبلا این بررسی به‌ازای هر تسک، جداگانه صدا زده می‌شد (تا ۲ کوئری
    // برای هر تسک) — یعنی برای سرپرستی که ۹۰۰ تسک می‌بیند، تا ~۱۸۰۰ کوئری
    // جدا فقط برای همین یک ویژگی. حالا با یک کوئری دسته‌ای (IN) و پردازش
    // درون‌حافظه‌ای، همان نتیجه برای همهٔ تسک‌ها یک‌جا محاسبه می‌شود.
    //
    // @return array<int,bool>  task_id => آیا یادآوری مجاز است
    // ========================================
    function batchCanUserRemind($db, $user_id, array $tasks): array
    {
        $result = [];
        $needsHistoryCheck = [];

        foreach ($tasks as $task) {
            if ($task['assignee_id'] == $user_id) {
                $result[$task['id']] = false;
            } elseif ($task['creator_id'] == $user_id) {
                $result[$task['id']] = true;
            } else {
                $needsHistoryCheck[] = $task['id'];
            }
        }

        if (empty($needsHistoryCheck)) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($needsHistoryCheck), '?'));
        $stmt = $db->prepare("
            SELECT task_id, from_user_id
            FROM task_history
            WHERE task_id IN ($placeholders)
              AND action IN ('assigned', 'delegated')
            ORDER BY task_id ASC, created_at ASC, id ASC
        ");
        $stmt->execute($needsHistoryCheck);

        // گروه‌بندی رکوردهای ارجاع بر اساس task_id (به ترتیب زمانی صعودی)
        $byTask = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byTask[$row['task_id']][] = $row['from_user_id'];
        }

        foreach ($needsHistoryCheck as $taskId) {
            $fromUserIds = $byTask[$taskId] ?? [];

            // آخرین رکوردی که از_user_id = کاربر جاری بوده
            $lastIdx = null;
            foreach ($fromUserIds as $idx => $fromUserId) {
                if ($fromUserId == $user_id) $lastIdx = $idx;
            }

            if ($lastIdx === null) {
                $result[$taskId] = false;
                continue;
            }

            // آیا بعد از آن، شخص دیگری هم واگذار کرده؟
            $hasLater = false;
            for ($i = $lastIdx + 1; $i < count($fromUserIds); $i++) {
                if ($fromUserIds[$i] != $user_id) {
                    $hasLater = true;
                    break;
                }
            }
            $result[$taskId] = !$hasLater;
        }

        return $result;
    }

    // ========================================
    // افزودن لیست مسئولان چک‌لیست به هر تسک
    // خروجی: به هر تسک یک کلید checklist_assignees اضافه می‌شود
    // که آرایه‌ای از نام کاربران و واحدهای ارجاع‌شده است
    // ========================================
    function attachChecklistAssignees($db, &$tasks)
    {
        if (empty($tasks)) return;

        // جمع‌آوری شناسه‌های تسک
        $task_ids = array_column($tasks, 'id');
        $placeholders = str_repeat('?,', count($task_ids) - 1) . '?';

        // یک کوئری برای همه‌ی ارجاع‌های چک‌لیست این تسک‌ها
        $sql = "
            SELECT ci.task_id, ci.assignee_type, ci.assignee_value,
                   CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS user_name,
                   sec.section_label AS section_name
            FROM task_checklist_items ci
            LEFT JOIN users u
                   ON (ci.assignee_type = 'user' AND ci.assignee_value = u.id AND u.is_active = 1)
            LEFT JOIN organization_activity_sections sec
                   ON (ci.assignee_type = 'section' AND ci.assignee_value = sec.section_key)
            WHERE ci.task_id IN ($placeholders)
              AND ci.assignee_type IS NOT NULL
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($task_ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // گروه‌بندی بر اساس task_id (و حذف تکراری‌ها)
        $map = [];   // task_id => [ 'برچسب مسئول', ... ]
        foreach ($rows as $r) {
            $tid = $r['task_id'];
            if (!isset($map[$tid])) $map[$tid] = [];

            if ($r['assignee_type'] === 'user') {
                $label = trim($r['user_name']) !== '' ? trim($r['user_name']) : 'کاربر حذف‌شده';
            } else {
                $label = $r['section_name'] ?: 'واحد حذف‌شده';
            }
            // جلوگیری از تکرار (مثلا دو آیتم به یک نفر)
            if (!in_array($label, $map[$tid], true)) {
                $map[$tid][] = $label;
            }
        }

        // الصاق به هر تسک
        foreach ($tasks as &$task) {
            $task['checklist_assignees'] = $map[$task['id']] ?? [];
        }
        unset($task);
    }

    // استخراج توکن
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        echo json_encode(['success' => false, 'message' => 'توکن نامعتبر'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $token = $matches[1];
    $auth = new Auth();
    $user_id = $auth->validateToken($token);

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'توکن نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('خطا در اتصال به دیتابیس');
    }

    // دریافت اطلاعات کاربر
    $stmt = $db->prepare("SELECT id, role, organization_id, is_manager FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (!in_array($user['role'], ['manager', 'supervisor']) && (int)($user['is_manager'] ?? 0) !== 1)) {
        echo json_encode([
            'success' => false,
            'message' => 'دسترسی غیرمجاز - نقش شما: ' . ($user['role'] ?? 'نامشخص')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ========================================
    // پارامتر: آیا کارهای خود کاربر هم نمایش داده شود؟
    // ========================================
    $include_my_tasks = isset($_GET['include_my_tasks']) && $_GET['include_my_tasks'] == '1';

    // ========================================
    // 🔵 SUPERVISOR: همه تسک‌ها
    // ========================================
    if ($user['role'] === 'supervisor' || (int)($user['is_manager'] ?? 0) === 1) {
        $sql = "
            SELECT
                t.id, t.title, t.description, t.task_type, t.priority, t.status,
                t.due_date, t.created_at, t.creator_id, t.assignee_id, t.deadline,
                t.is_workflow_task, t.workflow_instance_id, t.activity_section,
                t.group_id, t.period_type, t.start_date, t.end_date, t.overdue_forgiven_credit,
                tg.name as group_name, tg.color as group_color,
                CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
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
                ) AS history_text
            FROM tasks t
            LEFT JOIN users creator ON t.creator_id = creator.id AND creator.is_active = 1 AND creator.is_deleted = 0
            LEFT JOIN users assignee ON t.assignee_id = assignee.id AND assignee.is_active = 1 AND assignee.is_deleted = 0
            LEFT JOIN task_groups tg ON t.group_id = tg.id
            WHERE t.is_deleted = 0 AND t.organization_id = ?
            ORDER BY
                CASE WHEN t.due_date < CURDATE() THEN 1 ELSE 2 END,
                t.priority = 'high' DESC,
                t.created_at DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user['organization_id']]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ========================================
        // بررسی دسترسی یادآوری برای هر تسک — دسته‌ای (یک کوئری برای همه)
        // شرط: کاربر جاری creator باشد یا آخرین واگذارکننده در زنجیره ارجاعات
        // ========================================
        $remindMap = batchCanUserRemind($db, $user_id, $tasks);
        foreach ($tasks as &$task) {
            $task['can_remind'] = $remindMap[$task['id']] ?? false;
        }
        unset($task);
        attachContinuousFields($db, $tasks, $user_id);
        attachChecklistAssignees($db, $tasks);
        attachChecklistTitles($db, $tasks);
        echo json_encode([
            'success' => true,
            'tasks' => $tasks,
            'count' => count($tasks),
            'user_role' => 'supervisor',
            'message' => 'نمایش تمام تسک‌ها'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ========================================
    // 🟢 MANAGER: فقط تسک‌های زیردستان
    // ========================================

    // مرحله 1: پیدا کردن زیردستان مستقیم
    // کاربرانی که manager_id آنها برابر با id کاربر جاری است
    $stmt = $db->prepare("
        SELECT id
        FROM users
        WHERE manager_id = ?
        AND is_active = 1
    ");
    $stmt->execute([$user_id]);
    $direct_subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    error_log("👥 User $user_id - Direct subordinates: " . implode(',', $direct_subordinates));

    // مرحله 2: پیدا کردن زیردستان غیرمستقیم (سطح دوم)
    $indirect_subordinates = [];
    if (!empty($direct_subordinates)) {
        $placeholders = str_repeat('?,', count($direct_subordinates) - 1) . '?';

        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE manager_id IN ($placeholders)
            AND is_active = 1
        ");
        $stmt->execute($direct_subordinates);
        $indirect_subordinates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    error_log("👥 User $user_id - Indirect subordinates: " . implode(',', $indirect_subordinates));

    // ترکیب زیردستان + همیشه خود کاربر
    $all_subordinates = array_merge($direct_subordinates, $indirect_subordinates);
    $all_subordinates[] = $user_id;

    // حذف تکراری‌ها
    $all_subordinates = array_unique($all_subordinates);

    error_log("👥 User $user_id - Total to query: " . implode(',', $all_subordinates));

    // اگر هیچ زیردستی ندارد
    if (empty($all_subordinates)) {
        echo json_encode([
            'success' => true,
            'tasks' => [],
            'count' => 0,
            'user_role' => 'manager',
            'subordinates_count' => 0,
            'message' => 'شما هیچ زیردستی ندارید'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // مرحله 3: دریافت تسک‌ها
    $placeholders = str_repeat('?,', count($all_subordinates) - 1) . '?';

    $sql = "
        SELECT
            t.id, t.title, t.description, t.task_type, t.priority, t.status,
            t.due_date, t.created_at, t.creator_id, t.assignee_id, t.deadline,
            t.is_workflow_task, t.workflow_instance_id, t.activity_section,
            t.group_id, t.period_type, t.start_date, t.end_date, t.overdue_forgiven_credit,
            tg.name as group_name, tg.color as group_color,
            CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
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
                ) AS history_text
        FROM tasks t
        LEFT JOIN users creator ON t.creator_id = creator.id AND creator.is_active = 1 AND creator.is_deleted = 0
        LEFT JOIN users assignee ON t.assignee_id = assignee.id AND assignee.is_active = 1 AND assignee.is_deleted = 0
        LEFT JOIN task_groups tg ON t.group_id = tg.id
        WHERE t.is_deleted = 0
        AND (t.creator_id IN ($placeholders) OR t.assignee_id IN ($placeholders))
        ORDER BY
            CASE WHEN t.due_date < CURDATE() THEN 1 ELSE 2 END,
            t.priority = 'high' DESC,
            t.created_at DESC
    ";

    $params = array_merge($all_subordinates, $all_subordinates);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ========================================
    // بررسی دسترسی یادآوری برای هر تسک — دسته‌ای (یک کوئری برای همه)
    // ========================================
    $remindMap = batchCanUserRemind($db, $user_id, $tasks);
    foreach ($tasks as &$task) {
        $task['can_remind'] = $remindMap[$task['id']] ?? false;
    }
    unset($task);
    attachContinuousFields($db, $tasks, $user_id);
    attachChecklistAssignees($db, $tasks);
    attachChecklistTitles($db, $tasks);
    echo json_encode([
        'success' => true,
        'tasks' => $tasks,
        'count' => count($tasks),
        'user_role' => 'manager',
        'subordinates_count' => count($all_subordinates),
        'include_my_tasks' => $include_my_tasks
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("❌ Overview API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور',
        'error' => 'internal_error'
    ], JSON_UNESCAPED_UNICODE);
}
