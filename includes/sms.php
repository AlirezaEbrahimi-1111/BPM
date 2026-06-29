<?php
// ==================================================
// includes/SMS.php
// ==================================================
class SMS
{
    private $db;
    private $api_url = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS'; // آدرس API ملی‌پیامک
    private $username; // نام کاربری ملی‌پیامک
    private $password; // رمز عبور
    private $from_number; // شماره پنل

    public function __construct($database)
    {
        $this->db = $database;
        
        // ⚙️ تنظیمات API (این‌ها رو از پنل ملی‌پیامک بگیر)
        $this->username = '09153388329'; // 🔴 اینجا رو تغییر بده
        $this->password = '9e6df135-a97c-4609-a5e1-4be1885e6e84'; // 🔴 اینجا رو تغییر بده
        $this->from_number = '50004001388329'; // 🔴 مثلاً: +9821700000032030
    }

    /**
     * ارسال پیامک با الگو
     * 
     * @param int $user_id آی‌دی کاربر
     * @param string $template_name نام الگو
     * @param array $variables متغیرهای جایگزین (مثلاً: ['user_name' => 'علی', 'title' => 'تسک جدید'])
     * @param int|null $notification_id آی‌دی نوتیفیکیشن (اختیاری)
     * @return bool موفقیت یا عدم موفقیت
     */
    public function sendFromTemplate($user_id, $template_name, $variables = [], $notification_id = null)
    {
        try {
            // ✅ دریافت شماره موبایل کاربر
            $stmt = $this->db->prepare("SELECT phone FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || empty($user['phone'])) {
                $this->logSMS($user_id, '', '', $template_name, $notification_id, 'failed', null, 'شماره موبایل یافت نشد');
                return false;
            }

            $phone = $this->normalizePhone($user['phone']);

            // ✅ دریافت الگوی پیامک
            $stmt = $this->db->prepare("SELECT message FROM sms_templates WHERE name = ? AND is_active = 1");
            $stmt->execute([$template_name]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                $this->logSMS($user_id, $phone, '', $template_name, $notification_id, 'failed', null, 'الگو یافت نشد یا غیرفعال است');
                return false;
            }

            // ✅ جایگزینی متغیرها در متن پیامک
            $message = $template['message'];
            foreach ($variables as $key => $value) {
                $message = str_replace('{' . $key . '}', $value, $message);
            }

            // ✅ ارسال پیامک
            return $this->send($user_id, $phone, $message, $template_name, $notification_id);

        } catch (Exception $e) {
            error_log("SMS send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ارسال مستقیم پیامک (بدون الگو)
     */
    public function send($user_id, $phone, $message, $template_name = null, $notification_id = null)
    {
        try {
            $phone = $this->normalizePhone($phone);

            // ✅ ارسال درخواست به API ملی‌پیامک
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'to' => $phone,
                'from' => $this->from_number,
                'text' => $message . "\n" . 'لغو11', 
                'isflash' => false
            ];

            $ch = curl_init($this->api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // ✅ بررسی نتیجه
            $response_data = json_decode($response, true);
            
            if ($http_code == 200 && isset($response_data['Value']) && $response_data['Value'] > 0) {
                // ✅ موفق
                $this->logSMS($user_id, $phone, $message, $template_name, $notification_id, 'sent', $response);
                return true;
            } else {
                // ❌ ناموفق
                $error = $response_data['RetStatus'] ?? 'خطای نامشخص';
                $this->logSMS($user_id, $phone, $message, $template_name, $notification_id, 'failed', $response, $error);
                return false;
            }

        } catch (Exception $e) {
            $this->logSMS($user_id, $phone, $message, $template_name, $notification_id, 'failed', null, $e->getMessage());
            return false;
        }
    }

public function sendPattern($user_id, $bodyId, array $args, $notification_id = null)
{
    try {
        // گرفتن موبایل کاربر
        $stmt = $this->db->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $phone = $stmt->fetchColumn();
 
        if (empty($phone)) {
            $this->logSMS($user_id, '', '', 'pattern:' . $bodyId, $notification_id, 'failed', null, 'شماره موبایل یافت نشد');
            return false;
        }
        $phone = $this->normalizePhone($phone);
 
        // مقادیر را با ; به هم می‌چسبانیم (قرارداد ملی پیامک)
        $text = implode(';', array_map(fn($v) => trim((string)$v), $args));
 
        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'to'       => $phone,
            'bodyId'   => (int) $bodyId, // کد تأییدشده
            'text'     => $text,         // فقط مقادیر متغیرها
        ];
 
        // ⬅️ آدرس الگوی تأییدشده (نه SendSMS)
        $ch = curl_init('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
 
        $j = json_decode($response, true);
        // در روش الگو، Value یک recId بزرگ (>100) است؛ کدهای کوچک = خطا
        $ok = ($http_code == 200 && isset($j['Value']) && (int)$j['Value'] > 100);
 
        $this->logSMS(
            $user_id, $phone, $text, 'pattern:' . $bodyId, $notification_id,
            $ok ? 'sent' : 'failed', $response,
            $ok ? null : ('کد بازگشتی: ' . ($j['Value'] ?? 'نامشخص'))
        );
        return $ok;
 
    } catch (Exception $e) {
        $this->logSMS($user_id, $phone ?? '', '', 'pattern:' . $bodyId, $notification_id, 'failed', null, $e->getMessage());
        return false;
    }
}
    /**
     * ثبت لاگ ارسال
     */
    private function logSMS($user_id, $phone, $message, $template_name, $notification_id, $status, $api_response = null, $error_message = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sms_logs 
                (user_id, phone, message, template_name, notification_id, status, api_response, error_message, sent_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $sent_at = ($status == 'sent') ? date('Y-m-d H:i:s') : null;
            
            $stmt->execute([
                $user_id,
                $phone,
                $message,
                $template_name,
                $notification_id,
                $status,
                $api_response,
                $error_message,
                $sent_at
            ]);
        } catch (Exception $e) {
            error_log("SMS log error: " . $e->getMessage());
        }
    }

    /**
     * نرمال‌سازی شماره موبایل
     */
    private function normalizePhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // تبدیل 0912 به 912
        if (substr($phone, 0, 1) == '0') {
            $phone = substr($phone, 1);
        }
        
        // اضافه کردن کد کشور
        if (substr($phone, 0, 2) != '98') {
            $phone = '98' . $phone;
        }
        
        return $phone;
    }

    /**
     * دریافت گزارش پیامک‌ها
     */
    public function getLogs($filters = [], $limit = 50, $offset = 0)
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['user_id'])) {
                $where[] = "sms_logs.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['status'])) {
                $where[] = "sms_logs.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['template'])) {
                $where[] = "sms_logs.template_name = ?";
                $params[] = $filters['template'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = "DATE(sms_logs.created_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "DATE(sms_logs.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT 
                        sms_logs.*,
                        CONCAT(users.first_name, ' ', users.last_name) as user_name
                    FROM sms_logs
                    LEFT JOIN users ON sms_logs.user_id = users.id
                    $where_sql
                    ORDER BY sms_logs.created_at DESC
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get SMS logs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * دریافت آمار کلی
     */
    public function getStats($filters = [])
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['date_from'])) {
                $where[] = "DATE(created_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = "DATE(created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                    FROM sms_logs
                    $where_sql";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return ['total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0];
        }
    }
}
?>