<?php
/**
 * API: شروع (یا بازیابیِ) یک گفتگوی یک‌به‌یک با کاربرِ دیگر
 * POST /api/chat/start.php   body: { user_id: 123 }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

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

    $input = json_decode(file_get_contents('php://input'), true);
    $targetId = (int) ($input['user_id'] ?? 0);

    if (!$targetId || $targetId === $user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'کاربر مقصد نامعتبر است']);
        exit;
    }

    $stmt = $db->prepare("SELECT organization_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $orgId = $stmt->fetchColumn();

    // 🔒 کاربر مقصد باید از همان سازمان و فعال باشد
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND organization_id = ? AND is_active = 1 AND is_deleted = 0");
    $stmt->execute([$targetId, $orgId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'کاربر مقصد یافت نشد']);
        exit;
    }

    // ─── آیا گفتگوی یک‌به‌یکِ قبلی بینِ این دو نفر وجود دارد؟ ───
    $stmt = $db->prepare("
        SELECT cp1.conversation_id
        FROM chat_participants cp1
        JOIN chat_participants cp2 ON cp2.conversation_id = cp1.conversation_id AND cp2.user_id = ?
        JOIN chat_conversations c ON c.id = cp1.conversation_id AND c.type = 'direct'
        WHERE cp1.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$targetId, $user_id]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        // اگر قبلاً توسطِ همین کاربر بایگانی شده بود، با شروعِ دوباره از بایگانی خارج شود
        $db->prepare("UPDATE chat_participants SET is_archived = 0, archived_at = NULL WHERE conversation_id = ? AND user_id = ?")
            ->execute([$existingId, $user_id]);

        echo json_encode(['success' => true, 'conversation_id' => (int) $existingId]);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO chat_conversations (organization_id, type, created_by) VALUES (?, 'direct', ?)");
    $stmt->execute([$orgId, $user_id]);
    $conversationId = (int) $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO chat_participants (conversation_id, user_id) VALUES (?, ?)");
    $stmt->execute([$conversationId, $user_id]);
    $stmt->execute([$conversationId, $targetId]);

    $db->commit();

    echo json_encode(['success' => true, 'conversation_id' => $conversationId]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat start error: " . $e->getMessage());
}
