<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
class TaskManager
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }
        // 🆕 متد کمکی اعلان اتمام کار (ایمن: هیچ استثنایی پرتاب نمی‌کند)
    private function notifyCompletion($task, $to_user_id, $pattern, $actor_id, $reason = '')
    {
        try {
            if (empty($to_user_id) || (int)$to_user_id === (int)$actor_id) {
                return; // گیرنده نامعتبر یا خودِ اقدام‌کننده → پیامک نفرست
            }
            require_once __DIR__ . '/Notification.php';
            $notif = new Notification($this->db);

            $stmt = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
            $stmt->execute([$actor_id]);
            $actorName = $stmt->fetchColumn() ?: ('کاربر ' . $actor_id);

            $title = $task['title'] ?? 'نامشخص';

            switch ($pattern) {
                case 'completion_approved':
                    $msg  = 'درخواست اتمام کار «' . $title . '» توسط ' . $actorName . ' تأیید شد.';
                    $args = [$title, $actorName];
                    $ntitle = 'اتمام کار تأیید شد: ' . $title;
                    break;
                case 'completion_rejected':
                    $msg  = 'درخواست اتمام کار «' . $title . '» توسط ' . $actorName . ' رد شد.';
                    if ($reason !== '') $msg .= ' دلیل: ' . $reason;
                    $args = [$title, $actorName, ($reason !== '' ? $reason : '-')];
                    $ntitle = 'اتمام کار رد شد: ' . $title;
                    break;
                case 'completion_by_manager':
                    $msg  = 'کار «' . $title . '» توسط ' . $actorName . ' به پایان رسید.';
                    $args = [$title, $actorName];
                    $ntitle = 'کار تکمیل شد: ' . $title;
                    break;
                default:
                    return;
            }

            $notif->create([
                'to_user_id'   => $to_user_id,
                'title'        => $ntitle,
                'message'      => $msg,
                'type'         => 'info',
                'link'         => '/pages/task-detail.php?id=' . ($task['id'] ?? ''),
                'related_type' => 'task',
                'related_id'   => $task['id'] ?? null,
                'is_read'      => 0,
                'sms_pattern'  => $pattern,
                'sms_args'     => $args,
            ]);
        } catch (Exception $e) {
            error_log("notifyCompletion error: " . $e->getMessage());
        }
    }
    function halt($data, $label = 'HALT DEBUG')
    {
        // نمایش متغیر
        echo "<pre style='
        background:#000; color:#0f0; padding:15px; 
        font-family:monospace; font-size:14px; 
        border:3px solid #0f0; margin:10px; 
        position:relative; z-index:9999;
    '>";
        echo "<strong>$label:</strong>\n";
        print_r($data);
        echo "</pre>";

        // توقف کامل برنامه
        trigger_error("HALT: $label", E_USER_ERROR);
    }
    // ایجاد کار جدید
    // ✅ تابع createTask اصلاح شده - پشتیبانی deadline extension
    public function createTask($data, $creator_id, $organization_id)
    {
        try {
            // قانون 3: نمی‌توان کار قبل از امروز تعریف کرد
            $today = date('Y-m-d');

            // ✅ محاسبه deadline برای کارهای periodic
            $deadline = null;
            $original_deadline = null;

            if ($data['task_type'] === 'periodic') {
                if (!empty($data['due_date'])) {
                    // اگر due_date داده شده باشد
                    if ($data['due_date'] < $today) {
                        return ['success' => false, 'message' => 'نمی‌توانید کار با تاریخ گذشته ایجاد کنید'];
                    }
                    $deadline = $data['due_date'];
                } else {
                    // اگر due_date داده نشده باشد، deadline = امروز + 30 روز
                    $deadline = date('Y-m-d', strtotime('+30 days'));
                }
                // original_deadline همیشه برابر deadline است
                $original_deadline = $deadline;
            }

            if ($data['task_type'] === 'continuous' && !empty($data['start_date'])) {
                if ($data['start_date'] < $today) {
                    return ['success' => false, 'message' => 'تاریخ شروع نمی‌تواند قبل از امروز باشد'];
                }
            }

            // ✅ INSERT با فیلدهای deadline و original_deadline
            $sql = "INSERT INTO tasks (
                    title, 
                    description, 
                    creator_id, 
                    assignee_id, 
                    activity_section, 
                    task_type, 
                    priority, 
                    due_date, 
                    start_date, 
                    end_date,
                    period_type,
                    deadline,
                    original_deadline,
                    has_pending_deadline_request,
                    status,
                    organization_id,
                    group_id,
                    checklist_auto_complete
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?, 0, 'not_started',?,?,?)";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $creator_id,
                $data['assignee_id'] ?? $creator_id,
                $data['activity_section'] ?? null,
                $data['task_type'],
                $data['priority'] ?? 'medium',
                $data['due_date'] ?? null,
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $data['period_type'] ?? null,
                $deadline,
                $original_deadline,
                $organization_id,
                $data['group_id'] ?? null,          // 🆕 group_id
                $data['checklist_auto_complete'] ?? 1,   // 🆕 تکمیل خودکار
            ]);

            if ($result) {
                $task_id = $this->db->lastInsertId();
                $assignee_id = $data['assignee_id'] ?? $creator_id;
                $this->addTaskHistory(
                    $task_id,
                    $creator_id,
                    $assignee_id,
                    'created',
                    'کار ایجاد شد'
                );

                return [
                    'success' => true,
                    'task_id' => $task_id,
                    'message' => 'کار با موفقیت ایجاد شد',
                    'deadline' => $deadline  // ✅ بازگشت deadline به client
                ];
            }

            return ['success' => false, 'message' => 'خطا در ایجاد کار'];

        } catch (Exception $e) {
            error_log("CreateTask error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()];
        }
    }

    // ✅ متد اصلاح شده getMyTasks
    public function getMyTasks($user_id, $date = null)
    {
        try {
            $today = $date ? $date : date('Y-m-d');
            $sql = "
            SELECT t.*, 
                   creator.first_name as creator_first_name, 
                   creator.last_name as creator_last_name,
                   CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name
            FROM tasks t 
            LEFT JOIN users creator ON t.creator_id = creator.id 
            WHERE (
                t.assignee_id = ? 
                OR t.creator_id = ?
            )
            AND t.status IN ('not_started', 'in_progress', 'delegated')
            AND t.is_deleted = 0
            AND (
                t.is_workflow_task = 0
                OR (
                    t.is_workflow_task = 1 
                    AND EXISTS (
                        SELECT 1 FROM workflow_steps ws 
                        WHERE ws.id = t.current_stage_id 
                        AND ws.activity_section = (SELECT activity_section FROM users WHERE id = ?)
                    )
                )
            )";

            if ($date) {
                $sql .= " AND (
                        (t.due_date = ? OR (t.due_date < ? AND t.status != 'completed' AND t.status != 'approved'))
                        OR t.due_date IS NULL
                    )";
            }

            $sql .= " ORDER BY 
                    CASE WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1 ELSE 2 END,
                    t.priority = 'high' DESC,
                    t.priority = 'medium' DESC,
                    t.due_date ASC";

            $stmt = $this->db->prepare($sql);
            if ($date) {
                $stmt->execute([$user_id, $user_id, $user_id, $today, $today]);
            } else {
                $stmt->execute([$user_id, $user_id, $user_id]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("GetMyTasks error: " . $e->getMessage());
            return [];
        }
    }

    // بروزرسانی کار
    public function updateTask($task_id, $data, $user_id)
    {
        try {
            // بررسی مجوز ویرایش - قانون 4
            $check_result = $this->canEditTask($task_id, $user_id);
            if (!$check_result['can_edit']) {
                return ['success' => false, 'message' => $check_result['message']];
            }

            $allowed_fields = ['title', 'description', 'priority', 'due_date', 'start_date', 'period_type'];
            $update_fields = [];
            $update_values = [];

            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    // قانون 3: بررسی تاریخ
                    if (($field === 'due_date' || $field === 'start_date') && !empty($data[$field])) {
                        if ($data[$field] < date('Y-m-d')) {
                            return ['success' => false, 'message' => 'تاریخ نمی‌تواند قبل از امروز باشد'];
                        }
                    }
                    $update_fields[] = "$field = ?";
                    $update_values[] = $data[$field];
                }
            }

            if (!empty($update_fields)) {
                $update_values[] = $task_id;
                $sql = "UPDATE tasks SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);

                if ($stmt->execute($update_values)) {
                    $this->addTaskHistory($task_id, $user_id, null, 'updated', 'کار بروزرسانی شد');
                    return ['success' => true, 'message' => 'کار با موفقیت بروزرسانی شد'];
                }
            }

            return ['success' => false, 'message' => 'هیچ تغییری اعمال نشد'];
        } catch (Exception $e) {
            error_log("UpdateTask error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    private function debug($data, $label = 'DEBUG')
    {
        error_log("$label: " . print_r($data, true));
    }
/**
     * نهایی‌کردن «تکمیلِ بدون نیاز به تأیید».
     * - کار دوره‌ای (continuous): دوره را جلو می‌برد و کار را برای دوره بعد آماده می‌کند.
     * - کار عادی/مقطعی: مثل قبل فقط تکمیل می‌شود.
     */
    private function finalizeSelfCompletion($task, $task_id, $user_id, $notes)
    {
        if ($task['task_type'] === 'continuous') {
            $today = date('Y-m-d');
            $sql = "UPDATE tasks SET 
                        last_approved_date = ?,
                        status = 'period_done',
                        is_pending_approval = FALSE,
                        pending_approval_count = 0,
                        updated_at = NOW()
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$today, $task_id]);

            $this->addTaskHistory($task_id, $user_id, null, 'completed', $notes ?: 'کار تکمیل شد');
            return ['success' => true, 'message' => 'دوره تکمیل شد'];
        }

        // کار عادی/مقطعی — رفتار قبلی دست‌نخورده
        $sql = "UPDATE tasks SET status = 'completed', updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$task_id]);

        $this->addTaskHistory($task_id, $user_id, null, 'completed', $notes ?: 'کار تکمیل شد');
        return ['success' => true, 'message' => 'کار تکمیل شد'];
    }
    // ✅ متد اصلاح شده updateTaskStatus
    public function updateTaskStatus($task_id, $status, $user_id, $notes = '')
    {
        try {
            $valid_statuses = ['in_progress', 'completed', 'stopped', 'delegated', 'pending_approval', 'approved', 'not_started'];
            if (!in_array($status, $valid_statuses)) {
                return ['success' => false, 'message' => 'وضعیت نامعتبر'];
            }

            // دریافت اطلاعات کار
            $task = $this->getTask($task_id);

            if (!$task) {
                return ['success' => false, 'message' => 'کار یافت نشد'];
            }

            // ✅ بررسی: اگر status به completed تغییر می‌کند، چک کن آیا ../assets/js/cdn/ ارجاع وجود دارد
            if ($status === 'completed') {
                $chain = $this->getDelegationChain($task_id);

                // ✅ چک 1: اگر creator و assignee یکی هستند
                if ($task['creator_id'] == $user_id && $task['assignee_id'] == $user_id) {
                    return $this->finalizeSelfCompletion($task, $task_id, $user_id, $notes);
                }

                // ✅ پیدا کردن نفر قبلی از زنجیره
                $previousPerson = null;
                for ($i = count($chain) - 1; $i >= 0; $i--) {
                    if ($chain[$i]['to_user_id'] == $user_id) {
                        $previousPerson = $chain[$i]['from_user_id'];
                        break;
                    }
                }

                // ✅ چک 2: اگر نفر قبلی خودم بودم یا وجود نداشت
                if ($previousPerson == $user_id || $previousPerson === null) {
                    $result = $this->finalizeSelfCompletion($task, $task_id, $user_id, $notes);
                    $this->notifyCompletion($task, $task['creator_id'] ?? null, 'completion_by_manager', $user_id);  // 🆕
                    return $result;
                }

                // ✅ چک 3: اگر نفر قبلی creator اصلی کار است و من هم creator هستم
                if ($previousPerson == $task['creator_id'] && $user_id == $task['creator_id']) {
                    return $this->finalizeSelfCompletion($task, $task_id, $user_id, $notes);
                }

                // فقط اگر previousPerson دیگه‌ای باشه → pending_approval و نوتیفیکیشن
                $sql = "UPDATE tasks SET status = 'pending_approval', is_pending_approval = TRUE, assignee_id = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$previousPerson, $task_id]);
                
                // ✅ ثبت completed قبل از pending_approval (برای ریست دور تأییدها)
                // $this->addTaskHistory($task_id, $user_id, null, 'completed', $notes ?: 'کار تکمیل شد');
                $this->addTaskHistory($task_id, $user_id, $previousPerson, 'pending_approval', $notes ?: 'کار تکمیل شد و منتظر تأیید است');;

                try {
                    require_once __DIR__ . '/Notification.php';
                    $notification = new Notification($this->db);

                    $stmt = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    $performerName = $userRow['full_name'] ?? 'کاربر ' . $user_id;

                    $message = 'کار «' . ($task['title'] ?? 'نامشخص') . '» توسط "' . $performerName . '" تکمیل شد و منتظر تأیید شماست.';
                    if (!empty($notes)) {
                        $message .= "\n\nتوضیحات: " . trim($notes);
                    }

                    $notification->create([
                        'to_user_id' => $previousPerson,
                        'title' => 'کار جدید برای تأیید: ' . ($task['title'] ?? 'نامشخص'),
                        'message' => $message,
                        'type' => 'info',
                        'link' => '/pages/task-detail.php?id=' . $task_id,
                        'related_type' => 'task',
                        'related_id' => $task_id,
                        'is_read' => 0,
                        'sms_pattern' => 'completion_request',
                        'sms_args' => [($task['title'] ?? 'نامشخص'), $performerName],
                    ]);
                } catch (Exception $notifError) {
                    error_log("Notification error in updateTaskStatus: " . $notifError->getMessage());
                }

                return ['success' => true, 'message' => 'کار ارسال شد برای تأیید', 'awaiting_approval' => true];
            }

            // ✅ چک دسترسی مشابه detail.php
            $hasAccess = false;

            // 1. سازنده کار
            if ($task['creator_id'] == $user_id) {
                $hasAccess = true;
            }

            // 2. فرد تخصیص داده شده
            if ($task['assignee_id'] == $user_id) {
                $hasAccess = true;
            }

            // 3. افرادی که در زنجیره ارجاعات کار بوده‌اند
            if (!$hasAccess) {
                $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM task_history 
                WHERE task_id = ? 
                AND (from_user_id = ? OR to_user_id = ?)
            ");
                $stmt->execute([$task_id, $user_id, $user_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $historyCount = $row ? $row['count'] : 0;

                if ($historyCount > 0) {
                    $hasAccess = true;
                }
            }

            // 4. برای workflow (روتین مرحله‌ای): کاربر در بخش مرحله فعلی باشه و وضعیت مجاز
            if (!$hasAccess && $task['is_workflow_task'] == 1 && isset($task['current_stage_id'])) {
                try {
                    $stmt = $this->db->prepare("
                    SELECT ws.activity_section 
                    FROM workflow_stages ws 
                    WHERE ws.id = ? 
                    AND ws.is_active = 1
                ");
                    $stmt->execute([$task['current_stage_id']]);
                    $stageRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stageSection = $stageRow ? $stageRow['activity_section'] : null;

                    if ($stageSection) {
                        $stmt = $this->db->prepare("
                        SELECT COUNT(*) as count 
                        FROM users u 
                        WHERE u.id = ? 
                        AND u.activity_section = ? 
                        AND u.is_active = 1
                    ");
                        $stmt->execute([$user_id, $stageSection]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($result && $result['count'] > 0) {
                            // چک وضعیت: برای 'in_progress' فقط اگر از 'not_started' میاد، هر عضوی؛ برای 'approved' فقط assignee
                            if (
                                ($status === 'in_progress' && $task['status'] === 'not_started') ||
                                ($status === 'approved' && $task['status'] === 'in_progress' && $task['assignee_id'] == $user_id)
                            ) {
                                $hasAccess = true;
                            }
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Workflow stage query error: " . $e->getMessage());
                    // skip workflow access اگر جدول وجود نداشته باشه
                }
            }

            // 5. برای کارهای روتین (periodic/continuous)، همه اعضای بخش مربوطه
            if (!$hasAccess && in_array($task['task_type'], ['periodic', 'continuous']) && $task['activity_section']) {
                $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM users 
                WHERE id = ? 
                AND activity_section = ? 
                AND is_active = 1
            ");
                $stmt->execute([$user_id, $task['activity_section']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && $result['count'] > 0) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess) {
                return ['success' => false, 'message' => 'شما مجاز به تغییر وضعیت این کار نیستید'];
            }

            // ✅ بروزرسانی وضعیت
            $sql = "UPDATE tasks SET status = ?, updated_at = NOW()";
            $params = [$status];

            // برای 'in_progress'، assignee_id رو به user_id ست کن
            if ($status === 'in_progress') {
                $sql .= ", assignee_id = ?";
                $params[] = $user_id;
            }

            $sql .= " WHERE id = ?";
            $params[] = $task_id;

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            if (!$result) {
                return ['success' => false, 'message' => 'خطا در بروزرسانی وضعیت'];
            }

            // ✅ try-catch دور بخش‌های بعد از UPDATE برای جلوگیری از rollback
            try {
                // اضافه کردن به تاریخچه
                $this->addTaskHistory($task_id, $user_id, null, $status, $notes);

                // برای workflow، بعد از 'approved'، current_stage_id رو به مرحله بعد ببر
                if ($task['is_workflow_task'] == 1 && $status === 'approved') {
                    try {
                        $stmt = $this->db->prepare("
                        SELECT id FROM workflow_stages 
                        WHERE workflow_id = ? AND sequence > (SELECT sequence FROM workflow_stages WHERE id = ?) 
                        ORDER BY sequence ASC LIMIT 1
                    ");
                        $stmt->execute([$task['workflow_id'] ?? 0, $task['current_stage_id']]);
                        $nextStage = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($nextStage && $nextStage['id']) {
                            $updateStmt = $this->db->prepare("UPDATE tasks SET current_stage_id = ? WHERE id = ?");
                            $updateStmt->execute([$nextStage['id'], $task_id]);
                        }
                    } catch (PDOException $e) {
                        error_log("Workflow next stage error: " . $e->getMessage());
                        // skip بدون error
                    }
                }

                // برای کارهای continuous، completed_count رو افزایش بده
                if ($status === 'completed' && $task['task_type'] === 'continuous') {
                    $this->db->prepare("UPDATE tasks SET completed_count = COALESCE(completed_count, 0) + 1 WHERE id = ?")->execute([$task_id]);
                }

                // برای کارهای periodic، اگر due_date امروز باشه، completed_count افزایش بده (اگر فیلد داری)
                if ($status === 'completed' && $task['task_type'] === 'periodic' && $task['due_date'] == date('Y-m-d')) {
                    $this->db->prepare("UPDATE tasks SET completed_count = COALESCE(completed_count, 0) + 1 WHERE id = ?")->execute([$task_id]);
                }

            } catch (Exception $postUpdateError) {
                error_log("Post-update error in updateTaskStatus (task_id: $task_id, status: $status): " . $postUpdateError->getMessage());
                // ignore: status حفظ می‌شه، فقط history یا count fail می‌شه
            }

            return ['success' => true, 'message' => 'وضعیت کار بروزرسانی شد'];

        } catch (Exception $e) {
            error_log("UpdateTaskStatus error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // دریافت فعالیت‌های اخیر
    public function getRecentActivities($user_id, $limit = 10)
    {
        try {
            $sql = "SELECT th.*, t.title as task_title,
                        from_user.first_name as from_user_first_name,
                        from_user.last_name as from_user_last_name,
                        to_user.first_name as to_user_first_name,
                        to_user.last_name as to_user_last_name
                    FROM task_history th
                    JOIN tasks t ON th.task_id = t.id
                    LEFT JOIN users from_user ON th.from_user_id = from_user.id
                    LEFT JOIN users to_user ON th.to_user_id = to_user.id
                    WHERE t.assignee_id = ? OR t.creator_id = ?
                    ORDER BY th.created_at DESC
                    LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id, $user_id, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetRecentActivities error: " . $e->getMessage());
            return [];
        }
    }

    // مدیریت تکمیل کارهای دوره‌ای
    private function handleContinuousTaskCompletion($task_id, $task, $user_id, $notes)
    {
        try {
            // محاسبه تعداد دوره‌های معوقه
            $today = date('Y-m-d');
            $start_date = $task['start_date'];
            $period_type = $task['period_type'];

            // شمارش تکمیل‌های قبلی
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'");
            $stmt->execute([$task_id]);
            $completed_count = $stmt->fetch()['count'];

            // محاسبه تعداد دوره‌های سپری شده
            $expected_completions = $this->calculateExpectedCompletions($start_date, $today, $period_type);

            // ✅ اضافه کردن شرط جدید: فقط تا امروز مجاز به تکمیل است
            if ($completed_count >= $expected_completions) {
                return ['success' => false, 'message' => 'هنوز زمان تکمیل این دوره نرسیده است (فردا به بعد)'];
            }

            // اضافه کردن توضیحات به delegation_notes
            $new_notes = $task['delegation_notes'] ?
                $task['delegation_notes'] . "\n---\n" . $notes : $notes;

            // ثبت تکمیل
            $this->addTaskHistory($task_id, $user_id, null, 'completed', $notes);
            $remaining = $expected_completions - $completed_count - 1;

            // ✅ تغییر شرط: حتی اگر remaining صفر باشد، باید بررسی شود که آیا فردا دوره جدیدی هست
            $sql = "UPDATE tasks SET last_completed_date = NOW(), delegation_notes = ?, updated_at = NOW() WHERE id = ?";
            $this->db->prepare($sql)->execute([$new_notes, $task_id]);

            if ($remaining > 0) {
                return [
                    'success' => true,
                    'message' => "تکمیل ثبت شد. {$remaining} دوره معوقه باقی مانده است",
                    'remaining' => $remaining
                ];
            } else {
                return [
                    'success' => true,
                    'message' => 'کار به‌روز شد. تمام دوره‌های معوقه تکمیل شدند',
                    'remaining' => 0
                ];
            }

        } catch (Exception $e) {
            error_log("HandleContinuousTaskCompletion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // محاسبه تعداد دوره‌های مورد انتظار
    private function calculateExpectedCompletions($start_date, $end_date, $period_type)
    {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);

        switch ($period_type) {
            case 'daily':
                return $interval->days;
            case 'weekly':
                return floor($interval->days / 7);
            case 'monthly':
                return ($interval->y * 12) + $interval->m;
            default:
                return 0;
        }
    }

    // ارجاع کار - قانون 2
    public function delegateTask($task_id, $to_user_id, $from_user_id, $notes = '')
    {
        try {
            // دریافت اطلاعات کار
            $task = $this->getTask($task_id);
            if (!$task) {
                return ['success' => false, 'message' => 'کار یافت نشد'];
            }

            // قانون 1: نمی‌توان کار completed را ارجاع داد
            if ($task['status'] === 'completed' && $task['task_type'] === 'periodic') {
                return ['success' => false, 'message' => 'کار تکمیل شده قابل ارجاع نیست'];
            }

            // بررسی مجوز
            if ($task['assignee_id'] != $from_user_id && $task['creator_id'] != $from_user_id) {
                return ['success' => false, 'message' => 'شما مجاز به ارجاع این کار نیستید'];
            }

            // بررسی وجود کاربر مقصد
            if (!$this->userExists($to_user_id)) {
                return ['success' => false, 'message' => 'کاربر مقصد یافت نشد'];
            }

            // دریافت توضیحات قبلی
            $new_notes = $task['delegation_notes'] ?
                $task['delegation_notes'] . "\n---\n" . $notes : $notes;

            // بروزرسانی کار
            $sql = "UPDATE tasks SET assignee_id = ?, status = 'delegated', delegation_notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);

            if ($stmt->execute([$to_user_id, $new_notes, $task_id])) {
                $this->addTaskHistory($task_id, $from_user_id, $to_user_id, 'delegated', $notes);
                return ['success' => true, 'message' => 'کار با موفقیت ارجاع داده شد'];
            }

            return ['success' => false, 'message' => 'خطا در ارجاع کار'];
        } catch (Exception $e) {
            error_log("DelegateTask error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // بررسی مجوز ویرایش - قانون 4
    private function canEditTask($task_id, $user_id)
    {
        $stmt = $this->db->prepare("SELECT creator_id, status, task_type FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();

        if (!$task) {
            return ['can_edit' => false, 'message' => 'کار یافت نشد'];
        }

        // فقط سازنده می‌تواند ویرایش کند
        if ($task['creator_id'] != $user_id) {
            return ['can_edit' => false, 'message' => 'فقط ایجادکننده کار می‌تواند آن را ویرایش کند'];
        }

        // بررسی اینکه آیا حداقل یک بار تکمیل شده
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM task_history WHERE task_id = ? AND action = 'completed'");
        $stmt->execute([$task_id]);
        $completed_count = $stmt->fetch()['count'];

        if ($completed_count > 0) {
            return ['can_edit' => false, 'message' => 'کار تکمیل شده قابل ویرایش نیست'];
        }

        return ['can_edit' => true];
    }

    // حذف کار
    public function deleteTask($task_id, $user_id)
    {
        try {
            // بررسی مجوز (فقط سازنده کار می‌تواند حذف کند)
            $stmt = $this->db->prepare("SELECT creator_id FROM tasks WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();

            if (!$task) {
                return ['success' => false, 'message' => 'کار پیدا نشد'];
            }
        
                // گرفتن نقش کاربر
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        
            $isCreator = ($task['creator_id'] == $user_id);
            $isManager = ($user && ($user['role'] == 'management' || $user['role'] == 'supervisor'));
    
            if (!$isCreator && !$isManager) {
                return ['success' => false, 'message' => 'شما مجاز به حذف این کار نیستید'];
            }

            // ✅ حذف نرم
            $stmt = $this->db->prepare("
            UPDATE tasks 
            SET is_deleted = 1, 
                deleted_at = NOW(), 
                deleted_by = ? 
            WHERE id = ?
        ");

            if ($stmt->execute([$user_id, $task_id])) {
                // ثبت در تاریخچه
                $this->addTaskHistory($task_id, $user_id, null, 'deleted', 'کار حذف شد');

                return ['success' => true, 'message' => 'کار با موفقیت حذف شد'];
            }

            return ['success' => false, 'message' => 'خطا در حذف کار'];

        } catch (Exception $e) {
            error_log("DeleteTask error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    public function getPreviousDelegations($user_id)
    {
        try {
            $sql = "SELECT DISTINCT t.*, 
                    creator.first_name as creator_first_name, 
                    creator.last_name as creator_last_name,
                    assignee.first_name as assignee_first_name, 
                    assignee.last_name as assignee_last_name,
                    CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
                    CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
                    (SELECT COUNT(*) FROM task_history WHERE task_id = t.id AND action = 'completed') as completed_count
                FROM tasks t
                LEFT JOIN users creator ON t.creator_id = creator.id
                LEFT JOIN users assignee ON t.assignee_id = assignee.id
                INNER JOIN task_history th ON t.id = th.task_id
                WHERE (th.from_user_id = ? OR th.to_user_id = ?)
                AND th.action = 'delegated'
                AND t.status NOT IN ('completed', 'approved')
                ORDER BY th.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id, $user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetPreviousDelegations error: " . $e->getMessage());
            return [];
        }
    }

    // دریافت کارهای کاربر
    public function getUserTasks($user_id, $filters = [])
    {
        try {
            $where_conditions = ["t.assignee_id = ?"];
            $params = [$user_id];

            if (!empty($filters['status'])) {
                $where_conditions[] = "t.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['priority'])) {
                $where_conditions[] = "t.priority = ?";
                $params[] = $filters['priority'];
            }

            if (!empty($filters['date_from'])) {
                $where_conditions[] = "t.due_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where_conditions[] = "t.due_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql = "SELECT t.*, 
                            creator.first_name as creator_first_name, 
                            creator.last_name as creator_last_name,
                            CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
                            (SELECT COUNT(*) FROM task_history WHERE task_id = t.id AND action = 'completed') as completed_count
                        FROM tasks t 
                        LEFT JOIN users creator ON t.creator_id = creator.id 
                        WHERE " . implode(' AND ', $where_conditions) . " and t.is_deleted = 0
                        ORDER BY t.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetUserTasks error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllTasks($user_id, $filters = [])
    {
        try {
            // واحدِ کاربر را برای شرط چک‌لیست بخوان
            $secStmt = $this->db->prepare("SELECT activity_section FROM users WHERE id = ?");
            $secStmt->execute([$user_id]);
            $user_section = $secStmt->fetchColumn() ?: '';

            // ✅ نمایش: سازنده، یا مسئول، یا ارجاع چک‌لیست (به کاربر یا واحدش)
            $checklist_exists = "EXISTS (
                SELECT 1 FROM task_checklist_items ci
                WHERE ci.task_id = t.id
                  AND (
                      (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
                      OR (ci.assignee_type = 'section' AND ci.assignee_value = ?)
                  )
            )";
            $where_conditions = ["(t.creator_id = ? OR t.assignee_id = ? OR $checklist_exists)"];
            $params = [$user_id, $user_id, (string)$user_id, $user_section];

            if (!empty($filters['status'])) {
                $where_conditions[] = "t.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['priority'])) {
                $where_conditions[] = "t.priority = ?";
                $params[] = $filters['priority'];
            }

            if (!empty($filters['date_from'])) {
                $where_conditions[] = "t.due_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where_conditions[] = "t.due_date <= ?";
                $params[] = $filters['date_to'];
            }

            // ✅ اضافه کردن اطلاعات مسئول انجام کار
            $sql = "SELECT t.*, 
                        creator.first_name as creator_first_name, 
                        creator.last_name as creator_last_name,
                        assignee.first_name as assignee_first_name,
                        assignee.last_name as assignee_last_name,
                        CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
                        CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
                        tg.name  as group_name,
                        tg.color as group_color,
                        tg.icon  as group_icon,
                        (SELECT COUNT(*) FROM task_history WHERE task_id = t.id AND action = 'completed') as completed_count,
                        CASE 
                            WHEN t.creator_id = ? THEN 'created'
                            WHEN t.assignee_id = ? THEN 'assigned'
                            ELSE 'unknown'
                        END as user_role
                    FROM tasks t 
                    LEFT JOIN users creator ON t.creator_id = creator.id
                    LEFT JOIN users assignee ON t.assignee_id = assignee.id
                    LEFT JOIN task_groups tg ON t.group_id = tg.id
                    WHERE " . implode(' AND ', $where_conditions) . " and t.is_deleted = 0
                    ORDER BY 
                        CASE WHEN t.status != 'completed' THEN 1 ELSE 2 END,
                        t.priority = 'high' DESC,
                        t.priority = 'medium' DESC,
                        t.due_date ASC";

            // ✅ اضافه کردن user_id دوباره برای CASE
            $params[] = $user_id;
            $params[] = $user_id;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetAllTasks error: " . $e->getMessage());
            return [];
        }
    }

    // دریافت کارهای ایجاد شده توسط کاربر
    public function getCreatedTasks($user_id)
    {
        try {
$sql = "SELECT t.*, 
                assignee.first_name as assignee_first_name, 
                assignee.last_name as assignee_last_name,
                CASE 
                    WHEN t.assignee_id IS NOT NULL 
                        THEN CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, ''))
                    WHEN oas.section_label IS NOT NULL 
                        THEN oas.section_label
                    ELSE ''
                END as assignee_name,
                (SELECT COUNT(*) FROM task_history WHERE task_id = t.id AND action = 'completed') as completed_count
            FROM tasks t 
            LEFT JOIN users assignee ON t.assignee_id = assignee.id
            LEFT JOIN users creator_user ON t.creator_id = creator_user.id
            LEFT JOIN organization_activity_sections oas 
                ON t.activity_section = oas.section_key 
                AND oas.organization_id = (SELECT organization_id FROM users WHERE id = ?)
            WHERE t.creator_id = ? and t.is_deleted = 0
            AND (t.assignee_id != ? OR t.assignee_id IS NULL)
            ORDER BY t.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id, $user_id, $user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetCreatedTasks error: " . $e->getMessage());
            return [];
        }
    }

    // متودهای کمکی
    private function userExists($user_id)
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch() !== false;
    }

    public function getTaskHistory($task_id)
    {
        try {
            $sql = "SELECT th.*,
                        from_user.first_name as from_user_first_name,
                        from_user.last_name as from_user_last_name,
                        to_user.first_name as to_user_first_name,
                        to_user.last_name as to_user_last_name
                    FROM task_history th
                    LEFT JOIN users from_user ON th.from_user_id = from_user.id
                    LEFT JOIN users to_user ON th.to_user_id = to_user.id
                    WHERE th.task_id = ?
                    ORDER BY th.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("GetTaskHistory error: " . $e->getMessage());
            return [];
        }
    }

    private function addTaskHistory($task_id, $from_user_id, $to_user_id, $action, $notes)
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO task_history (task_id, from_user_id, to_user_id, action, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$task_id, $from_user_id, $to_user_id, $action, $notes]);
        } catch (Exception $e) {
            error_log("AddTaskHistory error: " . $e->getMessage());
        }
    }

    public function getTask($task_id)
    {
        try {
            $sql = "SELECT t.*, 
                            creator.first_name as creator_first_name, 
                            creator.last_name as creator_last_name,
                            assignee.first_name as assignee_first_name, 
                            assignee.last_name as assignee_last_name,
                            CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, '')) as creator_name,
                            CONCAT(COALESCE(assignee.first_name, ''), ' ', COALESCE(assignee.last_name, '')) as assignee_name,
                            tg.name  as group_name,
                            tg.color as group_color,
                            tg.icon  as group_icon
                        FROM tasks t 
                        LEFT JOIN users creator ON t.creator_id = creator.id 
                        LEFT JOIN users assignee ON t.assignee_id = assignee.id 
                        LEFT JOIN task_groups tg ON t.group_id = tg.id
                        WHERE t.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("GetTask error: " . $e->getMessage());
            return null;
        }
    }

    // ✅ دریافت زنجیره ارجاعات (اصلاح شده)
    public function getDelegationChain($task_id)
    {
        $sql = "SELECT th.from_user_id, th.to_user_id, th.created_at,
                   u1.first_name as from_first_name, u1.last_name as from_last_name,
                   u2.first_name as to_first_name, u2.last_name as to_last_name
            FROM task_history th
            LEFT JOIN users u1 ON th.from_user_id = u1.id
            LEFT JOIN users u2 ON th.to_user_id = u2.id
            WHERE th.task_id = ? 
            AND th.action IN ('created', 'delegated')
            ORDER BY th.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$task_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ✅ بررسی اینکه آیا کاربر قبلاً این تسک را در دور جاری تأیید (approved) کرده
    // دور جاری = از آخرین reject یا آخرین completed به بعد
    private function hasAlreadyApproved($task_id, $user_id, $creator_id = null)
    {
        try {
            $user_id = (int)$user_id;

            // ✅ creator هیچ‌وقت تأیید خودکار نمی‌خورد (باید همیشه تأیید بزنه)
            if ($creator_id !== null && $user_id === (int)$creator_id) {
                return false;
            }

            // پیدا کردن شروع "دور جاری": آخرین rejected یا completed
            $stmt = $this->db->prepare("
                SELECT created_at FROM task_history
                WHERE task_id = ?
                AND action IN ('rejected', 'completed')
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$task_id]);
            $lastReset = $stmt->fetch(PDO::FETCH_ASSOC);
            $resetTime = $lastReset ? $lastReset['created_at'] : '1970-01-01 00:00:00';
            
            // آیا این کاربر بعد از آخرین ریست، تأیید زده؟
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as cnt FROM task_history
                WHERE task_id = ?
                AND from_user_id = ?
                AND action = 'approved'
                AND created_at > ?
            ");
            $stmt->execute([$task_id, $user_id, $resetTime]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($row['cnt'] ?? 0) > 0;
        } catch (Exception $e) {
            error_log("hasAlreadyApproved error: " . $e->getMessage());
            return false;
        }
    }

    // ✅ پیدا کردن نفر بعدی در زنجیره که هنوز تأیید نزده
    // از زنجیره کامل (با تکرار) استفاده می‌کند تا ترتیب واقعی ارجاعات حفظ شود
    // creator هیچ‌وقت تأیید خودکار نمی‌خورد
    private function findNextPendingApprover($full_chain, $current_index, $task_id, $creator_id = null, $current_user_id = null)
    {
        for ($i = $current_index - 1; $i >= 0; $i--) {
            $candidate = (int)$full_chain[$i];
            
            // ✅ جلوگیری از حلقه بی‌نهایت: اگر نفر بعدی خود کاربر فعلی باشد، skip کن
            if ($current_user_id !== null && $candidate === (int)$current_user_id) {
                error_log("Auto-skip approver $candidate (same as current user, preventing loop in task $task_id)");
                continue;
            }
            
            if (!$this->hasAlreadyApproved($task_id, $candidate, $creator_id)) {
                return ['index' => $i, 'user_id' => $candidate];
            }
            error_log("Auto-skip approver $candidate (already approved task $task_id)");
        }
        return null;
    }

    // دریافت مسیر تأیید کار
    private function getApprovalPath($task_id)
    {
        $sql = "SELECT th.from_user_id, th.to_user_id, th.created_at, th.action
        FROM task_history th
        WHERE th.task_id = ? 
        AND th.action IN ('pending_approval', 'approved')
        ORDER BY th.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$task_id]);
        return $stmt->fetchAll();
    }

    // ✅ تأیید یا رد کار (اصلاح شده)
    public function approveOrRejectTask($task_id, $user_id, $approve, $notes = '')
    {
        try {
            $task = $this->getTask($task_id);

            if (!$task || $task['assignee_id'] != $user_id) {
                return ['success' => false, 'message' => 'شما مجاز به تأیید این کار نیستید'];
            }

            if (!$task['is_pending_approval']) {
                return ['success' => false, 'message' => 'این کار در انتظار تأیید نیست'];
            }

            if ($approve) {
                // ✅ چک: آیا کار دوره‌ای است؟
                $isPeriodic = in_array($task['task_type'], ['periodic', 'continuous']);

                if ($isPeriodic && $task['task_type'] === 'continuous') {
                    $today = date('Y-m-d');
                    $newPendingCount = max(0, ($task['pending_approval_count'] ?? 1) - 1);

                    if ($newPendingCount > 0) {
                        $sql = "UPDATE tasks SET 
                            last_approved_date = ?,
                            pending_approval_count = ?,
                            is_pending_approval = TRUE,
                            status = 'pending_approval',
                            updated_at = NOW()
                            WHERE id = ?";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([$today, $newPendingCount, $task_id]);

                        $this->addTaskHistory($task_id, $user_id, null, 'approved', 'تأیید یک دوره: ' . $notes);

                        return [
                            'success' => true,
                            'message' => "دوره تأیید شد. {$newPendingCount} دوره دیگر منتظر تأیید است",
                            'remaining_approvals' => $newPendingCount
                        ];
                    }

                    $performerId = $this->getLastPerformer($task_id);
                    $sql = "UPDATE tasks SET 
                        last_approved_date = ?,
                        pending_approval_count = 0,
                        is_pending_approval = FALSE,
                        status = 'in_progress',
                        assignee_id = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$today, $performerId, $task_id]);

                    // ✅ action باید 'completed' باشد نه 'approved' — همه‌ی محاسبات دوره‌های
                    // معوقه/موعد بعدی (calcOverduePeriods، next_due_date، maybeStartNextPeriod)
                    // فقط رکوردهای action='completed' را می‌شمارند؛ وگرنه completed_count برای
                    // همیشه ۰ می‌ماند و کار همیشه معوقه نشان داده می‌شود.
                    $this->addTaskHistory($task_id, $user_id, $performerId, 'completed', 'آخرین دوره تأیید شد: ' . $notes);
                    $this->notifyCompletion($task, $performerId, 'completion_approved', $user_id);  // 🆕
                    return [
                        'success' => true,
                        'message' => 'تمام دوره‌ها تأیید شد. کار به حالت عادی بازگشت',
                        'final_approval' => true
                    ];
                }

                // ✅ برای periodic - زنجیره کامل با ترتیب واقعی
                if ($isPeriodic && $task['task_type'] === 'periodic') {
                    $chain = $this->getDelegationChain($task_id);
                    $creator_id = (int)$task['creator_id'];

                    // ساخت full_chain: ترتیب واقعی همه افراد در زنجیره
                    // ✅ حذف تکرار متوالی (مثلاً created از 19→19 باعث [19,19,29] می‌شد)
                    $full_chain = [];
                    foreach ($chain as $entry) {
                        $from = (int)$entry['from_user_id'];
                        $to = (int)$entry['to_user_id'];
                        if (empty($full_chain)) {
                            $full_chain[] = $from;
                        }
                        // فقط اگر نفر بعدی با آخرین نفر فرق داره اضافه کن
                        if ($to !== end($full_chain)) {
                            $full_chain[] = $to;
                        }
                    }
                    if (empty($full_chain)) {
                        $full_chain[] = $creator_id;
                    }

                    // پیدا کردن آخرین ظهور user_id در full_chain
                    $current_index = null;
                    for ($i = count($full_chain) - 1; $i >= 0; $i--) {
                        if ($full_chain[$i] === (int)$user_id) {
                            $current_index = $i;
                            break;
                        }
                    }

                    if ($current_index === null) {
                        return ['success' => false, 'message' => 'کاربر در زنجیره ارجاعات یافت نشد'];
                    }

                    // ✅ تأیید نهایی: اولین نفر در زنجیره (index=0) یا creator کار
                    if ($current_index === 0 || count($full_chain) === 1 || (int)$user_id === $creator_id) {
                        $sql = "UPDATE tasks SET status = 'completed', is_pending_approval = FALSE, updated_at = NOW() WHERE id = ?";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([$task_id]);
                        $this->addTaskHistory($task_id, $user_id, null, 'completed', 'تأیید نهایی' . $notes);
                        $this->notifyCompletion($task, $this->getLastPerformer($task_id), 'completion_approved', $user_id);  // 🆕
                        return ['success' => true, 'message' => 'کار با موفقیت تکمیل شد', 'final_approval' => true];
                    }

                    // ثبت تأیید فعلی
                    $this->addTaskHistory($task_id, $user_id, null, 'approved', 'تأیید شد: ' . $notes);

                    // ✅ پیدا کردن نفر بعدی (با ارسال current_user_id برای جلوگیری از حلقه)
                    $next = $this->findNextPendingApprover($full_chain, $current_index, $task_id, $creator_id, $user_id);

                    if ($next === null) {
                        $sql = "UPDATE tasks SET status = 'completed', is_pending_approval = FALSE, updated_at = NOW() WHERE id = ?";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([$task_id]);
                        $this->addTaskHistory($task_id, $user_id, null, 'completed', 'تأیید خودکار نهایی: ' . $notes);
                        $this->notifyCompletion($task, $this->getLastPerformer($task_id), 'completion_approved', $user_id);  // 🆕
                        return ['success' => true, 'message' => 'کار با موفقیت تکمیل شد', 'final_approval' => true];
                    }

                    $previous_person = $next['user_id'];
                    $autoSkipped = ($next['index'] < $current_index - 1);

                    $sql = "UPDATE tasks SET status = 'pending_approval', is_pending_approval = TRUE, assignee_id = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$previous_person, $task_id]);

                    $historyNote = $autoSkipped
                        ? 'تأیید شد، تأیید خودکار برای نفرات قبلی و ارسال برای تأیید: ' . $notes
                        : 'تأیید شد و ارسال برای تأیید نفر بالاتر: ' . $notes;

                    $this->addTaskHistory($task_id, $user_id, $previous_person, 'pending_approval', $historyNote);
                    return ['success' => true, 'message' => 'کار تأیید و به مرحله بعد ارسال شد', 'continue_approval' => true];
                }

                // برای سایر حالات (non-periodic)
                $chain = $this->getDelegationChain($task_id);
                if (empty($chain)) {
                    $chain = [['from_user_id' => $task['creator_id'], 'to_user_id' => $task['creator_id']]];
                }
                $creator_id = (int)$task['creator_id'];

                // ✅ حذف تکرار متوالی
                $full_chain = [];
                foreach ($chain as $entry) {
                    $from = (int)$entry['from_user_id'];
                    $to = (int)$entry['to_user_id'];
                    if (empty($full_chain)) {
                        $full_chain[] = $from;
                    }
                    if ($to !== end($full_chain)) {
                        $full_chain[] = $to;
                    }
                }

                $current_index = null;
                for ($i = count($full_chain) - 1; $i >= 0; $i--) {
                    if ($full_chain[$i] === (int)$user_id) {
                        $current_index = $i;
                        break;
                    }
                }

                if ($current_index === null) {
                    return ['success' => false, 'message' => 'کاربر در زنجیره ارجاعات یافت نشد'];
                }

                // ✅ تأیید نهایی: اولین نفر یا creator
                if ($current_index === 0 || count($full_chain) === 1 || (int)$user_id === $creator_id) {
                    $sql = "UPDATE tasks SET status = 'completed', is_pending_approval = FALSE, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$task_id]);
                    $this->addTaskHistory($task_id, $user_id, null, 'completed', 'تأیید نهایی' . $notes);
                    $this->notifyCompletion($task, $this->getLastPerformer($task_id), 'completion_approved', $user_id);  // 🆕
                    return ['success' => true, 'message' => 'کار با موفقیت تکمیل نهایی شد', 'final_approval' => true];
                }

                $this->addTaskHistory($task_id, $user_id, null, 'approved', 'تأیید شد: ' . $notes);

                // ✅ ارسال current_user_id برای جلوگیری از حلقه
                $next = $this->findNextPendingApprover($full_chain, $current_index, $task_id, $creator_id, $user_id);

                if ($next === null) {
                    $sql = "UPDATE tasks SET status = 'completed', is_pending_approval = FALSE, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$task_id]);
                    $this->addTaskHistory($task_id, $user_id, null, 'completed', 'تأیید خودکار نهایی: ' . $notes);
                    $this->notifyCompletion($task, $this->getLastPerformer($task_id), 'completion_approved', $user_id);  // 🆕
                    return ['success' => true, 'message' => 'کار با موفقیت تکمیل نهایی شد', 'final_approval' => true];
                }

                $previous_person = $next['user_id'];
                $sql = "UPDATE tasks SET status = 'pending_approval', is_pending_approval = TRUE, assignee_id = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$previous_person, $task_id]);
                $this->addTaskHistory($task_id, $user_id, $previous_person, 'pending_approval', 'تأیید شد و ارسال شد برای تأیید نفر بالاتر: ' . $notes);
                return ['success' => true, 'message' => 'کار تأیید و به مرحله بعد ارسال شد', 'continue_approval' => true];

            } else {
                // رد کردن کار
                if ($task['task_type'] === 'continuous') {
                    $sql = "UPDATE tasks SET pending_approval_count = 0, is_pending_approval = FALSE WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$task_id]);
                }

                // ✅ برگشت به نفر بعدی در زنجیره از دید رد‌کننده
                $chain = $this->getDelegationChain($task_id);
                $full_chain = [];
                foreach ($chain as $entry) {
                    if (empty($full_chain)) $full_chain[] = (int)$entry['from_user_id'];
                    $full_chain[] = (int)$entry['to_user_id'];
                }

                $rejecter_index = null;
                for ($i = count($full_chain) - 1; $i >= 0; $i--) {
                    if ($full_chain[$i] === (int)$user_id) {
                        $rejecter_index = $i;
                        break;
                    }
                }

                $returnToPerson = null;
                if ($rejecter_index !== null && isset($full_chain[$rejecter_index + 1])) {
                    $returnToPerson = $full_chain[$rejecter_index + 1];
                }

                if (!$returnToPerson) {
                    $returnToPerson = $this->getLastPerformer($task_id);
                }

                if (!$returnToPerson) {
                    return ['success' => false, 'message' => 'خطا در یافتن نفر برگشت کار'];
                }

                $sql = "UPDATE tasks SET status = 'in_progress', is_pending_approval = FALSE, assignee_id = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$returnToPerson, $task_id]);
                $this->addTaskHistory($task_id, $user_id, $returnToPerson, 'rejected', 'رد شد و بازگشت داده شد: ' . $notes);
                $this->notifyCompletion($task, $returnToPerson, 'completion_rejected', $user_id, $notes);  // 🆕
                return ['success' => true, 'message' => 'کار رد شد و به نفر قبلی بازگشت', 'rejected' => true];
            }

        } catch (Exception $e) {
            error_log("ApproveOrRejectTask error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ✅ پیدا کردن نفری که کار بعد از رد شدن باید به او برگردد
    // مثال: زنجیره علی→حسن→رضا، نفر علی رد می‌کند → باید به حسن برگردد
    private function getNextPersonInChainAfterRejecter($task_id, $rejecter_id)
    {
        try {
            $rejecter_id = (int)$rejecter_id;

            // آخرین pending_approval که به rejecter رسیده - چه کسی فرستاده؟
            $stmt = $this->db->prepare("
                SELECT from_user_id FROM task_history
                WHERE task_id = ?
                AND action = 'pending_approval'
                AND to_user_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$task_id, $rejecter_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['from_user_id']) {
                return (int)$row['from_user_id'];
            }

            // fallback: از زنجیره ارجاعات، نفر بعد از rejecter
            $task = $this->getTask($task_id);
            $chain = $this->getDelegationChain($task_id);

            $unique_chain = [];
            $seen = [];

            if ($task && !in_array((int)$task['creator_id'], $seen)) {
                $unique_chain[] = (int)$task['creator_id'];
                $seen[] = (int)$task['creator_id'];
            }

            foreach ($chain as $entry) {
                $to_user = (int)$entry['to_user_id'];
                if ($to_user && !in_array($to_user, $seen)) {
                    $unique_chain[] = $to_user;
                    $seen[] = $to_user;
                }
            }

            $idx = array_search($rejecter_id, $unique_chain);
            if ($idx !== false && isset($unique_chain[$idx + 1])) {
                return $unique_chain[$idx + 1];
            }

            return null;
        } catch (Exception $e) {
            error_log("getNextPersonInChainAfterRejecter error: " . $e->getMessage());
            return null;
        }
    }

    // ✅ یافتن آخرین نفری که کار را تأیید کرده (اصلاح شده)
    private function getLastApprover($task_id, $current_user_id)
    {
        try {
            $sql = "SELECT from_user_id FROM task_history 
            WHERE task_id = ? 
            AND to_user_id = ?
            AND action = 'pending_approval'
            ORDER BY created_at DESC 
            LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task_id, $current_user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['from_user_id']) {
                return $result['from_user_id'];
            }

            $chain = $this->getDelegationChain($task_id);

            if (empty($chain)) {
                return null;
            }

            for ($i = count($chain) - 1; $i >= 0; $i--) {
                if ($chain[$i]['to_user_id'] == $current_user_id) {
                    if ($i < count($chain) - 1) {
                        return $chain[$i + 1]['to_user_id'];
                    }
                    break;
                }
            }

            return null;

        } catch (Exception $e) {
            error_log("GetLastApprover error: " . $e->getMessage());
            return null;
        }
    }

    // ✅ یافتن آخرین انجام‌دهنده (اصلاح شده - خط + حذف شده)
    private function getLastPerformer($task_id)
    {
        try {
            $sql = "SELECT from_user_id FROM task_history 
            WHERE task_id = ? 
            AND action = 'completed'
            ORDER BY created_at DESC 
            LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task_id]);
            $result = $stmt->fetch();

            if ($result && $result['from_user_id']) {
                return $result['from_user_id'];
            }

            $chain = $this->getDelegationChain($task_id);
            if (!empty($chain)) {
                $lastInChain = end($chain);
                return $lastInChain['to_user_id'];
            }

            return null;
        } catch (Exception $e) {
            error_log("GetLastPerformer error: " . $e->getMessage());
            return null;
        }
    }

    public function getTaskStats($user_id)
    {
        try {
            $stats = [
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'overdue' => 0,
                'today' => 0
            ];

            // -----------------------------------------------
            // 1) کل کارهای فعال (نه completed/approved)
            // هم کارهایی که کاربر مسئولشه هم کارهایی که ساخته
            // -----------------------------------------------
            $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE (assignee_id = ? OR creator_id = ?)
              AND is_deleted = 0 
              AND status NOT IN ('completed', 'approved', 'stopped')
        ");
            $stmt->execute([$user_id, $user_id]);
            $stats['total'] = (int) $stmt->fetch()['count'];

            // -----------------------------------------------
            // 2) کارهای تکمیل‌شده
            // -----------------------------------------------
            $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE (assignee_id = ? OR creator_id = ?)
              AND is_deleted = 0 
              AND status IN ('completed', 'approved')
        ");
            $stmt->execute([$user_id, $user_id]);
            $stats['completed'] = (int) $stmt->fetch()['count'];

            // -----------------------------------------------
            // 3) کارهای عقب‌افتاده
            // periodic: due_date گذشته و تکمیل نشده
            // continuous: از view محاسبه می‌شه (overdue_periods > 0)
            // -----------------------------------------------
            $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE (assignee_id = ? OR creator_id = ?)
              AND is_deleted = 0
              AND status NOT IN ('completed', 'approved', 'stopped')
              AND (
                  (task_type = 'periodic' AND due_date < CURDATE())
                  OR
                  (task_type = 'continuous' AND id IN (
                      SELECT id FROM continuous_tasks_overdue
                  ))
              )
        ");
            $stmt->execute([$user_id, $user_id]);
            $stats['overdue'] = (int) $stmt->fetch()['count'];

            // -----------------------------------------------
            // 4) کارهای امروز (due_date = امروز و تکمیل نشده)
            // -----------------------------------------------
            $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE (assignee_id = ? OR creator_id = ?)
              AND is_deleted = 0 
              AND due_date = CURDATE()
              AND status NOT IN ('completed', 'approved', 'stopped')
        ");
            $stmt->execute([$user_id, $user_id]);
            $stats['today'] = (int) $stmt->fetch()['count'];

            return $stats;

        } catch (Exception $e) {
            error_log("GetTaskStats error: " . $e->getMessage());
            return [
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'overdue' => 0,
                'today' => 0
            ];
        }
    }

    // =====================================================================
    // ✅ تمدید دورهٔ کارهای continuous (پس از پایان end_date)
    // =====================================================================

    // بررسی اینکه آیا کار آماده تمدید دوره است (end_date گذشته + آخرین دوره تأیید شده)
    private function isReadyForRenewal($task)
    {
        if (!$task || $task['task_type'] !== 'continuous') return false;
        if (empty($task['end_date'])) return false;
        if ((int)$task['is_pending_approval'] === 1) return false;
        if ((int)($task['has_pending_renewal_request'] ?? 0) === 1) return false;
        $today = date('Y-m-d');
        return $task['end_date'] <= $today;
    }

    // اعمال واقعیِ تمدید (مشترک بین مسیر مستقیم و مسیر تأیید زنجیره‌ای)
    private function applyRenewalChanges($task_id, $new_start_date, $new_end_date, $performer_id)
    {
        $sql = "UPDATE tasks SET
                    start_date = ?,
                    end_date = ?,
                    pending_approval_count = 0,
                    is_pending_approval = FALSE,
                    last_approved_date = NULL,
                    last_completed_date = NULL,
                    has_pending_renewal_request = 0,
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$new_start_date, $new_end_date, $task_id]);

        $endLabel = $new_end_date ? $new_end_date : 'نامحدود';
        $this->addTaskHistory(
            $task_id,
            $performer_id,
            null,
            'renewal_applied',
            "تمدید دوره اعمال شد. شروع جدید: {$new_start_date} — پایان جدید: {$endLabel}. شمارش دوره‌های قبلی بازنشانی شد."
        );
    }

    // ارسال نوتیفیکیشن (که خودش پیامک را هم async می‌فرستد)
    private function notifyRenewal($to_user_id, $task_title, $task_id, $title, $message)
    {
        try {
            require_once __DIR__ . '/Notification.php';
            $notification = new Notification($this->db);
            $notification->create([
                'to_user_id' => $to_user_id,
                'title' => $title,
                'message' => $message,
                'type' => 'info',
                'link' => '/pages/task-detail.php?id=' . $task_id,
                'related_type' => 'task',
                'related_id' => $task_id,
                'is_read' => 0
            ]);
        } catch (Exception $e) {
            error_log("notifyRenewal error: " . $e->getMessage());
        }
    }

    // ساختن زنجیرهٔ یکتا (حذف تکرار متوالی) — مشابه approveOrRejectTask
    private function buildFullChain($task_id, $creator_id)
    {
        $chain = $this->getDelegationChain($task_id);
        $full_chain = [];
        foreach ($chain as $entry) {
            $from = (int)$entry['from_user_id'];
            $to = (int)$entry['to_user_id'];
            if (empty($full_chain)) $full_chain[] = $from;
            if ($to !== end($full_chain)) $full_chain[] = $to;
        }
        if (empty($full_chain)) $full_chain[] = $creator_id;
        return $full_chain;
    }

    // ✅ ثبت درخواست تمدید دوره (توسط مسئول فعلی، وقتی مسئول ≠ تعریف‌کننده)
    public function requestPeriodRenewal($task_id, $user_id, $new_start_date, $new_end_date, $reason)
    {
        try {
            $task = $this->getTask($task_id);
            if (!$task) return ['success' => false, 'message' => 'کار یافت نشد'];
            if ((int)$task['assignee_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'فقط مسئول فعلی کار می‌تواند درخواست تمدید دهد'];
            }
            if (!$this->isReadyForRenewal($task)) {
                return ['success' => false, 'message' => 'این کار هنوز آماده تمدید دوره نیست'];
            }
            $today = date('Y-m-d');
            if ($new_start_date < $today) {
                return ['success' => false, 'message' => 'تاریخ شروع مجدد نمی‌تواند قبل از امروز باشد'];
            }
            if (!empty($new_end_date) && $new_end_date < $new_start_date) {
                return ['success' => false, 'message' => 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد'];
            }

            $creator_id = (int)$task['creator_id'];
            $full_chain = $this->buildFullChain($task_id, $creator_id);

            $current_index = null;
            for ($i = count($full_chain) - 1; $i >= 0; $i--) {
                if ($full_chain[$i] === (int)$user_id) { $current_index = $i; break; }
            }
            if ($current_index === null) {
                return ['success' => false, 'message' => 'کاربر در زنجیره این کار یافت نشد'];
            }

            // نفر بعدی برای تأیید (به سمت ابتدای زنجیره / تعریف‌کننده)
            $next_approver = ($current_index > 0) ? $full_chain[$current_index - 1] : $creator_id;

            $stmt = $this->db->prepare("
                INSERT INTO task_renewal_requests
                    (task_id, requested_by, current_approver_id, new_start_date, new_end_date, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$task_id, $user_id, $next_approver, $new_start_date, $new_end_date ?: null, $reason]);

            $this->db->prepare("UPDATE tasks SET has_pending_renewal_request = 1 WHERE id = ?")->execute([$task_id]);

            $endLabel = $new_end_date ?: 'نامحدود';
            $this->addTaskHistory($task_id, $user_id, $next_approver, 'renewal_requested',
                "درخواست تمدید دوره: شروع {$new_start_date}، پایان {$endLabel}. دلیل: " . $reason);

            $this->notifyRenewal($next_approver, $task['title'], $task_id,
                'درخواست تمدید دوره: ' . $task['title'],
                'درخواست تمدید دورهٔ کار «' . $task['title'] . '» منتظر بررسی شماست.');

            return ['success' => true, 'message' => 'درخواست تمدید دوره ارسال شد'];
        } catch (Exception $e) {
            error_log("requestPeriodRenewal error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ✅ تأیید یک مرحله از زنجیرهٔ تمدید (یا اعمال نهایی اگر به تعریف‌کننده رسیده باشد)
    public function approvePeriodRenewal($request_id, $user_id, $notes = '')
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM task_renewal_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req || $req['status'] !== 'pending') {
                return ['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً بررسی شده'];
            }
            if ((int)$req['current_approver_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'شما مجاز به تأیید این درخواست نیستید'];
            }

            $task = $this->getTask($req['task_id']);
            if (!$task) return ['success' => false, 'message' => 'کار یافت نشد'];
            $creator_id = (int)$task['creator_id'];

            // تأیید نهایی: رسیدن به تعریف‌کننده
            if ((int)$user_id === $creator_id) {
                $this->applyRenewalChanges($req['task_id'], $req['new_start_date'], $req['new_end_date'], $user_id);
                $this->db->prepare("UPDATE task_renewal_requests SET status='approved', decided_at=NOW() WHERE id = ?")
                    ->execute([$request_id]);

                $this->notifyRenewal($req['requested_by'], $task['title'], $req['task_id'],
                    'تمدید دوره تأیید شد: ' . $task['title'],
                    'درخواست تمدید دورهٔ کار «' . $task['title'] . '» تأیید و اعمال شد.');

                return ['success' => true, 'message' => 'تمدید دوره تأیید و اعمال شد', 'final_approval' => true];
            }

            // یافتن نفر بعدی در زنجیره (به سمت تعریف‌کننده)
            $full_chain = $this->buildFullChain($req['task_id'], $creator_id);
            $current_index = null;
            for ($i = count($full_chain) - 1; $i >= 0; $i--) {
                if ($full_chain[$i] === (int)$user_id) { $current_index = $i; break; }
            }
            if ($current_index === null) {
                return ['success' => false, 'message' => 'کاربر در زنجیره این کار یافت نشد'];
            }
            $next_approver = ($current_index > 0) ? $full_chain[$current_index - 1] : $creator_id;

            $this->db->prepare("UPDATE task_renewal_requests SET current_approver_id = ? WHERE id = ?")
                ->execute([$next_approver, $request_id]);

            $this->addTaskHistory($req['task_id'], $user_id, $next_approver, 'renewal_step_approved',
                'تأیید شد و ارسال برای تأیید نفر بالاتر: ' . $notes);

            $this->notifyRenewal($next_approver, $task['title'], $req['task_id'],
                'درخواست تمدید دوره: ' . $task['title'],
                'درخواست تمدید دورهٔ کار «' . $task['title'] . '» منتظر بررسی شماست.');

            return ['success' => true, 'message' => 'تأیید شد و به مرحله بعد ارسال شد', 'continue_approval' => true];
        } catch (Exception $e) {
            error_log("approvePeriodRenewal error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ✅ رد درخواست تمدید دوره — بازگشت به مسئول فعلی کار با ذکر دلیل
    public function rejectPeriodRenewal($request_id, $user_id, $rejection_reason)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM task_renewal_requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req || $req['status'] !== 'pending') {
                return ['success' => false, 'message' => 'درخواست یافت نشد یا قبلاً بررسی شده'];
            }
            if ((int)$req['current_approver_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'شما مجاز به رد این درخواست نیستید'];
            }

            $task = $this->getTask($req['task_id']);

            $this->db->prepare("UPDATE task_renewal_requests SET status='rejected', rejection_reason=?, decided_at=NOW() WHERE id = ?")
                ->execute([$rejection_reason, $request_id]);
            $this->db->prepare("UPDATE tasks SET has_pending_renewal_request = 0 WHERE id = ?")
                ->execute([$req['task_id']]);

            $this->addTaskHistory($req['task_id'], $user_id, $req['requested_by'], 'renewal_rejected',
                'درخواست تمدید موعد رد شد. دلیل: ' . $rejection_reason);

            $this->notifyRenewal($req['requested_by'], $task['title'] ?? '', $req['task_id'],
                'درخواست تمدید دوره رد شد: ' . ($task['title'] ?? ''),
                'درخواست تمدید دورهٔ کار «' . ($task['title'] ?? '') . '» رد شد. دلیل: ' . $rejection_reason);

            return ['success' => true, 'message' => 'درخواست رد شد'];
        } catch (Exception $e) {
            error_log("rejectPeriodRenewal error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    // ✅ تمدید مستقیم توسط تعریف‌کننده (بدون نیاز به تأیید)
    public function applyPeriodRenewalDirect($task_id, $user_id, $new_start_date, $new_end_date, $reason = '')
    {
        try {
            $task = $this->getTask($task_id);
            if (!$task) return ['success' => false, 'message' => 'کار یافت نشد'];
            if ((int)$task['creator_id'] !== (int)$user_id) {
                return ['success' => false, 'message' => 'فقط تعریف‌کنندهٔ کار می‌تواند بدون تأیید تمدید کند'];
            }
            if (!$this->isReadyForRenewal($task)) {
                return ['success' => false, 'message' => 'این کار هنوز آماده تمدید دوره نیست'];
            }
            $today = date('Y-m-d');
            if ($new_start_date < $today) {
                return ['success' => false, 'message' => 'تاریخ شروع مجدد نمی‌تواند قبل از امروز باشد'];
            }
            if (!empty($new_end_date) && $new_end_date < $new_start_date) {
                return ['success' => false, 'message' => 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد'];
            }

            $this->applyRenewalChanges($task_id, $new_start_date, $new_end_date, $user_id);

            if (!empty($reason)) {
                $this->addTaskHistory($task_id, $user_id, null, 'renewal_applied', 'دلیل تمدید: ' . $reason);
            }

            if ((int)$task['assignee_id'] !== (int)$user_id) {
                $this->notifyRenewal($task['assignee_id'], $task['title'], $task_id,
                    'تمدید دوره: ' . $task['title'],
                    'دورهٔ کار «' . $task['title'] . '» توسط تعریف‌کننده تمدید شد.');
            }

            return ['success' => true, 'message' => 'دوره با موفقیت تمدید شد'];
        } catch (Exception $e) {
            error_log("applyPeriodRenewalDirect error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }
}
?>