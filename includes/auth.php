<?php
/**
 * کلاس احراز هویت (Auth)
 * سازگار با هر دو پروژه (اصلی + attendance_system)
 */

date_default_timezone_set('Asia/Tehran');

class Auth
{
    private $db;
    private $secret_key = "REDACTED_OLD_JWT_SECRET";

    public function __construct($db = null)
    {
        if ($db === null) {
            // اگر Database pass نشد، خودش ایجاد کن
            require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
            $database = new Database();
            $this->db = $database->getConnection();
        } else {
            $this->db = $db;
        }
    }

    /**
     * ورود با نام کاربری و رمز عبور
     */
    public function login($username, $password, $remember_me = false)
    {
        try {
            // جستجوی کاربر با username یا phone
            $stmt = $this->db->prepare("
                SELECT * FROM users 
                WHERE (username = ? OR phone = ?) 
                AND is_active = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $is_valid = false;
            if (substr($user['password'], 0, 3) === '$2y') {
                $is_valid = password_verify($password, $user['password']);
            }
            
            if (!$is_valid || !$user) {
                return [
                    'success' => false,
                    'message' => 'نام کاربری یا رمز عبور اشتباه است'
                ];
            }

            // ✅ بررسی انقضای اشتراک سازمان
            if ($user['organization_id']) {
                $subStmt = $this->db->prepare("
                    SELECT end_date 
                    FROM subscriptions 
                    WHERE organization_id = ? 
                    AND is_active = 1
                    ORDER BY end_date DESC 
                    LIMIT 1
                ");
                $subStmt->execute([$user['organization_id']]);
                $subscription = $subStmt->fetch(PDO::FETCH_ASSOC);
            
                if (!$subscription || strtotime($subscription['end_date']) < time()) {
                    error_log("Login blocked - subscription expired | user_id={$user['id']} | organization_id={$user['organization_id']} | expired_date=" . ($subscription['end_date'] ?? 'none'));
                    return [
                        'success' => false,
                        'message' => 'اشتراک سازمان شما منقضی شده است',
                        'expired_date' => $subscription['end_date'] ?? null
                    ];
                }
            }

            // بروزرسانی زمان آخرین ورود
            $updateStmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);

            // تولید JWT Token
            $token_expiry = $remember_me ? (30 * 24 * 60 * 60) : (5 * 60 * 60);
            $token = $this->generateJWTToken($user['id'], $token_expiry, $user['organization_id']);

            // حذف اطلاعات حساس
            unset($user['password']);

            return [
                'success' => true,
                'token' => $token,
                'user' => $user,
                'message' => 'ورود موفقیت‌آمیز'
            ];

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    /**
     * ثبت‌نام کاربر جدید
     */
    public function register($username, $password, $phone, $first_name, $last_name, $activity_section = 'public')
    {
        try {
            // بررسی وجود username
            $checkUsername = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $checkUsername->execute([$username]);
            if ($checkUsername->fetch()) {
                return ['success' => false, 'message' => 'این نام کاربری قبلاً استفاده شده است'];
            }

            // بررسی وجود شماره موبایل
            $checkPhone = $this->db->prepare("SELECT id FROM users WHERE phone = ?");
            $checkPhone->execute([$phone]);
            if ($checkPhone->fetch()) {
                return ['success' => false, 'message' => 'این شماره موبایل قبلاً ثبت شده است'];
            }

            // هش کردن رمز عبور
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // ایجاد کاربر جدید
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, phone, first_name, last_name, activity_section, created_at,activity_unit) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(),'all')
            ");

            if ($stmt->execute([$username, $hashed_password, $phone, $first_name, $last_name, $activity_section])) {
                $user_id = $this->db->lastInsertId();

                // دریافت اطلاعات کاربر
                $userStmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
                $userStmt->execute([$user_id]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                // حذف اطلاعات حساس
                unset($user['password']);

                // تولید توکن
                $token = $this->generateJWTToken($user_id, null, $user['organization_id']);

                return [
                    'success' => true,
                    'token' => $token,
                    'user' => $user,
                    'message' => 'ثبت‌نام با موفقیت انجام شد'
                ];
            }

            return ['success' => false, 'message' => 'خطا در ثبت‌نام'];

        } catch (Exception $e) {
            error_log("Register error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    /**
     * تغییر رمز عبور
     */
    public function changePassword($user_id, $old_password, $new_password)
    {
        try {
            // دریافت رمز فعلی
            $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return ['success' => false, 'message' => 'کاربر یافت نشد'];
            }

            // بررسی رمز قدیمی
            if (!password_verify($old_password, $user['password'])) {
                return ['success' => false, 'message' => 'رمز عبور فعلی اشتباه است'];
            }

            // بروزرسانی رمز جدید
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");

            if ($updateStmt->execute([$hashed_password, $user_id])) {
                return ['success' => true, 'message' => 'رمز عبور با موفقیت تغییر کرد'];
            }

            return ['success' => false, 'message' => 'خطا در تغییر رمز عبور'];

        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
    }

    /**
     * تولید JWT Token
     */
    public function generateJWTToken($user_id, $expiry_seconds = null, $organization_id = null)
    {
        if ($expiry_seconds === null) {
            $expiry_seconds = 3 * 60 * 60;
        }

        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user_id,
            'organization_id' => $organization_id,
            'iat' => time(),
            'exp' => time() + $expiry_seconds
        ]);

        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secret_key, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    /**
     * استخراج توکن از هدر درخواست
     */
    private function extractToken()
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                return str_replace('Bearer ', '', $headers['Authorization']);
            }
            if (isset($headers['authorization'])) {
                return str_replace('Bearer ', '', $headers['authorization']);
            }
        }
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        }
        return null;
    }

