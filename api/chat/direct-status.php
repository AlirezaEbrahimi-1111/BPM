<?php
/**
 * API: وضعیتِ آنلاین/آخرین‌بازدیدِ طرفِ مقابلِ یک گفتگویِ مستقیم
 * GET /api/chat/direct-status.php?conversation_id=1
 *
 * 🔒 چرا این فایلِ جداست: لیستِ گفتگوها (api/chat/conversations.php) عمداً
 * گفتگویِ مستقیمِ بدون‌پیام رو نشون نمی‌ده (اولین‌بار که با کسی چت می‌زنیم،
 * قبلِ فرستادنِ اولین پیام). یعنی هدرِ صفحه‌ی چت برایِ چنین گفتگویی هیچ‌وقت
 * از اون لیست وضعیتِ طرفِ مقابل رو نمی‌گرفت و «آخرین بازدید» خالی می‌موند.
 * اینجا فقط با شناسه‌یِ خودِ گفتگو (که از لحظه‌یِ ساخته‌شدن، حتی بدون هیچ
 * پیامی، وجود داره) طرفِ مقابل و وضعیتش پیدا می‌شه — مستقل از پیام.
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
        echo json_encode(['success' => false, 'message' => 'شناسه‌ی گفتگو الزامی است']);
        exit;
    }

    // خودِ کاربر باید عضوِ همین گفتگو باشد، و گفتگو باید مستقیم باشد
    $stmt = $db->prepare("
        SELECT c.id
        FROM chat_conversations c
        JOIN chat_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
        WHERE c.id = ? AND c.type = 'direct'
    ");
    $stmt->execute([$user_id, $conversationId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
        exit;
    }

    // طرفِ مقابل: تنها شرکت‌کننده‌ی دیگرِ همین گفتگویِ مستقیم
    $stmt = $db->prepare("
        SELECT ou.id, ou.first_name, ou.last_name, ou.avatar_path, ocp.last_seen_at
        FROM chat_participants op
        JOIN users ou ON ou.id = op.user_id
        LEFT JOIN chat_presence ocp ON ocp.user_id = ou.id
        WHERE op.conversation_id = ? AND op.user_id != ?
        LIMIT 1
    ");
    $stmt->execute([$conversationId, $user_id]);
    $other = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$other) {
        echo json_encode(['success' => true, 'other_user_id' => null, 'full_name' => null, 'avatar_url' => null, 'is_online' => false, 'last_seen_at' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 🔒 آستانه‌ی آنلاین‌بودن: هم‌راستا با conversations.php و search-users.php
    $onlineThresholdSeconds = 60;
    $lastSeenAt = $other['last_seen_at'];
    $isOnline = $lastSeenAt && (time() - strtotime($lastSeenAt)) <= $onlineThresholdSeconds;

    echo json_encode([
        'success'       => true,
        'other_user_id' => (int) $other['id'],
        'full_name'     => trim($other['first_name'] . ' ' . $other['last_name']),
        'avatar_url'    => $other['avatar_path'] ?: null,
        'is_online'     => $isOnline,
        'last_seen_at'  => $lastSeenAt,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("Chat direct-status error: " . $e->getMessage());
}
