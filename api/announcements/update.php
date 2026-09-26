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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/permissions.php';
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
    $isSuper   = in_array((int)$user_id, getSuperAdminIds(), true);
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

    // ───── همه خوانده‌شده (دقیقا همان دامنهٔ دید کاربر در list.php) ─────
    if ($action === 'mark_all_read') {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/user-sections.php';
        $sectionsCsv = implode(',', us_getUserSections($db, (int) $user_id));
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT IGNORE INTO announcement_reads (announcement_id, user_id)
                SELECT a.id, :uid
                FROM announcements a
                WHERE a.is_active = 1
                  AND a.publish_at <= :now
                  AND (a.expire_at IS NULL OR a.expire_at > :now2)
                  AND (
                      a.target_user_id = :uid_self
                      OR (
                          a.target_user_id IS NULL
                          AND (
                              a.organization_id IS NULL
                              OR (
                                  a.organization_id = :org
                                  AND (a.target_section IS NULL OR FIND_IN_SET(a.target_section, :sections_csv))
                              )
                          )
                      )
                  )
                  AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = :uid2)";
        $st = $db->prepare($sql);
        $st->execute([
            ':uid'          => $user_id,
            ':uid_self'     => $user_id,
            ':uid2'         => $user_id,
            ':now'          => $now,
            ':now2'         => $now,
            ':org'          => intval($org),
            ':sections_csv' => $sectionsCsv,
        ]);
        ann_out(['success' => true, 'message' => 'همه اطلاعیه‌ها خوانده شد']);
    }

    // ───── از این‌جا به بعد فقط مدیر/سوپروایزر یا سوپرادمین ─────
    if (!$canManage) {
        error_log("Announcement update denied | user_id={$user_id} | action={$action}");
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
            error_log("Announcement delete denied | user_id={$user_id} | announcement_id={$id}");
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
        error_log("Announcement edit denied | user_id={$user_id} | announcement_id={$id}");
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
            if (!$isSuper) { error_log("Announcement scope=all_orgs denied | user_id={$user_id} | announcement_id={$id}"); ann_out(['success' => false, 'message' => 'فقط مدیر کل سیستم می‌تواند سراسری کند'], 403); }
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
    error_log("Announcement update error: " . $e->getMessage() . " | user_id=" . ($user_id ?? 'n/a'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}