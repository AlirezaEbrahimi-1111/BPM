<?php
/**
 * API: تغییر نام گروه — سازنده، یا هر عضوی که اختیار «تغییر اطلاعات گروه» (کلید avatar) را دارد
 * POST /api/chat/rename-group.php   body: { conversation_id: 1, title: '...' }
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/chat-helpers.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $auth = new Auth($db);
    $user_id = $auth->getUserFromToken();
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
    requirePlanFeature($user_id, 'chat');
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'عدم احراز هویت']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $conversationId = (int) ($input['conversation_id'] ?? 0);
    $title = trim(preg_replace('/\s+/u', ' ', (string) ($input['title'] ?? '')));

    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گروه الزامی است']);
        exit;
    }
    if ($title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام گروه نمی‌تواند خالی باشد']);
        exit;
    }
    if (mb_strlen($title) > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'نام گروه نباید بیش از ۱۰۰ حرف باشد']);
        exit;
    }

    $stmt = $db->prepare("SELECT type, title FROM chat_conversations WHERE id = ?");
    $stmt->execute([$conversationId]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conv || $conv['type'] === 'direct') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'این گفتگو گروهی نیست']);
        exit;
    }

    // عضوِ فعلیِ گروه بودن + اختیارِ «تغییر اطلاعات گروه» (سازنده همیشه دارد)
    $stmt = $db->prepare("SELECT 1 FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetchColumn() || !chatUserHasGroupPermission($db, $conversationId, $user_id, 'avatar')) {
        http_response_code(403);
        error_log("Chat rename-group denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'شما اجازهٔ تغییر نام این گروه را ندارید']);
        exit;
    }

    if ($title === (string) $conv['title']) {
        echo json_encode(['success' => true, 'title' => $title, 'unchanged' => true]);
        exit;
    }

    $db->prepare("UPDATE chat_conversations SET title = ?, updated_at = NOW() WHERE id = ?")
       ->execute([$title, $conversationId]);

    // پیامِ سیستمی تا همهٔ اعضا بدانند چه کسی نام را عوض کرد
    $s = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $s->execute([$user_id]);
    $u = $s->fetch(PDO::FETCH_ASSOC);
    $actorName = $u ? trim($u['first_name'] . ' ' . $u['last_name']) : '';
    $text = $actorName !== ''
        ? "{$actorName} نام گروه را به «{$title}» تغییر داد."
        : "نام گروه به «{$title}» تغییر کرد.";
    $db->prepare("INSERT INTO chat_messages (conversation_id, user_id, type, message) VALUES (?, ?, 'system', ?)")
       ->execute([$conversationId, $user_id, $text]);

    echo json_encode(['success' => true, 'title' => $title]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat rename-group error: " . $e->getMessage());
}
