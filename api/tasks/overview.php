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
    // ⚠️ رفع باگ: قبلاً اینجا یک فرمولِ جداگانه و قدیمی (calcOverduePeriods بر
    // مبنای شمارشِ خامِ completed_count) استفاده می‌شد که می‌توانست با آنچه
    // my-tasks.php/delegated-tasks.php/task-detail.php نشان می‌دهند فرق داشته
    // باشد (مثلاً اگر برای یک روز دو رکورد completed ثبت شده باشد، آن فرمول
    // عدد معوقهٔ اشتباه می‌داد). حالا همه‌جا از یک مرجع مشترک استفاده می‌شود:
    // enrichTaskDates() → pe_state() در includes/period-engine.php.
    function attachContinuousFields($db, &$tasks, $user_id)
    {
        $holidays = getHolidaySet($db);
        $today = date('Y-m-d');
        foreach ($tasks as &$task) {
            if ($task['task_type'] === 'continuous' && !empty($task['start_date'])) {
                // برگشت از period_done به دوره‌ی بعدی (اگر موعدش رسیده)
                maybeStartNextPeriod($db, $task, $user_id, $holidays);
            }
            $task = enrichTaskDates($task, $db, $holidays, $today);
        }
        unset($task);
    }

    // ========================================
    // تابع کمکی: آیا کاربر اجازه ارسال یادآوری دارد؟
    // شرط 1: کاربر creator تسک باشد
    // شرط 2: یا در زنجیره ارجاعات، آخرین نفری باشد که کار را واگذار کرده
    // ========================================
    function canUserRemind($db, $user_id, $task)
    {
        // شرط اولیه: کاربر جاری نباید مسئول انجام کار باشد
        if ($task['assignee_id'] == $user_id) {
            return false;
        }

        // شرط 1: کاربر جاری تعریف‌کننده (creator) تسک باشد
        if ($task['creator_id'] == $user_id) {
            return true;
        }

        // شرط 2: آخرین واگذارکننده در زنجیره ارجاعات باشد
        // آخرین رکورد assigned/delegated در task_history که from_user_id = کاربر جاری
        $stmt = $db->prepare("
            SELECT id FROM task_history 
            WHERE task_id = ? 
            AND action IN ('assigned', 'delegated') 
            AND from_user_id = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$task['id'], $user_id]);
        $lastDelegation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lastDelegation) {
            return false;
        }

        // بررسی: آیا بعد از واگذاری این کاربر، شخص دیگری هم واگذار کرده؟
        // اگر بله، یعنی این کاربر دیگر آخرین واگذارکننده نیست
        $stmt = $db->prepare("
            SELECT id FROM task_history 
            WHERE task_id = ? 
            AND action IN ('assigned', 'delegated') 
            AND from_user_id != ?
            AND id > ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$task['id'], $user_id, $lastDelegation['id']]);
        $laterDelegation = $stmt->fetch(PDO::FETCH_ASSOC);

        // اگر کسی بعد از این کاربر واگذار نکرده، پس این کاربر آخرین واگذارکننده است
        return !$laterDelegation;
    }

    // ========================================
    // افزودن لیست مسئولانِ چک‌لیست به هر تسک
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
                   ON (ci.assignee_type = 'user' AND ci.assignee_value = u.id)
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
            // جلوگیری از تکرار (مثلاً دو آیتم به یک نفر)
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
                t.is_workflow_task, t.activity_section,
                t.group_id, t.period_type, t.start_date, t.end_date, t.overdue_forgiven_credit,
                tg.name as group_name, tg.color as group_color,
                CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
                CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
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
                ) AS history_text
            FROM tasks t
            LEFT JOIN users creator ON t.creator_id = creator.id
            LEFT JOIN users assignee ON t.assignee_id = assignee.id
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
        // بررسی دسترسی یادآوری برای هر تسک
        // شرط: کاربر جاری creator باشد یا آخرین واگذارکننده در زنجیره ارجاعات
        // ========================================
        foreach ($tasks as &$task) {
            $task['can_remind'] = canUserRemind($db, $user_id, $task);
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
            t.is_workflow_task, t.activity_section,
            t.group_id, t.period_type, t.start_date, t.end_date, t.overdue_forgiven_credit,
            tg.name as group_name, tg.color as group_color,
            CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
            CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
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
                ) AS history_text
        FROM tasks t
        LEFT JOIN users creator ON t.creator_id = creator.id
        LEFT JOIN users assignee ON t.assignee_id = assignee.id
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
    // بررسی دسترسی یادآوری برای هر تسک
    // ========================================
    foreach ($tasks as &$task) {
        $task['can_remind'] = canUserRemind($db, $user_id, $task);
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
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
