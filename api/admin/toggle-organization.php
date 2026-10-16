<?php
/**
 * POST /api/admin/toggle-organization.php   body: {"org_id": 12, "status": 0|1}
 * فعال/غیرفعال کردن یک سازمان — فقط مدیر کل (superadmin)، از pages/superAdmin.php.
 *
 * غیرفعال‌سازی (status = 0):
 *   • همهٔ کاربران سازمان غیرفعال می‌شوند و token_version بالا می‌رود (فورا از
 *     همهٔ APIهای PHP و هر دو سرویس Go بیرون می‌افتند؛ همان کار toggle-user-status).
 *     کسانی که «فعال بودند» با org_deactivated = 1 علامت می‌خورند.
 *   • شماره و نام‌کاربری همه (حتی غیرفعال/حذف‌شده‌ها) «آزاد» می‌شود تا بتوان همان
 *     آدم را در سازمان دیگری کاربر تازه کرد: phone → «x» + شناسهٔ ۱۰ رقمی (ستون
 *     اجباری است؛ این مقدار هیچ‌وقت شمارهٔ واقعی نیست و ورود/پیامک/بررسی تکرار
 *     پیدایش نمی‌کنند)، username → NULL؛ مقادیر اصلی در released_* می‌مانند.
 *   • داده‌های سازمان (کارها، فاکتورها، …) دست نمی‌خورند.
 * فعال‌سازی دوباره (status = 1):
 *   • شماره/نام‌کاربری هر کاربر فقط اگر در این فاصله جای دیگری گرفته نشده باشد برمی‌گردد؛
 *     بقیه گزارش می‌شوند.
 *   • فقط کاربرانِ org_deactivated = 1 که شماره‌شان برگشت دوباره فعال می‌شوند.
 *
 * سرور PHP 7.4 است: از match/str_contains/nullsafe استفاده نشود.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/session_start.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/audit-log.php';
header('Content-Type: application/json; charset=utf-8');

function toggleOrgFail(int $code, string $msg)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// پیام‌ها در صفحه با innerHTML نمایش داده می‌شوند (uiConfirm/showToast)؛ نام سازمان و
// نام‌کاربری‌ها را کاربران وارد کرده‌اند → در پیام همیشه escape شوند.
function toggleOrgH($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    toggleOrgFail(405, 'متد غیرمجاز');
}
// 🔒 فقط JSON: فرم مخفیِ یک سایت دیگر (enctype=text/plain) نمی‌تواند application/json
// بفرستد مگر با اجازهٔ CORS — جلوی CSRF روی این کار حساس (احراز با کوکی نشست).
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
    toggleOrgFail(415, 'نوع درخواست نامعتبر است');
}

$database = new Database();
$db = $database->getConnection();

// همان ترتیب احراز صفحهٔ superAdmin: نشست، بعد توکن
$actorId = (int) ($_SESSION['user_id'] ?? 0);
if (!$actorId) {
    $auth = new Auth($db);
    $actorId = (int) ($auth->getUserFromToken() ?: 0);
    if (!$actorId && isset($_COOKIE['auth_token'])) {
        $actorId = (int) ($auth->validateToken($_COOKIE['auth_token']) ?: 0);
    }
}
$superAdmins = getSuperAdminIds();
if (!in_array($actorId, $superAdmins, true)) {
    toggleOrgFail(403, 'دسترسی غیرمجاز');
}

$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$orgId  = (int) ($data['org_id'] ?? 0);
$status = isset($data['status']) ? (int) $data['status'] : -1;
if ($orgId <= 0 || ($status !== 0 && $status !== 1)) {
    toggleOrgFail(400, 'درخواست نامعتبر است');
}

$org = $db->prepare("SELECT id, name, is_active FROM organizations WHERE id = ?");
$org->execute([$orgId]);
$org = $org->fetch(PDO::FETCH_ASSOC);
if (!$org) {
    toggleOrgFail(404, 'سازمان یافت نشد');
}
if ((int) $org['is_active'] === $status) {
    toggleOrgFail(409, $status ? 'این سازمان از قبل فعال است' : 'این سازمان از قبل غیرفعال است');
}

if ($status === 0) {
    // 🔒 سازمانی که مدیر کل در آن است بسته نمی‌شود (وگرنه خود مدیر کل هم بیرون می‌افتد)
    $in = implode(',', array_map('intval', $superAdmins));
    $hasSuper = $db->prepare("SELECT COUNT(*) FROM users WHERE organization_id = ? AND id IN ($in)");
    $hasSuper->execute([$orgId]);
    if ((int) $hasSuper->fetchColumn() > 0) {
        toggleOrgFail(422, 'سازمانی که مدیر کل عضو آن است قابل غیرفعال‌سازی نیست');
    }
}

try {
    $db->beginTransaction();

    if ($status === 0) {
        // ترتیب SET مهم است: MySQL از چپ به راست ارزیابی می‌کند، پس org_deactivated
        // با is_active «قبلی» پر می‌شود و بعد is_active صفر می‌شود.
        $st = $db->prepare("
            UPDATE users
            SET org_deactivated = IF(is_active = 1, 1, 0),
                is_active = 0,
                token_version = token_version + 1
            WHERE organization_id = ?
        ");
        $st->execute([$orgId]);
        $users = $st->rowCount();

        $st = $db->prepare("
            UPDATE users
            SET released_phone = phone,
                phone = CONCAT('x', LPAD(id, 10, '0'))
            WHERE organization_id = ? AND released_phone IS NULL AND phone NOT LIKE 'x%'
        ");
        $st->execute([$orgId]);
        $phones = $st->rowCount();

        $st = $db->prepare("
            UPDATE users
            SET released_username = username,
                username = NULL
            WHERE organization_id = ? AND released_username IS NULL AND username IS NOT NULL
        ");
        $st->execute([$orgId]);
        $usernames = $st->rowCount();

        $db->prepare("UPDATE organizations SET is_active = 0 WHERE id = ?")->execute([$orgId]);
        $db->commit();

        logSecurityEvent($actorId, 'org_deactivated', null, [
            'سازمان' => $org['name'] . ' (#' . $orgId . ')',
            'کاربران' => $users,
            'شمارهٔ آزادشده' => $phones,
            'نام‌کاربری آزادشده' => $usernames,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'سازمان «' . toggleOrgH($org['name']) . '» غیرفعال شد. ' . $users . ' کاربر غیرفعال و '
                . $phones . ' شماره آزاد شد؛ این شماره‌ها حالا در سازمان دیگری قابل افزودن‌اند.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── فعال‌سازی دوباره ──
    $rows = $db->prepare("
        SELECT id, released_phone, released_username, org_deactivated
        FROM users WHERE organization_id = ?
        FOR UPDATE
    ");
    $rows->execute([$orgId]);
    $rows = $rows->fetchAll(PDO::FETCH_ASSOC);

    $phoneTaken = $db->prepare("SELECT 1 FROM users WHERE phone = ? AND id <> ? LIMIT 1");
    $userTaken  = $db->prepare("SELECT 1 FROM users WHERE username = ? AND id <> ? LIMIT 1");
    $setPhone   = $db->prepare("UPDATE users SET phone = ?, released_phone = NULL WHERE id = ?");
    $setUser    = $db->prepare("UPDATE users SET username = ?, released_username = NULL WHERE id = ?");
    $activate   = $db->prepare("UPDATE users SET is_active = 1, org_deactivated = 0 WHERE id = ?");

    $conflictPhones = [];
    $conflictUsers  = [];
    $reactivated    = 0;
    foreach ($rows as $u) {
        $uid = (int) $u['id'];
        $phoneOk = true;
        if ($u['released_phone'] !== null && $u['released_phone'] !== '') {
            $phoneTaken->execute([$u['released_phone'], $uid]);
            if ($phoneTaken->fetchColumn()) {
                $phoneOk = false;
                $conflictPhones[] = $u['released_phone'];
            } else {
                $setPhone->execute([$u['released_phone'], $uid]);
            }
        }
        if ($u['released_username'] !== null && $u['released_username'] !== '') {
            $userTaken->execute([$u['released_username'], $uid]);
            if ($userTaken->fetchColumn()) {
                $conflictUsers[] = $u['released_username'];
            } else {
                $setUser->execute([$u['released_username'], $uid]);
            }
        }
        // فقط کسی که «به‌خاطر سازمان» غیرفعال شده بود، و شماره‌اش هم برگشت
        if ((int) $u['org_deactivated'] === 1 && $phoneOk) {
            $activate->execute([$uid]);
            $reactivated++;
        }
    }

    $db->prepare("UPDATE organizations SET is_active = 1 WHERE id = ?")->execute([$orgId]);
    $db->commit();

    logSecurityEvent($actorId, 'org_activated', null, [
        'سازمان' => $org['name'] . ' (#' . $orgId . ')',
        'کاربرانِ فعال‌شده' => $reactivated,
        'شمارهٔ برنگشته' => count($conflictPhones) ? implode('، ', $conflictPhones) : 'ندارد',
    ]);

    $msg = 'سازمان «' . toggleOrgH($org['name']) . '» فعال شد و ' . $reactivated . ' کاربر دوباره فعال شد.';
    if ($conflictPhones) {
        $msg .= ' این شماره‌ها در این فاصله در سازمان دیگری ثبت شده‌اند و برنگشتند (کاربرانشان غیرفعال ماندند): '
            . toggleOrgH(implode('، ', $conflictPhones)) . '.';
    }
    if ($conflictUsers) {
        $msg .= ' این نام‌های کاربری هم گرفته شده‌اند و برنگشتند: ' . toggleOrgH(implode('، ', $conflictUsers)) . '.';
    }
    echo json_encode([
        'success' => true,
        'message' => $msg,
        'conflict_phones' => $conflictPhones,
        'conflict_usernames' => $conflictUsers,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('toggle-organization error: ' . $e->getMessage());
    toggleOrgFail(500, 'خطای سرور؛ هیچ تغییری اعمال نشد');
}
