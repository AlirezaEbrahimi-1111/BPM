<?php
// api/admin/restore-user.php — بازگرداندن کاربر حذف‌شده + بازسازی موبایل و یوزرنیم
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد مجاز نیست'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // نقش و سازمانِ کاربر جاری
    // ⚠️ عمداً manager را شامل نمی‌شود: بازگردانیِ کاربرِ حذف‌شده یک
    // عملیاتِ حساس‌تر از مدیریتِ روزمرهٔ زیرمجموعه است، و از طرفی
    // getSubordinateIds() کاربرانِ حذف‌شده را در زنجیره نمی‌بیند —
    // پس برای manager قابل‌استفاده هم نبود.
    $stmtMe = $db->prepare('SELECT role, organization_id FROM users WHERE id = ? AND is_active = 1');
    $stmtMe->execute([$user_id]);
    $me = $stmtMe->fetch(PDO::FETCH_ASSOC);
    if (!isOrgWideRole($me)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $uid   = (int)($input['user_id'] ?? 0);
    if (!$uid) {
        echo json_encode(['success' => false, 'message' => 'شناسه کاربر الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // کاربرِ حذف‌شدهٔ همین سازمان
    $stmt = $db->prepare('SELECT id, username, phone FROM users
                          WHERE id = ? AND is_deleted = 1 AND organization_id = ?');
    $stmt->execute([$uid, $me['organization_id']]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر حذف‌شده یافت نشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // استخراج مقدار اصلی از یوزرنیم: deleted_‹اصلی›_‹تایم‌استمپ›
    $newUsername = $target['username'];
    $newPhone    = $target['phone'];
    $orig = null;
    if (preg_match('/^deleted_(.+)_(\d+)$/', $target['username'], $m)) {
        $orig = $m[1];                 // مقدار بین دو خط‌زیر = موبایل/یوزرنیم اصلی
        $newUsername = $orig;
        // موبایل را فقط در صورتی بازسازی کن که واقعاً خراب شده باشد
        if (strpos((string)$target['phone'], 'deleted_') === 0) {
            $newPhone = $orig;
        }
    }

    // محافظ برخورد: مبادا یوزرنیم/موبایل را کاربر فعال دیگری گرفته باشد
    if ($orig !== null) {
        $chk = $db->prepare('SELECT id FROM users
                             WHERE id <> ? AND is_deleted = 0
                               AND (username = ? OR phone = ?)
                             LIMIT 1');
        $chk->execute([$uid, $newUsername, $newPhone]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'موبایل یا نام‌کاربری این کاربر اکنون توسط کاربر دیگری استفاده می‌شود؛ ابتدا آن را آزاد کنید.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // بازگردانی
    $stmt = $db->prepare('UPDATE users
                          SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL,
                              username = ?, phone = ?
                          WHERE id = ?');
    $stmt->execute([$newUsername, $newPhone, $uid]);

    echo json_encode([
        'success'  => true,
        'message'  => 'کاربر بازگردانده شد',
        'restored' => ['username' => $newUsername, 'phone' => $newPhone]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده'], JSON_UNESCAPED_UNICODE);
}