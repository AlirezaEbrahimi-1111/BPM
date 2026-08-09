<?php
/**
 * API: فهرستِ فایل/عکسِ به‌اشتراک‌گذاشته‌شده در یک گفتگو (برایِ نمایِ گالری)
 * GET /api/chat/media.php?conversation_id=123
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

    // 🔒 فقط شرکت‌کننده‌هایِ همون گفتگو
    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT ca.id, ca.message_id, ca.original_name, ca.mime_type, ca.file_size, ca.created_at,
               TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS uploader_name
        FROM chat_attachments ca
        JOIN users u ON u.id = ca.user_id
        LEFT JOIN chat_messages m ON m.id = ca.message_id
        WHERE ca.conversation_id = ? AND (m.is_deleted IS NULL OR m.is_deleted = 0)
        ORDER BY ca.created_at DESC
    ");
    $stmt->execute([$conversationId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['is_image'] = strpos($r['mime_type'], 'image/') === 0;
    }
    unset($r);

    echo json_encode(['success' => true, 'files' => $rows], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat media list error: " . $e->getMessage());
}