    /**
     * دیکود کردن payload توکن
     */
    private function decodeToken($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3)
            return false;
        $payload = $this->base64UrlDecode($parts[1]);
        return json_decode($payload, true);
    }

    public function getOrganizationFromToken()
    {
        $token = $this->extractToken();
        if (!$token) {
            return false;
        }
        // امنیت: ابتدا صحتِ امضا و انقضا بررسی شود؛ نباید به محتوای توکنِ تأییدنشده اعتماد کرد
        if ($this->validateToken($token) === false) {
            return false;
        }
        $payload = $this->decodeToken($token);
        return $payload['organization_id'] ?? false;
    }

    /**
     * اعتبارسنجی JWT Token
     */
    public function validateToken($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) != 3) {
                return false;
            }

            $header = $this->base64UrlDecode($parts[0]);
            $payload = $this->base64UrlDecode($parts[1]);
            $signature = $this->base64UrlDecode($parts[2]);

            // بررسی امضا
            $expectedSignature = hash_hmac('sha256', $parts[0] . "." . $parts[1], $this->secret_key, true);

            if (!hash_equals($signature, $expectedSignature)) {
                error_log("JWT signature mismatch (possible tampering) | ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                return false;
            }

            $payloadData = json_decode($payload, true);

            // بررسی انقضا
            if ($payloadData['exp'] < time()) {
                return false;
            }

            return $payloadData['user_id'];
        } catch (Exception $e) {
            error_log("Validate token error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت کاربر از توکن
     */
    public function getUserFromToken()
    {

        $token = $this->extractToken();
        if ($token) {
            $user_id = $this->validateToken($token);
            return $user_id ? $user_id : false;
        }
        return false;
    }

    /**
     * خروج از سیستم
     */
    public function logout($token)
    {
        return ['success' => true, 'message' => 'خروج موفقیت‌آمیز'];
    }

    /**
     * Base64 URL Encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL Decode
     */
    private function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * اعتبارسنجی نام کاربری
     */
    public function validateUsername($username)
    {
        return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username);
    }

    /**
     * اعتبارسنجی رمز عبور
     */
    public function validatePassword($password)
    {
        // حداقل ۸ کاراکتر، شاملِ حداقل یک حرف و یک عدد
        if (strlen($password) < 8) {
            return false;
        }
        if (!preg_match('/[A-Za-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        return true;
    }
    
    
}


?>