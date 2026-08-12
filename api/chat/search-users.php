<?php
/**
 * API: جستجوی کاربرانِ هم‌سازمان برای شروعِ گفتگوی جدید
 * GET /api/chat/search-users.php?q=...
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/SectionHelper.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $orgId = $stmt->fetchColumn();
    if (!$orgId) {
        echo json_encode(['success' => true, 'users' => []]);
        exit;
    }

    $q = trim($_GET['q'] ?? '');

    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.role, u.activity_section, u.avatar_path,
               cp.last_seen_at
        FROM users u
        LEFT JOIN chat_presence cp ON cp.user_id = u.id
        WHERE u.organization_id = ?
          AND u.id != ?
          AND u.is_active = 1
          AND u.is_deleted = 0
    ";
    $params = [$orgId, $user_id];

    if ($q !== '') {
        $sql .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
        $params[] = '%' . $q . '%';
    }

    $sql .= " ORDER BY u.first_name, u.last_name LIMIT 30";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔒 آستانهٔ آنلاین‌بودن: هم‌راستا با api/chat/conversations.php
    $onlineThresholdSeconds = 60;

    $sectionsCache = [];
    $users = array_map(function ($u) use ($db, $orgId, &$sectionsCache, $onlineThresholdSeconds) {
        $sectionKey = $u['activity_section'] ?? '';
        if ($sectionKey !== '' && !array_key_exists($sectionKey, $sectionsCache)) {
            $sectionsCache[$sectionKey] = getSectionLabel($db, $orgId, $sectionKey);
        }
        $lastSeenAt = $u['last_seen_at'];
        return [
            'id'            => (int) $u['id'],
            'full_name'     => trim($u['first_name'] . ' ' . $u['last_name']),
            'section_label' => $sectionKey !== '' ? ($sectionsCache[$sectionKey] ?? $sectionKey) : '',
            'last_seen_at'  => $lastSeenAt,
            'is_online'     => $lastSeenAt && (time() - strtotime($lastSeenAt)) <= $onlineThresholdSeconds,
            'avatar_url'    => $u['avatar_path'] ?: null,
        ];
    }, $rows);

    echo json_encode(['success' => true, 'users' => $users], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat search-users error: " . $e->getMessage());
}
