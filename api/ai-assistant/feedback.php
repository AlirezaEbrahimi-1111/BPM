<?php
/**
 * API دستیارِ هوش‌مصنوعی: ثبتِ بازخوردِ 👍/👎 برایِ یک پاسخ
 * POST /api/ai-assistant/feedback.php   body: { log_id, rating: 'up'|'down', comment? }
 *
 * طبقِ بندِ ۱۷ سندِ docs/ai-assistant/spec-v1.md.
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/middleware.php';

try {
    $user_id = requireAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $logId = (int) ($input['log_id'] ?? 0);
    $rating = (string) ($input['rating'] ?? '');
    $comment = isset($input['comment']) ? trim((string) $input['comment']) : null;
    if ($comment === '') {
        $comment = null;
    } elseif ($comment !== null && mb_strlen($comment) > 500) {
        $comment = mb_substr($comment, 0, 500);
    }

    if (!$logId || !in_array($rating, ['up', 'down'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ورودی نامعتبر است']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    // 🔒 کاربر فقط می‌تواند به پاسخِ خودش بازخورد بدهد
    $stmt = $db->prepare("SELECT id FROM ai_query_logs WHERE id = ? AND user_id = ?");
    $stmt->execute([$logId, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'پرسش موردنظر یافت نشد']);
        exit;
    }

    $db->prepare("INSERT INTO ai_query_feedback (log_id, user_id, rating, comment) VALUES (?, ?, ?, ?)")
        ->execute([$logId, $user_id, $rating, $comment]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور']);
    error_log("AI feedback error: " . $e->getMessage());
}
