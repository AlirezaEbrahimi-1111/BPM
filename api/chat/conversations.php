<?php
/**
 * API: لیستِ گفتگوهای کاربرِ جاری
 * GET /api/chat/conversations.php?archived=0|1
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate'); // تعدادِ خوانده‌نشده مدام تغییر می‌کند، نباید کش شود

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

    $archived = !empty($_GET['archived']) ? 1 : 0;

    // ─── لیستِ گفتگوها + طرفِ مقابل (فقط برای direct) + آخرین پیام ───
    // 🔒 op/ou فقط وقتی c.type='direct' جوین می‌شوند — وگرنه برای گروه با چند
    // شرکت‌کننده‌ی دیگر، همین یک گفتگو چندین بار (یکی به‌ازای هر عضو) تکرار می‌شد
    $stmt = $db->prepare("
        SELECT
            c.id AS conversation_id,
            c.type,
            c.title,
            c.avatar_path AS group_avatar_path,
            c.created_by,
            c.updated_at,
            cp.is_muted,
            ou.id AS other_user_id,
            ou.first_name AS other_first_name,
            ou.last_name AS other_last_name,
            ou.avatar_path AS other_avatar_path,
            ocp.last_seen_at AS other_user_last_seen_at,
            lm.message AS last_message,
            lm.created_at AS last_message_at,
            lm.user_id AS last_message_user_id,
            lmu.first_name AS last_message_first_name,
            lmu.last_name AS last_message_last_name,
            (SELECT COUNT(*) FROM chat_participants pc WHERE pc.conversation_id = c.id) AS member_count
        FROM chat_participants cp
        JOIN chat_conversations c ON c.id = cp.conversation_id
        LEFT JOIN chat_participants op ON op.conversation_id = c.id AND op.user_id != ? AND c.type = 'direct'
        LEFT JOIN users ou ON ou.id = op.user_id
        LEFT JOIN chat_presence ocp ON ocp.user_id = ou.id
        LEFT JOIN chat_messages lm ON lm.id = (
            SELECT MAX(m2.id) FROM chat_messages m2
            LEFT JOIN chat_message_hidden h2 ON h2.message_id = m2.id AND h2.user_id = ?
            WHERE m2.conversation_id = c.id AND m2.is_deleted = 0 AND h2.id IS NULL
        )
        LEFT JOIN users lmu ON lmu.id = lm.user_id
        WHERE cp.user_id = ? AND cp.is_archived = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $archived]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo json_encode(['success' => true, 'conversations' => []]);
        exit;
    }

    $convIds = array_column($rows, 'conversation_id');
    $placeholders = implode(',', array_fill(0, count($convIds), '?'));

    // ─── تعدادِ پیام‌های خوانده‌نشده به‌ازای هر گفتگو (یک کوئریِ دسته‌ای) ───
    $stmt = $db->prepare("
        SELECT m.conversation_id, COUNT(*) AS unread
        FROM chat_messages m
        JOIN chat_participants cp ON cp.conversation_id = m.conversation_id AND cp.user_id = ?
        LEFT JOIN chat_message_hidden h ON h.message_id = m.id AND h.user_id = ?
        WHERE m.conversation_id IN ($placeholders)
          AND m.user_id != ?
          AND m.is_deleted = 0
          AND h.id IS NULL
          AND (cp.last_read_message_id IS NULL OR m.id > cp.last_read_message_id)
        GROUP BY m.conversation_id
    ");
    $stmt->execute(array_merge([$user_id, $user_id], $convIds, [$user_id]));
    $unreadMap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $unreadMap[(int) $r['conversation_id']] = (int) $r['unread'];
    }

    // ─── آیا طرفِ مقابلِ هر گفتگو الان در حالِ تایپ است (یک کوئریِ دسته‌ای) ───
    $stmt = $db->prepare("
        SELECT conversation_id FROM chat_participants
        WHERE conversation_id IN ($placeholders) AND user_id != ? AND typing_until > NOW()
    ");
    $stmt->execute(array_merge($convIds, [$user_id]));
    $typingSet = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

    // 🔒 آستانهٔ آنلاین‌بودن: اگر آخرین پینگ در 1 دقیقهٔ اخیر بوده باشد
    $onlineThresholdSeconds = 60;

    $conversations = array_map(function ($r) use ($unreadMap, $typingSet, $user_id, $onlineThresholdSeconds) {
        $isGroup = $r['type'] !== 'direct';
        $otherName = trim(($r['other_first_name'] ?? '') . ' ' . ($r['other_last_name'] ?? ''));
        $lastAt = $r['last_message_at'];
        $lastSeenAt = $r['other_user_last_seen_at'];
        $isOnline = $lastSeenAt && (time() - strtotime($lastSeenAt)) <= $onlineThresholdSeconds;
        $isOwnLast = $r['last_message_user_id'] !== null && (int) $r['last_message_user_id'] === $user_id;
        $lastSenderName = trim(($r['last_message_first_name'] ?? '') . ' ' . ($r['last_message_last_name'] ?? ''));
        return [
            'conversation_id' => (int) $r['conversation_id'],
            'type'            => $r['type'],
            'title'           => $isGroup ? ($r['title'] ?: 'گروهِ بدونِ‌نام') : ($otherName ?: 'کاربر حذف‌شده'),
            'avatar_url'      => $isGroup ? ($r['group_avatar_path'] ?: null) : ($r['other_avatar_path'] ?: null),
            'is_muted'        => (bool) $r['is_muted'],
            'created_by'      => (int) $r['created_by'],
            'is_owner'        => (int) $r['created_by'] === $user_id,
            'member_count'    => (int) $r['member_count'],
            'other_user_id'   => $r['other_user_id'] ? (int) $r['other_user_id'] : null,
            'other_user_last_seen_at' => $lastSeenAt,
            'other_user_is_online'    => $isOnline,
            'other_user_is_typing'    => isset($typingSet[(int) $r['conversation_id']]),
            'last_message'    => $r['last_message'],
            'last_message_sender_name' => $lastSenderName,
            'last_message_at' => $lastAt,
            'last_message_jalali' => $lastAt ? JalaliHelper::formatJalaliDate(substr($lastAt, 0, 10)) . ' ' . JalaliHelper::Persian(substr($lastAt, 11, 5)) : '',
            'is_own_last'     => $isOwnLast,
            'unread_count'    => $unreadMap[(int) $r['conversation_id']] ?? 0,
        ];
    }, $rows);

    echo json_encode(['success' => true, 'conversations' => $conversations], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat conversations list error: " . $e->getMessage());
}
