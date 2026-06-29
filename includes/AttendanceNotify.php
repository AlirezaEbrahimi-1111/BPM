<?php
// /includes/AttendanceNotify.php — نوتیفیکیشن + پیامکِ پترن‌محورِ تأیید دستگاه‌های حضور و غیاب
// پیامک از طریق Notification::create و کلیدهای sms_pattern/sms_args ارسال می‌شود (مثل بقیهٔ سیستم)

if (!function_exists('attendance_notify_managers_new_device')) {

    /**
     * اطلاع به مدیران/سرپرست‌ها هنگام ثبت یک دستگاهِ جدیدِ «در انتظار تأیید»
     */
    function attendance_notify_managers_new_device($db, $org, $requester_id, $ip)
    {
        require_once __DIR__ . '/Notification.php';
        try {
            $n = new Notification($db);

            // نام درخواست‌دهنده
            $rs = $db->prepare("SELECT CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) FROM users WHERE id = ?");
            $rs->execute([$requester_id]);
            $rname = trim($rs->fetchColumn() ?: '') ?: 'کاربر';

            // مدیران همان سازمان
            $st = $db->prepare("
                SELECT id FROM users
                WHERE organization_id = ? AND is_active = 1
                  AND (role IN ('supervisor','management') OR id = 1)
            ");
            $st->execute([$org]);
            $managers = $st->fetchAll(PDO::FETCH_COLUMN);

            // ملی‌پیامک مقادیرِ شبیه لینک/آدرس (IP با نقطه) را در الگو رد می‌کند → نقطه‌ها را به خط تبدیل می‌کنیم
            $ip_safe = str_replace('.', '-', $ip);

            foreach ($managers as $mid) {
                try {
                    $n->create([
                        'to_user_id'   => $mid,
                        'title'        => 'دستگاه جدید در انتظار تأیید',
                        'message'      => "یک دستگاه جدید برای ثبت ورود/خروج از {$ip} توسط {$rname} در انتظار تأیید است.",
                        'type'         => 'warning',
                        'link'         => '../pages/attendance-devices.php',
                        'related_type' => 'attendance_device',
                        'related_id'   => 0,
                        'is_read'      => 0,
                        // پیامک پترن‌محور
                        'sms_pattern'  => 'device_request',
                        'sms_args'     => [$ip_safe, $rname],
                    ]);
                } catch (Exception $e) { /* بی‌صدا */ }
            }
        } catch (Exception $e) {
            error_log('attendance_notify_managers_new_device: ' . $e->getMessage());
        }
    }

    /**
     * اطلاع به درخواست‌دهنده پس از تأیید/رد دستگاه
     */
    function attendance_notify_requester_review($db, $org, $device_id, $approved)
    {
        require_once __DIR__ . '/Notification.php';
        try {
            $st = $db->prepare("SELECT first_seen_user_id, first_seen_ip FROM attendance_devices WHERE id = ? AND organization_id = ?");
            $st->execute([$device_id, $org]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row || empty($row['first_seen_user_id'])) return; // درخواست‌دهنده در سیستم نیست

            $uid = $row['first_seen_user_id'];
            $ip  = $row['first_seen_ip'] ?: '';
            $ip_safe = str_replace('.', '-', $ip);   // نقطه‌ها برای ملی‌پیامک

            $n = new Notification($db);

            if ($approved) {
                $n->create([
                    'to_user_id'   => $uid,
                    'title'        => 'دستگاه شما تأیید شد',
                    'message'      => "دستگاه {$ip} تأیید شد و اکنون می‌توانید ورود/خروج ثبت کنید.",
                    'type'         => 'success',
                    'link'         => '#',
                    'related_type' => 'attendance_device',
                    'related_id'   => $device_id,
                    'is_read'      => 0,
                    'sms_pattern'  => 'device_approved',
                    'sms_args'     => [$ip_safe],
                ]);
            } else {
                $n->create([
                    'to_user_id'   => $uid,
                    'title'        => 'دستگاه شما رد شد',
                    'message'      => "درخواست ثبت دستگاه {$ip} رد شد. با مدیریت تماس بگیرید.",
                    'type'         => 'danger',
                    'link'         => '#',
                    'related_type' => 'attendance_device',
                    'related_id'   => $device_id,
                    'is_read'      => 0,
                    'sms_pattern'  => 'device_rejected',
                    'sms_args'     => [$ip_safe],
                ]);
            }
        } catch (Exception $e) {
            error_log('attendance_notify_requester_review: ' . $e->getMessage());
        }
    }
}