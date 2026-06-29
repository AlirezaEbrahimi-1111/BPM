<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

$user_id = requireAuth();
$user = getUserInfo($user_id);

if (!$user || !isset($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $limit  = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 10;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $now      = date('Y-m-d H:i:s');
    $userId   = intval($user['id']);
    $role     = $user['role'] ?? 'employee';
    $org      = intval($user['organization_id'] ?? 0);
    $section  = $user['activity_section'] ?? null;

    $isSuper   = ($userId === 1);
    $canManage = in_array($role, ['management', 'supervisor']);
    $showAll   = isset($_GET['all']) && $_GET['all'] == '1' && ($isSuper || $canManage);

    if ($showAll) {
        // ───── حالت مدیریت ─────
        // مدیر کل سیستم: همهٔ سازمان‌ها + سراسری
        // مدیر/سوپروایزر: فقط سازمان خودش + اطلاعیه‌های سراسری
        $scopeWhere = $isSuper
            ? "1 = 1"
            : "(a.organization_id = :org OR a.organization_id IS NULL)";

        $sql = "SELECT a.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                    u.role AS author_role,
                    (SELECT COUNT(*) FROM announcement_reads ar2 WHERE ar2.announcement_id = a.id) AS read_count,
                    (SELECT COUNT(*) FROM users uu
                        WHERE uu.is_active = 1
                          AND (a.organization_id IS NULL OR uu.organization_id = a.organization_id)
                          AND (a.target_section IS NULL OR uu.activity_section = a.target_section)
                    ) AS total_users
                FROM announcements a
                LEFT JOIN users u ON a.author_id = u.id
                WHERE $scopeWhere
                ORDER BY a.is_pinned DESC, a.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        if (!$isSuper) $stmt->bindValue(':org', $org, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

    } else {
        // ───── کاربر عادی: سراسری + سازمان خودش با واحد منطبق ─────
        $sql = "SELECT a.*,
                    CONCAT(u.first_name, ' ', u.last_name) AS author_name,
                    u.role AS author_role,
                    (CASE WHEN EXISTS (
                        SELECT 1 FROM announcement_reads ar
                        WHERE ar.announcement_id = a.id AND ar.user_id = :uid
                    ) THEN 1 ELSE 0 END) AS is_read
                FROM announcements a
                LEFT JOIN users u ON a.author_id = u.id
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
                                  AND (a.target_section IS NULL OR a.target_section = :section)
                              )
                          )
                      )
                  )
                ORDER BY a.is_pinned DESC, a.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid_self', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':now', $now, PDO::PARAM_STR);
        $stmt->bindValue(':now2', $now, PDO::PARAM_STR);
        $stmt->bindValue(':org', $org, PDO::PARAM_INT);
        $stmt->bindValue(':section', $section, $section !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    }

    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ───── تعداد نخوانده (همان دامنهٔ دیدِ کاربر عادی) ─────
    $unreadSql = "SELECT COUNT(*) AS unread_count
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
                                    AND (a.target_section IS NULL OR a.target_section = :section)
                                )
                            )
                        )
                    )
                    AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = :uid)";

    $unreadStmt = $db->prepare($unreadSql);
    $unreadStmt->bindValue(':now', $now, PDO::PARAM_STR);
    $unreadStmt->bindValue(':now2', $now, PDO::PARAM_STR);
    $unreadStmt->bindValue(':org', $org, PDO::PARAM_INT);
    $unreadStmt->bindValue(':section', $section, $section !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $unreadStmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $unreadStmt->bindValue(':uid_self', $userId, PDO::PARAM_INT);
    $unreadStmt->execute();
    $unreadRow = $unreadStmt->fetch(PDO::FETCH_ASSOC);
    $unreadCount = $unreadRow ? intval($unreadRow['unread_count']) : 0;

    // ───── پاکسازی خروجی ─────
    foreach ($announcements as &$ann) {
        $ann['is_pinned'] = (bool) $ann['is_pinned'];
        $ann['is_active'] = (bool) $ann['is_active'];
        $ann['is_read']   = isset($ann['is_read']) ? (bool) $ann['is_read'] : null;
        // برچسب دامنه برای نمایش
        if (!empty($ann['target_user_id'])) {
            $ann['scope_label'] = 'شخصی';
        } elseif (is_null($ann['organization_id'])) {
            $ann['scope_label'] = 'سراسری';
        } elseif (empty($ann['target_section'])) {
            $ann['scope_label'] = 'کل سازمان';
        } else {
            $ann['scope_label'] = 'واحد: ' . $ann['target_section'];
        }
    }
    unset($ann);

    echo json_encode([
        'success'       => true,
        'announcements' => $announcements,
        'unread_count'  => $unreadCount,
        'total'         => count($announcements)
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}