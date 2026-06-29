<?php
error_reporting(0);
ini_set('display_errors', '0');
/**
 * API ویرایش/حذف/خواندن اطلاعیه
 * POST (JSON):
 *   action = delete | mark_read | mark_all_read   (در غیر این صورت = ویرایش)
 *   id, title, content, priority, is_pinned, is_active, publish_at, expire_at,
 *   scope (all_orgs|organization|section), target_section
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

function ann_out($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $user_id = requireAuth();

    $database = new Database();
    $db = $database->getConnection();

    // اطلاعات کاربر مستقیم از دیتابیس (مستقل از getUserInfo)
    $uStmt = $db->prepare("SELECT id, role, organization_id, activity_section FROM users WHERE id = ?");
    $uStmt->execute([$user_id]);
    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) ann_out(['success' => false, 'message' => 'عدم احراز هویت'], 401);

    $role      = $u['role'] ?? 'employee';
    $org       = $u['organization_id'] ?? null;
    $section   = $u['activity_section'] ?? null;
    $isSuper   = ((int)$user_id === 1);
    $canManage = $isSuper || in_array($role, ['management', 'supervisor']);

    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // ───── علامت خوانده‌شده (همهٔ کاربران) ─────
    if ($action === 'mark_read') {
        $id = intval($input['id'] ?? 0);
        if (!$id) ann_out(['success' => false, 'message' => 'آیدی الزامی است'], 400);
        $db->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?, ?)")
           ->execute([$id, $user_id]);
        ann_out(['success' => true]);
    }

    // ───── همه خوانده‌شده (فقط اطلاعیه‌های مرتبط) ─────
    if ($action === 'mark_all_read') {
        $sql = "INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
                SELECT a.id, :uid
                FROM announcements a
                WHERE a.is_active = 1
                  AND (
                      a.organization_id IS NULL
                      OR (
                          a.organization_id = :org
                          AND (a.target_section IS NULL OR a.target_section = :section COLLATE utf8mb4_persian_ci)
                      )
                  )
                  AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = :uid2)";
        $st = $db->prepare($sql);
        $st->execute([
            ':uid'     => $user_id,
            ':uid2'    => $user_id,
            ':org'     => intval($org),
            ':section' => $section
        ]);
        ann_out(['success' => true, 'message' => 'همه اطلاعیه‌ها خوانده شد']);
    }

    // ───── از این‌جا به بعد فقط مدیر/سوپروایزر یا سوپرادمین ─────
    if (!$canManage) {
        ann_out(['success' => false, 'message' => 'دسترسی غیر مجاز'], 403);
    }

    // ───── حذف ─────
    if ($method === 'DELETE' || $action === 'delete') {
        $id = intval($input['id'] ?? 0);
        if (!$id) ann_out(['success' => false, 'message' => 'آیدی الزامی است'], 400);

        $c = $db->prepare("SELECT organization_id FROM announcements WHERE id = ?");
        $c->execute([$id]);
        $row = $c->fetch(PDO::FETCH_ASSOC);
        if (!$row) ann_out(['success' => false, 'message' => 'اطلاعیه یافت نشد'], 404);

        $sameOrg = ($row['organization_id'] !== null && (int)$row['organization_id'] === (int)$org);
        if (!$isSuper && !$sameOrg) {
            ann_out(['success' => false, 'message' => 'اجازهٔ حذف این اطلاعیه را ندارید'], 403);
        }

        $db->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        ann_out(['success' => true, 'message' => 'اطلاعیه حذف شد']);
    }

    // ───── ویرایش ─────
    $id = intval($input['id'] ?? 0);
    if (!$id) ann_out(['success' => false, 'message' => 'آیدی الزامی است'], 400);

    // بررسی مالکیت ردیف موجود
    $c = $db->prepare("SELECT organization_id FROM announcements WHERE id = ?");
    $c->execute([$id]);
    $row = $c->fetch(PDO::FETCH_ASSOC);
    if (!$row) ann_out(['success' => false, 'message' => 'اطلاعیه یافت نشد'], 404);

    $sameOrg = ($row['organization_id'] !== null && (int)$row['organization_id'] === (int)$org);
    if (!$isSuper && !$sameOrg) {
        ann_out(['success' => false, 'message' => 'اجازهٔ ویرایش این اطلاعیه را ندارید'], 403);
    }

    $fields = [];
    $params = [':id' => $id];

    foreach (['title', 'content', 'priority', 'publish_at', 'expire_at'] as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f = :$f";
            $params[":$f"] = ($input[$f] === '' ? null : $input[$f]);
        }
    }
    if (array_key_exists('is_pinned', $input)) {
        $fields[] = "is_pinned = :is_pinned";
        $params[':is_pinned'] = $input['is_pinned'] ? 1 : 0;
    }
    if (array_key_exists('is_active', $input)) {
        $fields[] = "is_active = :is_active";
        $params[':is_active'] = $input['is_active'] ? 1 : 0;
    }

    // تغییر دامنهٔ ارسال (در صورت ارسال scope)
    if (array_key_exists('scope', $input)) {
        $scope = $input['scope'];
        if ($scope === 'all_orgs') {
            if (!$isSuper) ann_out(['success' => false, 'message' => 'فقط مدیر کل سیستم می‌تواند سراسری کند'], 403);
            $fields[] = "organization_id = :org_id"; $params[':org_id'] = null;
            $fields[] = "target_section = :tsec";    $params[':tsec'] = null;
        } elseif ($scope === 'section') {
            $tsec = trim($input['target_section'] ?? '');
            if ($tsec === '') ann_out(['success' => false, 'message' => 'انتخاب واحد الزامی است'], 400);
            $fields[] = "organization_id = :org_id"; $params[':org_id'] = $isSuper ? ($row['organization_id'] ?? $org) : $org;
            $fields[] = "target_section = :tsec";    $params[':tsec'] = $tsec;
        } else { // organization
            $fields[] = "organization_id = :org_id"; $params[':org_id'] = $isSuper ? ($row['organization_id'] ?? $org) : $org;
            $fields[] = "target_section = :tsec";    $params[':tsec'] = null;
        }
    }

    if (empty($fields)) {
        ann_out(['success' => false, 'message' => 'هیچ فیلدی برای ویرایش ارسال نشده'], 400);
    }

    $sql = "UPDATE announcements SET " . implode(', ', $fields) . " WHERE id = :id";
    if (!$isSuper) {
        $sql .= " AND organization_id = :scope_org";
        $params[':scope_org'] = $org;
    }
    $db->prepare($sql)->execute($params);

    ann_out(['success' => true, 'message' => 'اطلاعیه ویرایش شد']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}