<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';

$user_id = requireAuth();
$user = getUserInfo($user_id); // اکنون شامل role و organization_id و activity_section

if (!$user || !isset($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$currentUser = loadUserForPermissions($db, $user_id);
$org = $currentUser['organization_id'] ?? null;

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['title']) || empty($input['content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'عنوان و متن اطلاعیه الزامی است'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ───── تعیین دامنهٔ ارسال (scope) و کنترل دسترسی ─────
$scope = $input['scope'] ?? 'organization'; // all_orgs | organization | section | user
$orgId = null;
$targetSection = null;
$targetUserId = null;

if ($scope === 'user') {
    // ارسال به یک شخصِ خاص (فقط مدیر/سوپروایزر)
    requirePermission($currentUser, 'send_org_announcement');
    $targetUserId = (int) ($input['target_user_id'] ?? 0);
    if ($targetUserId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'انتخاب کاربر الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ✅ چکِ امنیتیِ حیاتی: کاربرِ هدف باید در سازمانِ همین مدیر و فعال باشد
    $chk = $db->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND is_deleted = 0 AND is_active = 1 LIMIT 1");
    $chk->execute([$targetUserId, $org]);
    if (!$chk->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد یا متعلق به سازمان شما نیست'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orgId = $org;
    $targetSection = null;
} elseif ($scope === 'all_orgs') {
    // فقط مدیر کل سیستم
    if (!in_array((int)$user_id, getSuperAdminIds(), true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'فقط مدیر کل سیستم می‌تواند به همهٔ سازمان‌ها اطلاعیه بدهد'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $orgId = null;
    $targetSection = null;
} elseif ($scope === 'section') {
    requirePermission($currentUser, 'send_section_announcement');
    $targetSection = trim($input['target_section'] ?? '');
    if ($targetSection === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'انتخاب واحد الزامی است'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $orgId = $org;
} else { // organization (کل سازمان)
    requirePermission($currentUser, 'send_org_announcement');
    $orgId = $org;
    $targetSection = null;
}

try {
    $title    = trim($input['title']);
    $content  = trim($input['content']);
    $priority = in_array($input['priority'] ?? 'normal', ['low', 'normal', 'high', 'urgent'])
        ? $input['priority'] : 'normal';
    $isPinned  = ($input['is_pinned'] ?? 0) ? 1 : 0;
    $publishAt = !empty($input['publish_at']) ? $input['publish_at'] : date('Y-m-d H:i:s');
    $expireAt  = !empty($input['expire_at']) ? $input['expire_at'] : null;

    // اعتبارسنجی ساده: انقضا بعد از انتشار
    if ($expireAt !== null && strtotime($expireAt) <= strtotime($publishAt)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'زمان انقضا باید بعد از زمان انتشار باشد'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "INSERT INTO announcements
                (title, content, priority, is_pinned, publish_at, expire_at,
                 organization_id, target_section, target_user_id, author_id)
            VALUES
                (:title, :content, :priority, :is_pinned, :publish_at, :expire_at,
                 :organization_id, :target_section, :target_user_id, :author_id)";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':title'           => $title,
        ':content'         => $content,
        ':priority'        => $priority,
        ':is_pinned'       => $isPinned,
        ':publish_at'      => $publishAt,
        ':expire_at'       => $expireAt,
        ':organization_id' => $orgId,           // NULL = سراسری
        ':target_section'  => $targetSection,   // NULL = کل سازمان
        ':target_user_id'  => $targetUserId,    // NULL = همه/واحد، مقدار = یک شخص
        ':author_id' => $user_id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'اطلاعیه با موفقیت ایجاد شد',
        'id' => intval($db->lastInsertId())
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
