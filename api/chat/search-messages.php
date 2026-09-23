<?php
/**
 * API: جستجوی سراسری در متنِ پیام‌های همه‌ی گفتگوهای کاربرِ جاری
 * GET /api/chat/search-messages.php?q=...&conversation_id=  (اختیاری، برای محدودکردن به یک گفتگو)
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com', 'https://bpm.itmalek.com'];
$corsRequestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
header('Access-Control-Allow-Origin: ' . (in_array($corsRequestOrigin, $corsAllowedOrigins, true) ? $corsRequestOrigin : 'https://itmalek.com'));
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/JalaliHelper.php';

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

    $q = trim($_GET['q'] ?? '');
    $conversationId = (int) ($_GET['conversation_id'] ?? 0);

    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }

    // 🔒 قبلاً «طرفِ مقابل» بدونِ توجه به نوعِ گفتگو join می‌شد — برایِ گروه
    // (چند نفره)، این یعنی هر پیام به‌ازایِ هر عضوِ دیگه یک‌بار تکراری
    // برمی‌گشت (مثلاً یه پیام در گروهِ ۷نفره → ۶ ردیفِ عیناً تکراری)، که
    // LIMIT رو خیلی زود با تکرارهایِ یک پیام پر می‌کرد و نتایجِ واقعیِ
    // دیگه (به‌خصوص قدیمی‌ترها) اصلاً به کاربر نمی‌رسید — دقیقاً همون‌چیزی
    // که کاربر «جستجو چیزی پیدا نمی‌کنه» گزارش کرد. الان «طرفِ مقابل» فقط
    // برایِ گفتگویِ direct (که واقعاً یک‌نفره‌ست) join می‌شه؛ برایِ
    // گروه/کانال، عنوانِ خودِ گفتگو (c.title) استفاده می‌شه.
    $sql = "
        SELECT m.id, m.conversation_id, m.user_id, m.message, m.created_at,
               u.first_name, u.last_name,
               c.type AS conv_type, c.title AS conv_title,
               ou.id AS other_user_id, ou.first_name AS other_first_name, ou.last_name AS other_last_name
        FROM chat_messages m
        JOIN chat_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
        JOIN chat_conversations c ON c.id = m.conversation_id
        JOIN users u ON u.id = m.user_id
        LEFT JOIN chat_message_hidden h ON h.message_id = m.id AND h.user_id = ?
        LEFT JOIN chat_participants op ON op.conversation_id = m.conversation_id AND op.user_id != ? AND c.type = 'direct'
        LEFT JOIN users ou ON ou.id = op.user_id
        WHERE m.is_deleted = 0
          AND h.id IS NULL
          AND m.message LIKE ?
    ";
    $params = [$user_id, $user_id, $user_id, '%' . $q . '%'];

    if ($conversationId) {
        $sql .= " AND m.conversation_id = ?";
        $params[] = $conversationId;
    }

    $sql .= " ORDER BY m.id DESC LIMIT 50";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = array_map(function ($r) use ($user_id) {
        if ($r['conv_type'] === 'direct') {
            $otherName = trim(($r['other_first_name'] ?? '') . ' ' . ($r['other_last_name'] ?? ''));
            $title = $otherName ?: 'کاربر حذف‌شده';
        } else {
            $title = $r['conv_title'] ?: 'گروه';
        }
        $snippet = $r['message'];
        if (mb_strlen($snippet) > 100) {
            $snippet = mb_substr($snippet, 0, 100) . '…';
        }
        return [
            'message_id'      => (int) $r['id'],
            'conversation_id' => (int) $r['conversation_id'],
            'conversation_title' => $title,
            'sender_name'     => trim($r['first_name'] . ' ' . $r['last_name']),
            'is_own'          => (int) $r['user_id'] === $user_id,
            'snippet'         => $snippet,
            'created_at'      => $r['created_at'],
            'date_jalali'     => JalaliHelper::formatJalaliDate(substr($r['created_at'], 0, 10)),
            'time_jalali'     => JalaliHelper::Persian(substr($r['created_at'], 11, 5)),
        ];
    }, $rows);

    echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat search-messages error: " . $e->getMessage());
}
