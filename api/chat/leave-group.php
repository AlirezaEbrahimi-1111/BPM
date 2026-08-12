<?php
/**
 * API: خروجِ کاربرِ جاری از یک گروه (شاملِ خودِ سازنده)
 * POST /api/chat/leave-group.php   body: { conversation_id: 1 }
 *
 * توجه: اگر سازنده خارج شود، گروه بدونِ مدیر باقی می‌ماند (افزودن/حذفِ عضو
 * دیگر ممکن نیست) — تصمیمِ عامدانه برای سادگیِ فازِ اول، بدونِ انتقالِ مالکیت.
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
    $conversationId = (int) ($input['conversation_id'] ?? 0);

    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    $stmt = $db->prepare("SELECT type FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv || $conv['type'] === 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو گروهی نیست']);
        exit;
    }

    $db->beginTransaction();

    $stmt = $db->prepare("DELETE FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);

    $stmt = $db->prepare("SELECT COUNT(*) FROM chat_participants WHERE conversation_id = ?");
    $stmt->execute([$conversationId]);
    $remaining = (int) $stmt->fetchColumn();

    if ($remaining === 0) {
        // گروهِ بدونِ‌عضو دیگر فایده‌ای ندارد — کاملاً حذف می‌شود (پیام/پیوست‌ها هم با CASCADE پاک می‌شوند)
        $db->prepare("DELETE FROM chat_conversations WHERE id = ?")->execute([$conversationId]);
    }

    $db->commit();

    echo json_encode(['success' => true, 'group_deleted' => $remaining === 0]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat leave-group error: " . $e->getMessage());
}
