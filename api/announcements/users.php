<?php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();
    $user = getUserInfo($user_id);
    if (!$user || !isset($user['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $role    = $user['role'] ?? 'employee';
    $org     = (int) ($user['organization_id'] ?? 0);
    $isSuper = ((int) $user['id'] === 1);

    // فقط مدیر/سوپروایزر یا سوپرادمین
    if (!$isSuper && !in_array($role, ['management', 'supervisor'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // بدون سازمانِ معتبر، لیست خالی (سوپرادمینِ بدون سازمان)
    if ($org <= 0) {
        echo json_encode(['success' => true, 'users' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // ✅ فقط کاربرانِ فعالِ همان سازمان
    $stmt = $db->prepare("
        SELECT id, TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) AS name
        FROM users
        WHERE organization_id = ? AND is_deleted = 0 AND is_active = 1
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$org]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        if ($r['name'] === '') $r['name'] = 'کاربر ' . $r['id'];
    }
    unset($r);

    echo json_encode(['success' => true, 'users' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("announcements/users.php failed | user_id={$user_id} | org=" . ($org ?? 'null') . " | " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور'], JSON_UNESCAPED_UNICODE);
}