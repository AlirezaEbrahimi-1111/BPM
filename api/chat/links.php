<?php
/**
 * API: فهرست لینک‌های ردوبدل‌شده در یک گفتگو (تب «لینک» در اطلاعات گروه)
 * GET /api/chat/links.php?conversation_id=123
 *
 * لینک‌ها جای جداگانه‌ای ذخیره نمی‌شوند؛ از متن پیام‌ها بیرون کشیده می‌شوند.
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
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/plan-access.php';
    requirePlanFeature($user_id, 'chat');
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

    // 🔒 فقط شرکت‌کننده‌های همون گفتگو
    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // پیش‌فیلتر با LIKE تا فقط پیام‌های دارای لینک خوانده شوند؛ سقف ۳۰۰ پیام اخیر
    $stmt = $db->prepare("
        SELECT m.id AS message_id, m.message, m.created_at,
               TRIM(CONCAT(u.first_name, ' ', u.last_name)) AS sender_name
        FROM chat_messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.conversation_id = ? AND m.is_deleted = 0 AND m.type = 'text'
          AND (m.message LIKE '%http://%' OR m.message LIKE '%https://%' OR m.message LIKE '%www.%')
        ORDER BY m.id DESC
        LIMIT 300
    ");
    $stmt->execute([$conversationId]);

    $links = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!preg_match_all('~\b(?:https?://|www\.)[^\s<>"\']+~iu', $r['message'], $mm)) continue;
        foreach ($mm[0] as $url) {
            $url = rtrim($url, '.,;:!?)]}»،؛');
            $href = stripos($url, 'www.') === 0 ? 'https://' . $url : $url;
            $links[] = [
                'message_id'  => (int) $r['message_id'],
                'url'         => $href,
                'host'        => parse_url($href, PHP_URL_HOST) ?: $url,
                'sender_name' => $r['sender_name'],
                'created_at'  => $r['created_at'],
            ];
        }
    }

    echo json_encode(['success' => true, 'links' => $links], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat links list error: " . $e->getMessage());
}
