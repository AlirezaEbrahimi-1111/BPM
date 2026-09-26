<?php
/**
 * API: اطلاعات نمایشی طرف مقابل در یک گفتگوی مستقیم (برای دراور پروفایل)
 * GET /api/chat/user-profile.php?conversation_id=123
 *
 * 🔒 عمدا user_id از کلاینت گرفته نمی‌شه — فقط از روی یک گفتگوی مستقیم
 * واقعی که خود کاربر توش عضوه، طرف مقابل پیدا می‌شه؛ وگرنه می‌شد با یک
 * user_id دلخواه، شمارهٔ موبایل هر کاربری رو استعلام گرفت.
 */

header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/cors.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';

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

    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گفتگو الزامی است']);
        exit;
    }

    $stmt = $db->prepare("SELECT type FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv || $conv['type'] !== 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو مستقیم نیست']);
        exit;
    }

    // خود کاربر باید عضو همین گفتگو باشه
    $stmt = $db->prepare("SELECT user_id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // طرف مقابل
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.phone, u.activity_section, u.avatar_path
        FROM chat_participants cp
        JOIN users u ON u.id = cp.user_id
        WHERE cp.conversation_id = ? AND cp.user_id != ?
        LIMIT 1
    ");
    $stmt->execute([$conversationId, $user_id]);
    $other = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$other) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد']);
        exit;
    }

    $sectionLabel = $other['activity_section'];
    if ($other['activity_section'] === 'management') {
        $sectionLabel = 'مدیریت';
    } elseif (!empty($other['activity_section'])) {
        $stmt = $db->prepare("
            SELECT section_label FROM organization_activity_sections
            WHERE section_key = ? AND organization_id = (SELECT organization_id FROM users WHERE id = ?)
        ");
        $stmt->execute([$other['activity_section'], $other['id']]);
        $sectionLabel = $stmt->fetchColumn() ?: $other['activity_section'];
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id'             => (int) $other['id'],
            'full_name'      => trim($other['first_name'] . ' ' . $other['last_name']),
            'phone'          => $other['phone'],
            'section_label'  => $sectionLabel,
            'avatar_path'    => $other['avatar_path'],
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat user-profile error: " . $e->getMessage());
}
