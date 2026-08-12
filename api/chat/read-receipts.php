<?php
/**
 * API: وضعیتِ «خوانده‌شدنِ» پیام‌ها — آخرین پیامِ‌خوانده‌شده‌یِ بقیه‌یِ شرکت‌کننده‌ها
 * GET /api/chat/read-receipts.php?conversation_id=1
 *
 * توجه: last_read_message_id خودِ کاربرها توسطِ messages.php هر بار که پیام‌ها را
 * می‌خوانند به‌روز می‌شود — این اندپوینت فقط همان مقدار را برایِ «دیگران» برمی‌گرداند.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate');

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

    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گفتگو الزامی است']);
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT user_id, last_read_message_id
        FROM chat_participants
        WHERE conversation_id = ? AND user_id != ?
    ");
    $stmt->execute([$conversationId, $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $participants = array_map(function ($r) {
        return [
            'user_id'             => (int) $r['user_id'],
            'last_read_message_id' => $r['last_read_message_id'] !== null ? (int) $r['last_read_message_id'] : 0,
        ];
    }, $rows);

    echo json_encode(['success' => true, 'participants' => $participants], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat read-receipts error: " . $e->getMessage());
}
