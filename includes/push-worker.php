<?php
date_default_timezone_set('Asia/Tehran');

if (!isset($argv[1])) { error_log("push-worker: No payload"); exit(1); }
$payload = json_decode(base64_decode($argv[1]), true);
if (!$payload || empty($payload['to_user_id'])) { error_log("push-worker: Invalid payload"); exit(1); }

try {
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/pushfa-push.php';

    // 🔧 دیپ‌لینک فقط برایِ نوتیف‌هایِ مربوط به یک «کار» مشخص — بقیه‌ی
    // انواع (checklist, workflow, ticket, ...) فعلاً بدونِ دیپ‌لینکِ
    // اختصاصی باز می‌شوند (فقط خودِ اپ باز می‌شود)
    $linkUrl = null;
    if (($payload['related_type'] ?? null) === 'task' && !empty($payload['related_id'])) {
        $linkUrl = 'malekbpm://task?id=' . (string) $payload['related_id'];
    }

    $result = pushfa_send_push(
        $payload['to_user_id'],
        $payload['title'] ?? '',
        $payload['message'] ?? '',
        $linkUrl
    );

    error_log(
        ($result['ok'] ? "✅" : "⚠️") . " push-worker: notif=#" . ($payload['notification_id'] ?? '-') .
        " http=" . $result['http_code'] .
        " resp=" . json_encode($result['response'], JSON_UNESCAPED_UNICODE)
    );

} catch (Exception $e) {
    error_log("❌ push-worker error: " . $e->getMessage());
    exit(1);
}
exit(0);
