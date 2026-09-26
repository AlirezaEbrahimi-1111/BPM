<?php
/**
 * کلاس احراز هویت (Auth)
 * سازگار با هر دو پروژه (اصلی + attendance_system)
 */

date_default_timezone_set('Asia/Tehran');

class Auth
{
    private $db;
    private $secret_key;

    public function __construct($db = null)
    {
        // کلید امضای JWT از config/config.php (که .gitignore شده) خونده می‌شه،
        // نه هاردکد توی سورس — قبلا همین رشته از اولین کامیت پروژه بدون
        // تغییر توی گیت commit شده بود؛ هرکسی به سورس دسترسی داشت می‌تونست
        // توکن معتبر برای هر کاربری جعل کنه. اگه این مقدار تنظیم نشده باشه،
        // عمدا fail می‌کنیم (نه fallback به یه مقدار پیش‌فرض ضعیف)
        $config = require $_SERVER['DOCUMENT_ROOT'] . '/config/config.php';
        if (empty($config['jwt_secret'])) {
            throw new Exception('jwt_secret در config/config.php تنظیم نشده است');
        }
        $this->secret_key = $config['jwt_secret'];

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
            // 🔒 جستجوی کاربر بدون فیلتر is_active — برخلاف قبل، که چون این
            // شرط تو خود SELECT بود، یک حساب غیرفعال با رمز درست هم دقیقا
            // همون پیام کلی «نام کاربری یا رمز اشتباه» رو می‌گرفت (هیچ‌جوره
            // قابل تشخیص از رمز واقعا غلط نبود). الان اول احراز هویت واقعی
            // (رمز درسته یا نه) انجام می‌شه، بعد وضعیت is_active چک می‌شه —
            // فقط وقتی رمز واقعا درسته، پیام اختصاصی «حساب غیرفعال شده»
            // نشون داده می‌شه؛ برای رمز غلط یا کاربر ناموجود، همچنان همون
            // پیام کلی (تا وجود حساب لو نره)
            $stmt = $this->db->prepare("
                SELECT * FROM users
                WHERE (username = ? OR phone = ?)
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $is_valid = false;
            if ($user && substr($user['password'], 0, 3) === '$2y') {
                $is_valid = password_verify($password, $user['password']);
            }

            if (!$user || !$is_valid) {
                return [
                    'success' => false,
                    'message' => 'نام کاربری یا رمز عبور اشتباه است'
                ];
            }

            if ((int) $user['is_active'] !== 1) {
                return [
                    'success' => false,
                    'message' => 'این حساب غیرفعال شده است'
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

            // 🆕 فهرست کامل واحدهای کاربر (نه فقط واحد اصلی) — کاربران چندواحدی
            // (مثلا هم انبار هم فنی) بدون این، توی صفحات سمت کلاینت فقط با
            // واحد اصلی‌شون تشخیص داده می‌شن و دکمه‌های اقدام برای واحد دومشون
            // نمایش داده نمی‌شه — even though سرور خودش این چندواحدی رو درست چک می‌کنه.
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
            $user['activity_sections'] = us_getUserSections($this->db, $user['id']);

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
                return ['success' => false, 'message' => 'این نام کاربری قبلا استفاده شده است'];
            }

            // بررسی وجود شماره موبایل
            $checkPhone = $this->db->prepare("SELECT id FROM users WHERE phone = ?");
            $checkPhone->execute([$phone]);
            if ($checkPhone->fetch()) {
                return ['success' => false, 'message' => 'این شماره موبایل قبلا ثبت شده است'];
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

            // بروزرسانی رمز جدید — token_version هم بالا می‌ره تا با تغییر
            // رمز، هر توکن از‌قبل‌صادرشده (مثلا روی یه دستگاه دیگه) فورا
            // باطل بشه، نه این‌که تا انقضای طبیعی‌ش معتبر بمونه
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $this->db->prepare("UPDATE users SET password = ?, token_version = token_version + 1, updated_at = NOW() WHERE id = ?");

            if ($updateStmt->execute([$hashed_password, $user_id])) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/audit-log.php';
                logSecurityEvent($user_id, 'password_changed');
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

        // نسخه‌ی فعلی توکن کاربر توی خود توکن ثبت می‌شه؛ با خروج/تغییر
        // رمز/غیرفعال‌سازی این عدد توی دیتابیس بالا می‌ره و validateToken
        // دیگه این توکن رو (که نسخه‌ش قدیمیه) معتبر نمی‌دونه — قبلا
        // logout/تغییر رمز هیچ اثری روی توکن‌های صادرشده نداشت
        $tv = 0;
        try {
            $stmt = $this->db->prepare("SELECT token_version FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $tv = (int) ($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            error_log("generateJWTToken: خواندن token_version ناموفق بود: " . $e->getMessage());
        }

        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user_id,
            'organization_id' => $organization_id,
            'tv' => $tv,
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
        // امنیت: ابتدا صحت امضا و انقضا بررسی شود؛ نباید به محتوای توکن تأییدنشده اعتماد کرد
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

            // 🔒 چک نسخه‌ی توکن + فعال‌بودن حساب — قبلا هیچ‌کدوم از این دو
            // در این مسیر (که اکثر endpointها ازش استفاده می‌کنن) چک
            // نمی‌شدن؛ یعنی خروج/تغییر رمز/غیرفعال‌سازی حساب هیچ اثر
            // فوری‌ای روی توکن‌های از‌قبل‌صادرشده نداشت (تا انقضای طبیعی،
            // که با «به‌خاطر بسپار» تا ۳۰ روز هم می‌رسه، معتبر می‌موند)
            $stmt = $this->db->prepare("SELECT is_active, token_version FROM users WHERE id = ?");
            $stmt->execute([$payloadData['user_id']]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRow || (int) $userRow['is_active'] !== 1) {
                return false;
            }
            $tokenTv = (int) ($payloadData['tv'] ?? 0);
            if ((int) $userRow['token_version'] !== $tokenTv) {
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
     * خروج از سیستم — نسخه‌ی توکن کاربر رو بالا می‌بره تا همین توکن
     * (و هر توکن دیگه‌ی صادرشده‌ی قبلی) فورا نامعتبر بشه. قبلا این
     * تابع کاملا بی‌اثر بود؛ توکن تا انقضای طبیعی‌ش معتبر می‌موند
     */
    public function logout($user_id)
    {
        if (!$user_id) {
            return ['success' => false, 'message' => 'کاربر مشخص نیست'];
        }
        try {
            $stmt = $this->db->prepare("UPDATE users SET token_version = token_version + 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/audit-log.php';
            logSecurityEvent($user_id, 'logout');
            return ['success' => true, 'message' => 'خروج موفقیت‌آمیز'];
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            return ['success' => false, 'message' => 'خطای سرور'];
        }
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
        // حداقل ۸ کاراکتر، شامل حداقل یک حرف و یک عدد
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