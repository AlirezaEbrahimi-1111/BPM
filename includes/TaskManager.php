<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/error_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period-engine.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';

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
                // گیرنده اینجا حتماً غیرِ اقدام‌کننده است (بالاتر چک شد)؛ ولی چون
                // تکمیل/فاینالایز ممکن است assignee_id را روی همین گیرنده گذاشته
                // باشد، self-check نوتیفیکیشن آن را اشتباهاً بلاک می‌کند.
                'skip_self_check' => true,
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

            if ($data['task_type'] === 'periodic' && !empty($data['due_date'])) {
                if ($data['due_date'] < $today) {
                    return ['success' => false, 'message' => 'نمی‌توانید کار با تاریخ گذشته ایجاد کنید'];
                }
                // موعد فقط وقتی داده شده که کار به کسی ارجاع شده (بررسی‌شده در
                // لایهٔ API)؛ کارِ شخصیِ بدونِ موعد باید deadline/original_deadline
                // هم null بمونه — نه یک مقدارِ پیش‌فرضِ ۳۰ روزهٔ ساختگی
                $deadline = $data['due_date'];
                // original_deadline همیشه برابر deadline است
                $original_deadline = $deadline;
            }

            if ($data['task_type'] === 'continuous' && !empty($data['start_date'])) {
                if ($data['start_date'] < $today) {
                    return ['success' => false, 'message' => 'تاریخ شروع نمی‌تواند قبل از امروز باشد'];
                }
            }

            // 🔒 خط قرمز: مسئولِ کار باید از همان سازمانِ تعریف‌کننده باشد، وگرنه
            // می‌شود کاری را مستقیماً به کاربرِ سازمانِ کاملاً دیگری واگذار کرد
            if (!empty($data['assignee_id']) && (int)$data['assignee_id'] !== (int)$creator_id) {
                $assigneeOrgStmt = $this->db->prepare("SELECT organization_id FROM users WHERE id = ?");
                $assigneeOrgStmt->execute([$data['assignee_id']]);
                $assigneeOrg = $assigneeOrgStmt->fetchColumn();
                if ((int)$assigneeOrg !== (int)$organization_id) {
                    return ['success' => false, 'message' => 'مسئول کار باید از همین سازمان باشد'];
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

            error_log("createTask execute failed | creator_id={$creator_id} | organization_id={$organization_id}");
            return ['success' => false, 'message' => 'خطا در ایجاد کار'];
        } catch (Exception $e) {
            error_log("CreateTask error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()];
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

            error_log("updateTask no-op / execute failed | user_id={$user_id} | task_id={$task_id}");
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

            // ✅ موتور مشترک — تنها مرجع محاسبهٔ دوره
            $holidays = getHolidaySet($this->db);
            $state    = pe_state($this->db, $task, $holidays);

            // 🔒 محافظ: دورهٔ امروز نباید دو بار بسته شود
            if (!$state['can_complete']) {
                error_log("finalizeSelfCompletion blocked | user_id={$user_id} | task_id={$task_id} | is_today_done=" . ($state['is_today_done'] ? '1' : '0'));
                return [
                    'success' => false,
                    'message' => $state['is_today_done']
                        ? 'دورهٔ امروز قبلاً تکمیل شده است'
                        : 'این کار در وضعیت قابل تکمیل نیست'
                ];
            }

            $today = date('Y-m-d');

            $this->db->prepare("
                UPDATE tasks SET
                    status                 = 'period_done',
                    last_completed_date    = ?,
                    last_approved_date     = ?,
                    is_pending_approval    = FALSE,
                    pending_approval_count = 0,
                    updated_at             = NOW()
                WHERE id = ?
            ")->execute([$today, $today, $task_id]);

            $this->addTaskHistory(
                $task_id,
                $user_id,
                null,
                'completed',
                $notes ?: ('دورهٔ ' . $state['current_period_date'] . ' تکمیل شد')
            );

            $after = pe_state($this->db, $task, $holidays);
            $msg   = $after['overdue_periods'] > 0
                ? "دورهٔ امروز تکمیل شد. {$after['overdue_periods']} دورهٔ معوقه باقی است."
                : 'دورهٔ امروز تکمیل شد.';

            return ['success' => true, 'message' => $msg];
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

            // 🔒 قفلِ چک‌لیست: تا تیک‌نخوردنِ همهٔ آیتم‌ها، تکمیلِ کار مجاز نیست.
            // برای تسکِ معمولی status==='completed' همین‌جا کامل می‌شود؛ برای مرحلهٔ
            // روتین معمولاً از WorkflowManager::completeStep رد می‌شود (که خودش همین
            // چک را دارد) اما اگر آن مسیر خطا بدهد، update-status.php به همین تابع با
            // status==='approved' برمی‌گردد — پس همین‌جا هم باید چک شود، وگرنه قفل با
            // این مسیرِ جایگزین دور زده می‌شود.
            $isCompletionAttempt = ($status === 'completed') || ($task['is_workflow_task'] == 1 && $status === 'approved');
            if ($isCompletionAttempt) {
                $clStmt = $this->db->prepare("SELECT COUNT(*) FROM task_checklist_items WHERE task_id = ? AND is_done = 0");
                $clStmt->execute([$task_id]);
                if ((int) $clStmt->fetchColumn() > 0) {
                    return ['success' => false, 'message' => 'ابتدا باید همهٔ آیتم‌های چک‌لیست را تیک بزنید'];
                }
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

                // ✅ کار دوره‌ای (continuous): دورهٔ امروز باید همین الان — روزی که واقعاً
                // انجام شده — در task_history با action='completed' بسته شود، نه در لحظهٔ
                // تأیید. وگرنه اگر تأیید یک روز (یا بیشتر) بعد اتفاق بیفتد، موتور دوره
                // (period-engine → pe_completionDates) رکورد completed را با تاریخ تأیید
                // می‌بیند و اشتباهاً دورهٔ همان روزِ تأیید را «انجام‌شده» حساب می‌کند؛
                // نتیجه: دورهٔ واقعیِ آن روز قفل می‌ماند و کاربر نمی‌تواند تکمیلش بزند.
                if ($task['task_type'] === 'continuous') {
                    $holidays = getHolidaySet($this->db);
                    $state    = pe_state($this->db, $task, $holidays);

                    if (!$state['can_complete']) {
                        error_log("updateTaskStatus completion blocked (continuous) | user_id={$user_id} | task_id={$task_id} | is_today_done=" . ($state['is_today_done'] ? '1' : '0'));
                        return [
                            'success' => false,
                            'message' => $state['is_today_done']
                                ? 'دورهٔ امروز قبلاً تکمیل شده است'
                                : 'این کار در وضعیت قابل تکمیل نیست'
                        ];
                    }

                    $today = date('Y-m-d');
                    $sql = "UPDATE tasks SET status = 'pending_approval', is_pending_approval = TRUE, assignee_id = ?, last_completed_date = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$previousPerson, $today, $task_id]);

                    $this->addTaskHistory($task_id, $user_id, $previousPerson, 'completed', $notes ?: ('دورهٔ ' . $state['current_period_date'] . ' تکمیل شد و منتظر تأیید است'));
                } else {
                    $sql = "UPDATE tasks SET status = 'pending_approval', is_pending_approval = TRUE, assignee_id = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$previousPerson, $task_id]);
                }

                $this->addTaskHistory($task_id, $user_id, $previousPerson, 'pending_approval', $notes ?: 'کار تکمیل شد و منتظر تأیید است');

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
                        // درست بالاتر assignee_id را روی $previousPerson گذاشتیم؛ اگر او
                        // همان creator باشد (حالتِ رایج)، self-check نوتیفیکیشن را
                        // بلاک می‌کند. گیرنده قطعاً غیرِ اقدام‌کننده است، پس امن است.
                        'skip_self_check' => true,
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

            // 4. برای workflow (روتین مرحله‌ای): کاربر در بخش مرحله فعلی باشه،
            //    مرحله «فعال» باشه (نه pending)، و وضعیت مجاز
            if (!$hasAccess && $task['is_workflow_task'] == 1) {
                try {
                    // 🔒 ابتدا مطمئن شو مرحلهٔ این تسک واقعاً active است.
                    //    (جلوگیری از عمل روی مرحله‌ای که هنوز نوبتش نرسیده)
                    $stepStmt = $this->db->prepare("
                        SELECT wis.status AS step_status, ws.activity_section
                        FROM workflow_instance_steps wis
                        JOIN workflow_steps ws ON ws.id = wis.step_id
                        WHERE wis.task_id = ?
                        LIMIT 1
                    ");
                    $stepStmt->execute([$task_id]);
                    $stepRow = $stepStmt->fetch(PDO::FETCH_ASSOC);

                    $stepIsActive = $stepRow && $stepRow['step_status'] === 'active';
                    $stageSection = $stepRow ? $stepRow['activity_section'] : null;

                    if ($stepIsActive && $stageSection) {
                        // 🆕 عضویت در هر یک از واحدهای کاربر (چندواحدی)
                        if (us_userInSection($this->db, $user_id, $stageSection)) {
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
                }
            }

            // 5. برای کارهای روتین (periodic/continuous)، همه اعضای بخش مربوطه
            if (!$hasAccess && in_array($task['task_type'], ['periodic', 'continuous']) && $task['activity_section']) {
                // 🆕 عضویت در هر یک از واحدهای کاربر (چندواحدی)
                if (us_userInSection($this->db, $user_id, $task['activity_section'])) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess) {
                error_log("updateTaskStatus denied | user_id={$user_id} | task_id={$task_id} | requested_status={$status}");
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
                error_log("updateTaskStatus UPDATE failed | user_id={$user_id} | task_id={$task_id} | requested_status={$status}");
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

    /**
     * ⚠️ منسوخ (Deprecated)
     *
     * منطق تکمیل کارهای دوره‌ای به finalizeSelfCompletion منتقل شد،
     * که از موتور مشترک (period-engine) استفاده می‌کند.
     *
     * این تابع پیش از این با فرمول «روز تقویمی» کار می‌کرد و
     * جمعه‌ها و تعطیلات رسمی را هم «دوره» می‌شمرد — که منشأ
     * دوره‌های معوقهٔ جعلی بود.
     *
     * برای سازگاری با کدهای قدیمی، فقط به مسیر درست هدایت می‌کند.
     */
    private function handleContinuousTaskCompletion($task_id, $task, $user_id, $notes)
    {
        return $this->finalizeSelfCompletion($task, $task_id, $user_id, $notes);
    }

    // ارجاع کار - قانون 2
    public function delegateTask($task_id, $to_user_id, $from_user_id, $notes = '', $due_date = null)
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

            // 🆕 کارِ مقطعیِ بدونِ موعد (خودی) وقتی به کسِ دیگه‌ای ارجاع داده
            // می‌شه، باید همین حالا یک موعد براش تعیین بشه
            if ($task['task_type'] === 'periodic' && empty($task['due_date']) && empty($due_date)) {
                return ['success' => false, 'message' => 'این کار موعد ندارد — برایِ ارجاع، ابتدا یک موعد تعیین کنید'];
            }

            // بررسی مجوز
            if ($task['assignee_id'] != $from_user_id && $task['creator_id'] != $from_user_id) {
                error_log("delegateTask denied | from_user_id={$from_user_id} | task_id={$task_id}");
                return ['success' => false, 'message' => 'شما مجاز به ارجاع این کار نیستید'];
            }

            // بررسی وجود کاربر مقصد
            if (!$this->userExists($to_user_id)) {
                return ['success' => false, 'message' => 'کاربر مقصد یافت نشد'];
            }

            // 🔒 خط قرمز: کاربر مقصد باید از همان سازمانِ کار باشد، وگرنه یک
            // کار می‌تواند به کاربرِ سازمانِ کاملاً دیگری ارجاع داده شود
            $orgStmt = $this->db->prepare("SELECT organization_id FROM users WHERE id = ?");
            $orgStmt->execute([$to_user_id]);
            $toUserOrg = $orgStmt->fetchColumn();
            if ((int) $toUserOrg !== (int) $task['organization_id']) {
                return ['success' => false, 'message' => 'کاربر مقصد باید از همین سازمان باشد'];
            }

            // دریافت توضیحات قبلی
            $new_notes = $task['delegation_notes'] ?
                $task['delegation_notes'] . "\n---\n" . $notes : $notes;

            // بروزرسانی کار — status مستقیماً 'not_started' می‌شه (نه
            // 'delegated')؛ خودِ رخدادِ ارجاع چند خط پایین‌تر با addTaskHistory
            // (action='delegated') و همین‌جا با delegation_notes ثبت می‌مونه
            if (!empty($due_date)) {
                $sql = "UPDATE tasks SET assignee_id = ?, status = 'not_started', delegation_notes = ?, due_date = ?, updated_at = NOW() WHERE id = ?";
                $params = [$to_user_id, $new_notes, $due_date, $task_id];
            } else {
                $sql = "UPDATE tasks SET assignee_id = ?, status = 'not_started', delegation_notes = ?, updated_at = NOW() WHERE id = ?";
                $params = [$to_user_id, $new_notes, $task_id];
            }
            $stmt = $this->db->prepare($sql);

            if ($stmt->execute($params)) {
                $this->addTaskHistory($task_id, $from_user_id, $to_user_id, 'delegated', $notes);
                return ['success' => true, 'message' => 'کار با موفقیت ارجاع داده شد'];
            }

            error_log("delegateTask execute failed | from_user_id={$from_user_id} | task_id={$task_id} | to_user_id={$to_user_id}");
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
            error_log("canEditTask denied | user_id={$user_id} | task_id={$task_id}");
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
            $stmt = $this->db->prepare("SELECT creator_id, organization_id FROM tasks WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();

            if (!$task) {
                return ['success' => false, 'message' => 'کار پیدا نشد'];
            }

            // گرفتن نقش کاربر
            $stmt = $this->db->prepare("SELECT role, organization_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            $isCreator = ($task['creator_id'] == $user_id);
            // 🔒 خط قرمز: اختیار «مدیر» فقط داخل همان سازمانِ کار معتبر است
            $isManager = ($user
                && ($user['role'] == 'management' || $user['role'] == 'supervisor')
                && (int)$user['organization_id'] === (int)$task['organization_id']);

            if (!$isCreator && !$isManager) {
                error_log("deleteTask denied | user_id={$user_id} | task_id={$task_id}");
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
                    (SELECT COUNT(*) FROM task_history WHERE task_id = t.id AND action = 'completed') as completed_count,
                    (
                        SELECT CONCAT_WS(' ',
                            (
                                SELECT GROUP_CONCAT(
                                    CONCAT_WS(' ', fu.first_name, fu.last_name, tu.first_name, tu.last_name,
                                        CASE WHEN th2.notes LIKE '{%' THEN JSON_UNQUOTE(JSON_EXTRACT(th2.notes, '$.reason')) ELSE th2.notes END)
                                    SEPARATOR ' '
                                )
                                FROM task_history th2
                                LEFT JOIN users fu ON th2.from_user_id = fu.id
                                LEFT JOIN users tu ON th2.to_user_id = tu.id
                                WHERE th2.task_id = t.id
                            ),
                            (
                                SELECT GROUP_CONCAT(ta.file_original_name SEPARATOR ' ')
                                FROM task_attachments ta
                                WHERE ta.task_id = t.id
                            )
                        )
                    ) AS history_text
                FROM tasks t
                LEFT JOIN users creator ON t.creator_id = creator.id
                LEFT JOIN users assignee ON t.assignee_id = assignee.id
                INNER JOIN task_history th ON t.id = th.task_id
                WHERE (th.from_user_id = ? OR th.to_user_id = ?)
                AND th.action = 'delegated'
                AND t.is_deleted = 0
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
            // واحد و سازمانِ کاربر را برای شرط چک‌لیست بخوان
            $secStmt = $this->db->prepare("SELECT activity_section, organization_id FROM users WHERE id = ?");
            $secStmt->execute([$user_id]);
            $user_row = $secStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $user_section = $user_row['activity_section'] ?? '';
            $user_org_id = $user_row['organization_id'] ?? 0;

            // ✅ نمایش: سازنده، یا مسئول، یا ارجاع چک‌لیست (به کاربر یا واحدش)
            // نکته: چون activity_section بین سازمان‌های مختلف می‌تواند مقدار یکسان داشته باشد،
            // شرط چک‌لیستِ واحد باید حتماً به t.organization_id هم محدود شود.
            $checklist_exists = "EXISTS (
                SELECT 1 FROM task_checklist_items ci
                WHERE ci.task_id = t.id
                  AND (
                      (ci.assignee_type = 'user'    AND ci.assignee_value = ?)
                      OR (ci.assignee_type = 'section' AND ci.assignee_value = ? AND t.organization_id = ?)
                  )
            )";
            $where_conditions = ["(t.creator_id = ? OR t.assignee_id = ? OR $checklist_exists)"];
            // 🔒 ترتیبِ این آرایه باید دقیقاً با ترتیبِ ظاهرشدنِ «?»ها در متنِ
            // نهاییِ SQL یکی باشه — نه ترتیبِ اضافه‌شدنشون این‌جا در PHP.
            // چون CASE WHEN پایین‌تر (در SELECT) قبل از WHERE در متنِ SQL
            // میاد، دو تا $user_idِ اولش مالِ همون CASE ان، نه اینجا؛ قبلاً
            // این دو تا آخرِ آرایه اضافه می‌شدن (بعد از این ۵تا) و کلِ
            // بایندینگ از همین‌جا به بعد یکی جابه‌جا می‌شد — یعنی
            // t.assignee_id در WHERE عملاً با user_section مقایسه می‌شد،
            // نه با user_id، و کارهایی که فقط assignee بودی (نه creator)
            // اصلاً تویِ نتیجه نمی‌اومدن
            $params = [$user_id, $user_id, $user_id, $user_id, (string)$user_id, $user_section, $user_org_id];

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
                error_log("approveOrRejectTask denied (not assignee) | user_id={$user_id} | task_id={$task_id}");
                return ['success' => false, 'message' => 'شما مجاز به تأیید این کار نیستید'];
            }

            if (!$task['is_pending_approval']) {
                error_log("approveOrRejectTask denied (not pending approval) | user_id={$user_id} | task_id={$task_id}");
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

                    // ⚠️ اینجا نباید دوباره action='completed' ثبت شود. رکورد completedِ
                    // واقعی، در لحظهٔ ارسال برای تأیید (بالاتر در updateTaskStatus) و با
                    // تاریخ واقعیِ انجام کار ثبت می‌شود. تأیید ممکن است روز(های) بعد اتفاق
                    // بیفتد؛ اگر اینجا هم completed بزنیم، موتور دوره آن را با تاریخ تأیید
                    // می‌بیند و اشتباهاً دورهٔ همان روز را هم «انجام‌شده» حساب می‌کند.
                    $this->addTaskHistory($task_id, $user_id, $performerId, 'approved', 'آخرین دوره تأیید شد: ' . $notes);
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
    /**
     * پیدا کردن «آخرین انجام‌دهندهٔ واقعی» کار.
     *
     * ⚠️ چرا از 'completed' استفاده نمی‌کنیم؟
     *    رکوردهای 'completed' دو منشأ دارند:
     *      ۱) انجام‌دهنده کار را تمام کرد
     *      ۲) تأییدکننده، تأیید نهایی زد  ← from_user_id = تأییدکننده!
     *    تشخیص این دو از هم ممکن نیست. اگر به 'completed' تکیه کنیم،
     *    ممکن است تأییدکننده را «انجام‌دهنده» بپنداریم و کار را به
     *    خودش برگردانیم — که باعث می‌شود کار در لیستش گیر کند.
     *
     * ✅ رکورد 'pending_approval' همیشه توسط انجام‌دهندهٔ واقعی ثبت
     *    می‌شود (او کار را تمام کرد و برای تأیید فرستاد). پس مرجع
     *    مطمئن‌تری است.
     */
    private function getLastPerformer($task_id)
    {
        try {
            // ۱) آخرین کسی که کار را برای تأیید فرستاده = انجام‌دهندهٔ واقعی
            $sql = "SELECT from_user_id FROM task_history
                    WHERE task_id = ?
                      AND action = 'pending_approval'
                      AND from_user_id IS NOT NULL
                    ORDER BY id DESC
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task_id]);
            $result = $stmt->fetch();

            if ($result && $result['from_user_id']) {
                return (int) $result['from_user_id'];
            }

            // ۲) اگر هرگز برای تأیید نرفته (تکمیل مستقیم) → آخرین نفر زنجیرهٔ ارجاع
            $chain = $this->getDelegationChain($task_id);
            if (!empty($chain)) {
                $lastInChain = end($chain);
                return (int) $lastInChain['to_user_id'];
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
            // 3) کارهای معوقه — بخش اول: کارهای مقطعی
            // -----------------------------------------------
            $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE (assignee_id = ? OR creator_id = ?)
              AND is_deleted = 0
              AND status NOT IN ('completed', 'approved', 'stopped')
              AND task_type = 'periodic'
              AND due_date < CURDATE()
        ");
            $stmt->execute([$user_id, $user_id]);
            $stats['overdue'] = (int) $stmt->fetch()['count'];

            // ✅ بخش دوم: کارهای دوره‌ای — از موتور مشترک
            // (پیش از این از جدول continuous_tasks_overdue خوانده می‌شد
            //  که کاملاً خالی بود → معوقهٔ کارهای دوره‌ای همیشه صفر شمرده می‌شد)
            $stmt = $this->db->prepare("
                SELECT * FROM tasks
                WHERE (assignee_id = ? OR creator_id = ?)
                  AND is_deleted = 0
                  AND task_type = 'continuous'
                  AND status NOT IN ('completed', 'approved', 'stopped')
            ");
            $stmt->execute([$user_id, $user_id]);

            $holidays = getHolidaySet($this->db);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                if (pe_state($this->db, $t, $holidays)['overdue_periods'] > 0) {
                    $stats['overdue']++;
                }
            }

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
        // ✅ قدم 1: خواندن تاریخ‌های قبلی از دیتابیس، قبل از اینکه پاک شوند!
        $task = $this->getTask($task_id);
        $old_start_date = $task['start_date'] ?? 'نامشخص';
        $old_end_date = $task['end_date'] ?? 'نامشخص';

        // ✅ قدم 2: حالا که تاریخ‌های قبلی را در متغیرها داریم، دیتابیس را آپدیت می‌کنیم
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

        // ✅ قدم 3: ثبت در تاریخچه، همراه با تاریخ قبلی و جدید
        $endLabel = $new_end_date ? $new_end_date : 'نامحدود';
        $oldEndLabel = $old_end_date ? $old_end_date : 'نامحدود';

        $history_message = "تمدید دوره اعمال شد. \n تاریخ قبلی: از {$old_start_date} تا {$oldEndLabel} \n تاریخ جدید: از {$new_start_date} تا {$endLabel}. شمارش دوره‌های قبلی بازنشانی شد.";

        $this->addTaskHistory(
            $task_id,
            $performer_id,
            null,
            'renewal_applied',
            $history_message
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
                if ($full_chain[$i] === (int)$user_id) {
                    $current_index = $i;
                    break;
                }
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
            $this->addTaskHistory(
                $task_id,
                $user_id,
                $next_approver,
                'renewal_requested',
                "درخواست تمدید دوره: شروع {$new_start_date}، پایان {$endLabel}. دلیل: " . $reason
            );

            $this->notifyRenewal(
                $next_approver,
                $task['title'],
                $task_id,
                'درخواست تمدید دوره: ' . $task['title'],
                'درخواست تمدید دورهٔ کار «' . $task['title'] . '» منتظر بررسی شماست.'
            );

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

                $this->notifyRenewal(
                    $req['requested_by'],
                    $task['title'],
                    $req['task_id'],
                    'تمدید دوره تأیید شد: ' . $task['title'],
                    'درخواست تمدید دورهٔ کار «' . $task['title'] . '» تأیید و اعمال شد.'
                );

                return ['success' => true, 'message' => 'تمدید دوره تأیید و اعمال شد', 'final_approval' => true];
            }

            // یافتن نفر بعدی در زنجیره (به سمت تعریف‌کننده)
            $full_chain = $this->buildFullChain($req['task_id'], $creator_id);
            $current_index = null;
            for ($i = count($full_chain) - 1; $i >= 0; $i--) {
                if ($full_chain[$i] === (int)$user_id) {
                    $current_index = $i;
                    break;
                }
            }
            if ($current_index === null) {
                return ['success' => false, 'message' => 'کاربر در زنجیره این کار یافت نشد'];
            }
            $next_approver = ($current_index > 0) ? $full_chain[$current_index - 1] : $creator_id;

            $this->db->prepare("UPDATE task_renewal_requests SET current_approver_id = ? WHERE id = ?")
                ->execute([$next_approver, $request_id]);

            $this->addTaskHistory(
                $req['task_id'],
                $user_id,
                $next_approver,
                'renewal_step_approved',
                'تأیید شد و ارسال برای تأیید نفر بالاتر: ' . $notes
            );

            $this->notifyRenewal(
                $next_approver,
                $task['title'],
                $req['task_id'],
                'درخواست تمدید دوره: ' . $task['title'],
                'درخواست تمدید دورهٔ کار «' . $task['title'] . '» منتظر بررسی شماست.'
            );

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

            $this->addTaskHistory(
                $req['task_id'],
                $user_id,
                $req['requested_by'],
                'renewal_rejected',
                'درخواست تمدید موعد رد شد. دلیل: ' . $rejection_reason
            );

            $this->notifyRenewal(
                $req['requested_by'],
                $task['title'] ?? '',
                $req['task_id'],
                'درخواست تمدید دوره رد شد: ' . ($task['title'] ?? ''),
                'درخواست تمدید دورهٔ کار «' . ($task['title'] ?? '') . '» رد شد. دلیل: ' . $rejection_reason
            );

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
                $this->notifyRenewal(
                    $task['assignee_id'],
                    $task['title'],
                    $task_id,
                    'تمدید دوره: ' . $task['title'],
                    'دورهٔ کار «' . $task['title'] . '» توسط تعریف‌کننده تمدید شد.'
                );
            }

            return ['success' => true, 'message' => 'دوره با موفقیت تمدید شد'];
        } catch (Exception $e) {
            error_log("applyPeriodRenewalDirect error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }
}
