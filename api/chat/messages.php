<?php
/**
 * API: دریافتِ پیام‌های یک گفتگو (صفحه‌بندی‌شده)
 * GET /api/chat/messages.php?conversation_id=1&before_id=&limit=30
 */

header('Content-Type: application/json; charset=utf-8');
$corsAllowedOrigins = ['https://itmalek.com', 'https://www.itmalek.com'];
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

    $conversationId = (int) ($_GET['conversation_id'] ?? 0);
    $beforeId       = (int) ($_GET['before_id'] ?? 0);
    $afterId        = (int) ($_GET['after_id'] ?? 0);
    $aroundId       = (int) ($_GET['around_id'] ?? 0);
    $limit          = min(100, max(1, (int) ($_GET['limit'] ?? 30)));

    if (!$conversationId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'شناسه گفتگو الزامی است']);
        exit;
    }

    // 🔒 فقط شرکت‌کننده‌های همین گفتگو اجازه‌ی دیدنِ پیام‌ها را دارند
    $stmt = $db->prepare("SELECT id FROM chat_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        error_log("Chat messages denied | user_id={$user_id} | conversation_id={$conversationId}");
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    $sql = "
        SELECT m.id, m.user_id, m.message, m.created_at, m.edited_at, m.reply_to_message_id,
               m.forwarded_from_user_id,
               u.first_name, u.last_name,
               rm.message AS reply_message, rm.is_deleted AS reply_is_deleted,
               ru.first_name AS reply_first_name, ru.last_name AS reply_last_name,
               fu.first_name AS forward_first_name, fu.last_name AS forward_last_name
        FROM chat_messages m
        JOIN users u ON u.id = m.user_id
        LEFT JOIN chat_message_hidden h ON h.message_id = m.id AND h.user_id = ?
        LEFT JOIN chat_messages rm ON rm.id = m.reply_to_message_id
        LEFT JOIN users ru ON ru.id = rm.user_id
        LEFT JOIN users fu ON fu.id = m.forwarded_from_user_id
        WHERE m.conversation_id = ? AND m.is_deleted = 0 AND h.id IS NULL
    ";
    $params = [$user_id, $conversationId];

    if ($afterId > 0) {
        // ✅ حالتِ polling — پیام‌های جدیدترِ از afterId (صعودی، بدونِ نیاز به reverse)
        $sql .= " AND m.id > ? ORDER BY m.id ASC LIMIT " . $limit;
        $params[] = $afterId;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($aroundId > 0) {
        // ✅ حالتِ «پرش به یک پیامِ خاص» — نیمی از limit قبل و نیمی بعد از آن پیام
        $half = (int) floor($limit / 2);

        $beforeSql = $sql . " AND m.id <= ? ORDER BY m.id DESC LIMIT " . ($half + 1);
        $stmt = $db->prepare($beforeSql);
        $stmt->execute(array_merge($params, [$aroundId]));
        $beforeRows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

        $afterSql = $sql . " AND m.id > ? ORDER BY m.id ASC LIMIT " . $half;
        $stmt = $db->prepare($afterSql);
        $stmt->execute(array_merge($params, [$aroundId]));
        $afterRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = array_merge($beforeRows, $afterRows);
    } else {
        if ($beforeId > 0) {
            $sql .= " AND m.id < ?";
            $params[] = $beforeId;
        }
        $sql .= " ORDER BY m.id DESC LIMIT " . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    $messages = [];
    $messageIds = [];
    foreach ($rows as $r) {
        $messageIds[] = (int) $r['id'];

        $replyTo = null;
        if ($r['reply_to_message_id']) {
            // پیامِ اصلی ممکن است بعداً «حذف برای همه» شده باشد؛ رفرنسِ ستون هنوز
            // برقرار است (ON DELETE SET NULL فقط برای حذفِ کاملِ ردیف اعمال می‌شود)
            $replyTo = [
                'id'         => (int) $r['reply_to_message_id'],
                'user_name'  => trim(($r['reply_first_name'] ?? '') . ' ' . ($r['reply_last_name'] ?? '')),
                'snippet'    => $r['reply_is_deleted'] ? null : mb_substr((string) $r['reply_message'], 0, 80),
                'is_deleted' => (bool) $r['reply_is_deleted'],
            ];
        }

        $forwardedFrom = $r['forwarded_from_user_id']
            ? trim(($r['forward_first_name'] ?? '') . ' ' . ($r['forward_last_name'] ?? '')) ?: 'کاربر حذف‌شده'
            : null;

        $messages[] = [
            'id'         => (int) $r['id'],
            'user_id'    => (int) $r['user_id'],
            'user_name'  => trim($r['first_name'] . ' ' . $r['last_name']),
            'message'    => $r['message'],
            'created_at' => $r['created_at'],
            'time_jalali' => JalaliHelper::Persian(substr($r['created_at'], 11, 5)),
            'date_jalali' => JalaliHelper::formatJalaliDate(substr($r['created_at'], 0, 10)),
            'is_own'     => (int) $r['user_id'] === $user_id,
            'is_edited'  => $r['edited_at'] !== null,
            'reply_to'   => $replyTo,
            'forwarded_from' => $forwardedFrom,
            'attachments' => [],
            'reactions'  => [],
        ];
    }

    // ─── پیوست‌های همین دسته از پیام‌ها (یک کوئریِ دسته‌ای) ───
    if ($messageIds) {
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $db->prepare("
            SELECT id, message_id, original_name, mime_type, file_size
            FROM chat_attachments
            WHERE message_id IN ($placeholders)
        ");
        $stmt->execute($messageIds);
        $attByMessage = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $attByMessage[(int) $a['message_id']][] = [
                'id'            => (int) $a['id'],
                'original_name' => $a['original_name'],
                'mime_type'     => $a['mime_type'],
                'file_size'     => (int) $a['file_size'],
                'is_image'      => strpos($a['mime_type'], 'image/') === 0,
            ];
        }
        foreach ($messages as &$m) {
            $m['attachments'] = $attByMessage[$m['id']] ?? [];
        }
        unset($m);

        // ─── ری‌اکشن‌هایِ همین دسته از پیام‌ها (یک کوئریِ دسته‌ای) ───
        $stmt = $db->prepare("
            SELECT message_id, emoji, COUNT(*) AS cnt, SUM(user_id = ?) AS mine
            FROM chat_message_reactions
            WHERE message_id IN ($placeholders)
            GROUP BY message_id, emoji
        ");
        $stmt->execute(array_merge([$user_id], $messageIds));
        $reactByMessage = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $reactByMessage[(int) $r['message_id']][] = [
                'emoji'         => $r['emoji'],
                'count'         => (int) $r['cnt'],
                'reacted_by_me' => (int) $r['mine'] > 0,
            ];
        }
        foreach ($messages as &$m) {
            $m['reactions'] = $reactByMessage[$m['id']] ?? [];
        }
        unset($m);
    }

    // ─── بروزرسانیِ آخرین‌پیامِ‌خوانده‌شده برای همین کاربر ───
    if ($messageIds) {
        $maxId = max($messageIds);
        $db->prepare("
            UPDATE chat_participants
            SET last_read_message_id = GREATEST(COALESCE(last_read_message_id, 0), ?)
            WHERE conversation_id = ? AND user_id = ?
        ")->execute([$maxId, $conversationId, $user_id]);
    }

    echo json_encode([
        'success'  => true,
        'messages' => $messages,
        'has_more' => count($rows) === $limit,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat messages error: " . $e->getMessage());
}
