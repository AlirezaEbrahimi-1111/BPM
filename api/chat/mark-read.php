<?php
/**
 * API: علامت‌گذاریِ خوانده‌شدنِ پیام‌ها تا یک نقطه‌ی مشخص
 * POST /api/chat/mark-read.php   body: { conversation_id, up_to_id? }
 * اگر up_to_id داده نشود، کلِ گفتگو تا آخرین پیام خوانده‌شده علامت می‌خورد
 * (رفتارِ قدیمی — برای «علامت‌گذاریِ همه به‌عنوانِ خوانده‌شده»)؛ اگر داده شود،
 * فقط تا همان پیام جلو می‌رود (برای پیشرفتِ تدریجیِ خواندن هنگامِ اسکرول).
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
    $upToId         = (int) ($input['up_to_id'] ?? 0);

    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گفتگو الزامی است']);
        exit;
    }

    // 🔒 فقط شرکت‌کننده‌های همین گفتگو اجازه‌ی علامت‌زدنِ خوانده‌شده را دارند
    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("SELECT MAX(id) FROM chat_messages WHERE conversation_id = ? AND is_deleted = 0");
    $stmt->execute([$conversationId]);
    $maxId = (int) $stmt->fetchColumn();

    // up_to_id هرگز نباید از آخرین پیامِ واقعیِ گفتگو جلوتر برود
    $targetId = $upToId > 0 ? min($upToId, $maxId) : $maxId;

    $stmt = $db->prepare("
        UPDATE chat_participants
        SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$targetId, $conversationId, $user_id]);

    echo json_encode(['success' => true, 'last_read_message_id' => $targetId]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat mark-read error: " . $e->getMessage());
}
