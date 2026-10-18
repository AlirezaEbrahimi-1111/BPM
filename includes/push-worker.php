<?php
date_default_timezone_set('Asia/Tehran');

if (!isset($argv[1])) { error_log("push-worker: No payload"); exit(1); }
$payload = json_decode(base64_decode($argv[1]), true);
if (!$payload || empty($payload['to_user_id'])) { error_log("push-worker: Invalid payload"); exit(1); }

try {
    if (empty($_SERVER['DOCUMENT_ROOT'])) {
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/onesignal-push.php';

    // 🔧 دیپ‌لینک فقط برایِ نوتیف‌هایِ مربوط به یک «کار» مشخص — بقیه‌ی
    // انواع (checklist, workflow, ticket, ...) فعلاً بدونِ دیپ‌لینکِ
    // اختصاصی باز می‌شوند (فقط خودِ اپ باز می‌شود)
    $data = [];
    if (($payload['related_type'] ?? null) === 'task' && !empty($payload['related_id'])) {
        $data = ['route' => 'task_detail', 'task_id' => (string) $payload['related_id']];
    }

    $result = onesignal_send_push(
        $payload['to_user_id'],
        $payload['title'] ?? '',
        $payload['message'] ?? '',
        $data
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
