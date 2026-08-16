<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms.php';
require_once __DIR__ . '/../../includes/sms_patterns.php';
require_once __DIR__ . '/../../includes/audit-log.php';

// ------------------- توابع کمکی (قبلی) -------------------
// 🔒 قبلاً فقط بر اساسِ IP محدود می‌شد — مهاجمی با چند IP/پراکسیِ مختلف
// می‌تونست رويِ یک حسابِ مشخص بدونِ محدودیت brute-force کنه. الان هم IP
// هم خودِ نامِ کاربری جداگانه چک می‌شن؛ عبور از هرکدوم کافیه برایِ بلاک
function checkRateLimit($ip, $db, $username = null)
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $ipCount = (int) $stmt->fetchColumn();

    $userCount = 0;
    if (!empty($username)) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");
        $stmt->execute([$username]);
        $userCount = (int) $stmt->fetchColumn();
    }

    if ($ipCount >= 5 || $userCount >= 5) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'تعداد تلاش‌های ناموفق زیاد است. لطفاً ۱۵ دقیقه بعد دوباره تلاش کنید.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function recordFailedLogin($ip, $db, $username = null)
{
    $stmt = $db->prepare("INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())");
    $stmt->execute([$ip, $username]);
}

function resetRateLimit($ip, $db, $username = null)
{
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    if (!empty($username)) {
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
    }
}

// ------------------- توابع جدید OTP -------------------
function checkOtpRateLimit($phone, $db)
{
    // حداکثر ۳ درخواست کد در ۱۵ دقیقه
    $stmt = $db->prepare("SELECT COUNT(*) FROM otp_codes WHERE phone = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$phone]);
    $count = (int) $stmt->fetchColumn();
    if ($count >= 3) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'تعداد درخواست‌های کد تأیید زیاد است. ۱۵ دقیقه صبر کنید.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function generateOtpCode()
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// ------------------- پردازش درخواست -------------------
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
        exit;
    }

    $action = $data['action'] ?? 'login'; // پیش‌فرض: ورود با رمز

    // ---------- ارسال کد OTP (جدید) ----------
    if ($action === 'send_otp') {
        $phone = trim($data['phone'] ?? '');
        if (empty($phone) || !preg_match('/^09[0-9]{9}$/', $phone)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شماره موبایل نامعتبر است']);
            exit;
        }

        // بررسی وجود کاربر با این شماره
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? AND is_active = 1");
        $stmt->execute([$phone]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'کاربری با این شماره یافت نشد']);
            exit;
        }

        checkOtpRateLimit($phone, $db);

        $code = generateOtpCode();
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $stmt = $db->prepare("INSERT INTO otp_codes (phone, code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$phone, $code, $expires]);

        // ارسال پیامک
        $sms = new SMS($db);
        $bodyId = resolveBodyId('otp'); // یا مستقیم 495565
        $userStmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
        $userStmt->execute([$phone]);
        $userId = $userStmt->fetchColumn();
        $sent = false;
        if ($userId) {
            $sent = $sms->sendPattern($userId, $bodyId, [$code], null);
        } else {
            // Fallback: ارسال مستقیم با متد send
            $sent = $sms->send(null, $phone, "کد تأیید شما: $code\nلغو11", 'otp', null);
        }

        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'کد تأیید ارسال شد']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'ارسال پیامک ناموفق بود']);
        }
        exit;
    }

    // ---------- تأیید کد OTP و ورود (جدید) ----------
    if ($action === 'verify_otp') {
        $phone = trim($data['phone'] ?? '');
        $code = trim($data['code'] ?? '');
        if (empty($phone) || empty($code) || !preg_match('/^[0-9]{6}$/', $code)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'شماره یا کد نامعتبر است']);
            exit;
        }

        $stmt = $db->prepare("SELECT id, code, expires_at, used, attempts FROM otp_codes WHERE phone = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$phone]);
        $otp = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otp) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'کد تأیید یافت نشد']);
            exit;
        }
        if (strtotime($otp['expires_at']) < time()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'کد تأیید منقضی شده است']);
            exit;
        }
        if ($otp['used']) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'این کد قبلاً استفاده شده است']);
            exit;
        }
        if ($otp['code'] !== $code) {
            $stmt = $db->prepare("UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?");
            $stmt->execute([$otp['id']]);
            if ($otp['attempts'] + 1 >= 5) {
                $stmt = $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?");
                $stmt->execute([$otp['id']]);
            }
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'کد تأیید اشتباه است']);
            exit;
        }

        // کد صحیح است
        $stmt = $db->prepare("UPDATE otp_codes SET used = 1 WHERE id = ?");
        $stmt->execute([$otp['id']]);

        $userStmt = $db->prepare("SELECT * FROM users WHERE phone = ? AND is_active = 1");
        $userStmt->execute([$phone]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
            exit;
        }

        // ورود
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['organization_id'] = $user['organization_id'];
        try {
            $orgStmt = $db->prepare("SELECT name FROM organizations WHERE id = ?");
            $orgStmt->execute([$user['organization_id']]);
            $org = $orgStmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['organization_name'] = $org ? $org['name'] : 'یکتا همراهان ملک';
        } catch (Exception $e) {
            $_SESSION['organization_name'] = 'یکتا همراهان ملک';
        }


        // ✅ ساخت توکن JWT — بدون این خط header.php کاربر را به لاگین برمی‌گرداند
        $remember_me = $data['remember_me'] ?? false;
        $token_expiry = $remember_me ? (30 * 24 * 60 * 60) : (3 * 60 * 60);
        $auth = new Auth();
        $token = $auth->generateJWTToken($user['id'], $token_expiry, $user['organization_id']);
        logSecurityEvent($user['id'], 'login_success', null, ['method' => 'otp']);

        unset($user['password']);
        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => $user,
            'message' => 'ورود موفقیت‌آمیز'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ---------- ورود با رمز عبور (action = login یا بدون action) ----------
    if (
        empty($data['username']) || empty($data['password']) ||
        !is_string($data['username']) || !is_string($data['password'])
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'اطلاعات ورود نامعتبر است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $username = trim($data['username']);
    $password = $data['password'];
    $remember_me = $data['remember_me'] ?? false;

    // اعتبارسنجیِ ورودی قبل از چکِ rate limit انجام شد تا $username برایِ
    // چکِ محدودیتِ حساب‌محور (نه فقط IP) در دسترس باشه
    checkRateLimit($ip, $db, $username);

    $auth = new Auth();
    $result = $auth->login($username, $password, $remember_me);

    if ($result['success']) {
        resetRateLimit($ip, $db, $username);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['user_name'] = $result['user']['first_name'];
        $_SESSION['organization_id'] = $result['user']['organization_id'];

        try {
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("SELECT name FROM organizations WHERE id = ?");
            $stmt->execute([$result['user']['organization_id']]);
            $org = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['organization_name'] = $org ? $org['name'] : 'یکتا همراهان ملک';
        } catch (Exception $e) {
            $_SESSION['organization_name'] = 'یکتا همراهان ملک';
        }

        logSecurityEvent($result['user']['id'], 'login_success', null, ['method' => 'password']);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } else {
        recordFailedLogin($ip, $db, $username);
        // user_id عمداً null است — کاربرِ ناموفق هنوز شناسایی‌نشده؛ نامِ
        // واردشده (نه رمز، هرگز) برایِ بررسیِ بعدی توی details ثبت می‌شه
        logSecurityEvent(null, 'login_failed', null, ['method' => 'password', 'username_attempted' => $username]);
        http_response_code(401);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی سرور'
    ], JSON_UNESCAPED_UNICODE);
}
