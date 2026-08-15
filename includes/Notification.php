<?php
// ==================================================
// includes/Notification.php - نسخه بهبود یافته با فیلتر کامل
// ==================================================
class Notification
{
    private $db;

    public function __construct($database)
    {
        $this->db = $database;
    }

    /**
     * ایجاد اعلان جدید
     */
    public function create($data)
    {
        try {
            // ✅ DEBUG: لاگ کامل
            error_log("===========================================");
            error_log("📢 NOTIFICATION CREATE ATTEMPT");
            error_log("To User: " . ($data['to_user_id'] ?? 'NULL'));
            error_log("Title: " . ($data['title'] ?? 'NULL'));
            error_log("Message: " . ($data['message'] ?? 'NULL'));
            error_log("Related Type: " . ($data['related_type'] ?? 'NULL'));
            error_log("Related ID: " . ($data['related_id'] ?? 'NULL'));

            // ✅ چک کردن task — این چک روی وضعیتِ *زنده‌یِ فعلیِ* تسک انجام
            // می‌شه، نه وضعیتِ قبل از این اکشن. برایِ نوتیف‌هایی که caller
            // درست قبل از این فراخوانی خودش assignee_id رو به to_user_id
            // آپدیت کرده (مثلِ ارجاع)، این شرط همیشه true می‌شه چون همین
            // الان همون مقدار رو ست کردیم — نه چون واقعاً creator داره به
            // خودش نوتیف می‌فرسته. برایِ همین caller هایی مثلِ ارجاع، صریحاً
            // با skip_self_check این چک رو دور می‌زنن
            if (
                empty($data['skip_self_check']) &&
                !empty($data['related_id']) &&
                ($data['related_type'] ?? null) === 'task'
            ) {
                $stmt = $this->db->prepare("
                    SELECT creator_id, assignee_id
                    FROM tasks
                    WHERE id = ?
                ");
                $stmt->execute([$data['related_id']]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);

                error_log("Task Info - Creator: " . ($task['creator_id'] ?? 'NULL') .
                    ", Assignee: " . ($task['assignee_id'] ?? 'NULL'));

                // ✅ اگر creator و assignee هر دو گیرنده باشند
                if (
                    $task &&
                    $task['creator_id'] == $data['to_user_id'] &&
                    $task['assignee_id'] == $data['to_user_id']
                ) {
                    error_log("🚫 BLOCKED: Self-notification!");
                    error_log("===========================================");
                    return true;
                }
            }

            error_log("✅ ALLOWED: Creating notification");
            error_log("===========================================");

            $sql = "INSERT INTO notifications
                (user_id, title, message, type, link, related_type, related_id, bypass_self_filter)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['to_user_id'],
                $data['title'],
                $data['message'],
                $data['type'] ?? 'info',
                $data['link'] ?? null,
                $data['related_type'] ?? null,
                $data['related_id'] ?? null,
                !empty($data['skip_self_check']) ? 1 : 0
            ]);

            // 🆕 اگر نوتیفیکیشن ساخته شد، پیامک هم بفرست
            if ($result) {
                $notification_id = $this->db->lastInsertId();
                error_log("📧 Notification created with ID: $notification_id - Sending SMS...");
                
                // ارسال پیامک (به صورت async تا سرعت بالا باشه)
                $this->sendSMSAsync($data, $notification_id);
            }

            return $result;

        } catch (Exception $e) {
            error_log("Notification create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 🆕 ارسال پیامک برای نوتیفیکیشن
     */
    private function sendSMS($notification_data, $notification_id)
    {
        try {
            // بارگذاری کلاس SMS
            require_once __DIR__ . '/sms.php';
            
            $sms = new SMS($this->db);
            
            // 🔹 تعیین نام الگو بر اساس نوع نوتیفیکیشن
            $template_name = 'general'; // پیش‌فرض
            
            if (!empty($notification_data['related_type'])) {
                switch ($notification_data['related_type']) {
                    case 'task':
                        $template_name = 'task_notification';
                        break;
                    case 'workflow':
                        $template_name = 'workflow_notification';
                        break;
                    case 'report':
                        $template_name = 'report_notification';
                        break;
                }
            }
            
            // 🔹 دریافت نام کاربر برای استفاده در پیامک
            $stmt = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
            $stmt->execute([$notification_data['to_user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user_name = $user['name'] ?? 'کاربر';
            
            // 🔹 آماده‌سازی متغیرها
            $variables = [
                'user_name' => $user_name,
                'title' => $notification_data['title'],
                'message' => $notification_data['message']
            ];
            
            // 🔹 ارسال پیامک (به صورت async - نتیجه را چک نمی‌کنیم تا سرعت بالا باشه)
            $sms_result = $sms->sendFromTemplate(
                $notification_data['to_user_id'],
                $template_name,
                $variables,
                $notification_id
            );
            
            if ($sms_result) {
                error_log("✅ SMS sent successfully for notification #$notification_id");
            } else {
                error_log("⚠️ SMS failed for notification #$notification_id (logged in sms_logs)");
            }
            
        } catch (Exception $e) {
            // فقط لاگ می‌کنیم، خطا را نمی‌پرانیم تا نوتیفیکیشن ایجاد بشه
            error_log("❌ SMS send error: " . $e->getMessage());
        }
    }
    /**
     * ارسال پیامک غیرهمزمان — پروسه پس‌زمینه
     */
    private function sendSMSAsync($notification_data, $notification_id)
    {
        try {
            $worker_path = $_SERVER['DOCUMENT_ROOT'] . '/includes/sms-worker.php';
            
         // بررسی: آیا exec فعاله؟
         $disabled = explode(',', ini_get('disable_functions'));
         $disabled = array_map('trim', $disabled);
         $exec_available = !in_array('exec', $disabled) && file_exists($worker_path);
    
         if ($exec_available) {
             // ✅ روش async — پروسه پس‌زمینه
             $payload = json_encode([
                'to_user_id'    => $notification_data['to_user_id'],
                'title'         => $notification_data['title'] ?? '',
                'message'       => $notification_data['message'] ?? '',
                'related_type'  => $notification_data['related_type'] ?? '',
                'related_id'    => $notification_data['related_id'] ?? '',
                'sms_pattern'   => $notification_data['sms_pattern'] ?? 'general',  // 🆕
                'sms_args'      => $notification_data['sms_args'] ?? null,          // 🆕
                'notification_id' => $notification_id
            ], JSON_UNESCAPED_UNICODE);
    
            // encode base64 برای جلوگیری از مشکل کاراکترهای فارسی در CLI
            $encoded = base64_encode($payload);
    
            $php_bin = PHP_BINARY ?: '/usr/bin/php';
            $cmd = sprintf(
                '%s %s %s > /dev/null 2>&1 &',
                escapeshellarg($php_bin),
                escapeshellarg($worker_path),
                escapeshellarg($encoded)
            );
    
            exec($cmd);
            error_log("📤 SMS async dispatched for notification #$notification_id");
             } else {
             // ⚡ Fallback: ارسال sync با timeout کوتاه
             error_log("⚠️ exec not available, falling back to sync SMS with short timeout");
             $this->sendSMSSync($notification_data, $notification_id);
         }
        } catch (Exception $e) {
            error_log("❌ sendSMSAsync error: " . $e->getMessage());
        }
    }
    
    private function sendSMSSync($notification_data, $notification_id)
        {
            try {
                require_once __DIR__ . '/sms.php';
                require_once __DIR__ . '/sms_patterns.php';
                $sms = new SMS($this->db);
        
                $pattern_key = $notification_data['sms_pattern'] ?? 'general';
                $args = $notification_data['sms_args'] ?? [
                    $notification_data['title'] ?? '',
                    $notification_data['message'] ?? ''
                ];
                $sms->sendPattern($notification_data['to_user_id'], resolveBodyId($pattern_key), (array)$args, $notification_id);
            } catch (Exception $e) {
                error_log("❌ sendSMSSync error: " . $e->getMessage());
            }
        }

/**
 * تشخیص الگو و متغیرهای پیامک بر اساس داده نوتیفیکیشن
 */
private function resolveSMSTemplate($data): array
{
    $type    = $data['related_type'] ?? '';
    $title   = $data['title'] ?? '';
    $message = $data['message'] ?? '';
    $extra   = $data['extra'] ?? [];   // داده‌های اضافی اختیاری

    // متغیرهای پرکاربرد
    $base = [
        'task_title'   => $extra['task_title']   ?? $title,
        'sender_name'  => $extra['sender_name']  ?? '',
        'title'        => $title,
        'message'      => $message,
    ];

    // --- تسک‌ها ---
    if ($type === 'task') {
        if (str_contains($title, 'ایجاد') || str_contains($title, 'جدید')) {
            return ['task_created', $base];
        }
        if (str_contains($title, 'ارجاع')) {
            return ['task_assigned', $base];
        }
        if (str_contains($title, 'تأیید') && str_contains($title, 'نیاز')) {
            return ['task_needs_approval', $base];
        }
        if (str_contains($title, 'رد شد') || str_contains($title, 'رد کرد')) {
            return ['task_rejected', $base];
        }
        if (str_contains($title, 'تأیید') && str_contains($title, 'ارجاع')) {
            return ['task_approved', $base];
        }
        if (str_contains($title, 'یادآوری')) {
            return ['task_reminder', $base];
        }
    }

    // --- تمدید موعد ---
    if ($type === 'deadline') {
        $vars = array_merge($base, ['date' => $extra['date'] ?? '']);
        if (str_contains($title, 'درخواست تمدید')) {
            return ['deadline_request', $vars];
        }
        if (str_contains($title, 'تأیید') && str_contains($title, 'بررسی')) {
            return ['deadline_mid_approved', $vars];
        }
        if (str_contains($title, 'تأیید نهایی') || str_contains($title, 'موعد جدید')) {
            return ['deadline_approved', $vars];
        }
        if (str_contains($title, 'رد')) {
            return ['deadline_rejected', $vars];
        }
    }

    // --- اتمام کار ---
    if ($type === 'completion') {
        if (str_contains($title, 'درخواست اتمام')) {
            return ['completion_request', $base];
        }
        if (str_contains($title, 'تأیید')) {
            return ['completion_approved', $base];
        }
        if (str_contains($title, 'رد')) {
            return ['completion_rejected', array_merge($base, ['reason' => $extra['reason'] ?? ''])];
        }
        if (str_contains($title, 'پایان رسید')) {
            return ['completion_by_manager', $base];
        }
    }

    // --- روتین ---
    if ($type === 'routine') {
        if (str_contains($title, 'مرحله')) {
            return ['routine_new_stage', array_merge($base, ['stage' => $extra['stage'] ?? ''])];
        }
        if (str_contains($title, 'تکمیل')) {
            return ['routine_completed', $base];
        }
        if (str_contains($title, 'تأخیر') || str_contains($title, 'عقب')) {
            return ['routine_delayed', $base];
        }
    }

    // --- تیکت ---
    if ($type === 'ticket') {
        if (str_contains($title, 'پاسخ')) {
            return ['ticket_replied', $base];
        }
        if (str_contains($title, 'وضعیت')) {
            return ['ticket_status_changed', array_merge($base, ['status' => $extra['status'] ?? ''])];
        }
        return ['ticket_created', $base];
    }

    // --- اتوماسیون ---
    if ($type === 'automation') {
        $vars = array_merge($base, [
            'request_type' => $extra['request_type'] ?? '',
            'role'         => $extra['role']         ?? '',
            'prev_role'    => $extra['prev_role']     ?? '',
            'your_role'    => $extra['your_role']     ?? '',
        ]);
        if (str_contains($title, 'مرخصی') && str_contains($title, 'جانشین')) {
            return ['leave_to_deputy', $vars];
        }
        if (str_contains($title, 'مأموریت') || str_contains($title, 'فراموشی')) {
            return ['mission_to_manager', $vars];
        }
        if (str_contains($title, 'مشکل فنی')) {
            return ['tech_issue_request', $vars];
        }
        if (str_contains($title, 'تأیید نهایی')) {
            return ['auto_final_approved', $vars];
        }
        if (str_contains($title, 'تأیید') && str_contains($title, 'منتظر')) {
            return ['auto_mid_approved_next', $vars];
        }
        if (str_contains($title, 'تأیید')) {
            return ['auto_mid_approved_req', $vars];
        }
        if (str_contains($title, 'رد')) {
            return ['auto_rejected', $vars];
        }
    }

    // Fallback عمومی
    return ['general', $base];
}
    
    /**
     * 🆕 چک کردن اینکه آیا نوتیفیکیشن باید نشون داده بشه
     */
    private function shouldShowNotification($notification, $user_id)
    {
        // نوتیف‌هایی که caller صراحتاً از چکِ self-notification معاف کرده
        // (مثلِ ارجاعِ تسک به خودِ creator) — همیشه نشون داده بشن
        if (!empty($notification['bypass_self_filter'])) {
            return true;
        }

        // اگر نوتیفیکیشن مربوط به task است
        if (!empty($notification['related_id']) && $notification['related_type'] === 'task') {
            try {
                $stmt = $this->db->prepare("
                    SELECT creator_id, assignee_id 
                    FROM tasks 
                    WHERE id = ?
                ");
                $stmt->execute([$notification['related_id']]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($task) {
                    // ✅ اگر هم creator و هم assignee خودم باشم، نشون نده
                    if ($task['creator_id'] == $user_id && $task['assignee_id'] == $user_id) {
                        return false;
                    }
                }
            } catch (Exception $e) {
                error_log("Error checking task ownership: " . $e->getMessage());
                // در صورت خطا، نوتیفیکیشن رو نشون بده
                return true;
            }
        }
        
        return true;
    }

    /**
     * 🆕 فیلتر کردن نوتیفیکیشن‌ها
     */
    private function filterNotifications($notifications, $user_id)
    {
        $filtered = [];
        
        foreach ($notifications as $notification) {
            if ($this->shouldShowNotification($notification, $user_id)) {
                $filtered[] = $notification;
            }
        }
        
        return $filtered;
    }

    /**
     * دریافت اعلان‌های کاربر (با فیلتر)
     */
    public function getUserNotifications($user_id, $limit = 20, $unread_only = false)
    {
        try {
            $where = "user_id = ?";
            $params = [$user_id];

            if ($unread_only) {
                $where .= " AND is_read = 0";
            }

            // 🔹 می‌گیریم بیشتر از limit تا بعد از فیلتر کردن کافی باشه
            $fetch_limit = $limit * 3;

            $sql = "SELECT * FROM notifications 
                    WHERE $where 
                    ORDER BY created_at DESC 
                    LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $params[] = $fetch_limit;
            $stmt->execute($params);

            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ فیلتر کردن
            $filtered = $this->filterNotifications($notifications, $user_id);
            
            // برش به limit اصلی
            return array_slice($filtered, 0, $limit);
            
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تعداد اعلان‌های خوانده نشده (با فیلتر)
     */
    public function getUnreadCount($user_id)
    {
        try {
            // 🔹 می‌گیریم همه unread ها رو
            $stmt = $this->db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ فیلتر کردن
            $filtered = $this->filterNotifications($notifications, $user_id);
            
            return count($filtered);
            
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * علامت‌گذاری یک اعلان به عنوان خوانده شده
     */
    public function markAsRead($notification_id, $user_id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (Exception $e) {
            error_log("Notification::markAsRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
     */
    public function markAllAsRead($user_id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
            return $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Notification::markAllAsRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * حذف اعلان
     */
    public function delete($notification_id, $user_id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (Exception $e) {
            error_log("Notification::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت اعلان‌های جدید (برای real-time) با فیلتر
     */
    public function getNewNotifications($user_id, $since_id = 0)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY created_at DESC");
            $stmt->execute([$user_id, $since_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ فیلتر کردن
            return $this->filterNotifications($notifications, $user_id);
            
        } catch (Exception $e) {
            error_log("Get new notifications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 🆕 پاک کردن نوتیفیکیشن‌های نامعتبر (برای cleanup)
     * نوتیفیکیشن‌هایی که creator == assignee هستند
     */
    public function cleanupInvalidNotifications($user_id)
    {
        try {
            // پیدا کردن نوتیفیکیشن‌های مرتبط با task
            $stmt = $this->db->prepare("
                SELECT n.id, n.related_id 
                FROM notifications n
                WHERE n.user_id = ? 
                  AND n.related_type = 'task'
                  AND n.related_id IS NOT NULL
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $deleted_count = 0;
            
            foreach ($notifications as $notif) {
                // چک کردن task
                $stmt = $this->db->prepare("
                    SELECT creator_id, assignee_id 
                    FROM tasks 
                    WHERE id = ?
                ");
                $stmt->execute([$notif['related_id']]);
                $task = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // اگر creator == assignee == user_id باشه، حذف کن
                if ($task && $task['creator_id'] == $user_id && $task['assignee_id'] == $user_id) {
                    $deleteStmt = $this->db->prepare("DELETE FROM notifications WHERE id = ?");
                    if ($deleteStmt->execute([$notif['id']])) {
                        $deleted_count++;
                    }
                }
            }
            
            error_log("🧹 Cleaned up $deleted_count invalid notifications for user $user_id");
            
            return $deleted_count;
            
        } catch (Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
?>